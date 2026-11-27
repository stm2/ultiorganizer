<?php
declare(strict_types=1);

/**
 * HTTP client and Firestore JSON helpers for the Ultimate Tournament v1 REST API.
 */

readonly class UtApiClient {
  public function __construct(private UtConfig $config) {}

  /**
   * Accepts either a raw UT tournament ID or a full UT tournament URL and returns the ID.
   */
  public static function extractTournamentId(string $input): string {
    $trimmed = trim($input);
    if (preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
      return $trimmed;
    }
    if (preg_match('~^https?://[^/]+/#/tournament/([A-Za-z0-9_-]+)(?:/|$|\?)~i', $trimmed, $matches)) {
      return $matches[1];
    }

    throw new InvalidArgumentException('Please provide a valid Ultimate Tournament ID or URL.');
  }

  /**
   * Performs an authenticated GET request against the UT API.
   */
  public function get(string $path): array {
    $url = $this->config->apiUrl . '/' . ltrim($path, '/');

    if (!function_exists('curl_init')) {
      throw new RuntimeException('PHP cURL extension is required for Ultimate Tournament API requests.');
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $this->config->apiToken,
      'Accept: application/json',
    ]);

    $body = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
      throw new RuntimeException(sprintf(
        'UT API request failed [GET %s] (cURL error #%d: %s)',
        $url,
        $curlErrno,
        $curlErr
      ));
    }

    $decoded = json_decode((string) $body, true);
    if ($httpCode < 200 || $httpCode >= 300) {
      $errMsg = '';
      if (is_array($decoded)) {
        if (isset($decoded['error'])) {
          $errMsg = is_string($decoded['error']) ? $decoded['error'] : (string) json_encode($decoded['error']);
        } elseif (isset($decoded['message'])) {
          $errMsg = (string) $decoded['message'];
        }
      }
      if ($errMsg === '') {
        $snippet = trim((string) preg_replace('/\s+/', ' ', (string) $body));
        $errMsg = $snippet !== '' ? mb_substr($snippet, 0, 200) : 'Empty response';
      }
      throw new RuntimeException(sprintf(
        'UT API error [GET %s] (HTTP %d): %s',
        $url,
        $httpCode,
        $errMsg
      ));
    }

    if (!is_array($decoded)) {
      $snippet = trim((string) preg_replace('/\s+/', ' ', (string) $body));
      throw new RuntimeException(sprintf(
        'Invalid JSON response from UT API [GET %s] (HTTP %d, %s): %s',
        $url,
        $httpCode,
        json_last_error_msg(),
        $snippet !== '' ? mb_substr($snippet, 0, 200) : 'Empty response'
      ));
    }

    return $decoded;
  }

  /**
   * Fetches the full tournament bundle needed for Ultiorganizer sync.
   */
  public function fetchTournamentBundle(string $tournamentId): UtTournamentBundle {
    $encodedId = rawurlencode($tournamentId);

    return new UtTournamentBundle(
      tournament: UtTournament::fromArray($this->get('tournaments/' . $encodedId)),
      divisions: array_map(UtDivision::fromArray(...), $this->get('tournaments/' . $encodedId . '/divisions')),
      teams: array_map(UtTeam::fromArray(...), $this->get('tournaments/' . $encodedId . '/teams')),
      fields: array_map(UtField::fromArray(...), $this->get('tournaments/' . $encodedId . '/fields')),
      brackets: array_map(UtBracket::fromArray(...), $this->get('tournaments/' . $encodedId . '/brackets?types=group,swissGroup,elimination')),
      bracketTeams: array_map(UtBracketTeam::fromArray(...), $this->get('tournaments/' . $encodedId . '/bracketTeams')),
      games: array_map(UtGame::fromArray(...), $this->get('tournaments/' . $encodedId . '/games')),
    );
  }

  /**
   * Extracts a Firestore document ID from a serialized Admin SDK DocumentReference.
   */
  public static function extractRefId(mixed $ref): ?string {
    if ($ref === null) {
      return null;
    }
    if (is_array($ref) && isset($ref['_path']['segments']) && is_array($ref['_path']['segments']) && !empty($ref['_path']['segments'])) {
      $last = end($ref['_path']['segments']);
      if (is_string($last) && $last !== '') {
        return $last;
      }
    }

    throw new RuntimeException('Invalid Firestore DocumentReference format in UT API response.');
  }

  /**
   * Converts a Firestore Timestamp ({_seconds, _nanoseconds}) to a local MySQL DATETIME string.
   */
  public static function parseTimestampToLocalDatetime(mixed $ts, string $timezone): ?string {
    if ($ts === null) {
      return null;
    }
    if (!is_array($ts) || !isset($ts['_seconds']) || !is_int($ts['_seconds'])) {
      throw new RuntimeException('Invalid Firestore Timestamp format in UT API response.');
    }

    $tz = new DateTimeZone($timezone !== '' ? $timezone : 'UTC');
    $dt = new DateTimeImmutable('@' . $ts['_seconds']);
    return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
  }
}
