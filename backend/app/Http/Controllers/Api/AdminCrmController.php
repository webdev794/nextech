<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerEmail;
use App\Models\EmailCampaign;
use App\Models\GiftCard;
use App\Models\OrderRefund;
use App\Models\RiderReview;
use App\Models\Setting;
use App\Models\SiteFeedback;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\CustomEmail;
use App\Support\CustomerMail;
use App\Support\CustomerNames;
use App\Support\EmailCampaigns;
use App\Support\Market;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin CRM: everything about one customer (orders, emails, chats, credit,
 * ratings, logins) in a single view and timeline, plus admin emails — to one
 * customer or an audience, sent now or on a schedule, optionally repeating.
 */
class AdminCrmController extends Controller
{
    /** Customer 360. */
    public function customer(User $user): JsonResponse
    {
        if ($user->is_admin) {
            throw new NotFoundHttpException;
        }

        $orders = $user->orders()->with('items')->latest()->get();
        $emails = CustomerEmail::where('user_id', $user->id)->latest('id')->limit(200)->get();
        $threads = SupportThread::where('user_id', $user->id)->withCount('messages')->latest('id')->get();
        $giftCards = GiftCard::where('user_id', $user->id)->latest('id')->get(['id', 'code', 'initial_cents', 'balance_cents', 'is_active', 'order_id', 'created_at']);
        $reviews = RiderReview::where('user_id', $user->id)->latest('id')->get(['id', 'order_id', 'rating', 'comment', 'created_at']);
        $feedback = SiteFeedback::where('user_id', $user->id)->latest('id')->get(['id', 'rating', 'created_at']);
        $refunds = OrderRefund::whereIn('order_id', $orders->pluck('id'))->latest('id')->get(['id', 'order_id', 'amount_cents', 'reason', 'created_at']);
        $lastSeen = DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->max('last_used_at');

        $currencyOf = $orders->pluck('currency', 'id');
        $paid = $orders->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded']);
        $spend = $paid->groupBy(fn ($o) => $o->currency ?: 'usd')->map(fn ($g, $cur) => Money::format((int) $g->sum('total_cents'), $cur))->values();

        // One feed, newest first.
        $timeline = collect()
            ->push(['type' => 'joined', 'at' => $user->created_at, 'title' => 'Joined', 'detail' => $user->email])
            ->merge($orders->map(fn ($o) => ['type' => 'order', 'at' => $o->created_at, 'title' => "Placed order #{$o->id}", 'detail' => Money::format((int) $o->total_cents, $o->currency).' · '.str_replace('_', ' ', $o->status), 'order_id' => $o->id]))
            ->merge($orders->whereNotNull('delivered_at')->map(fn ($o) => ['type' => 'delivered', 'at' => $o->delivered_at, 'title' => "Order #{$o->id} delivered", 'detail' => null, 'order_id' => $o->id]))
            ->merge($refunds->map(fn ($r) => ['type' => 'refund', 'at' => $r->created_at, 'title' => "Refund on order #{$r->order_id}", 'detail' => Money::format((int) $r->amount_cents, $currencyOf[$r->order_id] ?? 'usd').($r->reason ? " · {$r->reason}" : ''), 'order_id' => $r->order_id]))
            ->merge($emails->map(fn ($e) => ['type' => 'email', 'at' => $e->sent_at ?? $e->created_at, 'title' => ($e->status === 'failed' ? 'Email failed: ' : 'Email: ').($e->subject ?? str_replace('_', ' ', $e->kind)), 'detail' => str_replace('_', ' ', $e->kind), 'email_id' => $e->id]))
            ->merge($threads->map(fn ($t) => ['type' => 'support', 'at' => $t->created_at, 'title' => 'Support chat #'.$t->id.' ('.str_replace('_', ' ', (string) $t->issue_type).')', 'detail' => $t->status.($t->rating ? " · rated {$t->rating}★" : ''), 'thread_id' => $t->id]))
            ->merge($giftCards->map(fn ($g) => ['type' => 'credit', 'at' => $g->created_at, 'title' => "Store credit {$g->code}", 'detail' => Money::format((int) $g->initial_cents, $currencyOf[$g->order_id] ?? 'usd'), 'order_id' => $g->order_id]))
            ->merge($reviews->map(fn ($r) => ['type' => 'rating', 'at' => $r->created_at, 'title' => "Rated delivery {$r->rating}★", 'detail' => $r->comment, 'order_id' => $r->order_id]))
            ->merge($feedback->map(fn ($f) => ['type' => 'feedback', 'at' => $f->created_at, 'title' => "Site feedback {$f->rating}/5", 'detail' => null]))
            ->filter(fn ($e) => $e['at'] !== null)
            ->sortByDesc('at')
            ->values()
            ->take(300);

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'display_name' => CustomerNames::map()[$user->id] ?? CustomerNames::base($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'is_rider' => (bool) $user->is_rider,
            'marketing_opt_out' => (bool) $user->marketing_opt_out,
            'joined_at' => $user->created_at,
            'addresses' => $user->addresses()->latest()->get(),
            'orders' => $orders,
            'emails' => $emails,
            'support_threads' => $threads,
            'gift_cards' => $giftCards,
            'rider_reviews' => $reviews,
            'scheduled_emails' => EmailCampaign::where('audience->user_id', $user->id)->whereIn('status', ['scheduled', 'draft'])->latest('id')->get(),
            'stats' => [
                'orders' => $orders->count(),
                'spend' => $spend,
                'refunds' => $refunds->count(),
                'last_order_at' => $orders->first()?->created_at,
                'last_seen_at' => $lastSeen,
                'emails' => $emails->count(),
                'support_threads' => $threads->count(),
            ],
            'timeline' => $timeline,
        ]]);
    }

    /** The rendered HTML of one logged email (admin preview). */
    public function emailBody(CustomerEmail $email): JsonResponse
    {
        return response()->json(['data' => ['id' => $email->id, 'subject' => $email->subject, 'html' => $email->body_html]]);
    }

    /** Email one customer now, or schedule it (send_at), optionally repeating. */
    public function emailCustomer(Request $request, User $user): JsonResponse
    {
        abort_if($user->is_admin, 404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'send_at' => ['nullable', 'date', 'after:now'],
            'repeat' => ['sometimes', Rule::in(EmailCampaign::REPEATS)],
        ]);

        $campaign = EmailCampaign::create([
            'name' => "To {$user->name}: {$data['subject']}",
            'subject' => $data['subject'],
            'body' => $data['body'],
            'audience' => ['user_id' => $user->id],
            'promotional' => false,
            'send_at' => $data['send_at'] ?? now(),
            'repeat' => $data['repeat'] ?? 'none',
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ]);

        if (empty($data['send_at'])) {
            $sent = EmailCampaigns::run($campaign);
            abort_if($sent === 0, 422, 'The email could not be sent — check the mail settings and the customer\'s address.');
        }

        return response()->json(['data' => $campaign->fresh()], 201);
    }

    // ------------------------------------------------------------ campaigns

    public function campaigns(): JsonResponse
    {
        return response()->json(['data' => [
            'campaigns' => EmailCampaign::latest('id')->limit(200)->get(),
            'recent' => CustomerEmail::with('user:id,name,email')->latest('id')->limit(100)->get(['id', 'user_id', 'to_email', 'kind', 'subject', 'status', 'error', 'order_id', 'campaign_id', 'sent_at', 'created_at']),
            'order_emails' => CustomerMail::orderEmailsEnabled(),
        ]]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $data = $this->validatedCampaign($request);
        $campaign = EmailCampaign::create($this->campaignAttributes($data) + ['created_by' => $request->user()->id]);

        return response()->json(['data' => $this->afterSave($campaign, $data)], 201);
    }

    public function updateCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        abort_if(in_array($campaign->status, ['sending', 'sent'], true) && $campaign->repeat === 'none', 422, 'This email was already sent.');
        $data = $this->validatedCampaign($request);
        $campaign->update($this->campaignAttributes($data));

        return response()->json(['data' => $this->afterSave($campaign, $data)]);
    }

    public function cancelCampaign(EmailCampaign $campaign): JsonResponse
    {
        abort_if($campaign->status === 'sending', 422, 'It is being sent right now.');
        $campaign->status === 'draft' ? $campaign->delete() : $campaign->update(['status' => 'cancelled']);

        return response()->json(status: 204);
    }

    public function sendCampaignNow(EmailCampaign $campaign): JsonResponse
    {
        abort_if(in_array($campaign->status, ['sending', 'cancelled'], true), 422, 'This email can\'t be sent now.');
        $sent = EmailCampaigns::run($campaign);

        return response()->json(['data' => $campaign->fresh(), 'sent' => $sent]);
    }

    /** How many customers an audience reaches, plus the email rendered for one of them. */
    public function previewCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', 'array'],
            'promotional' => ['sometimes', 'boolean'],
        ]);
        $promotional = (bool) ($data['promotional'] ?? true);
        $query = EmailCampaigns::audience($data['audience'], $promotional);
        $sample = (clone $query)->first() ?? new User(['name' => 'Alex Customer']);

        $html = (new CustomEmail($data['subject'], $data['body'], null, $promotional))->toMail($sample)->render();

        return response()->json(['data' => ['count' => $query->count(), 'html' => (string) $html]]);
    }

    /** Order confirmed / shipped emails on or off. */
    public function orderEmails(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        Setting::put('order_emails', (bool) $data['enabled']);

        return response()->json(['data' => ['order_emails' => (bool) $data['enabled']]]);
    }

    /** @return array<string, mixed> */
    private function validatedCampaign(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', 'array'],
            'audience.user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'audience.market' => ['sometimes', 'nullable', Rule::in(array_keys(config('markets', [])))],
            'audience.has_ordered' => ['sometimes', 'nullable', 'boolean'],
            'audience.no_order_in_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'promotional' => ['sometimes', 'boolean'],
            'send_at' => ['nullable', 'date'],
            'repeat' => ['sometimes', Rule::in(EmailCampaign::REPEATS)],
            'action' => ['required', Rule::in(['draft', 'schedule', 'send_now'])],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function campaignAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'audience' => array_filter($data['audience'], fn ($v) => $v !== null && $v !== ''),
            'promotional' => (bool) ($data['promotional'] ?? true),
            'send_at' => $data['action'] === 'send_now' ? now() : ($data['send_at'] ?? null),
            'repeat' => $data['repeat'] ?? 'none',
            'status' => $data['action'] === 'draft' ? 'draft' : 'scheduled',
        ];
    }

    /** @param array<string, mixed> $data */
    private function afterSave(EmailCampaign $campaign, array $data): EmailCampaign
    {
        abort_if($data['action'] === 'schedule' && ! $campaign->send_at, 422, 'Pick a date and time to schedule it.');
        if ($data['action'] === 'send_now') {
            EmailCampaigns::run($campaign);
        }

        return $campaign->fresh();
    }
}
