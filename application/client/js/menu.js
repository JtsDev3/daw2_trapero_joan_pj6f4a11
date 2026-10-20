/* ==========================================================================
   F4 / F6 - Selection menus (Operations menu and View orders menu)
   A single "Go" button opens the interface of the option chosen in the list.
   ========================================================================== */

'use strict';

document.addEventListener('DOMContentLoaded', initMenuPage);

function initMenuPage() {
    document.getElementById('menuForm').addEventListener('submit', handleMenuSubmit);
}

function handleMenuSubmit(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const select = form.elements.operation;
    const message = document.getElementById('menuMessage');

    if (select.value === '') {
        markInvalid(select, true);
        showMessage(message, 'Please choose an option from the list.', 'error');
        select.focus();
        return;
    }

    markInvalid(select, false);
    hideMessage(message);
    goTo(select.value);
}
