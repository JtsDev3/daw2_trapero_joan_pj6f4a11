<?php
/**
 * Order Management App - server configuration.
 * File paths and fixed values used by every endpoint.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

/* ---------- Data files ---------- */

const USERS_FILE  = __DIR__ . '/../../users/users.json';
const ORDERS_DIR  = __DIR__ . '/../../orders';
const ORDERS_FILE = ORDERS_DIR . '/orders.json';

/* ---------- Client pages (used in redirections and generated buttons) ---------- */

const CLIENT_PATH = '../client';

/* ---------- Business rules ---------- */

const VAT_RATE = 0.21;                         // 21% VAT
const MAX_QUANTITY = 999;                      // maximum units of one product per order
const ORDER_ID_PATTERN = '/^[A-Za-z0-9_-]{1,20}$/';
const PHONE_PATTERN = '/^\+?[0-9 ()-]{6,20}$/';

/**
 * Product catalog of the supermarket. It only exists on the server:
 * the client receives it through catalog.php and never sends prices back.
 * Associative multidimensional array: product ID => product data.
 */
function getCatalog(): array
{
    return [
        'P1' => ['name' => 'Whole Milk 1 L',             'price' => 0.99],
        'P2' => ['name' => 'Free-range Eggs (12 units)', 'price' => 2.95],
        'P3' => ['name' => 'Extra Virgin Olive Oil 1 L', 'price' => 5.49],
        'P4' => ['name' => 'Sliced Bread 450 g',         'price' => 1.39],
    ];
}
