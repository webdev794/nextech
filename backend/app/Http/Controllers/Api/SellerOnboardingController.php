<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Support\SellerOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seller Center onboarding tasks (tax information, additional compliance
 * information, bank account). Each submission goes to NexTech for review —
 * AdminSellerController::reviewOnboarding() approves or sends it back.
 */
class SellerOnboardingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->seller($request))]);
    }

    /** Tax step 1: the tax registration number (and certificate where required). */
    public function saveTaxNumber(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $config = SellerOnboarding::config($seller);
        $data = $request->validate([
            'tax_number' => ['required', 'string', 'max:60'],
            'certificate_path' => [($config['tax_certificate_required'] ?? false) ? 'required' : 'nullable', 'string', 'max:255'],
            'certificate_name' => ['nullable', 'string', 'max:255'],
        ], ['certificate_path.required' => 'Upload your '.($config['tax_certificate_label'] ?? 'tax certificate').'.']);

        if (SellerOnboarding::normalizeTaxNumber($data['tax_number']) !== SellerOnboarding::normalizeTaxNumber($seller->tax_id)) {
            throw ValidationException::withMessages(['tax_number' => 'This doesn’t match the '.($config['tax_number_label'] ?? 'tax number').' you gave when you registered ('.$seller->tax_id.').']);
        }
        $this->assertOwnDocument($request, $data['certificate_path'] ?? null);

        $info = array_merge((array) $seller->tax_info, [
            'tax_number' => $seller->tax_id, // matched above; kept in the registered format
            'certificate_path' => $data['certificate_path'] ?? null,
            'certificate_name' => $data['certificate_name'] ?? null,
        ]);
        // Changing step 1 after step 2 was done sends it back for review.
        $resubmit = ! empty($info['tax_code']);
        $seller->forceFill([
            'tax_info' => $info,
            'tax_status' => $resubmit ? 'pending' : null,
            'tax_note' => null,
            'tax_submitted_at' => $resubmit ? now() : $seller->tax_submitted_at,
        ])->save();

        return response()->json(['data' => $this->payload($seller)]);
    }

    /** Tax step 2: default item tax code + terms, then it goes for review. */
    public function saveTaxSettings(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        abort_unless(! empty($seller->tax_info['tax_number']), 422, 'Add your tax registration number first.');
        $data = $request->validate([
            'tax_code' => ['required', Rule::in(array_map('strval', array_keys(SellerOnboarding::config($seller)['tax_codes'] ?? [])))],
            'agree' => ['accepted'],
        ], ['agree.accepted' => 'Agree to the tax terms to continue.']);

        $seller->forceFill([
            'tax_info' => array_merge((array) $seller->tax_info, ['tax_code' => (string) $data['tax_code'], 'terms_accepted_at' => now()->toIso8601String()]),
            'tax_status' => 'pending',
            'tax_note' => null,
            'tax_submitted_at' => now(),
        ])->save();

        return response()->json(['data' => $this->payload($seller)]);
    }

    /** Business roles (beneficial owners, directors, executives) and corporate documents. */
    public function saveCompliance(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $config = SellerOnboarding::config($seller);
        $isCompany = SellerOnboarding::isCompany($seller);
        $today = now()->toDateString();

        $data = $request->validate([
            'primary' => ['required', 'array'],
            'primary.roles' => ['present', 'array'],
            'primary.roles.*' => [Rule::in(SellerOnboarding::ROLES)],
            'primary.ownership_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'primary.citizenship' => ['required', 'string', 'size:2'],
            'primary.place_of_birth' => ['required', 'string', 'max:120'],
            'primary.id_expiry' => ['required', 'date', 'after:'.$today],
            'primary.address' => ['required', 'array'],
            'people' => ['present', 'array', 'max:20'],
            'people.*.id' => ['nullable', 'string', 'max:40'],
            'people.*.legal_name' => ['required', 'string', 'max:160'],
            'people.*.roles' => ['required', 'array', 'min:1'],
            'people.*.roles.*' => [Rule::in(SellerOnboarding::ROLES)],
            'people.*.ownership_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'people.*.citizenship' => ['required', 'string', 'size:2'],
            'people.*.place_of_birth' => ['required', 'string', 'max:120'],
            'people.*.date_of_birth' => ['required', 'date', 'before:'.$today],
            'people.*.id_country' => ['required', 'string', 'size:2'],
            'people.*.id_type' => ['required', Rule::in(['passport', 'national_id', 'drivers_license'])],
            'people.*.id_number' => ['required', 'string', 'max:60'],
            'people.*.id_expiry' => ['required', 'date', 'after:'.$today],
            'people.*.address' => ['required', 'array'],
            'documents' => [$isCompany || $seller->business_type === 'proprietorship' ? 'required' : 'present', 'array', 'max:10'],
            'documents.*.type' => ['required', Rule::in(array_keys($config['corporate_documents'] ?? []))],
            'documents.*.path' => ['required', 'string', 'max:255'],
            'documents.*.name' => ['nullable', 'string', 'max:255'],
        ], [
            'documents.required' => 'Upload at least one business certification document.',
            'people.*.roles.required' => 'Pick at least one role for each person.',
        ]);

        $addressRules = ['line1' => ['required', 'string', 'max:255'], 'line2' => ['nullable', 'string', 'max:255'], 'city' => ['required', 'string', 'max:100'], 'state' => ['nullable', 'string', 'max:60'], 'postal_code' => ['required', 'string', 'max:12'], 'country' => ['required', 'string', 'size:2']];
        $cleanAddress = fn (array $a, string $prefix) => validator($a, $addressRules, [], collect($addressRules)->mapWithKeys(fn ($r, $k) => [$k => $prefix.' '.str_replace('_', ' ', $k)])->all())->validate();

        $primaryContact = SellerOnboarding::primaryContact($seller);
        // Individuals and sole proprietors are their own owner, director and executive.
        $primaryRoles = $isCompany ? array_values(array_unique($data['primary']['roles'])) : SellerOnboarding::ROLES;
        $primary = $primaryContact + [
            'id' => 'primary',
            'is_primary' => true,
            'roles' => $primaryRoles,
            'ownership_pct' => $data['primary']['ownership_pct'] ?? null,
            'citizenship' => strtoupper($data['primary']['citizenship']),
            'place_of_birth' => trim($data['primary']['place_of_birth']),
            'id_expiry' => $data['primary']['id_expiry'],
            'address' => $cleanAddress($data['primary']['address'], 'Primary contact'),
        ];

        $people = [$primary];
        $seen = [SellerOnboarding::personKey($primary['legal_name'], $primary['date_of_birth']) => $primary['legal_name']];
        foreach ($data['people'] as $i => $person) {
            $dob = date('Y-m-d', strtotime($person['date_of_birth']));
            $key = SellerOnboarding::personKey($person['legal_name'], $dob);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["people.$i.legal_name" => ($key === array_key_first($seen)
                    ? $person['legal_name'].' is your primary contact — don’t create them again. Use “Choose” to give the primary contact this role.'
                    : $person['legal_name'].' is listed twice. Give one person every role they hold instead of adding them again.')]);
            }
            $seen[$key] = $person['legal_name'];
            $people[] = [
                'id' => $person['id'] ?? (string) Str::uuid(),
                'is_primary' => false,
                'legal_name' => trim($person['legal_name']),
                'roles' => array_values(array_unique($person['roles'])),
                'ownership_pct' => $person['ownership_pct'] ?? null,
                'citizenship' => strtoupper($person['citizenship']),
                'place_of_birth' => trim($person['place_of_birth']),
                'date_of_birth' => $dob,
                'id_country' => strtoupper($person['id_country']),
                'id_type' => $person['id_type'],
                'id_number' => trim($person['id_number']),
                'id_expiry' => $person['id_expiry'],
                'address' => $cleanAddress($person['address'], $person['legal_name'].'’s'),
            ];
        }

        if ($isCompany) {
            $labels = ['ubo' => 'ultimate beneficial owner', 'director' => 'director', 'executive' => 'executive'];
            foreach ($labels as $role => $label) {
                if (! collect($people)->contains(fn ($p) => in_array($role, $p['roles'], true))) {
                    throw ValidationException::withMessages(['people' => "Add at least one $label (you can give the primary contact this role)."]);
                }
            }
        }

        foreach ($data['documents'] as $doc) {
            $this->assertOwnDocument($request, $doc['path']);
        }

        $seller->forceFill([
            'compliance' => ['people' => $people, 'documents' => array_values($data['documents'])],
            'compliance_status' => 'pending',
            'compliance_note' => null,
            'compliance_submitted_at' => now(),
        ])->save();

        return response()->json(['data' => $this->payload($seller)]);
    }

    /** Bank account + a recent bank document; NexTech verifies it before payouts. */
    public function saveBank(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        abort_unless(in_array($seller->compliance_status, ['pending', 'approved'], true), 422, 'Add your additional compliance information first.');
        $bank = SellerOnboarding::config($seller)['bank'] ?? [];
        $oldest = now()->subDays(SellerOnboarding::BANK_DOCUMENT_MAX_AGE_DAYS)->toDateString();

        $data = $request->validate([
            'holder_name' => ['required', 'string', 'max:160'],
            'bank_name' => ['required', 'string', 'max:160'],
            'bank_code' => ['required', 'string', 'max:20', 'regex:/'.($bank['code_regex'] ?? '.+').'/i'],
            'account_number' => ['required', 'string', 'max:40', 'regex:/'.($bank['account_regex'] ?? '.+').'/'],
            'document_path' => ['required', 'string', 'max:255'],
            'document_name' => ['nullable', 'string', 'max:255'],
            'document_issued_on' => ['required', 'date', 'after_or_equal:'.$oldest, 'before_or_equal:today'],
        ], [
            'bank_code.regex' => 'Enter a valid '.($bank['code_label'] ?? 'bank code').'.',
            'account_number.regex' => 'Enter a valid '.strtolower($bank['account_label'] ?? 'account number').'.',
            'document_path.required' => 'Upload a bank document.',
            'document_issued_on.after_or_equal' => 'The bank document must be issued within the last '.SellerOnboarding::BANK_DOCUMENT_MAX_AGE_DAYS.' days.',
        ]);
        $this->assertOwnDocument($request, $data['document_path']);

        $seller->forceFill([
            'payout_method' => 'bank',
            'payout_details' => [
                'holder_name' => trim($data['holder_name']),
                'bank_name' => trim($data['bank_name']),
                // Kept under routing_number so older payout screens still read it.
                'routing_number' => strtoupper(trim($data['bank_code'])),
                'bank_code_label' => $bank['code_label'] ?? 'Routing number',
                'account_number' => trim($data['account_number']),
                'bank_country' => $seller->country,
                'document_path' => $data['document_path'],
                'document_name' => $data['document_name'] ?? null,
                'document_issued_on' => $data['document_issued_on'],
            ],
            'bank_status' => 'processing',
            'bank_note' => null,
            'bank_submitted_at' => now(),
            'bank_verified_at' => null,
        ])->save();

        return response()->json(['data' => $this->payload($seller)]);
    }

    private function seller(Request $request): Seller
    {
        $seller = $request->user()->seller()->with('shop')->first();
        abort_unless($seller?->status === 'approved', 403, 'Approved seller access required.');

        return $seller;
    }

    /** Uploaded documents must be this seller's own KYC uploads. */
    private function assertOwnDocument(Request $request, ?string $path): void
    {
        if ($path !== null && $path !== '' && ! str_starts_with($path, 'kyc/'.$request->user()->id.'/')) {
            throw ValidationException::withMessages(['document' => 'Upload the document again.']);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Seller $seller): array
    {
        $seller->refresh()->load('shop');

        return [
            'config' => SellerOnboarding::config($seller) + ['bank_document_max_age_days' => SellerOnboarding::BANK_DOCUMENT_MAX_AGE_DAYS],
            'tasks' => SellerOnboarding::tasks($seller),
            'business_type' => $seller->business_type,
            'is_company' => SellerOnboarding::isCompany($seller),
            'company_name' => $seller->company_name,
            'country' => $seller->country,
            'registered_tax_id' => $seller->tax_id,
            'registered_address' => array_filter([$seller->registered_line1, $seller->registered_line2, $seller->registered_city, $seller->registered_state, $seller->registered_postal_code, $seller->registered_country]),
            'primary_contact' => SellerOnboarding::primaryContact($seller),
            'tax' => ['info' => $seller->tax_info, 'status' => $seller->tax_status, 'note' => $seller->tax_note, 'submitted_at' => $seller->tax_submitted_at],
            'compliance' => ['data' => $seller->compliance, 'status' => $seller->compliance_status, 'note' => $seller->compliance_note, 'submitted_at' => $seller->compliance_submitted_at],
            'bank' => [
                'details' => $seller->payout_method === 'bank' ? $seller->payout_details : null,
                'status' => $seller->bank_status,
                'note' => $seller->bank_note,
                'submitted_at' => $seller->bank_submitted_at,
                'verified_at' => $seller->bank_verified_at,
            ],
        ];
    }
}
