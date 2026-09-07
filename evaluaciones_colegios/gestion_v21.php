<?php require_once __DIR__.'/seguridad.php';simceRequireRole(array('colegio_admin','docente'),false);?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestion del colegio</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#f1f5f9;color:#172554;font-family:Inter,Arial,sans-serif}main{max-width:1180px;margin:auto}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}.back{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 13px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#1e293b;text-decoration:none;font-weight:800;white-space:nowrap;box-shadow:0 1px 2px #0f172a0d}.back:hover{background:#f8fafc;border-color:#94a3b8}h1{margin:8px 0}section{background:#fff;padding:22px;border-radius:16px;margin:18px 0;box-shadow:0 8px 25px #0f172a12}.form-row,.course-row,.actions,.access-mode{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.form-row{margin-bottom:14px}.access-mode{justify-content:space-between;gap:18px}.access-mode h2{margin:0 0 7px}.access-mode p{margin:0}.mode-state{display:inline-flex;margin-top:9px;padding:5px 10px;border-radius:999px;font-size:13px;font-weight:800}.mode-state.list{color:#166534;background:#dcfce7}.mode-state.free{color:#92400e;background:#fef3c7}.course-row{padding:10px 0;border-bottom:1px solid #e2e8f0}.course-row strong{min-width:165px}input,select,button{padding:10px;border:1px solid #94a3b8;border-radius:8px;font:inherit}input{min-width:190px}button{border:0;background:#1d4ed8;color:#fff;font-weight:700;cursor:pointer}button:hover:not(:disabled){filter:brightness(.94)}button:disabled{opacity:.5;cursor:not-allowed}button.secondary{background:#64748b}button.warning{background:#b45309}button.danger{background:#b91c1c}table{width:100%;border-collapse:collapse;min-width:850px}th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:middle}th{color:#64748b;font-size:12px;text-transform:uppercase}.scroll{overflow-x:auto}.status{display:inline-flex;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:800}.status.active{color:#166534;background:#dcfce7}.status.inactive{color:#991b1b;background:#fee2e2}.message{min-height:24px;margin:8px 0;font-weight:700;color:#1d4ed8}.message.error{color:#b91c1c}.help{margin-top:0;color:#64748b;font-size:13px}.config-summary{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 4px;color:#475569;font-size:12px}.config-summary .config-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:999px;background:#f1f5f9;white-space:nowrap}.config-summary .config-chip.ok{color:#166534;background:#dcfce7}.config-summary .config-chip.off{color:#64748b;background:#f1f5f9}.config-summary .config-chip.state{color:#1d4ed8;background:#dbeafe}[hidden]{display:none!important}@media(max-width:700px){body{padding:16px}section{padding:17px}.form-row{align-items:stretch;flex-direction:column}.form-row input,.form-row select,.form-row button{width:100%}}
</style>
</head>
<body data-brand-page="Gestion del colegio"><main>
<div class="top"><a class="back" href="pruebas_educativas.php"><span aria-hidden="true">←</span><span aria-hidden="true">🏠</span><span>Panel docente</span></a><div data-simce-identity aria-live="polite"></div></div>
<h1 id="title">Gestion del colegio</h1><div id="message" class="message" role="status"></div>

<section id="accessModeSection" class="access-mode">
<div><h2>Ingreso de estudiantes</h2><p class="help" id="accessModeHelp">Los alumnos seleccionan su curso y nombre segun el modo activo.</p><span id="accessModeState" class="mode-state list">LISTAS ACTIVADAS</span></div>
<button id="toggleAccessMode" type="button" class="warning">Desactivar listas</button>
</section>

<section id="teachers">
<h2>Profesores</h2><p class="help">Solo el administrador del colegio puede crear y administrar docentes.</p>
<div class="form-row"><input id="user" placeholder="Usuario"><input id="pass" type="password" placeholder="Contrasena segura"><button id="create" type="button">Crear profesor</button></div>
<div id="users" class="scroll"></div>
</section>

<section>
<h2>Crear curso</h2><p class="help">Puedes crear cursos antes de importar alumnos. Luego podras asignar pruebas, PIN y configuracion.</p>
<form id="createCourseForm" class="form-row">
<label>Nivel <select id="courseGrade" required></select></label>
<label>Seccion <input id="courseSection" maxlength="8" value="A" pattern="[A-Za-z0-9]{1,8}" required style="min-width:70px;width:100px"></label>
<label>Pruebas visibles <select id="createCourseMode"><option value="asignadas">Solo pruebas asignadas</option><option value="todas">Todas las pruebas de su nivel</option></select></label>
<label>PIN opcional <input id="createCoursePin" type="password" maxlength="30" placeholder="Vacio: sin PIN"></label>
<button type="submit">Crear curso</button>
</form>
</section>

<section>
<h2>Cursos creados</h2>
<p class="help">Los cursos habilitados aparecen para estudiantes. Deshabilitar conserva alumnos, pruebas e historial. Solo se pueden eliminar cursos sin alumnos ni resultados.</p>
<div id="courseCatalog" class="scroll"></div><p id="courseCatalogMessage" role="status" aria-live="polite"></p>
<form id="editCourseForm" hidden><h3>Editar curso</h3><div class="form-row">
<label>Nivel <select id="editCourseGrade" required></select></label>
<label>Seccion <input id="editCourseSection" maxlength="8" pattern="[A-Za-z0-9]{1,8}" required></label>
<button type="submit">Guardar curso</button><button type="button" id="cancelCourseEdit" class="secondary">Cancelar</button>
</div><p class="help">Con alumnos o resultados, solo se permite cambiar la seccion.</p></form>
</section>

<section>
<h2>Pruebas y configuracion por curso</h2>
<p class="help">Selecciona un curso para ver que tiene activo. El resumen muestra estado, modo, PIN, retroalimentacion, resultado e intentos.</p>
<label>Curso <select id="managedCourse"><option value="">Selecciona un curso</option></select></label>
<div id="courseConfigSummary" class="config-summary" hidden aria-live="polite"></div>
<div id="courseTestEditor" hidden>
<p><label>Pruebas visibles <select id="managedMode"><option value="asignadas">Solo pruebas asignadas</option><option value="todas">Todas las pruebas de su nivel</option></select></label></p>
<p class="help" id="visibilityHelp"></p><div id="managedTests"></div><button id="saveCourseTests" type="button">Guardar configuracion del curso</button>
</div><p id="courseSettingsMessage" role="status" aria-live="polite"></p>
</section>

<section id="coursesSection">
<h2>PIN comun por curso</h2><p class="help">El PIN actual no puede recuperarse. Puedes definir uno nuevo o dejar el curso sin PIN.</p><div id="courseRows"></div>
</section>
</main>
<script src="sesion-cliente.js"></script>
<script src="cursos.js"></script>
<script>
(function(){'use strict';
const msg=document.querySelector('#message'),teachers=document.querySelector('#teachers'),users=document.querySelector('#users'),user=document.querySelector('#user'),pass=document.querySelector('#pass'),create=document.querySelector('#create'),title=document.querySelector('#title'),courseRows=document.querySelector('#courseRows'),accessModeSection=document.querySelector('#accessModeSection'),accessModeState=document.querySelector('#accessModeState'),accessModeHelp=document.querySelector('#accessModeHelp'),toggleAccessMode=document.querySelector('#toggleAccessMode');
let studentListsEnabled=true;
function esc(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function show(text,error){msg.textContent=text||'';msg.classList.toggle('error',Boolean(error))}
function renderAccessMode(enabled){studentListsEnabled=Boolean(enabled);accessModeState.className='mode-state '+(studentListsEnabled?'list':'free');accessModeState.textContent=studentListsEnabled?'LISTAS ACTIVADAS':'ACCESO LIBRE ACTIVADO';accessModeHelp.textContent=studentListsEnabled?'Los alumnos deben seleccionar su nombre desde la nomina del colegio.':'Los alumnos seleccionan el curso y escriben su propio nombre.';toggleAccessMode.textContent=studentListsEnabled?'Desactivar listas':'Activar listas';toggleAccessMode.className=studentListsEnabled?'warning':''}
async function call(body){const r=await window.simceAuthenticatedFetch('gestion_v21_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const x=await r.json();if(!r.ok||!x.ok)throw new Error(x.error||'No se pudo completar la operacion.');return x}
async function load(){
 const response=await window.simceAuthenticatedFetch('gestion_v21_api.php',{cache:'no-store'}),x=await response.json();if(!response.ok||!x.ok)throw new Error(x.error||'No se pudo cargar la gestion.');
 title.textContent='Gestion del colegio - '+x.colegio.nombre;
 renderAccessMode(x.usa_lista_estudiantes!==false);
 courseRows.innerHTML=(x.cursos||[]).map(c=>'<div class="course-row"><strong>'+esc(c.nombre)+'</strong><span>'+(Number(c.requiere_pin)===1?'PIN protegido':'SIN PIN')+'</span><input type="password" id="pin'+Number(c.id)+'" maxlength="30" placeholder="Nuevo PIN"><button type="button" data-pin-action="save" data-id="'+Number(c.id)+'">Guardar</button><button type="button" class="secondary" data-pin-action="clear" data-id="'+Number(c.id)+'">Sin PIN</button></div>').join('')||'<p class="help">No hay cursos disponibles.</p>';
 if(x.rol==='docente'){teachers.hidden=true;accessModeSection.hidden=true;show('Perfil docente: puedes administrar cursos, pruebas y PIN. Las cuentas de usuario permanecen restringidas.',false);return;}
 accessModeSection.hidden=false;
 teachers.hidden=false;users.innerHTML='<table><thead><tr><th>Usuario</th><th>Estado</th><th>Nueva contrasena</th><th>Acciones</th></tr></thead><tbody>'+((x.usuarios||[]).map(u=>'<tr><td><strong>'+esc(u.usuario)+'</strong></td><td><span class="status '+(Number(u.activo)===1?'active':'inactive')+'">'+(Number(u.activo)===1?'Activo':'Desactivado')+'</span></td><td><input type="password" id="pw'+Number(u.id)+'" placeholder="Nueva contrasena"></td><td class="actions"><button type="button" data-teacher-action="reset" data-id="'+Number(u.id)+'">Restablecer</button><button type="button" class="warning" data-teacher-action="toggle" data-id="'+Number(u.id)+'" data-active="'+(Number(u.activo)===1?'0':'1')+'">'+(Number(u.activo)===1?'Desactivar':'Activar')+'</button><button type="button" class="danger" data-teacher-action="delete" data-id="'+Number(u.id)+'" data-name="'+esc(u.usuario)+'">Eliminar</button></td></tr>').join(''))+'</tbody></table>';
}
create.onclick=async()=>{create.disabled=true;try{await call({action:'create_teacher',usuario:user.value,password:pass.value});user.value='';pass.value='';show('Profesor creado.',false);await load();}catch(e){show(e.message,true)}finally{create.disabled=false}};
users.onclick=async e=>{const b=e.target.closest('button[data-teacher-action]');if(!b)return;const id=Number(b.dataset.id);try{if(b.dataset.teacherAction==='reset'){await call({action:'reset_teacher',id,password:document.querySelector('#pw'+id).value});document.querySelector('#pw'+id).value='';show('Contrasena actualizada.',false)}else if(b.dataset.teacherAction==='toggle'){const active=Number(b.dataset.active);if(!confirm('Deseas '+(active?'activar':'desactivar')+' este usuario?'))return;await call({action:'set_teacher_status',id,activo:active});show('Estado actualizado.',false);await load()}else{if(!confirm('Eliminar permanentemente al usuario "'+(b.dataset.name||'')+'"?'))return;await call({action:'delete_teacher',id});show('Usuario eliminado.',false);await load()}}catch(err){show(err.message,true)}};
courseRows.onclick=async e=>{const b=e.target.closest('button[data-pin-action]');if(!b)return;const id=Number(b.dataset.id);try{if(b.dataset.pinAction==='save'){await call({action:'course_pin',curso:id,pin:document.querySelector('#pin'+id).value});show('PIN actualizado.',false)}else{if(!confirm('Dejar este curso sin PIN?'))return;await call({action:'clear_course_pin',curso:id});show('El curso quedo sin PIN.',false)}await load();window.dispatchEvent(new Event('simce-courses-updated'));}catch(err){show(err.message,true)}};
toggleAccessMode.onclick=async()=>{const enable=!studentListsEnabled;const text=enable?'Activar el ingreso mediante listas para este colegio?':'Desactivar listas para este colegio? Los alumnos escribiran su propio nombre.';if(!confirm(text))return;toggleAccessMode.disabled=true;try{const x=await call({action:'student_list_mode',activo:enable?1:0});renderAccessMode(x.usa_lista_estudiantes);show(enable?'Ingreso mediante listas activado.':'Acceso libre mediante nombre activado.',false)}catch(e){show(e.message,true)}finally{toggleAccessMode.disabled=false}};
window.addEventListener('simce-courses-updated',()=>load().catch(e=>show(e.message,true)));
load().catch(e=>show(e.message,true));
})();
</script>
<script src="marca.js"></script>
</body></html>
