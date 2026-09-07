<?php
declare(strict_types=1);
require_once __DIR__ . '/seguridad.php';
simceRequireAdmin(false);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cursos, pruebas y usuarios</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f1f5f9; color: #172554; font-family: Inter, Arial, sans-serif; }
        main { max-width: 1180px; margin: auto; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        .back { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 9px 13px; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; color: #1e293b; text-decoration: none; font-weight: 800; white-space: nowrap; box-shadow: 0 1px 2px #0f172a0d; }
        .back:hover { background: #f8fafc; border-color: #94a3b8; }
        h1 { margin: 8px 0; }
        section { background: #fff; padding: 22px; border-radius: 16px; margin: 18px 0; box-shadow: 0 8px 25px #0f172a12; }
        .form-row, .course-row, .actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .form-row { margin-bottom: 14px; }
        .course-row { padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .course-row strong { min-width: 165px; }
        input, select, button { padding: 10px; border: 1px solid #94a3b8; border-radius: 8px; font: inherit; }
        input { min-width: 190px; }
        button { border: 0; background: #1d4ed8; color: #fff; font-weight: 700; cursor: pointer; }
        button:hover:not(:disabled) { filter: brightness(.94); }
        button:disabled { opacity: .5; cursor: not-allowed; }
        button.secondary { background: #64748b; }
        button.warning { background: #b45309; }
        button.danger { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; min-width: 850px; }
        th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
        th { color: #64748b; font-size: 12px; text-transform: uppercase; }
        .scroll { overflow-x: auto; }
        .status { display: inline-flex; padding: 4px 9px; border-radius: 999px; font-size: 12px; font-weight: 800; }
        .status.active { color: #166534; background: #dcfce7; }
        .status.inactive { color: #991b1b; background: #fee2e2; }
        .current { color: #475569; font-weight: 700; font-size: 13px; }
        .message { min-height: 24px; margin: 8px 0; font-weight: 700; color: #1d4ed8; }
        .message.error { color: #b91c1c; }
        .help { margin-top: 0; color: #64748b; font-size: 13px; }
        .config-summary { display:flex; flex-wrap:wrap; gap:6px; margin:12px 0 4px; color:#475569; font-size:12px; }
        .config-summary .config-chip { display:inline-flex; align-items:center; gap:4px; padding:4px 8px; border-radius:999px; background:#f1f5f9; white-space:nowrap; }
        .config-summary .config-chip.ok { color:#166534; background:#dcfce7; }
        .config-summary .config-chip.off { color:#64748b; background:#f1f5f9; }
        .config-summary .config-chip.state { color:#1d4ed8; background:#dbeafe; }
        .access-mode { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
        .access-mode h2 { margin: 0 0 7px; }
        .access-mode p { margin: 0; }
        .mode-state { display: inline-flex; margin-top: 9px; padding: 5px 10px; border-radius: 999px; font-size: 13px; font-weight: 800; }
        .mode-state.list { color: #166534; background: #dcfce7; }
        .mode-state.free { color: #92400e; background: #fef3c7; }
        [hidden] { display: none !important; }
        @media (max-width: 700px) {
            body { padding: 16px; }
            section { padding: 17px; }
            .form-row { align-items: stretch; flex-direction: column; }
            .form-row input, .form-row select, .form-row button { width: 100%; }
        }
    </style>
</head>
<body data-brand-page="Cursos, pruebas y usuarios">
<main>
    <div class="top">
        <a class="back" href="pruebas_educativas.php"><span aria-hidden="true">←</span><span aria-hidden="true">🏠</span><span>Panel docente</span></a>
        <div data-simce-identity aria-live="polite"></div>
    </div>
    <h1>Administración de cursos, pruebas y usuarios</h1>
    <div id="message" class="message" role="status"></div>

    <section id="usersSection" hidden>
        <h2>Usuarios y perfiles</h2>
        <p class="help" id="usersHelp">Crea usuarios y administra sus accesos.</p>
        <div class="form-row">
            <input id="username" autocomplete="off" maxlength="50" placeholder="Usuario">
            <input id="password" type="password" autocomplete="new-password" placeholder="Contraseña segura">
            <select id="newRole" aria-label="Perfil del nuevo usuario">
                <option value="docente">Docente</option>
                <option value="administrador">Administrador</option>
                <option value="superadmin">Superadministrador</option>
            </select>
            <button id="createUser" type="button">Crear usuario</button>
        </div>
        <p class="help">La contraseña debe tener al menos 12 caracteres, una mayúscula, una minúscula y un número.</p>
        <div id="userRows" class="scroll"></div>
    </section>

    <section id="accessModeSection" class="access-mode">
        <div>
            <h2>Ingreso de estudiantes</h2>
            <p class="help" id="accessModeHelp">Control general para toda la plataforma.</p>
            <span id="accessModeState" class="mode-state"></span>
        </div>
        <button id="toggleAccessMode" type="button"></button>
    </section>

    <section>
        <h2>Crear curso</h2>
        <p class="help">Puedes crear cursos sin importar alumnos. Con acceso libre, los estudiantes escriben su nombre.</p>
        <form id="createCourseForm" class="form-row">
            <label>Nivel <select id="courseGrade" required></select></label>
            <label>Sección <input id="courseSection" maxlength="8" value="A" pattern="[A-Za-z0-9]{1,8}" required style="min-width:70px;width:100px"></label>
            <label>Pruebas visibles <select id="createCourseMode"><option value="asignadas">Solo pruebas asignadas</option><option value="todas">Todas las pruebas de su nivel</option></select></label>
            <label>PIN opcional <input id="createCoursePin" type="password" maxlength="30" placeholder="Vacío: sin PIN"></label>
            <button type="submit">Crear curso</button>
        </form>
    </section>
    <section>
        <h2>Cursos creados</h2>
        <p class="help">Los cursos habilitados aparecen en el portal del estudiante. Deshabilitar conserva alumnos, pruebas e historial. Solo se pueden eliminar cursos sin alumnos ni resultados.</p>
        <div id="courseCatalog" class="scroll"></div>
        <p id="courseCatalogMessage" role="status" aria-live="polite"></p>
        <form id="editCourseForm" hidden>
            <h3>Editar curso</h3>
            <div class="form-row">
                <label>Nivel <select id="editCourseGrade" required></select></label>
                <label>Sección <input id="editCourseSection" maxlength="8" pattern="[A-Za-z0-9]{1,8}" required></label>
                <button type="submit">Guardar curso</button>
                <button type="button" id="cancelCourseEdit" class="secondary">Cancelar</button>
            </div>
            <p class="help">Cambiar de nivel quita las asignaciones del nivel anterior. Con alumnos o resultados, solo se permite cambiar la sección.</p>
        </form>
    </section>
    <section>
        <h2>Pruebas y configuración por curso</h2>
        <p class="help">Las opciones del creador se usan inicialmente. Puedes personalizarlas para cada prueba de este curso.</p>
        <p class="help">En acceso libre, los intentos se cuentan por nombre y curso. Para identificar al alumno mediante la nómina, activa las listas.</p>
        <label>Curso <select id="managedCourse"><option value="">Selecciona un curso</option></select></label>
        <div id="courseConfigSummary" class="config-summary" hidden aria-live="polite"></div>
        <div id="courseTestEditor" hidden>
            <p><label>Pruebas visibles <select id="managedMode"><option value="asignadas">Solo pruebas asignadas</option><option value="todas">Todas las pruebas de su nivel</option></select></label></p>
            <p class="help" id="visibilityHelp"></p>
            <div id="managedTests"></div>
            <button id="saveCourseTests" type="button">Guardar configuración del curso</button>
        </div>
        <p id="courseSettingsMessage" role="status" aria-live="polite"></p>
    </section>
    <section id="coursesSection">
        <h2>PIN común por curso</h2>
        <p class="help">El PIN actual no puede recuperarse porque se almacena de forma segura. Puedes definir uno nuevo o dejar el curso sin PIN.</p>
        <div id="courseRows"></div>
    </section>
</main>

<script src="sesion-cliente.js"></script>
<script>
(function () {
    'use strict';

    const message = document.querySelector('#message');
    const usersSection = document.querySelector('#usersSection');
    const coursesSection = document.querySelector('#coursesSection');
    const accessModeHelp = document.querySelector('#accessModeHelp');
    const accessModeState = document.querySelector('#accessModeState');
    const toggleAccessMode = document.querySelector('#toggleAccessMode');
    const usersHelp = document.querySelector('#usersHelp');
    const username = document.querySelector('#username');
    const password = document.querySelector('#password');
    const newRole = document.querySelector('#newRole');
    const createUser = document.querySelector('#createUser');
    const userRows = document.querySelector('#userRows');
    const courseRows = document.querySelector('#courseRows');
    let currentRole = '';
    let studentListsEnabled = true;

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function roleLabel(role) {
        return { superadmin: 'Superadministrador', administrador: 'Administrador', docente: 'Docente' }[role] || role;
    }

    function showMessage(text, isError) {
        message.textContent = text || '';
        message.classList.toggle('error', Boolean(isError));
    }

    async function call(body) {
        const response = await window.simceAuthenticatedFetch('gestion_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo completar la operación.');
        return data;
    }

    function renderUsers(users) {
        if (!users.length) {
            userRows.innerHTML = '<p class="help">No hay usuarios disponibles para administrar.</p>';
            return;
        }
        userRows.innerHTML = '<table><thead><tr><th>Usuario</th><th>Perfil</th><th>Estado</th><th>Nueva contraseña</th><th>Acciones</th></tr></thead><tbody>'
            + users.map(function (user) {
                const active = Number(user.activo) === 1;
                const current = Boolean(user.es_actual);
                const passwordControl = current
                    ? '<span class="current">Cuenta actual</span>'
                    : '<input type="password" id="pw' + Number(user.id) + '" autocomplete="new-password" placeholder="Nueva contraseña">';
                const actions = current
                    ? '<span class="current">Sesión actual protegida</span>'
                    : '<div class="actions"><button type="button" data-action="reset" data-id="' + Number(user.id) + '">Restablecer</button>'
                        + '<button type="button" class="warning" data-action="toggle" data-id="' + Number(user.id) + '" data-active="' + (active ? '0' : '1') + '">' + (active ? 'Desactivar' : 'Activar') + '</button>'
                        + '<button type="button" class="danger" data-action="delete" data-id="' + Number(user.id) + '" data-name="' + esc(user.usuario) + '">Eliminar</button></div>';
                return '<tr><td><strong>' + esc(user.usuario) + '</strong></td><td>' + esc(roleLabel(user.rol)) + '</td><td><span class="status ' + (active ? 'active' : 'inactive') + '">' + (active ? 'Activo' : 'Desactivado') + '</span></td><td>' + passwordControl + '</td><td>' + actions + '</td></tr>';
            }).join('') + '</tbody></table>';
    }

    function renderCourses(courses) {
        courseRows.innerHTML = courses.map(function (course) {
            const protectedByPin = Number(course.requiere_pin) === 1;
            return '<div class="course-row"><strong>' + esc(course.nombre) + '</strong><span>' + (protectedByPin ? 'PIN protegido' : 'SIN PIN') + '</span>'
                + '<input type="password" id="pin' + Number(course.id) + '" maxlength="30" placeholder="Nuevo PIN">'
                + '<button type="button" data-course-action="save" data-id="' + Number(course.id) + '">Guardar</button>'
                + '<button type="button" class="secondary" data-course-action="clear" data-id="' + Number(course.id) + '">Sin PIN</button></div>';
        }).join('') || '<p class="help">No hay cursos disponibles.</p>';
    }

    function renderAccessMode(enabled) {
        studentListsEnabled = Boolean(enabled);
        accessModeState.className = 'mode-state ' + (studentListsEnabled ? 'list' : 'free');
        accessModeState.textContent = studentListsEnabled ? 'LISTAS ACTIVADAS' : 'ACCESO LIBRE ACTIVADO';
        accessModeHelp.textContent = studentListsEnabled
            ? 'Los alumnos deben seleccionar su nombre desde la lista oficial.'
            : 'Los alumnos seleccionan el curso y escriben su propio nombre.';
        toggleAccessMode.textContent = studentListsEnabled ? 'Desactivar listas' : 'Activar listas';
        toggleAccessMode.className = studentListsEnabled ? 'warning' : '';
    }

    async function load() {
        const response = await window.simceAuthenticatedFetch('gestion_api.php?action=summary', { cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo cargar la gestión.');
        currentRole = data.rol;
        renderAccessMode(data.usa_lista_estudiantes !== false);
        renderCourses(data.cursos || []);

        if (currentRole === 'docente') {
            usersSection.hidden = true;
            coursesSection.hidden = false;
            showMessage('Perfil docente: puedes administrar cursos, pruebas, ingreso de estudiantes y PIN; las cuentas de usuario permanecen restringidas.', false);
            return;
        }

        usersSection.hidden = false;
        coursesSection.hidden = false;
        newRole.hidden = currentRole !== 'superadmin';
        newRole.value = 'docente';
        createUser.textContent = currentRole === 'superadmin' ? 'Crear usuario' : 'Crear docente';
        usersHelp.textContent = currentRole === 'superadmin'
            ? 'Puedes administrar todos los perfiles de la plataforma.'
            : 'Puedes crear y administrar cuentas docentes.';
        renderUsers(data.usuarios || []);
    }

    toggleAccessMode.addEventListener('click', async function () {
        const enable = !studentListsEnabled;
        const text = enable
            ? '¿Activar el ingreso mediante las listas oficiales para toda la plataforma?'
            : '¿Desactivar las listas para toda la plataforma? Los alumnos escribirán su propio nombre.';
        if (!window.confirm(text)) return;
        toggleAccessMode.disabled = true;
        try {
            const data = await call({ action: 'student_list_mode', activo: enable ? 1 : 0 });
            renderAccessMode(data.usa_lista_estudiantes);
            showMessage(enable ? 'Ingreso mediante listas activado.' : 'Acceso libre mediante nombre activado.', false);
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            toggleAccessMode.disabled = false;
        }
    });

    createUser.addEventListener('click', async function () {
        createUser.disabled = true;
        try {
            await call({ action: 'create_user', usuario: username.value, password: password.value, rol: newRole.value });
            username.value = '';
            password.value = '';
            showMessage(currentRole === 'superadmin' ? 'Usuario creado.' : 'Docente creado.', false);
            await load();
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            createUser.disabled = false;
        }
    });

    userRows.addEventListener('click', async function (event) {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const id = Number(button.dataset.id);
        try {
            if (button.dataset.action === 'reset') {
                const input = document.querySelector('#pw' + id);
                await call({ action: 'reset_password', id: id, password: input.value });
                input.value = '';
                showMessage('Contraseña actualizada; las sesiones anteriores fueron invalidadas.', false);
            } else if (button.dataset.action === 'toggle') {
                const active = Number(button.dataset.active);
                if (!window.confirm('¿Deseas ' + (active ? 'activar' : 'desactivar') + ' este usuario?')) return;
                await call({ action: 'set_user_status', id: id, activo: active });
                showMessage('Estado del usuario actualizado.', false);
                await load();
            } else if (button.dataset.action === 'delete') {
                const name = button.dataset.name || '';
                if (!window.confirm('¿Eliminar permanentemente al usuario "' + name + '"? Esta acción no se puede deshacer.')) return;
                await call({ action: 'delete_user', id: id });
                showMessage('Usuario eliminado.', false);
                await load();
            }
        } catch (error) {
            showMessage(error.message, true);
        }
    });

    courseRows.addEventListener('click', async function (event) {
        const button = event.target.closest('button[data-course-action]');
        if (!button) return;
        const id = Number(button.dataset.id);
        try {
            if (button.dataset.courseAction === 'save') {
                await call({ action: 'course_pin', curso: id, pin: document.querySelector('#pin' + id).value });
                showMessage('PIN actualizado.', false);
                await load();
            } else {
                if (!window.confirm('¿Dejar este curso sin PIN?')) return;
                await call({ action: 'clear_course_pin', curso: id });
                showMessage('El curso quedó sin PIN.', false);
                await load();
            }
        } catch (error) {
            showMessage(error.message, true);
        }
    });

    window.addEventListener('simce-courses-updated', function(){load().catch(function(error){showMessage(error.message,true);});});
    load().catch(function (error) { showMessage(error.message, true); });
})();
</script>
<script src="cursos.js"></script>
<script src="marca.js"></script>
</body>
</html>
