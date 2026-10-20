<?php
/**
 * F8 - View a single order (GET - reading operation).
 *
 * Receives the order ID in the URL (?orderId=...), opens orders/orders.json,
 * finds the order and sends:
 * - 200 OK + an HTML document with the table of that order and 3 buttons.
 * - 404 Not Found + an HTML error page if the file cannot be found or opened.
 * - 404 Not Found + an HTML error page if there is no order with that ID.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

requireMethod('GET');
requireLogin(true);

$orderId = getRequestField($_GET, 'orderId');

$orders = readOrders();
if ($orders === null) {
    sendOrdersFileError();
}

$order = findOrder($orders, $orderId);
if ($order === null) {
    sendOrderNotFound($orderId);
}

$content = '<h1>Order ' . escapeHtml($order['orderId']) . '</h1>'
    . renderOrdersTable([$order]);

sendHtmlPage(200, 'Order ' . $order['orderId'], $content);
