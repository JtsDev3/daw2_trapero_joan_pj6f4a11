# Test script for the presentation

Open the browser **DevTools → Network** tab before starting: it shows the method
(GET/POST) and the status code (200/404) of every request to PHP.

## 0. Before starting

- Delete `orders/orders.json` if it exists, so that the first order creates it.
- Check that the `orders/` folder exists and that Apache can write in it.

## 1. HTTPS (rubric 2)

| Step | Expected result |
|------|-----------------|
| Open `http://localhost/` (or `http://localhost/orderManagementApp/` in XAMPP) | Redirected to `https://…` and the Home page opens |
| Click the padlock in the address bar | The connection uses TLS (self-signed certificate) |
| Open `https://localhost/users/users.json` | 403 Forbidden: the data files cannot be downloaded |

## 2. Home, Documentation and Login (rubric 7, 10)

| Step | Expected result |
|------|-----------------|
| Home → **Documentation** → **Home** → **Login** | Every button navigates correctly |
| Login with an empty field | Message on screen; **no request** appears in the Network tab |
| Login `admin` + wrong password | POST `login.php` → `{"authenticated":false}`; authentication error shown; back to Home after 3 s |
| Login `admin` + correct password | Operations menu opens |

## 3. Menus (rubric 7)

| Step | Expected result |
|------|-----------------|
| Click **Go** without choosing an option | Message "Please choose an option" |
| Choose **View orders** → Go → **All orders** → Go (before any order exists) | GET `allOrders.php` → **404**, page "Orders file not found" with 3 buttons |

## 4. Create new order (rubric 4, 7, 9)

| Step | Expected result |
|------|-----------------|
| Open **Create new order** | GET `catalog.php` → 200 JSON; 4 products with prices from the server |
| Tick / untick a product | Its quantity field is enabled / disabled |
| Click **Submit order** with empty fields | Message listing the missing fields; nothing is sent |
| Order `ORD-0001`: 2 × Whole Milk + 1 × Olive Oil | POST `createOrder.php` → 200, response body `9.04`; screen shows **9.04 €** |
| Check the `orders/` folder | `orders.json` has been **created** with the order in JSON format |
| Order `ORD-0002`: 3 × Eggs + 2 × Bread | Total **14.07 €**; the order is **added** to `orders.json` |
| Order with ID `ORD-0001` again | 409: "The order ID … already exists" |

Calculation check for ORD-0001: 2 × 0.99 + 1 × 5.49 = 7.47 → VAT 21% = 1.57 → total 9.04 €.

## 5. View orders (rubric 3, 4, 7)

| Step | Expected result |
|------|-----------------|
| View orders → **All orders** | GET `allOrders.php` → **200**, table with all the orders and 3 buttons |
| View orders → **Single order** → empty ID → Search | Message; nothing is sent |
| Single order `ORD-0002` | GET `getOrder.php?orderId=ORD-0002` → **200**, table of that order |
| Single order `XYZ-999` | **404**, page "Order not found" with 3 buttons |

## 6. Error control (rubric 7-II, 8)

| Error to show | How to cause it | Expected result |
|---------------|-----------------|-----------------|
| Authentication error | Wrong password | Error message and back to Home |
| File not found – create order | Rename `orders/` to `orders_off/` and submit an order | **404** HTML page shown inside the create order page, with 3 buttons |
| File not found – all orders | Same, then All orders | **404** "Orders file not found" |
| File not found – single order | Same, then Single order | **404** "Orders file not found" |
| File cannot be opened | Linux: `chmod 000 orders/orders.json` · Windows: Properties → Read-only (for create order) | **404** "Orders file not found" |
| Order not found | Single order with an ID that does not exist | **404** "Order not found" |

Remember to undo the change afterwards (`mv orders_off orders`, `chmod 664 orders/orders.json`).

## 7. Code checks (rubric 1, 3, 5, 6, 8, 9)

- `git log --oneline`: the commits show how the project evolved.
- `application/server/functions.php`: shared functions, called by every endpoint and by each other.
- `http_response_code()` is only called inside `sendText()`, `sendJson()` and `sendHtmlPage()`.
- All runtime data are multidimensional associative arrays (`json_decode($json, true)`).
- CSS only in `application/client/css/styles.css`; no `style` attributes or `<style>` tags.
- JavaScript never calculates amounts, never stores data and has no prices in its code.
