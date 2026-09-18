<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class CustomerNames
{
    /** The name shown for an account: its name, or the text before '@' in its email when unnamed. */
    public static function base(User $user): string
    {
        return trim((string) $user->name) !== '' ? $user->name : Str::before($user->email, '@');
    }

    /**
     * Map of every non-admin user id => display name, with "[1]", "[2]", ...
     * appended when multiple accounts share the same base name, so the same
     * two customers are told apart the same way everywhere their name shows
     * (orders, customers list, order/customer detail).
     *
     * @return array<int, string>
     */
    public static function map(): array
    {
        $users = User::query()->where('is_admin', false)->select('id', 'name', 'email')->orderBy('id')->get();

        $map = [];
        foreach ($users->groupBy(fn (User $u) => self::base($u)) as $base => $group) {
            if ($group->count() === 1) {
                $map[$group->first()->id] = $base;

                continue;
            }
            foreach ($group->values() as $i => $u) {
                $map[$u->id] = "{$base} [".($i + 1).']';
            }
        }

        return $map;
    }
}
