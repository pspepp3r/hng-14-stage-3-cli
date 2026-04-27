# Insighta CLI

A globally installable command-line interface for the Insighta Labs+ platform.

## Installation

1. Clone the repository and navigate to the `cli` directory.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set environment variables (optional, defaults provided):
   ```bash
   GITHUB_CLI_CLIENT_ID=your_id
   INSIGHTA_BACKEND_URL=http://localhost:8000
   ```
4. Run via PHP or link the binary:
   ```bash
   php bin/insighta help
   ```

## Usage

### Authentication
```bash
insighta login     # Opens browser for GitHub OAuth
insighta logout    # Clears local credentials
insighta whoami    # Shows current logged-in user and role
```

### Profiles
```bash
# List profiles with filters
insighta profiles list --gender=male --country=NG

# Get specific profile
insighta profiles get <uuid>

# Create profile (Admin only)
insighta profiles create --name="John Doe"

# Export to CSV
insighta profiles export --country=US
```

### Search
```bash
insighta search "young females from nigeria"
```

## Credential Storage

Credentials (JWT access and refresh tokens) are securely stored at:
- **Windows:** `%USERPROFILE%/.insighta/credentials.json`
- **Linux/macOS:** `~/.insighta/credentials.json`

## Token Management

The CLI automatically handles token refresh. If a request returns a `401 Unauthorized` error, the CLI attempts to use the stored refresh token to obtain a new access token and then retries the original request seamlessly.
