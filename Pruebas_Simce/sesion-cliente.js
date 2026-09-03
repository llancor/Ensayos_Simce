(function () {
    'use strict';

    let sessionPromise = null;

    async function loadSession() {
        const response = await fetch('sesion.php', {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const data = await response.json();
        if (!response.ok || !data.authenticated) {
            window.location.replace('index.html');
            throw new Error('Sesión no válida.');
        }
        return data;
    }

    window.simceGetSession = function () {
        if (!sessionPromise) sessionPromise = loadSession();
        return sessionPromise;
    };

    window.simceGetCsrfToken = async function () {
        const session = await window.simceGetSession();
        return session.csrfToken;
    };

    window.simceAuthenticatedFetch = async function (url, options) {
        const requestOptions = Object.assign({}, options || {});
        requestOptions.credentials = 'same-origin';
        requestOptions.headers = Object.assign({}, requestOptions.headers || {});
        const method = String(requestOptions.method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') {
            requestOptions.headers['X-CSRF-Token'] = await window.simceGetCsrfToken();
        }
        const response = await fetch(url, requestOptions);
        if (response.status === 401) {
            window.location.replace('index.html');
        }
        return response;
    };

    async function logout() {
        const buttons = document.querySelectorAll('[data-simce-logout]');
        buttons.forEach(function (button) { button.disabled = true; });
        try {
            await window.simceAuthenticatedFetch('autenticacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout' })
            });
        } finally {
            window.location.replace('index.html');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.simceGetSession().catch(function () {});
        document.querySelectorAll('[data-simce-logout]').forEach(function (button) {
            button.addEventListener('click', logout);
        });
    });
})();

