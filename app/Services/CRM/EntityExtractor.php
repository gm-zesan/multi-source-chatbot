<?php

declare(strict_types=1);

namespace App\Services\CRM;

class EntityExtractor
{
    public function extract(?string $text): array
    {
        $text ??= '';

        $emails   = $this->emails($text);
        $phones   = $this->phones($text);
        $websites = $this->websites($text, $emails);
        $nid      = $this->nid($text, $phones);

        return [
            'person' => [
                'name' => null,
            ],

            'contact' => [
                'phones'   => $phones,
                'emails'   => $emails,
                'websites' => $websites,
            ],

            'business' => [
                'company' => null,
            ],

            'location' => [
                'address' => null,
            ],

            'document' => [
                'nid' => $nid,
            ],
        ];
    }

    protected function emails(string $text): array
    {
        preg_match_all(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            $text,
            $matches
        );

        return array_values(array_unique($matches[0]));
    }

    protected function phones(string $text): array
    {
        preg_match_all(
            '/(?:\+?88\s*|88)?01[3-9](?:[\s-]?\d){8}\b/',
            $text,
            $matches
        );

        $results = [];
        foreach ($matches[0] as $match) {
            $clean = preg_replace('/[\s-]/', '', $match);
            $results[] = $clean;
        }

        return array_values(array_unique($results));
    }

    protected function websites(string $text, array $knownEmails = []): array
    {
        // Strip out email addresses so email usernames and email domains are not extracted as standalone websites
        $cleanText = $text;
        foreach ($knownEmails as $email) {
            $cleanText = str_replace($email, ' ', $cleanText);
        }
        $cleanText = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', ' ', $cleanText);

        // Match URLs starting with http://, https://, or www.
        preg_match_all(
            '/\b(?:https?:\/\/|www\.)[a-z0-9-]+(?:\.[a-z0-9-]+)+[^\s()<>]*/i',
            $cleanText,
            $urlMatches
        );

        // Match standalone domain names with valid web TLDs
        preg_match_all(
            '/\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:com|org|net|io|co|edu|gov|dev|app|me|info|biz|bd|co\.bd)\b/i',
            $cleanText,
            $domainMatches
        );

        $rawWebsites = array_merge($urlMatches[0] ?? [], $domainMatches[0] ?? []);
        $cleaned = [];

        foreach ($rawWebsites as $site) {
            $site = rtrim(trim($site), '.,!?:;)');
            if ($site !== '') {
                $cleaned[] = $site;
            }
        }

        return array_values(array_unique($cleaned));
    }

    protected function nid(string $text, array $knownPhones = []): ?string
    {
        // Explicit NID keyword context check (e.g. "NID: 1234567890" or "National ID 1234567890123")
        if (preg_match('/(?:nid|national\s*id|smart\s*card)(?:\s*no\.?|\s*number)?[\s:]*(\d{10}|\d{13}|\d{17})\b/i', $text, $matches)) {
            return $matches[1];
        }

        // Clean out extracted phone numbers so mobile numbers are not misidentified as NID
        $cleanDigitsText = $text;
        foreach ($knownPhones as $phone) {
            $digitsOnly = preg_replace('/\D/', '', $phone);
            if (!empty($digitsOnly)) {
                $cleanDigitsText = str_replace($digitsOnly, ' ', $cleanDigitsText);
            }
        }

        // BD NIDs are strictly 10 digits (Smart Card), 13 digits (Legacy), or 17 digits (Legacy + Year).
        // 11-digit mobile numbers are excluded by avoiding 11-digit length matching and excluding 01 prefixes.
        if (preg_match('/\b(?!(?:01|8801))\d{10}\b|\b\d{13}\b|\b\d{17}\b/', $cleanDigitsText, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
