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
