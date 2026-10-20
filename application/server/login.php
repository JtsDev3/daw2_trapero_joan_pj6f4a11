<?php
/**
 * F3 - Authentication (POST).
 *
 * Receives the username and password, checks them against users/users.json
 * and answers ONLY with the result, in JSON:
 *     {"authenticated": true}   or   {"authenticated": false}
 * POST is used so that the password travels in the request body and never
 * appears in the URL, the browser history or the server logs.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

requireMethod('POST');

$credentials = [
    'username' => getRequestField($_POST, 'username'),
    'password' => getRequestField($_POST, 'password', false),
];

if ($credentials['username'] === '' || $credentials['password'] === '') {
    sendJson(400, ['authenticated' => false, 'message' => 'Username and password are required.']);
}

$authenticated = verifyCredentials($credentials['username'], $credentials['password']);

if ($authenticated) {
    logIn($credentials['username']);
}

sendJson(200, ['authenticated' => $authenticated]);
