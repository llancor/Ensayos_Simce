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

    function renderIdentity(session) {
        const role = session.rol === 'colegio_admin' ? 'Administrador del colegio' : 'Docente';
        const icon = session.rol === 'colegio_admin' ? '🛡️' : '👤';
        document.querySelectorAll('[data-simce-identity]').forEach(function (container) {
            container.replaceChildren();
            const iconElement = document.createElement('span');
            iconElement.className = 'simce-identity-icon';
            iconElement.setAttribute('aria-hidden', 'true');
            iconElement.textContent = icon;
            const text = document.createElement('span');
            const name = document.createElement('strong');
            const roleElement = document.createElement('small');
            name.textContent = session.usuario;
            roleElement.textContent = role;
            text.append(name, roleElement);
            container.append(iconElement, text);
            container.setAttribute('aria-label', role + ': ' + session.usuario);
        });
        if (session.rol === 'docente') {
            document.querySelectorAll('[data-hide-for-docente]').forEach(function (element) {
                element.hidden = true;
            });
        }
    }

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
        if (!document.getElementById('simce-identity-style')) {
            const style = document.createElement('style');
            style.id = 'simce-identity-style';
            style.textContent = '[data-simce-identity]{display:inline-flex;align-items:center;gap:9px;padding:7px 11px;border:1px solid rgba(148,163,184,.45);border-radius:10px;background:rgba(255,255,255,.12);color:inherit;line-height:1.15;white-space:nowrap}[data-simce-identity] .simce-identity-icon{font-size:20px}[data-simce-identity] strong,[data-simce-identity] small{display:block}[data-simce-identity] strong{font-size:13px}[data-simce-identity] small{margin-top:3px;font-size:10px;opacity:.78}';
            document.head.appendChild(style);
        }
        window.simceGetSession().then(renderIdentity).catch(function () {});
        document.querySelectorAll('[data-simce-logout]').forEach(function (button) {
            button.addEventListener('click', logout);
        });
    });
})();
