<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Acceso de estudiantes</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#eef2ff;color:#172554;padding:24px}.card{max-width:700px;margin:5vh auto;background:#fff;border-radius:20px;padding:28px;box-shadow:0 18px 50px #1e3a8a22}h1{margin:0 0 8px}label{display:block;font-weight:700;margin:18px 0 6px}select,input,button{width:100%;padding:13px;border:1px solid #94a3b8;border-radius:10px;font-size:16px}button{margin-top:20px;background:#1d4ed8;color:#fff;border:0;font-weight:800;cursor:pointer}.error{color:#b91c1c;margin-top:12px}.test{display:block;padding:15px;margin:12px 0;border:1px solid #bfdbfe;border-radius:12px;text-decoration:none;color:#1e3a8a;background:#eff6ff;font-weight:700}.muted{color:#64748b}.mode{padding:10px 12px;border-radius:10px;background:#eff6ff;color:#1e40af;font-weight:700}.hidden{display:none!important}.logout{background:#475569;width:auto;padding:9px 15px;float:right}
    </style>
</head>
<body data-brand-page="Estudiantes">
<main class="card">
    <button id="logout" class="logout hidden">Salir</button>
    <h1>Pruebas de estudiantes</h1>
    <p id="modeNotice" class="mode">Cargando modalidad de ingreso…</p>
    <section id="login">
        <label for="curso">Curso</label>
        <select id="curso"><option value="">Selecciona un curso</option></select>
        <div id="listedStudentFields">
            <label for="estudiante">Estudiante</label>
            <select id="estudiante" disabled><option value="">Primero selecciona el curso</option></select>
        </div>
        <div id="freeStudentFields" class="hidden">
            <label for="nombreLibre">Nombre completo</label>
            <input id="nombreLibre" type="text" maxlength="150" autocomplete="name" placeholder="Escribe tu nombre completo">
        </div>
        <label for="pin">PIN del curso</label>
        <input id="pin" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="30">
        <button id="enter" type="button">Ingresar a mis pruebas</button>
        <div id="error" class="error" role="alert"></div>
    </section>
    <section id="panel" class="hidden">
        <h2 id="welcome"></h2>
        <p class="muted">Las respuestas y resultados quedarán asociados al nombre mostrado.</p>
        <div id="tests"></div>
    </section>
</main>
<script>
(function(){
    'use strict';
    const api='portal_estudiante_api.php';
    const course=document.querySelector('#curso');
    const student=document.querySelector('#estudiante');
    const freeName=document.querySelector('#nombreLibre');
    const pin=document.querySelector('#pin');
    const error=document.querySelector('#error');
    const login=document.querySelector('#login');
    const panel=document.querySelector('#panel');
    const logout=document.querySelector('#logout');
    let usesStudentList=true;

    async function request(url,options){
        const response=await fetch(url,options);
        const data=await response.json();
        if(!response.ok||data.ok===false)throw new Error(data.error||'No se pudo completar la solicitud.');
        return data;
    }

    function setMode(enabled){
        usesStudentList=Boolean(enabled);
        document.querySelector('#listedStudentFields').classList.toggle('hidden',!usesStudentList);
        document.querySelector('#freeStudentFields').classList.toggle('hidden',usesStudentList);
        document.querySelector('#modeNotice').textContent=usesStudentList
            ? 'Ingreso con lista: selecciona tu curso y tu nombre.'
            : 'Acceso libre: selecciona tu curso y escribe tu nombre completo.';
    }

    async function loadCourses(){
        const data=await request(api+'?action=courses');
        setMode(data.usa_lista_estudiantes!==false);
        data.cursos.forEach(function(item){course.add(new Option(item.nombre,item.id))});
        if(!data.cursos.length){error.textContent='No hay cursos disponibles. El administrador debe crear un curso en Administración.';document.querySelector('#enter').disabled=true;}
    }

    course.onchange=async function(){
        error.textContent='';
        student.innerHTML='<option value="">Selecciona tu nombre</option>';
        student.disabled=true;
        if(!course.value||!usesStudentList)return;
        try{
            const data=await request(api+'?action=students&curso='+encodeURIComponent(course.value));
            data.estudiantes.forEach(function(item){student.add(new Option(item.nombre,item.public_id))});
            student.disabled=false;
        }catch(loadError){error.textContent=loadError.message}
    };

    document.querySelector('#enter').onclick=async function(){
        error.textContent='';
        if(!course.value){error.textContent='Selecciona el curso.';return}
        if(usesStudentList&&!student.value){error.textContent='Selecciona tu nombre desde la lista.';return}
        if(!usesStudentList&&freeName.value.trim().length<2){error.textContent='Escribe tu nombre completo.';return}
        try{
            await request(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'login',curso:course.value,estudiante:student.value,nombre:freeName.value,pin:pin.value})});
            await loadSession();
        }catch(loginError){error.textContent=loginError.message;pin.value=''}
    };

    async function loadSession(){
        const data=await request(api+'?action=session');
        if(!data.authenticated)return;
        login.classList.add('hidden');panel.classList.remove('hidden');logout.classList.remove('hidden');
        document.querySelector('#welcome').textContent=data.estudiante.nombre+' — '+data.estudiante.curso;
        const tests=document.querySelector('#tests');
        tests.replaceChildren();
        if(!data.pruebas.length){tests.textContent='No hay pruebas asignadas por ahora.';return}
        data.pruebas.forEach(function(item){const link=document.createElement(item.agotada?'div':'a');link.className='test';if(!item.agotada)link.href='rendir_prueba.php?id='+encodeURIComponent(item.id);link.textContent=item.asignatura+': '+item.titulo+(item.agotada?' — Intentos completados':(item.intentos_maximos?' — Intentos: '+item.intentos_usados+'/'+item.intentos_maximos:''));tests.appendChild(link)});
    }

    logout.onclick=async function(){try{await request(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout'})})}finally{location.reload()}};
    loadCourses().then(loadSession).catch(function(loadError){error.textContent=loadError.message});
})();
</script>
<script src="marca.js"></script>
</body>
</html>
