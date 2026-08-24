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
        $name     = $this->names($text);

        return [
            'person' => [
                'name' => $name,
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

    protected function names(string $text): ?string
    {
        // 1. Explicit English Name patterns
        if (preg_match('/(?:my\s+name\s+is|i\s+am|i\'m|name\s*[:\-])\s*([a-zA-Z\s\.\']{2,40})/i', $text, $matches)) {
            $candidate = trim($matches[1]);
            // Strip trailing conjunctions, punctuation, or action verbs
            $candidate = preg_split('/[,.\n\r\t!?;]|\b(?:and|from|with|calling|asking|interested|here|looking)\b/i', $candidate)[0];
            $candidate = trim($candidate);
            if (mb_strlen($candidate) >= 2 && !preg_match('/\b(?:a|an|the|customer|user|admin|support)\b/i', $candidate)) {
                return $candidate;
            }
        }

        // 2. Banglish Name patterns
        if (preg_match('/(?:amar\s+nam|amar\s+naam)\s*(?:is|holo|hoche)?\s*([a-zA-Z\s\.\']{2,40})/i', $text, $matches)) {
            $candidate = trim($matches[1]);
            $candidate = preg_split('/[,.\n\r\t!?;]|\b(?:and|ami|theke)\b/i', $candidate)[0];
            $candidate = trim($candidate);
            if (mb_strlen($candidate) >= 2) {
                return $candidate;
            }
        }

        // 3. Bengali Name patterns
        if (preg_match('/(?:আমার\s*নাম|আমি)\s*([^\s,।!?\n\r\t0-9]{2,30}(?:\s+[^\s,।!?\n\r\t0-9]{2,30})*)/u', $text, $matches)) {
            $candidate = trim($matches[1]);
            // Strip trailing Bengali conjunctions and punctuation without ASCII \b
            $candidate = preg_split('/[,।!?\n\r\t]|\s+(?:এবং|থেকে|বলছি|আমি|জানতে|চাই|হলো|হল)(?:\s|$|[,।!?])/u', $candidate)[0];
            $candidate = trim($candidate);
            if (mb_strlen($candidate) >= 2 && !in_array($candidate, ['কি', 'কী', 'কেমন', 'একটা', 'একটু', 'কিভাবে', 'কোন', 'সাহায্য'])) {
                return $candidate;
            }
        }

        return null;
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
