(function(){
  function setText(selector,value){document.querySelectorAll(selector).forEach(function(e){e.textContent=value;});}
  var school=new URLSearchParams(location.search).get('colegio')||'';
  fetch('personalizacion_v21_api.php'+(school?'?colegio='+encodeURIComponent(school):''),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(x){if(!x.ok)return;var m=x.marca;
    document.title=m.nombre_plataforma+(document.body.dataset.brandPage?' · '+document.body.dataset.brandPage:'');
    setText('[data-brand="name"]',m.nombre_plataforma);setText('[data-brand="institution"]',m.nombre_establecimiento);setText('[data-brand="subtitle"]',m.subtitulo);setText('[data-brand="institution-subtitle"]',m.nombre_establecimiento+' — '+m.subtitulo);setText('[data-brand="welcome"]',m.saludo);setText('[data-brand="profile-prompt"]',m.seleccion_perfil);
    if(m.logo){document.querySelectorAll('[data-brand-logo]').forEach(function(img){img.src=m.logo;img.hidden=false;});document.querySelectorAll('[data-brand-logo-fallback]').forEach(function(e){e.hidden=true;});}
    var iconUrl=m.favicon||m.logo;if(iconUrl){var icon=document.querySelector('link[rel~="icon"]')||document.createElement('link');icon.rel='icon';icon.href=iconUrl;if(!icon.parentNode)document.head.appendChild(icon);}
    if(!document.querySelector('.simce-version')){var version=document.createElement('div');version.className='simce-version';version.textContent='v'+(x.version||'2.1.0');version.style.cssText='position:fixed;right:10px;bottom:7px;z-index:99999;color:#64748b;font:600 12px Arial;background:rgba(255,255,255,.8);padding:3px 6px;border-radius:5px';document.body.appendChild(version);}
  }).catch(function(){});
})();
