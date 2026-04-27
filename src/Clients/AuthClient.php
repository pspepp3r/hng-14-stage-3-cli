<?php

declare(strict_types=1);

namespace App\Clients;

use GuzzleHttp\Client;
use Exception;

final class AuthClient
{
    private string $storagePath;
    private string $backendUrl;
    private string $clientId;

    public function __construct()
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        $this->storagePath = $home . '/.insighta/credentials.json';
        $this->backendUrl = getenv('INSIGHTA_BACKEND_URL') ?: 'http://localhost:8000';
        $this->clientId = getenv('GITHUB_CLI_CLIENT_ID') ?: '';
    }

    public function login(): void
    {
        $state = bin2hex(random_bytes(16));
        $codeVerifier = strtr(rtrim(base64_encode(random_bytes(32)), '='), '+/', '-_');
        $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

        $authUrl = "https://github.com/login/oauth/authorize?" . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => 'http://localhost:8080/callback',
            'state' => $state,
            'scope' => 'read:user user:email',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256'
        ]);

        echo "Opening browser for GitHub authentication...\n";
        $this->openBrowser($authUrl);

        echo "Waiting for callback on http://localhost:8080/callback ...\n";
        $code = $this->startLocalServer($state);

        if ($code) {
            $this->exchangeCode($code, $codeVerifier);
        }
    }

    private function exchangeCode(string $code, string $codeVerifier): void
    {
        $client = new Client(['base_uri' => $this->backendUrl]);
        try {
            $response = $client->get('/auth/github/callback', [
                'query' => [
                    'code' => $code,
                    'code_verifier' => $codeVerifier
                ],
                'headers' => ['Accept' => 'application/json']
            ]);

            $data = json_decode((string)$response->getBody(), true);
            if ($data['status'] === 'success') {
                $this->saveCredentials($data['data']);
                echo "Successfully logged in!\n";
            } else {
                throw new Exception($data['message'] ?? 'Login failed');
            }
        } catch (Exception $e) {
            echo "Login failed: " . $e->getMessage() . "\n";
        }
    }

    private function startLocalServer(string $expectedState): ?string
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, '127.0.0.1', 8080);
        socket_listen($socket);
        
        $client = socket_accept($socket);
        $request = socket_read($client, 2048);
        
        preg_match("/GET \/callback\?code=([^&]+)&state=([^& ]+)/", $request, $matches);
        
        $code = $matches[1] ?? null;
        $state = $matches[2] ?? null;

        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n<h1>Authentication Successful</h1><p>You can close this window now.</p>";
        socket_write($client, $response, strlen($response));
        socket_close($client);
        socket_close($socket);

        if ($state !== $expectedState) {
            echo "Invalid state returned.\n";
            return null;
        }

        return $code;
    }

    private function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("start $url");
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            exec("open $url");
        } else {
            exec("xdg-open $url");
        }
    }

    private function saveCredentials(array $tokens): void
    {
        if (!is_dir(dirname($this->storagePath))) {
            mkdir(dirname($this->storagePath), 0700, true);
        }
        file_put_contents($this->storagePath, json_encode($tokens));
    }

    public function getAccessToken(): ?string
    {
        if (!file_exists($this->storagePath)) return null;
        $creds = json_decode(file_get_contents($this->storagePath), true);
        
        // Basic check for expiry could be done here if we decoded the JWT
        // For now, we'll just return it and let ApiClient handle refresh if 401 occurs
        return $creds['access_token'] ?? null;
    }

    public function refreshToken(): bool
    {
        if (!file_exists($this->storagePath)) return false;
        $creds = json_decode(file_get_contents($this->storagePath), true);
        $refreshToken = $creds['refresh_token'] ?? null;

        if (!$refreshToken) return false;

        $client = new Client(['base_uri' => $this->backendUrl]);
        try {
            $response = $client->post('/auth/refresh', [
                'form_params' => ['refresh_token' => $refreshToken],
                'headers' => ['Accept' => 'application/json']
            ]);

            $data = json_decode((string)$response->getBody(), true);
            if ($data['status'] === 'success') {
                $this->saveCredentials($data['data']);
                return true;
            }
        } catch (Exception $e) {}

        return false;
    }

    public function logout(): void
    {
        if (file_exists($this->storagePath)) {
            unlink($this->storagePath);
            echo "Logged out.\n";
        }
    }

    public function getUser(): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) return null;
        
        $parts = explode('.', $token);
        if (count($parts) < 2) return null;
        
        $payload = json_decode(base64_decode($parts[1]), true);
        return [
            'id' => $payload['sub'],
            'role' => $payload['role'],
            'username' => 'user' // In a real app, we'd fetch this or put it in token
        ];
    }
}
