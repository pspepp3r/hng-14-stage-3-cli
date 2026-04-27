<?php

declare(strict_types=1);

namespace App\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Exception;

final class ApiClient
{
    private Client $client;
    private AuthClient $auth;
    private string $backendUrl = 'http://localhost:8000';

    public function __construct(AuthClient $auth)
    {
        $this->auth = $auth;
        $this->client = new Client([
            'base_uri' => $this->backendUrl,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Version' => '1'
            ]
        ]);
    }

    private function request(string $method, string $uri, array $options = [], bool $retry = true)
    {
        $token = $this->auth->getAccessToken();
        if (!$token) throw new Exception("Not authenticated. Run 'insighta login' first.");

        $options['headers']['Authorization'] = 'Bearer ' . $token;

        try {
            $response = $this->client->request($method, $uri, $options);
            return json_decode((string)$response->getBody(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 401 && $retry) {
                if ($this->auth->refreshToken()) {
                    return $this->request($method, $uri, $options, false);
                }
            }
            throw new Exception($e->getResponse()->getBody()->getContents());
        }
    }

    public function listProfiles(array $query): void
    {
        $data = $this->request('GET', '/api/profiles', ['query' => $query]);
        $this->printTable($data['data']);
        $this->printPagination($data);
    }

    public function getProfile(string $id): void
    {
        $data = $this->request('GET', "/api/profiles/$id");
        $this->printTable($data['data']);
    }

    public function searchProfiles(string $q): void
    {
        $data = $this->request('GET', '/api/profiles/search', ['query' => ['q' => $q]]);
        $this->printTable($data['data']);
        $this->printPagination($data);
    }

    public function createProfile(array $params): void
    {
        $data = $this->request('POST', '/api/profiles', ['json' => $params]);
        echo "Profile created successfully!\n";
        $this->printTable([$data['data']]);
    }

    public function updateUserRole(string $id, string $role): void
    {
        $data = $this->request('PATCH', "/api/users/$id/role", ['json' => ['role' => $role]]);
        echo $data['data']['message'] . "\n";
    }

    public function exportProfiles(array $query): void
    {
        $query['format'] = 'csv';
        $token = $this->auth->getAccessToken();
        
        try {
            $response = $this->client->get('/api/profiles/export', [
                'query' => $query,
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);

            $filename = "profiles_export_" . time() . ".csv";
            file_put_contents($filename, $response->getBody());
            echo "Profiles exported to $filename\n";
        } catch (Exception $e) {
            echo "Export failed: " . $e->getMessage() . "\n";
        }
    }

    private function printTable(array $rows): void
    {
        if (empty($rows)) {
            echo "No results found.\n";
            return;
        }

        $headers = array_keys($rows[0]);
        $widths = [];
        foreach ($headers as $h) $widths[$h] = strlen($h);
        
        foreach ($rows as $row) {
            foreach ($row as $k => $v) {
                $widths[$k] = max($widths[$k], strlen((string)$v));
            }
        }

        // Print header
        foreach ($headers as $h) echo str_pad($h, $widths[$h] + 2);
        echo "\n" . str_repeat('-', array_sum($widths) + (count($widths) * 2)) . "\n";

        // Print rows
        foreach ($rows as $row) {
            foreach ($row as $k => $v) echo str_pad((string)$v, $widths[$k] + 2);
            echo "\n";
        }
    }

    private function printPagination(array $data): void
    {
        if (isset($data['page'])) {
            echo "\nPage {$data['page']} of {$data['total_pages']} (Total: {$data['total']})\n";
        }
    }
}
