<?php

declare(strict_types=1);

namespace App\Services\CRM;

class EntityNormalizer
{
    public function normalize(array $entities): array
    {
        $entities['contact']['phones'] = array_map(
            fn($phone) => $this->phone($phone),
            $entities['contact']['phones']
        );

        $entities['contact']['emails'] = array_map(
            fn($email) => strtolower(trim($email)),
            $entities['contact']['emails']
        );

        $normalizedWebsites = array_map(
            fn($url) => $this->website($url),
            $entities['contact']['websites'] ?? []
        );

        $entities['contact']['websites'] = array_values(array_unique(array_filter($normalizedWebsites)));

        return $entities;
    }

    protected function phone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '880')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '01')) {
            return '+88' . $phone;
        }

        return $phone;
    }

    protected function website(string $url): string
    {
        $url = rtrim(trim($url), '.,!?:;)');

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        return strtolower($url);
    }
}
