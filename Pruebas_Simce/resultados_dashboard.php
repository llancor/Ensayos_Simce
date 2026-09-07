<?php
declare(strict_types=1);
require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('superadmin', 'administrador', 'docente'), false);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Resultados de evaluaciones</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #172554; font-family: Inter, Arial, sans-serif; }
        main { max-width: 1240px; margin: auto; padding: 24px; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
        .back { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 9px 13px; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; color: #1e293b; text-decoration: none; font-weight: 800; white-space: nowrap; box-shadow: 0 1px 2px #0f172a0d; }
        .back:hover { background: #f8fafc; border-color: #94a3b8; }
        .title h1 { margin: 0 0 5px; }
        .title p { margin: 0; color: #64748b; }
        .filters, .kpis, .courses { display: grid; gap: 14px; }
        .filters { grid-template-columns: repeat(3, minmax(180px, 1fr)); background: #fff; padding: 16px; border-radius: 14px; margin-bottom: 16px; }
        .filters label { font-size: 13px; font-weight: 700; color: #475569; }
        .filters select, .filters input { display: block; width: 100%; margin-top: 6px; padding: 11px; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; }
        .kpis { grid-template-columns: repeat(4, 1fr); margin-bottom: 18px; }
        .kpi, .course-card { background: #fff; border-radius: 14px; padding: 18px; border: 1px solid #e2e8f0; }
        .kpi span { display: block; color: #64748b; font-size: 13px; font-weight: 700; }
        .kpi strong { display: block; font-size: 28px; margin-top: 8px; }
        .courses { grid-template-columns: repeat(auto-fit, minmax(205px, 1fr)); margin-bottom: 18px; }
        .course-card h3 { margin: 0 0 12px; }
        .course-card div { display: flex; justify-content: space-between; margin-top: 7px; color: #475569; }
        .panel { background: #fff; border-radius: 14px; padding: 18px; overflow: auto; }
        .panel h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; min-width: 780px; }
        th, td { text-align: left; padding: 11px; border-bottom: 1px solid #e2e8f0; }
        th { font-size: 12px; text-transform: uppercase; color: #64748b; }
        .score { font-weight: 800; }
        .good { color: #15803d; }
        .low { color: #b91c1c; }
        .free-tag { display: inline-block; margin-left: 6px; padding: 2px 6px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .empty { text-align: center; color: #64748b; padding: 35px; }
        @media (max-width: 760px) {
            .filters, .kpis { grid-template-columns: 1fr 1fr; }
            .top { align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .filters, .kpis { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-brand-page="Resultados">
<main>
    <div class="top">
        <a class="back" href="pruebas_educativas.php"><span aria-hidden="true">←</span><span aria-hidden="true">🏠</span><span>Panel docente</span></a>
        <div class="title">
            <h1>📊 Resultados de evaluaciones</h1>
            <p id="subtitle">Resumen por curso y prueba</p>
        </div>
        <div data-simce-identity aria-live="polite"></div>
    </div>

    <section class="filters" aria-label="Filtros de resultados">
        <label>Curso
            <select id="courseFilter"><option value="">Todos los cursos</option></select>
        </label>
        <label>Asignatura
            <select id="subjectFilter"><option value="">Todas las asignaturas</option></select>
        </label>
        <label>Buscar estudiante o prueba
            <input id="search" type="search" placeholder="Escribe para buscar…">
        </label>
    </section>

    <section class="kpis" aria-label="Indicadores de resultados">
        <article class="kpi"><span>Intentos registrados</span><strong id="attempts">0</strong></article>
        <article class="kpi"><span>Estudiantes evaluados</span><strong id="students">0</strong></article>
        <article class="kpi"><span>Promedio general</span><strong id="average">—</strong></article>
        <article class="kpi"><span>Logro ≥ 60%</span><strong id="passRate">—</strong></article>
    </section>

    <section>
        <h2>Resumen por curso</h2>
        <div id="courseCards" class="courses"></div>
    </section>

    <section class="panel">
        <h2>Detalle de resultados</h2>
        <table>
            <thead><tr><th>Curso</th><th>Estudiante / participante</th><th>Asignatura</th><th>Prueba</th><th>Intento</th><th>Resultado</th><th>Nota</th><th>Fecha</th></tr></thead>
            <tbody id="resultRows"></tbody>
        </table>
        <div id="empty" class="empty" hidden>No hay resultados para los filtros seleccionados.</div>
    </section>
</main>

<script src="sesion-cliente.js"></script>
<script>
(function () {
    'use strict';

    let allResults = [];
    const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });
    const courseFilter = document.querySelector('#courseFilter');
    const subjectFilter = document.querySelector('#subjectFilter');
    const search = document.querySelector('#search');
    const attempts = document.querySelector('#attempts');
    const students = document.querySelector('#students');
    const average = document.querySelector('#average');
    const passRate = document.querySelector('#passRate');
    const courseCards = document.querySelector('#courseCards');
    const resultRows = document.querySelector('#resultRows');
    const empty = document.querySelector('#empty');
    const resultsTable = document.querySelector('table');

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function unique(values) {
        return Array.from(new Set(values.filter(Boolean))).sort(collator.compare);
    }

    function filtered() {
        const term = search.value.trim().toLocaleLowerCase('es');
        return allResults.filter(function (result) {
            return (!courseFilter.value || result.curso === courseFilter.value)
                && (!subjectFilter.value || result.asignatura === subjectFilter.value)
                && (!term || (result.nombre + ' ' + result.titulo + ' ' + result.asignatura).toLocaleLowerCase('es').includes(term));
        });
    }

    function fillSelect(select, values, label) {
        const current = select.value;
        select.innerHTML = '<option value="">' + label + '</option>' + values.map(function (value) {
            return '<option value="' + esc(value) + '">' + esc(value) + '</option>';
        }).join('');
        select.value = values.includes(current) ? current : '';
    }

    function render() {
        const rows = filtered().sort(function (a, b) {
            return collator.compare(a.curso, b.curso)
                || collator.compare(a.asignatura, b.asignatura)
                || collator.compare(a.titulo, b.titulo)
                || collator.compare(a.nombre, b.nombre);
        });
        const percentages = rows.map(function (result) { return Number(result.porcentaje) || 0; });
        attempts.textContent = String(rows.length);
        students.textContent = String(new Set(rows.map(function (result) { return result.nombre + '|' + result.curso; })).size);
        average.textContent = rows.length ? (percentages.reduce(function (sum, value) { return sum + value; }, 0) / rows.length).toFixed(1) + '%' : '—';
        passRate.textContent = rows.length ? Math.round(percentages.filter(function (value) { return value >= 60; }).length * 100 / rows.length) + '%' : '—';

        const groups = {};
        rows.forEach(function (result) { (groups[result.curso] ??= []).push(result); });
        courseCards.innerHTML = Object.keys(groups).sort(collator.compare).map(function (course) {
            const values = groups[course].map(function (result) { return Number(result.porcentaje) || 0; });
            const courseAverage = values.reduce(function (sum, value) { return sum + value; }, 0) / values.length;
            const studentCount = new Set(groups[course].map(function (result) { return result.nombre; })).size;
            return '<article class="course-card"><h3>' + esc(course) + '</h3>'
                + '<div><span>Intentos</span><strong>' + values.length + '</strong></div>'
                + '<div><span>Promedio</span><strong>' + courseAverage.toFixed(1) + '%</strong></div>'
                + '<div><span>Estudiantes</span><strong>' + studentCount + '</strong></div></article>';
        }).join('');

        resultRows.innerHTML = rows.map(function (result) {
            return '<tr><td>' + esc(result.curso) + '</td><td>' + esc(result.nombre) + (Number(result.acceso_libre) === 1 ? '<span class="free-tag">ACCESO LIBRE</span>' : '') + '</td><td>' + esc(result.asignatura)
                + '</td><td>' + esc(result.titulo) + '</td><td>' + esc(result.intento) + '</td><td class="score '
                + (Number(result.porcentaje) >= 60 ? 'good' : 'low') + '">' + esc(result.correctas) + '/' + esc(result.total)
                + ' (' + esc(result.porcentaje) + '%)</td><td>' + esc(result.nota || '—') + '</td><td>'
                + esc(result.finalizado_at || '—') + '</td></tr>';
        }).join('');
        empty.hidden = rows.length > 0;
        resultsTable.hidden = rows.length === 0;
    }

    async function load() {
        const response = await window.simceAuthenticatedFetch('gestion_api.php?action=summary', { cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudieron cargar los resultados.');
        allResults = data.resultados || [];
        fillSelect(courseFilter, unique(allResults.map(function (result) { return result.curso; })), 'Todos los cursos');
        fillSelect(subjectFilter, unique(allResults.map(function (result) { return result.asignatura; })), 'Todas las asignaturas');
        render();
    }

    courseFilter.addEventListener('change', render);
    subjectFilter.addEventListener('change', render);
    search.addEventListener('input', render);
    load().catch(function (error) {
        resultsTable.hidden = true;
        empty.hidden = false;
        empty.textContent = error.message;
    });
})();
</script>
<script src="marca.js"></script>
</body>
</html>
