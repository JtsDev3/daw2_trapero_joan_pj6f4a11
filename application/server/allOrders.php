<?php
/**
 * F7 - View all orders (GET - reading operation).
 *
 * Reads every order from orders/orders.json and sends:
 * - 200 OK + an HTML document with a table of all the orders and 3 buttons.
 * - 404 Not Found + an HTML error page if the file cannot be found or opened.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

requireMethod('GET');
requireLogin(true);

$orders = readOrders();
if ($orders === null) {
    sendOrdersFileError();
}

$content = '<h1>All orders</h1>'
    . '<p class="summary">Orders stored: ' . count($orders) . '</p>'
    . renderOrdersTable($orders);

sendHtmlPage(200, 'All orders', $content);
