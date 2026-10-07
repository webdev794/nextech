<?php

namespace App\Support;

use App\Models\Trademark;
use Illuminate\Support\Carbon;

/**
 * Changing an approved trademark (its name or registration). Not while
 * buyers of its products are still covered by returns / warranty; otherwise
 * the seller asks, and admin approves, rejects or asks for documents first.
 */
class TrademarkChanges
{
    public const FIELDS = ['name', 'registration_number', 'registration_country', 'certificate_path'];

    /**
     * Products under this trademark that buyers are still covered on, latest first.
     *
     * @return list<array{name: string, until: string}>
     */
    public static function covered(Trademark $trademark): array
    {
        return $trademark->products()->get()
            ->map(fn ($p) => ['name' => $p->name, 'until' => ProductCatalog::supportUntil($p)])
            ->filter(fn ($row) => $row['until'] !== null)
            ->sortByDesc(fn ($row) => $row['until'])
            ->map(fn ($row) => ['name' => $row['name'], 'until' => $row['until']->toDateString()])
            ->values()->all();
    }

    public static function coveredUntil(Trademark $trademark): ?Carbon
    {
        $first = self::covered($trademark)[0] ?? null;

        return $first ? Carbon::parse($first['until']) : null;
    }

    /**
     * What admin should check before approving (a new trademark or a change).
     *
     * @return list<string>
     */
    public static function advice(Trademark $trademark): array
    {
        $change = (array) ($trademark->change_request ?? []);
        $name = trim((string) ($change['name'] ?? $trademark->name));
        $advice = [];

        $owners = Trademark::query()->where('id', '!=', $trademark->id)->where('status', 'approved')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->with('shop:id,name')->get();
        foreach ($owners as $other) {
            $advice[] = "“{$name}” is already an approved trademark of ".($other->shop?->name ?? 'another shop').' — check who owns it before approving.';
        }

        if ($change) {
            $renamed = isset($change['name']) && $change['name'] !== $trademark->name;
            $newRegistration = (isset($change['registration_number']) && $change['registration_number'] !== $trademark->registration_number)
                || (isset($change['registration_country']) && $change['registration_country'] !== $trademark->registration_country);
            if (($renamed || $newRegistration) && empty($change['certificate_path'])) {
                $advice[] = 'No new registration certificate uploaded for the changed details — ask the seller for it.';
            }
            if ($until = self::coveredUntil($trademark)) {
                $advice[] = 'Buyers are still covered by returns / warranty until '.$until->format('j M Y').' — this can’t be approved before then.';
            }
            $products = $trademark->products()->count();
            if ($products && $renamed) {
                $advice[] = "{$products} product".($products === 1 ? '' : 's').' will show the new brand name.';
                $withDocs = $trademark->products()->get(['id', 'compliance'])->filter(fn ($p) => ! empty(((array) $p->compliance)['documents'] ?? []))->count();
                if ($withDocs) {
                    $advice[] = "{$withDocs} of them have compliance documents uploaded under the old brand — ask the seller to re-upload any that show the brand name.";
                }
            }
        }

        return $advice;
    }

    /** Admin approved the change: apply it (the name follows onto every product, which link by id). */
    public static function apply(Trademark $trademark): void
    {
        $change = array_intersect_key((array) $trademark->change_request, array_flip(self::FIELDS));
        $trademark->update(array_filter($change, fn ($v) => $v !== null && $v !== '') + [
            'change_request' => null, 'change_status' => null, 'change_note' => null, 'reviewed_at' => now(),
        ]);
    }
}
