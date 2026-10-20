<?php
/**
 * Order Management App - common server functions.
 *
 * Every endpoint includes this file, so reading and writing JSON files,
 * HTTP responses, sessions, calculations and HTML generation are written
 * only once and reused by all of them (no repeated code).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ==========================================================================
   1. Requests
   ========================================================================== */

/**
 * Stops the request with "405 Method Not Allowed" when the HTTP method is not
 * the expected one (GET for reading operations, POST for writing operations).
 */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        sendText(405, 'Method not allowed. This operation only accepts ' . $method . ' requests.');
    }
}

/**
 * Returns a text field from $_POST or $_GET ($source), or "" if it is missing.
 * Non-text values (for example arrays) are ignored.
 */
function getRequestField(array $source, string $name, bool $trim = true): string
{
    $value = $source[$name] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return $trim ? trim($value) : $value;
}

/* ==========================================================================
   2. Responses that only contain the result (no HTML)
   ========================================================================== */

/** Sends a plain text response with the given status code and ends the script. */
function sendText(int $statusCode, string $text): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

/** Sends an associative array as JSON with the given status code and ends the script. */
function sendJson(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==========================================================================
   3. Session (only logged-in users can work with orders)
   ========================================================================== */

/** Returns true when the request arrived over HTTPS. */
function isHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/** Starts the PHP session with a secure cookie (only once per request). */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure'   => isHttps(),
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
        ]);
    }
}

/** Saves the authenticated user in the session. */
function logIn(string $username): void
{
    startSession();
    session_regenerate_id(true);
    $_SESSION['user'] = ['username' => $username, 'loginTime' => date('Y-m-d H:i:s')];
}

/** Returns true when there is a logged-in user. */
function isLoggedIn(): bool
{
    startSession();

    return isset($_SESSION['user']['username']);
}

/**
 * Stops the request when nobody is logged in.
 * - HTML pages ($isHtmlPage = true): redirect to the login page.
 * - fetch() requests: "401 Unauthorized" with a plain text message.
 */
function requireLogin(bool $isHtmlPage): void
{
    if (isLoggedIn()) {
        return;
    }

    if ($isHtmlPage) {
        header('Location: ' . CLIENT_PATH . '/login.html', true, 303);
        exit;
    }

    sendText(401, 'Your session has expired or you are not logged in. Please log in again.');
}

/* ==========================================================================
   4. JSON files and users
   ========================================================================== */

/**
 * Reads a JSON file and returns it as an associative array (json_decode with
 * true, never stdClass objects). Returns null when the file does not exist,
 * cannot be opened or does not contain valid JSON.
 */
function readJsonFile(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : null;
}

/**
 * Checks a username and password against users/users.json.
 * The file is only read, never modified. Passwords are bcrypt hashes,
 * so they are checked with password_verify().
 */
function verifyCredentials(string $username, string $password): bool
{
    $users = readJsonFile(USERS_FILE);
    if ($users === null) {
        return false;
    }

    foreach ($users as $user) {
        if (
            is_array($user)
            && ($user['user'] ?? null) === $username
            && is_string($user['password'] ?? null)
            && password_verify($password, $user['password'])
        ) {
            return true;
        }
    }

    return false;
}
