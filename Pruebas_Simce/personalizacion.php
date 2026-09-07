<?php
require_once __DIR__ . '/seguridad.php';
simceRequireRole(array('superadmin', 'administrador'), false);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Personalización</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:24px;background:#f1f5f9;color:#172554;font-family:Arial,sans-serif}
        .card{max-width:760px;margin:auto;background:white;padding:28px;border-radius:18px;box-shadow:0 12px 40px #0f172a18}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px}
        .back{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 13px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#1e293b;text-decoration:none;font-weight:800;white-space:nowrap;box-shadow:0 1px 2px #0f172a0d}
        .back:hover{background:#f8fafc;border-color:#94a3b8}
        label{display:block;font-weight:700;margin-top:16px}
        input{width:100%;padding:12px;margin-top:6px;border:1px solid #94a3b8;border-radius:9px;font-size:16px}
        button{margin-top:22px;padding:13px 20px;border:0;border-radius:9px;background:#1d4ed8;color:white;font-weight:800;cursor:pointer}
        .preview{display:flex;align-items:center;gap:16px;padding:18px;margin-top:20px;background:#eff6ff;border-radius:12px}
        .preview img{width:80px;height:80px;object-fit:contain;background:white;border-radius:10px}
        .help{display:block;margin-top:6px;color:#64748b;font-size:13px;font-weight:400}
        .msg{font-weight:700;margin-top:14px}
    </style>
</head>
<body data-brand-page="Personalización">
<main class="card">
    <div class="topbar">
        <a class="back" href="pruebas_educativas.php"><span aria-hidden="true">←</span><span aria-hidden="true">🏠</span><span>Panel docente</span></a>
        <div data-simce-identity aria-live="polite"></div>
    </div>
    <h1>Personalización de la plataforma</h1>
    <p>Estos datos aparecerán en la portada, el menú de pruebas y el panel docente.</p>
    <form id="form">
        <label>Nombre de la plataforma<input name="nombre_plataforma" maxlength="80" required></label>
        <label>Nombre del establecimiento<input name="nombre_establecimiento" maxlength="120" required></label>
        <label>Subtítulo institucional<input name="subtitulo" maxlength="120" required></label>
        <label>Saludo de la portada<input name="saludo" maxlength="120" required></label>
        <label>Texto para elegir perfil<input name="seleccion_perfil" maxlength="100" required></label>
        <label>Logo institucional (PNG, JPG, WebP o SVG; máximo 2 MB)<input name="logo" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,image/*"></label>
        <label>Ícono del navegador / favicon (PNG o ICO; máximo 512 KB)
            <input name="favicon" type="file" accept=".png,.ico,image/png,image/x-icon">
            <span class="help" id="faviconState">Si no cargas uno, se utilizará el logo institucional.</span>
        </label>
        <div class="preview">
            <img id="logoPreview" alt="Vista previa del logo" hidden>
            <div><strong id="namePreview"></strong><div id="institutionPreview"></div></div>
        </div>
        <button type="submit">Guardar personalización</button>
        <div id="message" class="msg" role="status"></div>
    </form>
</main>
<script src="sesion-cliente.js"></script>
<script src="marca.js"></script>
<script>
const form=document.querySelector('#form');
const message=document.querySelector('#message');
const logoPreview=document.querySelector('#logoPreview');
const namePreview=document.querySelector('#namePreview');
const institutionPreview=document.querySelector('#institutionPreview');
const faviconState=document.querySelector('#faviconState');

function applyFavicon(brand){
    const iconUrl=String(brand.favicon||brand.logo||'').trim();
    if(!iconUrl)return;
    let icon=document.querySelector('link[rel~="icon"]');
    if(!icon){icon=document.createElement('link');icon.rel='icon';document.head.appendChild(icon)}
    icon.href=iconUrl;
}

function show(brand){
    Object.keys(brand).forEach(key=>{
        if(form.elements[key]&&!['logo','favicon'].includes(key))form.elements[key].value=brand[key];
    });
    if(brand.logo){logoPreview.src=brand.logo;logoPreview.hidden=false}else{logoPreview.removeAttribute('src');logoPreview.hidden=true}
    namePreview.textContent=brand.nombre_plataforma;
    institutionPreview.textContent=brand.nombre_establecimiento+' — '+brand.subtitulo;
    faviconState.textContent=brand.favicon?'Favicon personalizado activo.':'Sin favicon propio: se utilizará el logo institucional.';
    applyFavicon(brand);
}

fetch('personalizacion_api.php',{credentials:'same-origin'}).then(response=>response.json()).then(result=>{if(result.ok)show(result.marca)});
form.logo.onchange=()=>{const file=form.logo.files[0];if(file){logoPreview.src=URL.createObjectURL(file);logoPreview.hidden=false}};
form.favicon.onchange=()=>{const file=form.favicon.files[0];faviconState.textContent=file?'Nuevo favicon seleccionado: '+file.name:'Sin favicon nuevo seleccionado.'};
form.oninput=()=>{namePreview.textContent=form.nombre_plataforma.value;institutionPreview.textContent=form.nombre_establecimiento.value+' — '+form.subtitulo.value};
form.onsubmit=async event=>{
    event.preventDefault();
    message.textContent='Guardando…';
    try{
        const response=await window.simceAuthenticatedFetch('personalizacion_api.php',{method:'POST',body:new FormData(form)});
        const result=await response.json();
        message.textContent=result.ok?'Personalización guardada correctamente.':result.error;
        if(result.ok){form.logo.value='';form.favicon.value='';show(result.marca)}
    }catch(error){message.textContent=error.message||'No se pudo guardar la personalización.'}
};
</script>
</body>
</html>
