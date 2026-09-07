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
        const roles = {
            superadmin: { label: 'Superadministrador', icon: '👑' },
            administrador: { label: 'Administrador', icon: '🛡️' },
            docente: { label: 'Docente', icon: '👤' }
        };
        const presentation = roles[session.rol] || { label: 'Usuario', icon: '👤' };
        const username = String(session.usuario || 'Usuario');

        document.querySelectorAll('[data-simce-identity]').forEach(function (container) {
            const icon = document.createElement('span');
            const details = document.createElement('span');
            const name = document.createElement('strong');
            const role = document.createElement('small');

            icon.className = 'simce-identity-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = presentation.icon;
            details.className = 'simce-identity-details';
            name.textContent = username;
            role.textContent = presentation.label;
            details.append(name, role);
            container.replaceChildren(icon, details);
            container.title = username + ' — ' + presentation.label;
        });

        document.querySelectorAll('[data-hide-for-docente]').forEach(function (element) {
            element.hidden = session.rol === 'docente';
        });
    }

    function injectIdentityStyles() {
        if (document.getElementById('simce-identity-styles')) return;
        const style = document.createElement('style');
        style.id = 'simce-identity-styles';
        style.textContent = '[data-simce-identity]{display:inline-flex;align-items:center;gap:.55rem;max-width:260px;padding:.38rem .68rem;border:1px solid rgba(147,197,253,.35);border-radius:10px;background:rgba(15,45,78,.88);color:#fff;line-height:1.15}'
            + '[data-simce-identity]:empty{display:none}'
            + '.simce-identity-icon{display:grid;place-items:center;width:30px;height:30px;flex:0 0 30px;border-radius:50%;background:rgba(255,255,255,.14);font-size:1rem}'
            + '.simce-identity-details{display:flex;min-width:0;flex-direction:column}'
            + '.simce-identity-details strong{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem}'
            + '.simce-identity-details small{margin-top:2px;color:#bfdbfe;font-size:.68rem;font-weight:600}';
        document.head.appendChild(style);
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
        injectIdentityStyles();
        window.simceGetSession().then(renderIdentity).catch(function () {});
        document.querySelectorAll('[data-simce-logout]').forEach(function (button) {
            button.addEventListener('click', logout);
        });
    });
})();
