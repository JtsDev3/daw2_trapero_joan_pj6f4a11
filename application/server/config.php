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
