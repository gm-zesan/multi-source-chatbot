<?php

declare(strict_types=1);

namespace App\Casts;

use App\Exceptions\ChannelTokenDecryptionException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EnvelopedEncryptedString implements CastsAttributes
{
    /**
     * Envelope prefix used to deterministically identify encrypted ciphertext.
     */
    public const PREFIX = 'enc:v1:';

    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return mixed
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        // Check if value is wrapped in our envelope
        if (str_starts_with($value, self::PREFIX)) {
            $payload = substr($value, strlen(self::PREFIX));

            try {
                return Crypt::decryptString($payload);
            } catch (Throwable $e) {
                // Fail-closed: Never fall back to raw ciphertext or plaintext!
                throw new ChannelTokenDecryptionException(
                    message: "Failed to decrypt attribute [{$key}] for model [" . get_class($model) . ']: corrupted ciphertext or APP_KEY mismatch.',
                    previous: $e,
                );
            }
        }

        // Legacy plaintext record without prefix
        return $value;
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return mixed
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stringValue = (string) $value;

        // Avoid re-encrypting already-enveloped ciphertext
        if (str_starts_with($stringValue, self::PREFIX)) {
            return $stringValue;
        }

        return self::PREFIX . Crypt::encryptString($stringValue);
    }
}
