<?php
declare(strict_types=1);

/**
 * Loads Ultimate Tournament API configuration from .env or environment variables.
 */

readonly class UtConfig {
  public const DEFAULT_APP_URL = 'https://ultimate-tournament.app';

  public string $apiUrl;
  public string $apiToken;
  public string $appUrl;

  public function __construct(string $apiUrl, string $apiToken, string $appUrl = self::DEFAULT_APP_URL) {
    $this->apiUrl = rtrim(trim($apiUrl), '/');
    $this->apiToken = trim($apiToken);
    $cleanAppUrl = trim($appUrl);
    $this->appUrl = rtrim($cleanAppUrl !== '' ? $cleanAppUrl : self::DEFAULT_APP_URL, '/');
  }

  public static function load(?string $envPath = null): self {
    $vars = self::parseEnvFile($envPath);

    $apiUrl = self::getValue($vars, 'UT_API_URL');
    $apiToken = self::getValue($vars, 'UT_API_TOKEN');
    $appUrl = self::getValue($vars, 'UT_APP_URL');

    if ($apiUrl === '' || $apiToken === '') {
      throw new RuntimeException(
        'Missing UT_API_URL or UT_API_TOKEN. Configure them in ultiorganizer/.env (see .env.example).'
      );
    }

    return new self($apiUrl, $apiToken, $appUrl !== '' ? $appUrl : self::DEFAULT_APP_URL);
  }

  public static function loadAppUrl(?string $envPath = null): string {
    $vars = self::parseEnvFile($envPath);
    $appUrl = self::getValue($vars, 'UT_APP_URL');
    return rtrim($appUrl !== '' ? $appUrl : self::DEFAULT_APP_URL, '/');
  }

  /**
   * @return array<string, string>
   */
  private static function parseEnvFile(?string $envPath = null): array {
    if ($envPath === null) {
      global $include_prefix;
      $prefix = (string) ($include_prefix ?? '');
      $envPath = $prefix . '.env';
    }

    $vars = [];
    if (is_file($envPath) && is_readable($envPath)) {
      $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      if ($lines !== false) {
        foreach ($lines as $line) {
          $trimmed = trim($line);
          if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
          }
          [$rawKey, $rawVal] = explode('=', $trimmed, 2);
          $key = trim($rawKey);
          $val = trim($rawVal);
          if (strlen($val) >= 2 && (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
          )) {
            $val = substr($val, 1, -1);
          }
          $vars[$key] = $val;
        }
      }
    }
    return $vars;
  }

  private static function getValue(array $vars, string $key): string {
    if (isset($vars[$key]) && $vars[$key] !== '') {
      return (string) $vars[$key];
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
      return (string) $_ENV[$key];
    }
    $env = getenv($key);
    if ($env !== false && $env !== '') {
      return (string) $env;
    }
    return '';
  }
}
