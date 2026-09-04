(function () {
    'use strict';

    const schoolCode = new URLSearchParams(window.location.search).get('colegio') || '';
    function apiUrl(url) {
        if (!schoolCode) return url;
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'colegio=' + encodeURIComponent(schoolCode);
    }

    const state = {
        file: null,
        rows: [],
        page: 1,
        limit: 100,
        total: 0,
        summaryTotal: 0,
        searchTimer: null
    };

    const elements = {};

    function showStatus(message, type) {
        elements.status.hidden = false;
        elements.status.className = 'status ' + type;
        elements.status.textContent = message;
    }

    function normalizeHeader(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase();
    }

    function mapRows(matrix) {
        if (!Array.isArray(matrix) || matrix.length < 2) {
            throw new Error('El archivo no contiene filas de estudiantes.');
        }
        const headerMap = {};
        matrix[0].forEach(function (header, index) {
            headerMap[normalizeHeader(header)] = index;
        });
        const required = ['nombre', 'rut', 'curso', 'idgrado'];
        const missing = required.filter(function (header) { return headerMap[header] === undefined; });
        if (missing.length) {
            throw new Error('Faltan columnas obligatorias: ' + missing.join(', ') + '.');
        }

        return matrix.slice(1).filter(function (row) {
            return row.some(function (value) { return String(value || '').trim() !== ''; });
        }).map(function (row) {
            return {
                Nombre: String(row[headerMap.nombre] || '').trim(),
                Rut: String(row[headerMap.rut] || '').trim(),
                Curso: String(row[headerMap.curso] || '').trim(),
                idgrado: String(row[headerMap.idgrado] || '').trim()
            };
        });
    }

    async function readWorkbook(file) {
        if (typeof XLSX === 'undefined') {
            throw new Error('No se pudo cargar el lector de Excel. Revisa la conexión a Internet.');
        }
        if (!/\.(xlsx|xls)$/i.test(file.name)) {
            throw new Error('Selecciona un archivo .xlsx o .xls.');
        }
        if (file.size > 10 * 1024 * 1024) {
            throw new Error('El archivo supera el máximo de 10 MB.');
        }
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: 'array', cellDates: false });
        const firstSheetName = workbook.SheetNames[0];
        if (!firstSheetName) throw new Error('El libro no contiene hojas.');
        const matrix = XLSX.utils.sheet_to_json(workbook.Sheets[firstSheetName], {
            header: 1,
            defval: '',
            raw: false
        });
        return mapRows(matrix);
    }

    async function onFileSelected(event) {
        const file = event.target.files && event.target.files[0];
        state.file = null;
        state.rows = [];
        elements.importButton.disabled = true;
        elements.filePreview.hidden = true;
        elements.status.hidden = true;
        if (!file) return;

        try {
            const rows = await readWorkbook(file);
            if (rows.length > 5000) throw new Error('El archivo contiene más de 5.000 estudiantes.');
            const courses = new Set(rows.map(function (row) { return row.Curso; }).filter(Boolean));
            state.file = file;
            state.rows = rows;
            elements.filePreview.hidden = false;
            elements.filePreview.textContent = file.name + ': ' + rows.length + ' estudiante(s) y ' + courses.size + ' curso(s) detectados.';
            elements.importButton.disabled = false;
        } catch (error) {
            showStatus(error.message || 'No se pudo leer el archivo.', 'error');
        }
    }

    async function importStudents() {
        if (!state.file || state.rows.length === 0) return;
        elements.importButton.disabled = true;
        showStatus('Importando estudiantes de forma segura…', 'info');
        try {
            const response = await window.simceAuthenticatedFetch(apiUrl('estudiantes_api.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'import',
                    filename: state.file.name,
                    students: state.rows
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar la importación.');
            let message = 'Importación terminada: ' + result.inserted + ' nuevos, ' + result.updated + ' actualizados y ' + result.rejected + ' rechazados.';
            if (result.errors && result.errors.length) {
                message += ' Primera observación: fila ' + result.errors[0].fila + ', ' + result.errors[0].error;
            }
            showStatus(message, result.rejected ? 'error' : 'success');
            state.page = 1;
            await Promise.all([loadSummary(), loadStudents()]);
        } catch (error) {
            showStatus(error.message || 'No se pudo completar la importación.', 'error');
        } finally {
            elements.importButton.disabled = false;
        }
    }

    async function clearStudents() {
        if (state.summaryTotal === 0) return;

        const confirmation = window.prompt(
            'Se eliminarán los ' + state.summaryTotal + ' estudiantes de la nómina. ' +
            'Esta acción no se puede deshacer. Escribe VACIAR para continuar.'
        );
        if (confirmation !== 'VACIAR') {
            showStatus('La nómina no fue modificada.', 'info');
            return;
        }

        elements.clearButton.disabled = true;
        showStatus('Vaciando la nómina…', 'info');
        try {
            const response = await window.simceAuthenticatedFetch(apiUrl('estudiantes_api.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'clear',
                    confirmation: confirmation,
                    expectedTotal: state.summaryTotal
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo vaciar la nómina.');

            state.page = 1;
            elements.courseFilter.value = '';
            elements.search.value = '';
            showStatus('Nómina vaciada: se eliminaron ' + result.deleted + ' estudiante(s). Ya puedes importar el archivo actualizado.', 'success');
            await Promise.all([loadSummary(), loadStudents()]);
        } catch (error) {
            showStatus(error.message || 'No se pudo vaciar la nómina.', 'error');
        } finally {
            elements.clearButton.disabled = state.summaryTotal === 0;
        }
    }

    function setText(id, value) {
        document.getElementById(id).textContent = String(value);
    }

    async function loadSummary() {
        const response = await window.simceAuthenticatedFetch(apiUrl('estudiantes_api.php?action=summary'));
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo cargar el resumen.');
        setText('summary-total', result.total);
        setText('summary-basic', result.basica);
        setText('summary-middle', result.media);
        setText('summary-courses', result.cursos.length);
        state.summaryTotal = result.total;
        elements.clearButton.disabled = result.total === 0;

        const selected = elements.courseFilter.value;
        elements.courseFilter.replaceChildren();
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'Todos los cursos';
        elements.courseFilter.appendChild(allOption);
        result.cursos.forEach(function (course) {
            const option = document.createElement('option');
            option.value = course.curso;
            option.textContent = course.curso + ' (' + course.total + ')';
            elements.courseFilter.appendChild(option);
        });
        elements.courseFilter.value = selected;
    }

    function appendCell(row, text, className) {
        const cell = document.createElement('td');
        if (className) {
            const badge = document.createElement('span');
            badge.className = className;
            badge.textContent = text;
            cell.appendChild(badge);
        } else {
            cell.textContent = text;
        }
        row.appendChild(cell);
    }

    async function loadStudents() {
        const params = new URLSearchParams({
            action: 'list',
            page: String(state.page),
            limit: String(state.limit)
        });
        if (elements.courseFilter.value) params.set('curso', elements.courseFilter.value);
        if (elements.search.value.trim()) params.set('q', elements.search.value.trim());
        const response = await window.simceAuthenticatedFetch(apiUrl('estudiantes_api.php?' + params.toString()));
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo cargar la nómina.');
        state.total = result.total;
        elements.studentsBody.replaceChildren();
        if (!result.students.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'empty';
            cell.textContent = 'No hay estudiantes para este filtro.';
            row.appendChild(cell);
            elements.studentsBody.appendChild(row);
        } else {
            result.students.forEach(function (student) {
                const row = document.createElement('tr');
                appendCell(row, student.nombre);
                appendCell(row, student.rut);
                appendCell(row, student.curso);
                appendCell(row, student.nivel === 'media' ? 'Media' : 'Básica', 'level-badge ' + (student.nivel === 'media' ? 'media' : ''));
                appendCell(row, String(student.idgrado));
                elements.studentsBody.appendChild(row);
            });
        }

        const from = result.total ? (state.page - 1) * state.limit + 1 : 0;
        const to = Math.min(state.page * state.limit, result.total);
        elements.listCount.textContent = 'Mostrando ' + from + '–' + to + ' de ' + result.total;
        elements.previous.disabled = state.page <= 1;
        elements.next.disabled = to >= result.total;
    }

    async function reloadList() {
        try {
            await loadStudents();
        } catch (error) {
            elements.studentsBody.innerHTML = '<tr><td colspan="5" class="empty">No se pudo cargar la nómina.</td></tr>';
            showStatus(error.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        elements.fileInput = document.getElementById('students-file');
        elements.importButton = document.getElementById('import-button');
        elements.clearButton = document.getElementById('clear-students-button');
        elements.filePreview = document.getElementById('file-preview');
        elements.status = document.getElementById('import-status');
        elements.courseFilter = document.getElementById('course-filter');
        elements.search = document.getElementById('student-search');
        elements.studentsBody = document.getElementById('students-body');
        elements.listCount = document.getElementById('list-count');
        elements.previous = document.getElementById('previous-page');
        elements.next = document.getElementById('next-page');

        elements.fileInput.addEventListener('change', onFileSelected);
        elements.importButton.addEventListener('click', importStudents);
        elements.clearButton.addEventListener('click', clearStudents);
        elements.courseFilter.addEventListener('change', function () { state.page = 1; reloadList(); });
        elements.search.addEventListener('input', function () {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(function () { state.page = 1; reloadList(); }, 300);
        });
        elements.previous.addEventListener('click', function () { if (state.page > 1) { state.page--; reloadList(); } });
        elements.next.addEventListener('click', function () { if (state.page * state.limit < state.total) { state.page++; reloadList(); } });

        try {
            const session = await window.simceGetSession();
            if (session.rol === 'plataforma_superadmin') {
                const backLink = document.querySelector('.topbar-actions a');
                if (backLink) {
                    backLink.href = 'plataforma.php';
                    backLink.textContent = '← Volver a colegios';
                }
            }
            if (session.rol === 'docente') {
                document.querySelectorAll('[data-admin-only]').forEach(function (element) {
                    element.hidden = true;
                });
            }
            await Promise.all([loadSummary(), loadStudents()]);
        } catch (error) {
            showStatus(error.message || 'No se pudo iniciar el módulo de estudiantes.', 'error');
        }
    });
})();
