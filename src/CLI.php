<?php

declare(strict_types=1);

namespace App;

use App\Clients\AuthClient;
use App\Clients\ApiClient;

final class CLI
{
    private array $args;
    private AuthClient $auth;
    private ApiClient $api;

    public function __construct(array $args)
    {
        $this->args = $args;
        $this->auth = new AuthClient();
        $this->api = new ApiClient($this->auth);
    }

    public function run(): void
    {
        $command = $this->args[1] ?? 'help';
        $subCommand = $this->args[2] ?? null;

        try {
            switch ($command) {
                case 'login':
                    $this->auth->login();
                    break;
                case 'logout':
                    $this->auth->logout();
                    break;
                case 'whoami':
                    $this->whoami();
                    break;
                case 'profiles':
                    $this->handleProfiles($subCommand);
                    break;
                case 'search':
                    $this->handleSearch();
                    break;
                case 'help':
                default:
                    $this->showHelp();
                    break;
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    private function whoami(): void
    {
        $user = $this->auth->getUser();
        if ($user) {
            echo "Logged in as @{$user['username']} ({$user['role']})\n";
        } else {
            echo "Not logged in.\n";
        }
    }

    private function handleProfiles(?string $subCommand): void
    {
        switch ($subCommand) {
            case 'list':
                $this->api->listProfiles($this->parseOptions());
                break;
            case 'get':
                $id = $this->args[3] ?? null;
                if (!$id) throw new \Exception("Profile ID required");
                $this->api->getProfile($id);
                break;
            case 'create':
                $this->api->createProfile($this->parseOptions());
                break;
            case 'export':
                $this->api->exportProfiles($this->parseOptions());
                break;
            default:
                echo "Unknown profiles subcommand: $subCommand\n";
                break;
        }
    }

    private function handleSearch(): void
    {
        $query = $this->args[2] ?? null;
        if (!$query) throw new \Exception("Search query required");
        $this->api->searchProfiles($query);
    }

    private function parseOptions(): array
    {
        $options = [];
        for ($i = 2; $i < count($this->args); $i++) {
            if (str_starts_with($this->args[$i], '--')) {
                $parts = explode('=', substr($this->args[$i], 2), 2);
                $key = $parts[0];
                $value = $parts[1] ?? ($this->args[$i+1] ?? true);
                if ($value === $this->args[$i+1]) $i++;
                $options[$key] = $value;
            }
        }
        return $options;
    }

    private function showHelp(): void
    {
        echo <<<HELP
Insighta Labs+ CLI

Usage:
  insighta login                    Authenticate with GitHub
  insighta logout                   Remove local credentials
  insighta whoami                   Show current user
  insighta profiles list [options]  List profiles
  insighta profiles get <id>        Get profile by ID
  insighta profiles create --name="Name"
  insighta profiles export [options] Export to CSV
  insighta search "query"           Natural language search

Options:
  --gender=male|female
  --country=NG
  --min-age=25
  --max-age=40
  --sort-by=age|created_at
  --order=asc|desc
  --page=1
  --limit=10
  --format=csv (for export)

HELP;
    }
}
