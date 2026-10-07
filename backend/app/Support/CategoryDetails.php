<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin-editable "product details" per category (categories.detail_fields):
 * text boxes, number boxes, dropdowns, checkbox lists and yes/no checkboxes,
 * each with a label. A detail — or a dropdown/checkbox choice — that products
 * already use is locked: it can be renamed but not removed or changed in type.
 */
class CategoryDetails
{
    /** text box, number box, dropdown, checkbox list (several choices), one yes/no checkbox */
    public const TYPES = ['text', 'number', 'select', 'multiselect', 'checkbox'];

    /** Details the store's own rules rely on (warranty follow-ups, model number on sold items): renamable, never removable. */
    public const CORE = ['model_number', 'warranty', 'warranty_terms'];

    /** Details every physical product is asked for, after its category's own (admin-editable). */
    public static function common(): array
    {
        $saved = \App\Models\Setting::get('common_detail_fields');

        return is_array($saved) ? array_values($saved) : (array) config('product_catalog.common_attributes', []);
    }

    /** The category whose list $category uses: itself, or its nearest parent with a list (null = built-in). */
    public static function owner(Category $category): ?Category
    {
        foreach (array_reverse($category->lineage()) as $c) {
            if (is_array($c->detail_fields)) {
                return $c;
            }
        }

        return null;
    }

    /**
     * How products use this category's details: per key, how many products have
     * a value, and per choice how many picked it. Counts products in this
     * category and every subcategory that uses the same list.
     *
     * @return array<string, array{count: int, options: array<string, int>}>
     */
    public static function usage(Category $category): array
    {
        // This category and the subcategories that share its list (they'd change with it).
        $ownerId = self::owner($category)?->id;
        $ids = collect(Category::withDescendantIds($category->id))
            ->filter(fn ($id) => ($c = Category::tree()->get($id)) && self::owner($c)?->id === $ownerId)->values()->all() ?: [$category->id];

        return self::usageIn($ids);
    }

    /** Usage of the common details: every physical product. */
    public static function commonUsage(): array
    {
        return self::usageIn(Category::tree()->where('kind', '!=', 'digital')->keys()->all());
    }

    /** @param  list<int>  $ids */
    private static function usageIn(array $ids): array
    {
        $usage = [];
        Product::query()->whereIn('category_id', $ids)->whereNull('archived_at')->whereNotNull('product_details')
            ->select(['id', 'product_details', 'pending_changes'])->chunkById(500, function ($products) use (&$usage) {
                foreach ($products as $product) {
                    // An edit waiting for review counts too.
                    $sets = [(array) $product->product_details, (array) (((array) $product->pending_changes)['data']['product_details'] ?? [])];
                    $seen = [];
                    foreach ($sets as $details) {
                        foreach ($details as $key => $value) {
                            if ($value === null || $value === '' || $value === []) {
                                continue;
                            }
                            if (! isset($seen[$key])) {
                                $usage[$key]['count'] = ($usage[$key]['count'] ?? 0) + 1;
                                $usage[$key]['options'] ??= [];
                                $seen[$key] = [];
                            }
                            foreach ((array) $value as $v) {
                                $v = (string) $v;
                                if (! in_array($v, $seen[$key], true)) {
                                    $usage[$key]['options'][$v] = ($usage[$key]['options'][$v] ?? 0) + 1;
                                    $seen[$key][] = $v;
                                }
                            }
                        }
                    }
                }
            });

        return $usage;
    }

    /**
     * Check and tidy a list from the admin form. $current is the list it replaces
     * (to keep keys of existing details); locked details and choices must stay.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $current
     * @return list<array<string, mixed>>
     */
    public static function clean(array $fields, array $current, array $usage): array
    {
        $errors = [];
        $out = [];
        $keys = [];
        $currentByKey = collect($current)->keyBy('key');
        // Keys the form sends back for existing details, so a new one never takes theirs.
        $given = collect($fields)->pluck('key')->filter(fn ($k) => $k && $currentByKey->has($k))->all();
        foreach (array_values($fields) as $i => $f) {
            $label = trim((string) ($f['label'] ?? ''));
            $type = (string) ($f['type'] ?? 'text');
            if ($label === '') {
                $errors["fields.$i.label"] = 'Give detail '.($i + 1).' a label.';

                continue;
            }
            if (! in_array($type, self::TYPES, true)) {
                $errors["fields.$i.type"] = "“{$label}”: choose what kind of box it is.";

                continue;
            }
            // Existing details keep their key (products store values under it); new ones get one from the label.
            $key = (string) ($f['key'] ?? '');
            if ($key === '' || ! $currentByKey->has($key)) {
                $base = substr(preg_replace('/[^a-z0-9_]/', '', Str::snake(Str::ascii($label))) ?: 'detail', 0, 40);
                $key = $base;
                for ($n = 2; in_array($key, $keys, true) || in_array($key, $given, true); $n++) {
                    $key = $base.'_'.$n;
                }
            }
            if (in_array($key, $keys, true)) {
                $errors["fields.$i.label"] = "“{$label}” is listed twice.";

                continue;
            }
            $keys[] = $key;
            $row = ['key' => $key, 'label' => mb_substr($label, 0, 80), 'type' => $type];
            if (in_array($type, ['select', 'multiselect'], true)) {
                $options = array_values(array_unique(array_filter(array_map(fn ($o) => mb_substr(trim((string) $o), 0, 80), (array) ($f['options'] ?? [])), fn ($o) => $o !== '')));
                if (! $options) {
                    $errors["fields.$i.options"] = "“{$label}”: add at least one choice.";
                }
                $row['options'] = $options;
            }
            if (! empty($f['required']) && $type !== 'checkbox') {
                $row['required'] = true;
            }
            if (($unit = trim((string) ($f['unit'] ?? ''))) !== '' && in_array($type, ['text', 'number'], true)) {
                $row['unit'] = mb_substr($unit, 0, 20);
            }
            if (($placeholder = trim((string) ($f['placeholder'] ?? ''))) !== '' && in_array($type, ['text', 'number'], true)) {
                $row['placeholder'] = mb_substr($placeholder, 0, 300);
            }
            // "Only when another detail is …" — kept from the built-in lists (not edited here).
            if (! empty($currentByKey[$key]['when'])) {
                $row['when'] = $currentByKey[$key]['when'];
            }
            $out[] = $row;
        }

        // Locked: details and choices that products use.
        $newByKey = collect($out)->keyBy('key');
        foreach ($current as $old) {
            if (in_array($old['key'], self::CORE, true) && ! $newByKey->has($old['key'])) {
                $errors['fields'][] = "“{$old['label']}” is needed by the store (warranty and order follow-ups) — it can’t be removed.";
            }
            // Another detail shows only for some answers to this one.
            foreach ($out as $other) {
                if (isset($other['when'][$old['key']]) && ! $newByKey->has($old['key'])) {
                    $errors['fields'][] = "“{$old['label']}” decides when “{$other['label']}” is shown — remove that one first.";
                }
            }
            $used = $usage[$old['key']] ?? null;
            if (! $used) {
                continue;
            }
            $new = $newByKey->get($old['key']);
            if (! $new) {
                $errors['fields'][] = "“{$old['label']}” is used by {$used['count']} product".($used['count'] === 1 ? '' : 's').' — it can’t be removed.';

                continue;
            }
            // A dropdown can become a checkbox list and back; any other change of kind would break saved values.
            $swap = [$old['type'], $new['type']] === ['select', 'multiselect'] || [$old['type'], $new['type']] === ['multiselect', 'select'];
            if ($old['type'] !== $new['type'] && ! $swap) {
                $errors['fields'][] = "“{$new['label']}” is used by products — its kind of box can’t change.";
            }
            if (in_array($new['type'], ['select', 'multiselect'], true)) {
                foreach (array_keys($used['options']) as $choice) {
                    if (! in_array($choice, $new['options'] ?? [], true)) {
                        $errors['fields'][] = "“{$new['label']}”: the choice “{$choice}” is used by {$used['options'][$choice]} product".($used['options'][$choice] === 1 ? '' : 's').' — it can’t be removed.';
                    }
                }
            }
        }
        if ($errors) {
            $errors = collect($errors)->map(fn ($e) => (array) $e)->all();
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
