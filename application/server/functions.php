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

/* ==========================================================================
   5. Catalog, validation and calculations of an order
   ========================================================================== */

/** Formats an amount with 2 decimals, e.g. 12.5 -> "12.50". */
function formatMoney(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

/** Formats an amount in euros, e.g. 12.5 -> "12.50 €". */
function formatEuros(float $amount): string
{
    return formatMoney($amount) . ' €';
}

/** Formats a rate as a percentage, e.g. 0.21 -> "21%". */
function formatPercentage(float $rate): string
{
    return round($rate * 100, 2) . '%';
}

/**
 * Catalog sent to the client: only the name and the price ready to be shown.
 * Example: ['P1' => ['name' => 'Whole Milk 1 L', 'price' => '0.99'], ...]
 */
function getPublicCatalog(): array
{
    $publicCatalog = [];

    foreach (getCatalog() as $productId => $product) {
        $publicCatalog[$productId] = [
            'name'  => $product['name'],
            'price' => formatMoney($product['price']),
        ];
    }

    return $publicCatalog;
}

/**
 * Collects the order data sent by POST in a multidimensional associative array.
 * Only the quantities are received for the products: prices never come from
 * the client.
 */
function getOrderInput(): array
{
    $quantities = $_POST['quantities'] ?? [];

    return [
        'orderId'    => getRequestField($_POST, 'orderId'),
        'customer'   => [
            'fullName' => getRequestField($_POST, 'fullName'),
            'address'  => getRequestField($_POST, 'address'),
            'email'    => getRequestField($_POST, 'email'),
            'phone'    => getRequestField($_POST, 'phone'),
        ],
        'quantities' => is_array($quantities) ? $quantities : [],
    ];
}

/** A quantity is valid when it is a whole number between 1 and MAX_QUANTITY. */
function isValidQuantity(mixed $quantity): bool
{
    return is_string($quantity)
        && ctype_digit($quantity)
        && (int) $quantity >= 1
        && (int) $quantity <= MAX_QUANTITY;
}

/**
 * Validates the order data. Returns an associative array field => error
 * message (empty array when everything is correct).
 */
function validateOrderInput(array $input, array $catalog): array
{
    $errors = [];
    $customer = $input['customer'];

    if ($input['orderId'] === '') {
        $errors['orderId'] = 'Order ID is required.';
    } elseif (preg_match(ORDER_ID_PATTERN, $input['orderId']) !== 1) {
        $errors['orderId'] = 'Order ID can only contain letters, numbers, "-" and "_" (maximum 20 characters).';
    }

    if ($customer['fullName'] === '') {
        $errors['fullName'] = 'Full name is required.';
    }

    if ($customer['address'] === '') {
        $errors['address'] = 'Address is required.';
    }

    if ($customer['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Email is not a valid email address.';
    }

    if ($customer['phone'] === '') {
        $errors['phone'] = 'Phone is required.';
    } elseif (preg_match(PHONE_PATTERN, $customer['phone']) !== 1) {
        $errors['phone'] = 'Phone can only contain digits, spaces, "+", "-" and brackets (6 to 20 characters).';
    }

    if ($input['quantities'] === []) {
        $errors['products'] = 'Select at least one product.';
    }

    foreach ($input['quantities'] as $productId => $quantity) {
        if (!isset($catalog[$productId])) {
            $errors['products'] = 'The order contains a product that does not exist.';
        } elseif (!isValidQuantity($quantity)) {
            $errors['quantity' . $productId] = 'The quantity of "' . $catalog[$productId]['name']
                . '" must be a whole number between 1 and ' . MAX_QUANTITY . '.';
        }
    }

    return $errors;
}

/**
 * Builds the order lines with the SERVER prices.
 * Result: ['P1' => ['name' => ..., 'unitPrice' => ..., 'quantity' => ..., 'lineTotal' => ...], ...]
 */
function buildOrderItems(array $quantities, array $catalog): array
{
    $items = [];

    foreach ($catalog as $productId => $product) {
        if (!isset($quantities[$productId])) {
            continue;
        }

        $quantity = (int) $quantities[$productId];

        $items[$productId] = [
            'name'      => $product['name'],
            'unitPrice' => $product['price'],
            'quantity'  => $quantity,
            'lineTotal' => round($product['price'] * $quantity, 2),
        ];
    }

    return $items;
}

/** Calculates subtotal, VAT (21%) and total of the order lines. */
function calculateTotals(array $items): array
{
    $subtotal = 0.0;

    foreach ($items as $item) {
        $subtotal += $item['lineTotal'];
    }

    $subtotal = round($subtotal, 2);
    $vat = round($subtotal * VAT_RATE, 2);

    return [
        'subtotal' => $subtotal,
        'vatRate'  => VAT_RATE,
        'vat'      => $vat,
        'total'    => round($subtotal + $vat, 2),
    ];
}

/**
 * Builds the complete order that is saved in orders.json:
 * ['orderId' => ..., 'createdAt' => ..., 'customer' => [...], 'items' => [...], 'totals' => [...]]
 */
function buildOrder(array $input, array $catalog): array
{
    $items = buildOrderItems($input['quantities'], $catalog);

    return [
        'orderId'   => $input['orderId'],
        'createdAt' => date('Y-m-d H:i:s'),
        'customer'  => $input['customer'],
        'items'     => $items,
        'totals'    => calculateTotals($items),
    ];
}

/** Finds an order by its ID (not case-sensitive). Returns null if it does not exist. */
function findOrder(array $orders, string $orderId): ?array
{
    foreach ($orders as $order) {
        if (is_array($order) && strcasecmp((string) ($order['orderId'] ?? ''), $orderId) === 0) {
            return $order;
        }
    }

    return null;
}

/* ==========================================================================
   6. Orders file (orders/orders.json)
   ========================================================================== */

/**
 * Reads every order from orders.json (used by "All orders" and "Single order").
 * Returns null when the file does not exist, cannot be opened or is corrupt.
 */
function readOrders(): ?array
{
    $orders = readJsonFile(ORDERS_FILE);

    if ($orders === null || array_values($orders) !== $orders) {
        return null;
    }

    return $orders;
}

/**
 * Loads the orders before adding a new one (used by "Create new order").
 * - orders.json does not exist yet: returns [] so that it is created with the first order.
 * - The orders folder does not exist, or the file cannot be read or written,
 *   or its JSON is corrupt: returns null (file error -> 404).
 */
function loadOrdersForWriting(): ?array
{
    if (!is_dir(ORDERS_DIR)) {
        return null;
    }

    if (!file_exists(ORDERS_FILE)) {
        return is_writable(ORDERS_DIR) ? [] : null;
    }

    if (!is_writable(ORDERS_FILE)) {
        return null;
    }

    return readOrders();
}

/**
 * Saves all the orders in orders.json (created automatically if it does not exist).
 * JSON_PRETTY_PRINT keeps the file readable and LOCK_EX locks it while writing,
 * so two simultaneous requests cannot mix their data.
 */
function saveOrders(array $orders): bool
{
    $json = json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return false;
    }

    return file_put_contents(ORDERS_FILE, $json, LOCK_EX) !== false;
}

/* ==========================================================================
   7. HTML pages generated by PHP
      (only for: all orders, single order and 404 error pages)
   ========================================================================== */

/** Escapes text before putting it inside HTML (prevents HTML/JS injection). */
function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Header with the logo and the name of the application. */
function renderAppHeader(): string
{
    return <<<HTML
    <header class="app-header">
        <svg class="logo" viewBox="0 0 64 64" aria-hidden="true">
            <circle class="logo-background" cx="32" cy="32" r="32"/>
            <path class="logo-handle" d="M19 28 Q32 3 45 28"/>
            <circle class="logo-dot" cx="31" cy="23" r="7"/>
            <path class="logo-basket" d="M11 27 H53 L47.5 50 Q47 52 45 52 H19 Q17 52 16.5 50 Z"/>
            <path class="logo-slats" d="M24 33 V46 M32 33 V46 M40 33 V46"/>
        </svg>
        <span class="app-name">Order Management App</span>
    </header>
HTML;
}

/**
 * The 3 simple buttons of every page generated by PHP:
 * View orders menu, Operations menu and Home.
 * Each button is a tiny GET form, so it works without JavaScript.
 */
function renderNavigationButtons(): string
{
    $buttons = [
        'viewOrders' => ['label' => 'View orders menu', 'page' => 'viewOrders.html', 'class' => 'primary'],
        'operations' => ['label' => 'Operations menu',  'page' => 'operations.html', 'class' => ''],
        'home'       => ['label' => 'Home',             'page' => 'index.html',      'class' => ''],
    ];

    $html = '<nav class="button-bar" aria-label="Navigation">';

    foreach ($buttons as $button) {
        $html .= '<form action="' . CLIENT_PATH . '/' . $button['page'] . '" method="get">'
            . '<button type="submit" class="' . $button['class'] . '">' . escapeHtml($button['label']) . '</button>'
            . '</form>';
    }

    return $html . '</nav>';
}

/**
 * Sends a complete HTML document with the given status code (200 or 404):
 * header + content + navigation buttons, using the common stylesheet.
 */
function sendHtmlPage(int $statusCode, string $title, string $content): void
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');

    $pageTitle = escapeHtml($title);
    $styleSheet = CLIENT_PATH . '/css/styles.css';
    $header = renderAppHeader();
    $buttons = renderNavigationButtons();

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle} – Order Management App</title>
    <link rel="stylesheet" href="{$styleSheet}">
</head>
<body>
{$header}
    <main class="page page--wide">
        <section class="card">
            {$content}
            {$buttons}
        </section>
    </main>
</body>
</html>
HTML;
    exit;
}

/** Sends a "404 Not Found" HTML page with an error message and the 3 buttons. */
function sendNotFound(string $heading, string $message): void
{
    $content = '<div class="error-box">'
        . '<h1>Error 404 – ' . escapeHtml($heading) . '</h1>'
        . '<p>' . escapeHtml($message) . '</p>'
        . '</div>';

    sendHtmlPage(404, 'Error 404', $content);
}

/** 404 error: orders.json cannot be found or opened (F5, F7 and F8). */
function sendOrdersFileError(): void
{
    sendNotFound(
        'Orders file not found',
        'The orders file (orders/orders.json) could not be found or could not be opened, '
        . 'so the operation could not be completed.'
    );
}

/* ==========================================================================
   8. Orders tables (used by "All orders" and "Single order")
   ========================================================================== */

/** One table cell with escaped text and an optional CSS class. */
function renderCell(string $text, string $class = ''): string
{
    $classAttribute = $class === '' ? '' : ' class="' . $class . '"';

    return '<td' . $classAttribute . '>' . escapeHtml($text) . '</td>';
}

/** List of the products of an order: "Name: quantity × unit price = line total". */
function renderProductList(array $items): string
{
    $html = '<ul class="product-list">';

    foreach ($items as $item) {
        $html .= '<li>' . escapeHtml(
            (string) ($item['name'] ?? '') . ': '
            . (int) ($item['quantity'] ?? 0) . ' × ' . formatEuros((float) ($item['unitPrice'] ?? 0))
            . ' = ' . formatEuros((float) ($item['lineTotal'] ?? 0))
        ) . '</li>';
    }

    return $html . '</ul>';
}

/** One table row with all the data of an order. */
function renderOrderRow(array $order): string
{
    $customer = $order['customer'] ?? [];
    $totals = $order['totals'] ?? [];

    return '<tr>'
        . renderCell((string) ($order['orderId'] ?? ''), 'total nowrap')
        . renderCell((string) ($order['createdAt'] ?? ''))
        . renderCell((string) ($customer['fullName'] ?? ''))
        . renderCell((string) ($customer['address'] ?? ''))
        . renderCell((string) ($customer['email'] ?? ''))
        . renderCell((string) ($customer['phone'] ?? ''), 'nowrap')
        . '<td>' . renderProductList($order['items'] ?? []) . '</td>'
        . renderCell(formatEuros((float) ($totals['subtotal'] ?? 0)), 'amount')
        . renderCell(formatEuros((float) ($totals['vat'] ?? 0)), 'amount')
        . renderCell(formatEuros((float) ($totals['total'] ?? 0)), 'amount total')
        . '</tr>';
}

/**
 * Table with meaningful column names for a list of orders.
 * The same function is used for all the orders and for a single order.
 */
function renderOrdersTable(array $orders): string
{
    $rows = '';

    foreach ($orders as $order) {
        if (is_array($order)) {
            $rows .= renderOrderRow($order);
        }
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="10">There are no orders yet.</td></tr>';
    }

    $vatHeader = escapeHtml('VAT (' . formatPercentage(VAT_RATE) . ')');

    return <<<HTML
<div class="table-wrapper">
    <table class="orders-table">
        <thead>
            <tr>
                <th scope="col">Order ID</th>
                <th scope="col">Date</th>
                <th scope="col">Customer name</th>
                <th scope="col">Address</th>
                <th scope="col">Email</th>
                <th scope="col">Phone</th>
                <th scope="col">Products (qty × unit price)</th>
                <th scope="col" class="amount">Subtotal</th>
                <th scope="col" class="amount">{$vatHeader}</th>
                <th scope="col" class="amount">Total (VAT incl.)</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
</div>
HTML;
}

/** 404 error: there is no order with the requested ID (F8). */
function sendOrderNotFound(string $orderId): void
{
    $message = $orderId === ''
        ? 'No order ID was provided.'
        : 'There is no order with the ID "' . $orderId . '". Check the ID and try again.';

    sendNotFound('Order not found', $message);
}
