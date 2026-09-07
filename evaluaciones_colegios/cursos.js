(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let summary;
    const message = (text, error=false) => { $('courseSettingsMessage').textContent=text; $('courseSettingsMessage').style.color=error?'#b91c1c':'#166534'; };
    for(let grade=1;grade<=12;grade++) $('courseGrade').add(new Option((grade<=8?grade:grade-8)+'° '+(grade<=8?'básico':'medio'),grade));
    async function request(body) {
        const response=await window.simceAuthenticatedFetch('gestion_v21_api.php',body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}:{cache:'no-store'});
        const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'No se pudo guardar.');return data;
    }
    async function load(selected=$('managedCourse').value) {
        summary=await request();$('managedCourse').replaceChildren(new Option('Selecciona un curso',''));
        summary.cursos.forEach(c=>$('managedCourse').add(new Option(c.nombre,c.id)));
        $('managedCourse').value=String(selected);render();renderCatalog();
        if(!summary.cursos.length)message('No hay cursos. Crea el primero con el formulario superior.');
    }
    let editingId=null;
    for(let grade=1;grade<=12;grade++) $('editCourseGrade').add(new Option((grade<=8?grade:grade-8)+'° '+(grade<=8?'básico':'medio'),grade));
    function catalogMessage(text,error=false){$('courseCatalogMessage').textContent=text;$('courseCatalogMessage').style.color=error?'#b91c1c':'#166534';}
    function renderConfigSummary(course){
        const box=$('courseConfigSummary'); if(!course){box.hidden=true;box.innerHTML='';return;}
        const pin=summary.todos_cursos.find(c=>Number(c.id)===Number(course.id));
        const assignments=summary.asignaciones.filter(a=>Number(a.curso_id)===Number(course.id)&&Number(a.activa));
        const custom=assignments.find(a=>a.configuracion); let p=null;try{p=custom&&custom.configuracion?JSON.parse(custom.configuracion):null;}catch(error){p=null;}
        const chip=(text,kind='')=>'<span class="config-chip '+kind+'">'+esc(text)+'</span>';
        box.innerHTML=chip(Number(course.activo)?'● Activo':'● Deshabilitado',Number(course.activo)?'state':'off')
            +chip(course.modo_pruebas==='todas'?'✓ Todas las del nivel':'✓ Solo asignadas','ok')
            +chip(pin&&Number(pin.pin_configurado)?'✓ PIN':'✕ Sin PIN',pin&&Number(pin.pin_configurado)?'ok':'off')
            +(p?chip((p.feedback?'✓':'✕')+' Retroalimentación',p.feedback?'ok':'off'):'')
            +(p?chip((p.results?'✓':'✕')+' Resultado',p.results?'ok':'off'):'')
            +(p?chip(p.attempts>0?p.attempts+' intento'+(p.attempts===1?'':'s'):'↻ Sin límite','state'):'');
        box.hidden=false;
    }
    function renderCatalog(){
        $('courseCatalog').innerHTML=summary.todos_cursos.length?'<table><thead><tr><th>Curso</th><th>Estado</th><th>Pruebas visibles</th><th>Acciones</th></tr></thead><tbody>'+summary.todos_cursos.map(c=>`<tr><td>${esc(c.nombre)}</td><td><span class="status ${Number(c.activo)?'active':'inactive'}">${Number(c.activo)?'Habilitado':'Deshabilitado'}</span></td><td>${c.modo_pruebas==='todas'?'Todas las del nivel':'Solo asignadas'}</td><td class="actions"><button data-course="${Number(c.id)}" data-action="edit">Editar</button><button data-course="${Number(c.id)}" data-action="tests" ${Number(c.activo)?'':'disabled'}>Configurar pruebas</button><button class="warning" data-course="${Number(c.id)}" data-action="toggle">${Number(c.activo)?'Deshabilitar':'Habilitar'}</button><button class="danger" data-course="${Number(c.id)}" data-action="delete">Eliminar</button></td></tr>`).join('')+'</tbody></table>':'<p>No hay cursos creados.</p>';
    }
    $('cancelCourseEdit').onclick=()=>{$('editCourseForm').hidden=true;editingId=null;};
    $('courseCatalog').onclick=async e=>{
        const button=e.target.closest('button[data-course]');if(!button)return;
        const course=summary.todos_cursos.find(c=>Number(c.id)===Number(button.dataset.course));if(!course)return;
        const action=button.dataset.action;
        if(action==='edit'){
            editingId=Number(course.id);$('editCourseGrade').value=String(course.idgrado);
            $('editCourseSection').value=course.nombre.split(' ').pop();$('editCourseForm').hidden=false;$('editCourseSection').focus();return;
        }
        if(action==='tests'){$('managedCourse').value=String(course.id);render();$('managedCourse').scrollIntoView({behavior:'smooth',block:'center'});return;}
        if(action==='delete'&&!confirm('¿Eliminar el curso '+course.nombre+' y sus asignaciones? Esta acción no se puede deshacer.'))return;
        button.disabled=true;
        try{await request({action:action==='delete'?'delete_course':'toggle_course',curso:Number(course.id),activo:!Number(course.activo)});
            $('editCourseForm').hidden=true;editingId=null;await load();window.dispatchEvent(new Event('simce-courses-updated'));catalogMessage(action==='delete'?'Curso eliminado.':Number(course.activo)?'Curso deshabilitado.':'Curso habilitado.');
        }catch(err){catalogMessage(err.message,true);}finally{button.disabled=false;}
    };
    $('editCourseForm').onsubmit=async e=>{
        e.preventDefault();const button=e.target.querySelector('button[type="submit"]');button.disabled=true;
        try{await request({action:'update_course',curso:editingId,grado:Number($('editCourseGrade').value),seccion:$('editCourseSection').value});
            $('editCourseForm').hidden=true;await load(editingId);editingId=null;window.dispatchEvent(new Event('simce-courses-updated'));catalogMessage('Curso actualizado.');
        }catch(err){catalogMessage(err.message,true);}finally{button.disabled=false;}
    };
    function render() {
        const course=summary.cursos.find(c=>Number(c.id)===Number($('managedCourse').value));
        $('courseTestEditor').hidden=!course;if(!course)return;
        renderConfigSummary(course);
        $('managedMode').value=course.modo_pruebas;
        const grade=Number(course.idgrado);const code=(grade<=8?grade:grade-8)+(grade<=8?'basico':'medio');
        const tests=summary.pruebas.filter(t=>t.curso_codigo===code);
        let subject='';
        $('managedTests').innerHTML=tests.map(t=>{
            const a=summary.asignaciones.find(a=>Number(a.curso_id)===Number(course.id)&&Number(a.prueba_id)===Number(t.id));
            let p=null;try{p=a&&a.configuracion?JSON.parse(a.configuracion):null;}catch(error){p=null;}
            const heading=t.asignatura!==subject?'<h3>'+esc(t.asignatura)+'</h3>':'';subject=t.asignatura;
            return heading+`<div data-test="${Number(t.id)}" style="border:1px solid #cbd5e1;border-radius:10px;padding:14px;margin:12px 0">
                <label><input type="checkbox" data-field="active" style="min-width:0" ${a&&Number(a.activa)?'checked':''}> ${esc(t.titulo)}</label>
                <p><label>Configuración <select data-field="inherit"><option value="yes" ${p?'':'selected'}>Usar opciones del creador</option><option value="no" ${p?'selected':''}>Personalizar para este curso</option></select></label></p>
                <div data-options class="form-row" ${p?'':'hidden'}>
                    <label>Retroalimentación <select data-field="feedback"><option value="yes">Mostrar</option><option value="no" ${p&&!p.feedback?'selected':''}>Ocultar</option></select></label>
                    <label>Resultado <select data-field="results"><option value="yes">Mostrar</option><option value="no" ${p&&!p.results?'selected':''}>Ocultar</option></select></label>
                    <label>Intentos máximos (0: sin límite) <input data-field="attempts" type="number" min="0" max="100" value="${p?Number(p.attempts):0}" style="min-width:70px;width:85px"></label>
                    <label>Al revisar <select data-field="finish"><option value="results">Permanecer en la prueba</option><option value="return" ${p&&p.finish==='return'?'selected':''}>Finalizar y volver al listado</option></select></label>
                </div>
                <p class="help">Con resultado oculto o regreso al listado, no se muestra retroalimentación. Un intento impide repetir; cero permite repetir sin límite.</p>
            </div>`;
        }).join('')||'<p>No hay pruebas guardadas para este nivel.</p>';
        updateMode();
    }
    function updateMode() {
        const all=$('managedMode').value==='todas';
        $('visibilityHelp').textContent=all?'Incluye todas las asignaturas del nivel y las pruebas nuevas automáticamente.':'Marca las pruebas que podrán ver los alumnos. Las nuevas quedan ocultas hasta que las asignes.';
        document.querySelectorAll('[data-field="active"]').forEach(el=>{el.disabled=all;});
    }
    $('managedCourse').onchange=render;$('managedMode').onchange=updateMode;
    $('managedTests').onchange=e=>{if(e.target.dataset.field==='inherit')e.target.closest('[data-test]').querySelector('[data-options]').hidden=e.target.value==='yes';};
    $('createCourseForm').onsubmit=async e=>{
        e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
        try { const data=await request({action:'create_course',grado:Number($('courseGrade').value),seccion:$('courseSection').value,modo:$('createCourseMode').value,pin:$('createCoursePin').value});
            $('createCoursePin').value='';await load(data.id);message('Curso creado. Configura sus pruebas y guarda los cambios.');
            // Refresh the separate PIN controls without discarding the selected course.
            window.dispatchEvent(new Event('simce-courses-updated'));
        } catch(err){message(err.message,true);}finally{button.disabled=false;}
    };
    $('saveCourseTests').onclick=async function(){
        const rows=[...document.querySelectorAll('[data-test]')];
        if(rows.some(row=>!row.querySelector('[data-field="attempts"]').reportValidity()))return;
        this.disabled=true;
        try {
            const tests=rows.map(row=>{const get=f=>row.querySelector('[data-field="'+f+'"]');return {id:Number(row.dataset.test),activa:get('active').checked,configuracion:get('inherit').value==='yes'?null:{feedback:get('feedback').value==='yes',results:get('results').value==='yes',attempts:Number(get('attempts').value),finish:get('finish').value}};});
            await request({action:'save_course_tests',curso:Number($('managedCourse').value),modo:$('managedMode').value,pruebas:tests});await load();message('Configuración guardada.');
        }catch(err){message(err.message,true);}finally{this.disabled=false;}
    };
    window.addEventListener('simce-courses-updated',()=>load().catch(err=>message(err.message,true)));
    load().catch(err=>message(err.message,true));
})();
