<?php

namespace App\Support;

/**
 * Keeps buyers' personal details away from sellers: names are shown masked
 * ("J**n D*e"), and emails / phone numbers in chats sellers take part in are
 * replaced so neither side can move the conversation off NexTech.
 */
class Privacy
{
    public const EMAIL_HIDDEN = '[email hidden]';

    public const PHONE_HIDDEN = '[phone number hidden]';

    /** "John Doe" -> "J**n D*e": first and last letter of each word, stars between. */
    public static function maskName(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return 'Customer';
        }

        return implode(' ', array_map(function (string $word) {
            $length = mb_strlen($word);

            return match (true) {
                $length <= 1 => $word.'*',
                $length === 2 => mb_substr($word, 0, 1).'*',
                default => mb_substr($word, 0, 1).str_repeat('*', $length - 2).mb_substr($word, -1),
            };
        }, $words));
    }

    /**
     * Replace email addresses and phone numbers in a chat message. Phone
     * numbers are 10-digit numbers (US / India), optionally with a country
     * code, or anything written with a leading "+"; longer digit runs such
     * as courier tracking numbers are left alone.
     */
    public static function redactContacts(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $text = preg_replace('/[A-Z0-9._%+-]+\s*(?:@|\(at\)|\[at\])\s*[A-Z0-9.-]+\s*(?:\.|\(dot\)|\[dot\])\s*[A-Z]{2,}/i', self::EMAIL_HIDDEN, $text);

        return preg_replace_callback('/(?<![\w+(])\(?\+?\d[\d\s().-]{7,17}\d(?!\w)/', function (array $m) {
            $raw = ltrim($m[0], '(');
            $digits = preg_replace('/\D/', '', $raw);
            $count = strlen($digits);
            $isPhone = $count === 10
                || (str_starts_with($raw, '+') && $count >= 8 && $count <= 13)
                || ($count === 11 && str_starts_with($digits, '1') && preg_match('/[\s().-]/', $raw))
                || ($count === 12 && str_starts_with($digits, '91') && preg_match('/[\s().-]/', $raw));

            return $isPhone ? self::PHONE_HIDDEN : $m[0];
        }, $text);
    }
}
