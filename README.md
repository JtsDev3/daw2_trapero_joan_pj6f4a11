# Order Management App

E-commerce order management application for a European supermarket chain.
Sales representatives log in and create or view customer orders.

## Stack
PHP 8 (no frameworks), vanilla JavaScript, HTML5, CSS3, Apache with HTTPS.
Orders are stored in `orders/orders.json` (created automatically with the first order).

## Structure
- `application/client` – HTML, CSS and JavaScript (user interface only)
- `application/server` – PHP (storage, reading and calculations)
- `orders` – orders data file
- `users` – predefined users file (read-only)
