(function(){
  function setText(selector,value){document.querySelectorAll(selector).forEach(function(e){e.textContent=value;});}
  function setFavicon(value){
    if(!value)return;
    var icon=document.querySelector('link[rel~="icon"]')||document.createElement('link');
    icon.rel='icon';icon.href=value;
    if(!icon.parentNode)document.head.appendChild(icon);
  }
  fetch('personalizacion_api.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(x){if(!x.ok)return;var m=x.marca;
    document.title=m.nombre_plataforma+(document.body.dataset.brandPage?' · '+document.body.dataset.brandPage:'');
    setText('[data-brand="name"]',m.nombre_plataforma);setText('[data-brand="institution"]',m.nombre_establecimiento);setText('[data-brand="subtitle"]',m.subtitulo);setText('[data-brand="institution-subtitle"]',m.nombre_establecimiento+' — '+m.subtitulo);setText('[data-brand="welcome"]',m.saludo);setText('[data-brand="profile-prompt"]',m.seleccion_perfil);
    var logo=String(m.logo||'').trim();
    document.querySelectorAll('[data-brand-logo]').forEach(function(img){if(logo){img.src=logo;img.hidden=false;}else{img.removeAttribute('src');img.hidden=true;}});document.querySelectorAll('[data-brand-logo-fallback]').forEach(function(e){e.hidden=!!logo;});
    setFavicon(String(m.favicon||logo).trim());
  }).catch(function(){});
})();
