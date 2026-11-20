<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request authenticated via external API Key.
     */
    public function handle(Request $request, Closure $next, ?string $requiredAbility = null): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'unauthorized',
                'message' => 'Missing API Key. Provide your key via "Authorization: Bearer <key>" or "X-API-Key" header.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = ApiKey::findByToken($token);

        if (! $apiKey || ! $apiKey->isValid()) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'unauthorized',
                'message' => 'Invalid, expired, or deactivated API Key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($requiredAbility && ! $apiKey->hasAbility($requiredAbility)) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'forbidden',
                'message' => "This API key does not have the '{$requiredAbility}' permission.",
            ], Response::HTTP_FORBIDDEN);
        }

        // Attach resolved tenant context to the request
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('workspace', $apiKey->workspace);
        $request->attributes->set('workspace_id', $apiKey->workspace_id);

        $apiKey->recordUsage($request->ip());

        return $next($request);
    }

    /**
     * Extract bearer or X-API-Key header.
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        $xApiKey = $request->header('X-API-Key');
        if ($xApiKey) {
            return trim((string) $xApiKey);
        }

        return null;
    }
}
