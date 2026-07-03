<?php

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

        $entities['contact']['websites'] = array_map(
            fn($url) => $this->website($url),
            $entities['contact']['websites']
        );

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
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        return strtolower($url);
    }
}
