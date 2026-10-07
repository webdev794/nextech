<?php

namespace App\Support;

/**
 * Store pages stay on the platform: a seller's own text (store design, shop
 * description) can't send buyers elsewhere — no websites, social media,
 * messaging apps, phone numbers or email addresses.
 */
class ContactInfo
{
    /** What kind of off-platform contact this text contains ("a phone number"…), or null. */
    public static function find(?string $text): ?string
    {
        $t = (string) $text;
        if (trim($t) === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $t) => 'an email address',
            (bool) preg_match('~https?://|www\.|\b[a-z0-9-]+\.(com|net|org|in|co|io|me|shop|store|site|online|info|biz|app|link|ly)\b(/|\s|$)~i', $t) => 'a website address',
            (bool) preg_match('/\b(instagram|insta|facebook|fb\.com|youtube|youtu\.be|tiktok|twitter|x\.com|telegram|t\.me|whatsapp|wa\.me|snapchat|linkedin|pinterest|discord)\b/i', $t) => 'a social media or messaging app',
            (bool) preg_match('/(?<![\w@])@[a-z0-9_.]{3,}/i', $t) => 'a social media handle',
            (bool) preg_match('/(?:\+?\d[\s\-().]*){8,}/', $t) => 'a phone number',
            default => null,
        };
    }
}
