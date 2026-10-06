<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Checks a seller's hosted download link (Google Drive, Dropbox, own server)
 * actually leads to a file a buyer can get without signing in. Share links
 * are turned into their direct-download form first. Only public internet
 * addresses are fetched, and only the first bytes of the file.
 *
 * Result: status ok (a file), page (opens a web page — may still work, buyers
 * click Download there) or broken (unreachable, error, or asks to sign in).
 */
class DownloadLinkCheck
{
    /** Share-page links → direct downloads. */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        // Google Drive: /file/d/{id}/view, open?id={id}, uc?id={id}
        if (preg_match('~^https://drive\.google\.com/(?:file/d/([\w-]+)|(?:open|uc)\?(?:.*&)?id=([\w-]+))~', $url, $m)) {
            return 'https://drive.google.com/uc?export=download&id='.($m[1] ?: $m[2]);
        }
        // Dropbox: ?dl=0 (preview page) → dl=1 (the file)
        if (preg_match('~^https://(www\.)?dropbox\.com/~', $url)) {
            $url = preg_replace('~([?&])dl=0~', '$1dl=1', $url);

            return str_contains($url, 'dl=1') || str_contains($url, 'raw=1') ? $url : $url.(str_contains($url, '?') ? '&' : '?').'dl=1';
        }

        return $url;
    }

    /** @return array{status: string, note: ?string, size: ?int} */
    public static function check(string $url): array
    {
        if (! self::publicHttps($url)) {
            return ['status' => 'broken', 'note' => 'Use a public https:// link.', 'size' => null];
        }
        try {
            $response = Http::timeout(15)->withHeaders(['Range' => 'bytes=0-0', 'User-Agent' => 'NexTech-LinkCheck/1.0'])
                ->withOptions(['stream' => true, 'allow_redirects' => [
                    'max' => 6, 'track_redirects' => true,
                    'on_redirect' => function ($request, $response, $uri): void {
                        if (! self::publicHttps((string) $uri)) {
                            throw new \RuntimeException('Redirects to a non-public address.');
                        }
                    },
                ]])->get($url);
        } catch (\Throwable $e) {
            return ['status' => 'broken', 'note' => 'Couldn\'t open the link ('.Str::limit($e->getMessage(), 120).').', 'size' => null];
        }

        $final = (string) (collect($response->header('X-Guzzle-Redirect-History') ? explode(', ', $response->header('X-Guzzle-Redirect-History')) : [])->last() ?: $url);
        if (preg_match('~accounts\.google\.com|/login|/signin|ServiceLogin~i', $final)) {
            return ['status' => 'broken', 'note' => 'The link asks buyers to sign in — share it as "Anyone with the link".', 'size' => null];
        }
        if ($response->status() >= 400) {
            return ['status' => 'broken', 'note' => "The link returns an error (HTTP {$response->status()}) — check it's shared publicly and still exists.", 'size' => null];
        }

        $type = strtolower((string) $response->header('Content-Type'));
        $disposition = strtolower((string) $response->header('Content-Disposition'));
        $size = self::size($response->header('Content-Range'), $response->header('Content-Length'));
        if (str_contains($disposition, 'attachment') || ($type !== '' && ! str_contains($type, 'text/html'))) {
            return ['status' => 'ok', 'note' => null, 'size' => $size];
        }

        // An HTML page: Google Drive's "can't scan this large file" page still leads to the file.
        $body = '';
        try {
            $stream = $response->toPsrResponse()->getBody();
            while (! $stream->eof() && strlen($body) < 65536) {
                $body .= $stream->read(8192);
            }
        } catch (\Throwable) {
        }
        if (preg_match('~ServiceLogin|accounts\.google\.com/v3/signin|type="password"~i', $body)) {
            return ['status' => 'broken', 'note' => 'The link asks buyers to sign in — share it as "Anyone with the link".', 'size' => null];
        }
        if (str_contains($url, 'drive.google.com') && preg_match('~virus scan|too large|download anyway|uc-download-link~i', $body)) {
            return ['status' => 'ok', 'note' => 'Large Google Drive file — buyers confirm "Download anyway".', 'size' => null];
        }

        // Dropbox's direct (dl=1) form answers with the file itself; a page means it's gone or not shared.
        if (preg_match('~^https://(www\.)?dropbox\.com/~', $url)) {
            return ['status' => 'broken', 'note' => 'Dropbox shows a page instead of the file — check the link is shared publicly and the file still exists.', 'size' => null];
        }

        return ['status' => 'page', 'note' => 'Opens a web page, not the file directly — buyers will need to click Download there.', 'size' => null];
    }

    /** https, and the host resolves only to public internet addresses. */
    private static function publicHttps(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge((array) @gethostbynamel($host), array_column((array) @dns_get_record($host, DNS_AAAA), 'ipv6'));
        $ips = array_filter($ips);
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private static function size(?string $range, ?string $length): ?int
    {
        if ($range && preg_match('~/(\d+)$~', $range, $m)) {
            return (int) $m[1];
        }

        return $length !== null && $length !== '' && (int) $length > 1 ? (int) $length : null;
    }
}
