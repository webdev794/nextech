<?php

namespace App\Support;

/**
 * Masks abusive words in chat messages with asterisks (same length), so
 * nobody in a NexTech chat — buyers, sellers, riders or staff — sees them.
 * Applied when a message is saved (SupportThread::post()).
 *
 * STEMS match a word starting with them ("fuck" → "fucking", "fucker");
 * WORDS only match whole words, for short ones that are parts of innocent
 * words ("ass" in "class", "assist").
 */
class Profanity
{
    private const STEMS = [
        'fuck', 'fuk', 'fck', 'motherfuck', 'shit', 'bullshit', 'bitch', 'bastard', 'asshole', 'arsehole',
        'dickhead', 'cunt', 'wanker', 'whore', 'slut', 'retard', 'nigg', 'faggot', 'douche', 'jackass', 'dumbass',
        'scumbag', 'piss off', 'pissed off', 'stfu', 'wtf',
        // Common Hindi / Punjabi abuse, as typed in English letters.
        'madarchod', 'maderchod', 'mc bc', 'behenchod', 'bhenchod', 'benchod', 'bhosdi', 'bhosad', 'chutiya', 'chutiye',
        'chootiya', 'gaandu', 'gandu', 'randi', 'harami', 'haramkhor', 'kamina', 'kutta', 'kutti', 'lauda', 'lawda',
        'lund', 'jhant', 'tatti',
    ];

    private const WORDS = ['ass', 'arse', 'dick', 'cock', 'prick', 'twat', 'crap', 'damn', 'hell no', 'bsdk', 'idiot', 'stupid', 'moron'];

    public static function mask(string $text): string
    {
        $stems = implode('|', array_map(fn ($w) => str_replace(' ', '\s+', preg_quote($w, '/')), self::STEMS));
        $words = implode('|', array_map(fn ($w) => str_replace(' ', '\s+', preg_quote($w, '/')), self::WORDS));

        return (string) preg_replace_callback(
            "/\\b(?:(?:{$stems})[a-z]*|(?:{$words}))\\b/iu",
            fn ($m) => preg_replace('/\S/u', '*', $m[0]),
            $text,
        );
    }
}
