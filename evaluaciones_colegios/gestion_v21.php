<?php require_once __DIR__.'/seguridad.php';simceRequireRole(array('colegio_admin','docente'),false);?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gestión del colegio</title><style>body{font-family:Arial;background:#f1f5f9;color:#172554;padding:20px}main{max-width:1100px;margin:auto}section{background:#fff;padding:20px;border-radius:14px;margin:15px 0}input,button{padding:10px;margin:4px}button{background:#1d4ed8;color:#fff;border:0;border-radius:7px}table{width:100%;border-collapse:collapse}th,td{padding:9px;border-bottom:1px solid #ddd;text-align:left}.scroll{overflow:auto}.back{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:9px;background:#e2e8f0;color:#1e293b;text-decoration:none;font-weight:700}</style></head><body><main>
<a class="back" href="pruebas_educativas.php">← Panel docente</a><div data-simce-identity style="float:right"></div><h1 id="title">Gestión del colegio</h1><p id="msg"></p>
<section id="teachers"><h2>Profesores</h2><input id="user" placeholder="Usuario"><input id="pass" type="password" placeholder="Contraseña segura"><button id="create">Crear profesor</button><div id="users"></div></section>
<section id="pins"><h2>PIN por curso</h2><div id="courses"></div></section>
</main><script src="sesion-cliente.js"></script><script>
const msg=document.querySelector('#msg');
function esc(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
async function call(body){const r=await window.simceAuthenticatedFetch('gestion_v21_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});return r.json()}
async function load(){
  const response=await window.simceAuthenticatedFetch('gestion_v21_api.php'),x=await response.json();
  if(!response.ok||!x.ok)throw new Error(x.error||'No se pudo cargar la gestión.');
  title.textContent='Gestión — '+x.colegio.nombre;
  courses.innerHTML=x.cursos.map(c=>'<div>'+esc(c.nombre)+' — '+(Number(c.requiere_pin)===1?'PIN protegido':'SIN PIN')+' <input id="pin'+c.id+'" placeholder="Nuevo PIN"><button onclick="setPin('+c.id+')">Guardar</button><button onclick="clearPin('+c.id+')">Sin PIN</button></div>').join('');
  if(x.rol==='docente'){
    teachers.style.display='none';
    msg.textContent='Puedes configurar los PIN de los cursos.';
  }else{
    users.innerHTML='<div class="scroll"><table><thead><tr><th>Usuario</th><th>Estado</th><th>Nueva contraseña</th><th>Acciones</th></tr></thead><tbody>'+x.usuarios.map(u=>'<tr><td>'+esc(u.usuario)+'</td><td>'+(Number(u.activo)===1?'Activo':'Desactivado')+'</td><td><input type="password" id="pw'+u.id+'" placeholder="Nueva contraseña"></td><td><button onclick="reset('+u.id+')">Restablecer</button><button onclick="toggleUser('+u.id+','+(Number(u.activo)===1?0:1)+')">'+(Number(u.activo)===1?'Desactivar':'Activar')+'</button><button onclick="deleteUser('+u.id+',\''+esc(u.usuario)+'\')" style="background:#b91c1c">Eliminar</button></td></tr>').join('')+'</tbody></table></div>';
  }
}
create.onclick=async()=>{const x=await call({action:'create_teacher',usuario:user.value,password:pass.value});msg.textContent=x.ok?'Profesor creado.':x.error;if(x.ok){user.value='';pass.value='';load()}};
async function setPin(id){const x=await call({action:'course_pin',curso:id,pin:document.querySelector('#pin'+id).value});msg.textContent=x.ok?'PIN actualizado.':x.error;if(x.ok)load()}
async function clearPin(id){if(confirm('¿Dejar el curso sin PIN?')){const x=await call({action:'clear_course_pin',curso:id});msg.textContent=x.ok?'PIN eliminado.':x.error;if(x.ok)load()}}
async function reset(id){const x=await call({action:'reset_teacher',id,password:document.querySelector('#pw'+id).value});msg.textContent=x.ok?'Contraseña actualizada.':x.error;if(x.ok)document.querySelector('#pw'+id).value=''}
async function toggleUser(id,activo){const verb=activo?'activar':'desactivar';if(!confirm('¿Deseas '+verb+' este usuario?'))return;const x=await call({action:'set_teacher_status',id,activo});msg.textContent=x.ok?'Usuario '+(activo?'activado.':'desactivado.'):x.error;if(x.ok)load()}
async function deleteUser(id,name){if(!confirm('¿Eliminar permanentemente al usuario "'+name+'"? Esta acción no se puede deshacer.'))return;const x=await call({action:'delete_teacher',id});msg.textContent=x.ok?'Usuario eliminado.':x.error;if(x.ok)load()}
load().catch(e=>{msg.textContent=e.message||'No se pudo cargar la gestión.'});
</script><script src="marca.js"></script></body></html>
