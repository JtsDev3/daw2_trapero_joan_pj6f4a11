# Order Management App

E-commerce order management application for a European supermarket chain.
The company's sales representatives log in, create customer orders and view them.

- **Client** (`application/client`): HTML, CSS and JavaScript. It is only the user interface:
  it never calculates amounts and never stores data.
- **Server** (`application/server`): PHP 8. It stores and reads the orders, checks the users
  and calculates every amount (21% VAT included).
- **Data**: JSON text files. `orders/orders.json` is created automatically with the first
  order; `users/users.json` is predefined and never modified.

## Requirements

- Apache 2.4 with `mod_ssl` and `mod_rewrite`
- PHP 8 (no frameworks, no Composer, no database)
- Firefox or Chrome

## Installation with HTTPS

### Option A - Linux (Ubuntu/Debian, LAMP)

```bash
# 1. Apache + PHP and the required modules
sudo apt update
sudo apt install apache2 php libapache2-mod-php git
sudo a2enmod ssl rewrite

# 2. Get the project
cd /var/www
sudo git clone https://github.com/<user>/<repository>.git orderManagementApp

# 3. Apache (www-data) must be able to create and write orders/orders.json
sudo chown -R "$USER":www-data /var/www/orderManagementApp
sudo chmod 775 /var/www/orderManagementApp/orders

# 4. Self-signed certificate
sudo mkdir -p /etc/apache2/ssl
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/apache2/ssl/orderManagementApp.key \
  -out /etc/apache2/ssl/orderManagementApp.crt \
  -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost"
```

Create `/etc/apache2/sites-available/orderManagementApp.conf`:

```apache
# Port 80: every HTTP request is redirected to HTTPS
<VirtualHost *:80>
    ServerName localhost
    Redirect permanent / https://localhost/
</VirtualHost>

# Port 443: the application over HTTPS
<VirtualHost *:443>
    ServerName localhost
    DocumentRoot /var/www/orderManagementApp

    SSLEngine on
    SSLCertificateFile    /etc/apache2/ssl/orderManagementApp.crt
    SSLCertificateKeyFile /etc/apache2/ssl/orderManagementApp.key

    <Directory /var/www/orderManagementApp>
        AllowOverride All
        Require all granted
    </Directory>

    # The data files can only be read by PHP, never downloaded
    <Directory /var/www/orderManagementApp/users>
        Require all denied
    </Directory>
    <Directory /var/www/orderManagementApp/orders>
        Require all denied
    </Directory>
</VirtualHost>
```

```bash
sudo a2dissite 000-default
sudo a2ensite orderManagementApp
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Open **https://localhost/** (accept the self-signed certificate warning once).

### Option B - Windows (XAMPP)

1. Install XAMPP (it already includes Apache with `mod_ssl`, `mod_rewrite` and a
   self-signed certificate).
2. Clone or copy the project into `C:\xampp\htdocs\orderManagementApp`.
3. Check in `C:\xampp\apache\conf\httpd.conf` that these lines are **not** commented:
   `LoadModule ssl_module`, `LoadModule rewrite_module` and
   `Include conf/extra/httpd-ssl.conf`.
4. Start Apache from the XAMPP Control Panel.
5. Open **https://localhost/orderManagementApp/** (accept the certificate warning once).

The `.htaccess` file in the project root redirects any `http://` request to `https://`
and blocks direct access to the `users` and `orders` folders.

## Login

The user is defined in `users/users.json` (user `admin`). The password is stored as a
bcrypt hash and is checked on the server with `password_verify()`.

## Structure

```
orderManagementApp/
├── application/
│   ├── client/
│   │   ├── index.html          1. Home
│   │   ├── documentation.html  2. Description and documentation
│   │   ├── login.html          3. Authentication
│   │   ├── operations.html     4. Operations menu
│   │   ├── createOrder.html    5. Create new order
│   │   ├── viewOrders.html     6. View orders menu
│   │   ├── singleOrder.html    8. Single order search
│   │   ├── css/styles.css      Common stylesheet
│   │   └── js/                 common.js, login.js, menu.js, createOrder.js, singleOrder.js
│   └── server/
│       ├── config.php          Paths, VAT rate and product catalog
│       ├── functions.php       Shared functions used by every endpoint
│       ├── login.php           POST  - checks the credentials (JSON result)
│       ├── catalog.php         GET   - products and prices (JSON)
│       ├── createOrder.php     POST  - saves an order, answers the total with VAT
│       ├── allOrders.php       GET   - 7. HTML table with all the orders (200 / 404)
│       └── getOrder.php        GET   - 8. HTML table with one order (200 / 404)
├── orders/                     orders.json is created with the first order
└── users/
    └── users.json              Predefined users (read-only)
```

## Products

| ID | Product | Price |
|----|---------|-------|
| P1 | Whole Milk 1 L | 0.99 € |
| P2 | Free-range Eggs (12 units) | 2.95 € |
| P3 | Extra Virgin Olive Oil 1 L | 5.49 € |
| P4 | Sliced Bread 450 g | 1.39 € |

Prices are defined only in `application/server/config.php` (`getCatalog()`). VAT: 21%.

## HTTP methods and status codes

| Request | Method | Success | Errors |
|---------|--------|---------|--------|
| `login.php` | POST | 200 `{"authenticated": true/false}` | 400 empty data |
| `catalog.php` | GET | 200 JSON | – |
| `createOrder.php` | POST | 200 total with VAT (plain text) | 404 orders file (HTML), 400 invalid data, 409 duplicated ID, 401 not logged in |
| `allOrders.php` | GET | 200 HTML table | 404 orders file (HTML) |
| `getOrder.php?orderId=…` | GET | 200 HTML table | 404 orders file / order not found (HTML) |

Any request with the wrong method gets 405 Method Not Allowed.

## Testing

See [TESTING.md](TESTING.md) for the complete test script (normal use and every error case).
