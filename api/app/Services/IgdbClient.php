<?php

namespace App\Services;

use App\Exceptions\IgdbNotConfigured;
use App\Exceptions\IgdbRequestFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IgdbClient
{
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';

    private const API_URL = 'https://api.igdb.com/v4';

    private const TOKEN_CACHE_KEY = 'igdb.app_access_token';

    private const TOKEN_EXPIRY_MARGIN_SECONDS = 300;

    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = (string) config('services.igdb.client_id');
        $this->clientSecret = (string) config('services.igdb.client_secret');
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * @return list<array{igdb_id: int, name: string, cover_url: ?string, first_release_year: ?int}>
     */
    public function searchGames(int $platformId, string $q): array
    {
        $body = sprintf(
            'fields name,cover.url,first_release_date; search "%s"; where platforms = (%d); limit 20;',
            $this->escape($q),
            $platformId,
        );

        return array_map(fn (array $item) => $this->normalizeGame($item), $this->query('games', $body));
    }

    /**
     * @return list<array{id: int, name: string, abbreviation: ?string}>
     */
    public function platforms(string $search): array
    {
        $body = sprintf('fields id,name,abbreviation; search "%s"; limit 20;', $this->escape($search));

        return array_map(fn (array $item) => [
            'id' => $item['id'],
            'name' => $item['name'] ?? '',
            'abbreviation' => $item['abbreviation'] ?? null,
        ], $this->query('platforms', $body));
    }

    private function query(string $endpoint, string $body): array
    {
        $this->ensureConfigured();

        $response = $this->send(fn () => Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer '.$this->accessToken(),
        ])->withBody($body, 'text/plain')->post(self::API_URL.'/'.$endpoint));

        if ($response->failed()) {
            throw new IgdbRequestFailed("IGDB respondeu {$response->status()} em /{$endpoint}.");
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->send(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ]));

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new IgdbRequestFailed("Twitch nao emitiu token de acesso (HTTP {$response->status()}).");
        }

        $token = $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - self::TOKEN_EXPIRY_MARGIN_SECONDS);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new IgdbRequestFailed('Falha de conexao com a IGDB: '.$e->getMessage(), previous: $e);
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new IgdbNotConfigured;
        }
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function normalizeGame(array $item): array
    {
        $cover = $item['cover']['url'] ?? null;
        $timestamp = $item['first_release_date'] ?? null;

        return [
            'igdb_id' => (int) $item['id'],
            'name' => $item['name'] ?? '',
            'cover_url' => $cover ? 'https:'.str_replace('t_thumb', 't_cover_big', $cover) : null,
            'first_release_year' => $timestamp ? (int) gmdate('Y', (int) $timestamp) : null,
        ];
    }
}
