<?php

namespace App\Support;

/** A link to a page of the website, from its address setting (FRONTEND_URL, else APP_URL) — never typed in. */
final class SiteUrl
{
    public static function to(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').'/'.ltrim($path, '/');
    }
}
