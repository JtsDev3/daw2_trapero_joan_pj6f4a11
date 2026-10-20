<?php
/**
 * F5 - Create a new order (POST - writing operation).
 *
 * 1. Validates the data sent by the client.
 * 2. Calculates the lines, subtotal, 21% VAT and total with the SERVER prices.
 * 3. Adds the complete order to orders/orders.json (created with the first order).
 * 4. Answers ONLY with the result: the total with VAT, as plain text (e.g. "12.34").
 *
 * Errors:
 * - Orders file not found / cannot be opened -> 404 with an HTML error page.
 * - Invalid data -> 400, duplicated order ID -> 409 (plain text message).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

requireMethod('POST');
requireLogin(false);

$catalog = getCatalog();
$input = getOrderInput();

$errors = validateOrderInput($input, $catalog);
if ($errors !== []) {
    sendText(400, "The order could not be saved:\n- " . implode("\n- ", $errors));
}

$orders = loadOrdersForWriting();
if ($orders === null) {
    sendOrdersFileError();
}

if (findOrder($orders, $input['orderId']) !== null) {
    sendText(409, 'The order ID "' . $input['orderId'] . '" already exists. Please use a different order ID.');
}

$order = buildOrder($input, $catalog);
$orders[] = $order;

if (!saveOrders($orders)) {
    sendOrdersFileError();
}

sendText(200, formatMoney($order['totals']['total']));
