<?php

declare(strict_types=1);

namespace App\Services\CRM;

class EntityExtractor
{
    public function extract(?string $text): array
    {
        $text ??= '';

        return [

            'person' => [
                'name' => null,
            ],

            'contact' => [
                'phones' => $this->phones($text),
                'emails' => $this->emails($text),
                'websites' => $this->websites($text),
            ],

            'business' => [
                'company' => null,
            ],

            'location' => [
                'address' => null,
            ],

            'document' => [
                'nid' => $this->nid($text),
            ],

        ];
    }

    protected function emails(string $text): array
    {
        preg_match_all(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            $text,
            $matches
        );

        return array_unique($matches[0]);
    }

    protected function phones(string $text): array
    {
        preg_match_all(
            '/(?:\+8801|8801|01)[3-9]\d{8}/',
            preg_replace('/[\s\-]/', '', $text),
            $matches
        );

        return array_unique($matches[0]);
    }

    protected function websites(string $text): array
    {
        preg_match_all(
            '/((https?:\/\/)?([a-z0-9-]+\.)+[a-z]{2,})(\/\S*)?/i',
            $text,
            $matches
        );

        return array_unique($matches[0]);
    }

    protected function nid(string $text): ?string
    {
        preg_match('/\b\d{10,17}\b/', $text, $matches);

        return $matches[0] ?? null;
    }
}
