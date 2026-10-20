/* ==========================================================================
   F3 - Login page
   Checks that both fields are filled in, sends them to PHP with fetch()
   (POST) and goes to the operations menu or back to Home with the result.
   ========================================================================== */

'use strict';

const LOGIN_URL = '../server/login.php';
const HOME_PAGE = 'index.html';
const OPERATIONS_PAGE = 'operations.html';

document.addEventListener('DOMContentLoaded', initLoginPage);

function initLoginPage() {
    document.getElementById('loginForm').addEventListener('submit', handleLoginSubmit);
}

/** Returns the inputs that are empty (and marks them in red). */
function findEmptyLoginFields(form) {
    const emptyFields = [];

    [form.elements.username, form.elements.password].forEach(function (input) {
        const empty = isEmpty(input.value);
        markInvalid(input, empty);
        if (empty) {
            emptyFields.push(input);
        }
    });

    return emptyFields;
}

async function handleLoginSubmit(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const message = document.getElementById('loginMessage');
    const submitButton = form.querySelector('button[type="submit"]');

    // Missing data is controlled on the client: nothing is sent to the server.
    const emptyFields = findEmptyLoginFields(form);
    if (emptyFields.length > 0) {
        showMessage(message, 'Please enter both your username and your password.', 'error');
        emptyFields[0].focus();
        return;
    }

    hideMessage(message);
    submitButton.disabled = true;

    try {
        const response = await fetch(LOGIN_URL, {
            method: 'POST',
            body: new FormData(form)
        });
        const result = await response.json();

        if (response.ok && result.authenticated === true) {
            goTo(OPERATIONS_PAGE);
            return;
        }

        showAuthenticationError(message);
    } catch (error) {
        showMessage(message, 'The server could not be reached. Please try again.', 'error');
        submitButton.disabled = false;
    }
}

/** Authentication failed: show the error and go back to the Home page. */
function showAuthenticationError(message) {
    showMessage(
        message,
        'Authentication error: the username or password is not correct.\nReturning to the Home page…',
        'error'
    );
    setTimeout(function () {
        goTo(HOME_PAGE);
    }, REDIRECT_DELAY_MS);
}
