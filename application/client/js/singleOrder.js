/* ==========================================================================
   F8 - Single order
   Checks that the order ID is not empty. If it is filled in, the form is sent
   by GET to getOrder.php, which answers with the HTML page of the order
   (200 OK) or with an HTML error page (404 Not Found).
   ========================================================================== */

'use strict';

document.addEventListener('DOMContentLoaded', initSingleOrderPage);

function initSingleOrderPage() {
    document.getElementById('singleOrderForm').addEventListener('submit', handleSearchSubmit);
}

function handleSearchSubmit(event) {
    const form = event.currentTarget;
    const orderIdInput = form.elements.orderId;
    const message = document.getElementById('searchMessage');

    if (isEmpty(orderIdInput.value)) {
        event.preventDefault();
        markInvalid(orderIdInput, true);
        showMessage(message, 'Please enter the order ID you want to search for.', 'error');
        orderIdInput.focus();
        return;
    }

    orderIdInput.value = orderIdInput.value.trim();
    markInvalid(orderIdInput, false);
    hideMessage(message);
}
