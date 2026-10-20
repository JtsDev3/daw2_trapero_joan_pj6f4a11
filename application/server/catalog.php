<?php
/**
 * Product catalog (GET - reading operation).
 *
 * Answers ONLY with the result, in JSON: the 4 products with their prices,
 * so that the client can show them in the "Create new order" form.
 * The prices live only here, on the server.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

requireMethod('GET');

sendJson(200, getPublicCatalog());
