<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class NvrAuthService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $realm;

    public function __construct()
    {
        $this->baseUrl = env('NVR_BASE_URL', 'https://192.168.1.88:20443');
        $this->username = env('NVR_USERNAME', 'admin');
        $this->password = env('NVR_PASSWORD', '');
        $this->realm = env('NVR_REALM', 'TP-LINK NVR');
    }

    /**
     * Get nonce from NVR
     */
    public function getNonce(): string
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 10,
            ])->get("{$this->baseUrl}/openapi/token");

            // Debug: Log response info
            if (config('app.debug')) {
                \Log::info('NVR Response:', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ]);
            }

            // 401 is expected for digest auth challenge
            if ($response->status() !== 401) {
                throw new \Exception("Unexpected status code. Expected 401, got: {$response->status()}");
            }

            // Get headers from response
            $headers = $response->headers();
            $authHeader = null;

            // Try to get WWW-Authenticate header from different possible locations
            if (isset($headers['WWW-Authenticate'])) {
                $authHeader = is_array($headers['WWW-Authenticate'])
                    ? $headers['WWW-Authenticate'][0]
                    : $headers['WWW-Authenticate'];
            } elseif (isset($headers['www-authenticate'])) {
                $authHeader = is_array($headers['www-authenticate'])
                    ? $headers['www-authenticate'][0]
                    : $headers['www-authenticate'];
            }

            if (!$authHeader) {
                throw new \Exception("No WWW-Authenticate header found in response. Available headers: " . implode(', ', array_keys($headers)));
            }

            if (config('app.debug')) {
                \Log::info('WWW-Authenticate header:', ['header' => $authHeader]);
            }

            if (preg_match('/nonce="([^"]+)"/i', $authHeader, $matches)) {
                return $matches[1];
            }

            throw new \Exception("Could not extract nonce from WWW-Authenticate header: {$authHeader}");
        } catch (\Exception $e) {
            throw new \Exception("Error getting nonce: " . $e->getMessage());
        }
    }

    /**
     * Get access token using nonce and credentials
     */
    public function getAccessToken(string $nonce): array
    {
        try {
            // Calculate digest auth response
            $ha1 = hash('sha256', "{$this->username}:{$this->realm}:{$this->password}");
            $ha2 = hash('sha256', "GET:/openapi/token");
            $response = hash('sha256', "{$ha1}:{$nonce}:{$ha2}");

            $authString = sprintf(
                'Digest username="%s", realm="%s", nonce="%s", uri="/openapi/token", response="%s", algorithm=SHA-256',
                $this->username,
                $this->realm,
                $nonce,
                $response
            );

            $tokenResponse = Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Authorization' => $authString,
            ])->get("{$this->baseUrl}/openapi/token");

            if ($tokenResponse->failed()) {
                throw new \Exception("Failed to get access token: " . $tokenResponse->body());
            }

            return $tokenResponse->json();
        } catch (\Exception $e) {
            throw new \Exception("Error getting access token: " . $e->getMessage());
        }
    }

    /**
     * Start recording search process
     */
    public function startRecordingSearch(string $accessToken): int
    {
        try {
            $decodedToken = urldecode($accessToken);

            $response = Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Authorization' => "Bearer {$decodedToken}",
            ])->get("{$this->baseUrl}/openapi/record/search/free_process");

            if ($response->failed()) {
                throw new \Exception("Failed to start recording search: " . $response->body());
            }

            $data = $response->json();
            return $data['process'] ?? throw new \Exception("No process ID in response");
        } catch (\Exception $e) {
            throw new \Exception("Error starting recording search: " . $e->getMessage());
        }
    }

    /**
     * Get recording search results
     */
    public function getRecordingResults(string $accessToken, int $process, string $day, int $startIndex = 0, int $endIndex = 99): array
    {
        try {
            $decodedToken = urldecode($accessToken);

            $url = sprintf(
                '%s/openapi/record/search/results?channel=1&process=%d&day=%s&start_index=%d&end_index=%d',
                $this->baseUrl,
                $process,
                $day,
                $startIndex,
                $endIndex
            );

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->withHeaders([
                'Authorization' => "Bearer {$decodedToken}",
            ])->get($url);

            if (config('app.debug')) {
                \Log::info('NVR Recording Results:', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            if ($response->failed()) {
                throw new \Exception("Failed to get recording results: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            throw new \Exception("Error getting recording results: " . $e->getMessage());
        }
    }

    /**
     * Complete authentication flow
     */
    public function authenticate(): array
    {
        $nonce = $this->getNonce();
        return $this->getAccessToken($nonce);
    }
}
