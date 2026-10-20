/* ==========================================================================
   F5 - Create new order
   - Loads the products and their prices from PHP (GET).
   - A checkbox enables or disables the quantity of each product.
   - Checks for empty fields and sends the order to PHP (POST).
   - Shows the total with VAT calculated by the server, or the 404 page.
   JavaScript never calculates any amount and has no prices in its code.
   ========================================================================== */

'use strict';

const CATALOG_URL = '../server/catalog.php';
const CREATE_ORDER_URL = '../server/createOrder.php';
const LOGIN_PAGE = 'login.html';

// Text fields of the form and the name shown in the error messages.
const ORDER_FIELDS = {
    orderId: 'Order ID',
    fullName: 'Full name',
    address: 'Address',
    email: 'Email',
    phone: 'Phone'
};

document.addEventListener('DOMContentLoaded', initCreateOrderPage);

function initCreateOrderPage() {
    loadCatalog();
    document.getElementById('orderForm').addEventListener('submit', handleOrderSubmit);
}

/* ---------- Products ---------- */

/** Asks PHP for the product list (GET) and shows it in the table. */
async function loadCatalog() {
    const productList = document.getElementById('productList');

    try {
        const response = await fetch(CATALOG_URL);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        const products = await response.json();
        renderProducts(productList, products);
    } catch (error) {
        productList.replaceChildren(createInfoRow('The products could not be loaded. Please reload the page.'));
    }
}

/** Creates one table row per product received from the server. */
function renderProducts(productList, products) {
    productList.replaceChildren();

    Object.entries(products).forEach(function ([productId, product]) {
        productList.appendChild(createProductRow(productId, product));
    });
}

/** Row: checkbox | product name | price (as sent by the server) | quantity. */
function createProductRow(productId, product) {
    const row = document.createElement('tr');

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'select-' + productId;
    checkbox.className = 'product-check';
    checkbox.dataset.productName = product.name;

    const label = document.createElement('label');
    label.htmlFor = checkbox.id;
    label.textContent = product.name;

    const quantity = document.createElement('input');
    quantity.type = 'number';
    quantity.id = 'quantity-' + productId;
    quantity.name = 'quantities[' + productId + ']';
    quantity.min = '1';
    quantity.max = '999';
    quantity.step = '1';
    quantity.disabled = true;
    quantity.setAttribute('aria-label', 'Quantity of ' + product.name);

    checkbox.addEventListener('change', function () {
        toggleQuantity(checkbox, quantity);
    });

    row.append(
        createCell(checkbox),
        createCell(label),
        createCell(product.price + ' €', 'amount'),
        createCell(quantity)
    );

    return row;
}

function createCell(content, className) {
    const cell = document.createElement('td');
    if (className) {
        cell.className = className;
    }
    cell.append(content);
    return cell;
}

function createInfoRow(text) {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 4;
    cell.textContent = text;
    row.appendChild(cell);
    return row;
}

/**
 * Enables the quantity when the product is ticked and disables it when not.
 * Disabled inputs are not sent by the form, so only ticked products reach PHP.
 */
function toggleQuantity(checkbox, quantity) {
    quantity.disabled = !checkbox.checked;
    markInvalid(quantity, false);

    if (checkbox.checked) {
        quantity.value = '1';
        quantity.focus();
    } else {
        quantity.value = '';
    }
}

/** Unticks every product and disables its quantity (after saving an order). */
function resetProducts(form) {
    form.querySelectorAll('.product-check').forEach(function (checkbox) {
        const quantity = document.getElementById(checkbox.id.replace('select-', 'quantity-'));
        checkbox.checked = false;
        toggleQuantity(checkbox, quantity);
    });
}

/* ---------- Validation of empty fields ---------- */

/** Returns the names of the empty fields and marks them in red. */
function findEmptyOrderFields(form) {
    const emptyFields = [];

    Object.entries(ORDER_FIELDS).forEach(function ([fieldName, fieldLabel]) {
        const input = form.elements[fieldName];
        const empty = isEmpty(input.value);
        markInvalid(input, empty);
        if (empty) {
            emptyFields.push(fieldLabel);
        }
    });

    const checkedProducts = form.querySelectorAll('.product-check:checked');
    if (checkedProducts.length === 0) {
        emptyFields.push('at least one product');
    }

    checkedProducts.forEach(function (checkbox) {
        const quantity = document.getElementById(checkbox.id.replace('select-', 'quantity-'));
        const empty = isEmpty(quantity.value);
        markInvalid(quantity, empty);
        if (empty) {
            emptyFields.push('quantity of ' + checkbox.dataset.productName);
        }
    });

    return emptyFields;
}

/* ---------- Sending the order ---------- */

async function handleOrderSubmit(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const message = document.getElementById('orderMessage');
    const submitButton = form.querySelector('button[type="submit"]');

    hideMessage(message);
    document.getElementById('orderResult').hidden = true;

    const emptyFields = findEmptyOrderFields(form);
    if (emptyFields.length > 0) {
        showMessage(message, 'Please complete: ' + emptyFields.join(', ') + '.', 'error');
        return;
    }

    submitButton.disabled = true;

    try {
        const response = await fetch(CREATE_ORDER_URL, {
            method: 'POST',
            body: new FormData(form)
        });
        const responseText = await response.text();
        handleOrderResponse(response.status, responseText, form, message);
    } catch (error) {
        showMessage(message, 'The server could not be reached. Please try again.', 'error');
    } finally {
        submitButton.disabled = false;
    }
}

/** Acts according to the HTTP status code returned by PHP. */
function handleOrderResponse(status, responseText, form, message) {
    if (status === 200) {
        showOrderTotal(form.elements.orderId.value.trim(), responseText);
        form.reset();
        resetProducts(form);
    } else if (status === 404) {
        // The orders file could not be found or opened: show the HTML page sent by PHP.
        showServerPage(responseText);
    } else if (status === 401) {
        showMessage(message, responseText + '\nRedirecting to the login page…', 'error');
        setTimeout(function () {
            goTo(LOGIN_PAGE);
        }, REDIRECT_DELAY_MS);
    } else {
        // 400 (invalid data) or 409 (duplicated order ID): message written by PHP.
        showMessage(message, responseText, 'error');
    }
}

/** Shows the total with VAT exactly as calculated and formatted by the server. */
function showOrderTotal(orderId, total) {
    const resultText = document.getElementById('orderResultText');
    const amount = document.createElement('span');
    amount.className = 'result-amount';
    amount.textContent = total + ' €';

    resultText.replaceChildren(
        'Order ' + orderId + ' has been saved. Total amount to pay (VAT included): ',
        amount
    );
    document.getElementById('orderResult').hidden = false;
}
