function simceInstallPolicy(policy, context) {
    'use strict';
    if(window.simcePolicyInstalled)return;
    window.simcePolicyInstalled=true;
    let used=Number(context.used||0), saved=false;
    const root=document.documentElement;
    const style=document.createElement('style');
    style.textContent=`html.simce-pending #result,html.simce-pending .q-block,html.simce-pending #results-panel,html.simce-pending #download-section,html.simce-pending .question-card,
html.simce-no-results #result,html.simce-no-results #results-panel,html.simce-no-results #download-section,
html.simce-no-feedback #download-section,html.simce-no-feedback .btn-rev,html.simce-no-feedback.simce-finished .q-block,html.simce-no-feedback #btn-informe,html.simce-no-feedback #btn-download-results,html.simce-no-feedback #btn-send-email,
html.simce-no-feedback.simce-finished .question-card,html.simce-no-retry #btn-reintentar{display:none!important}`;
    document.head.appendChild(style);
    const feedback=policy.feedback && policy.results && policy.finish!=='return';
    root.classList.toggle('simce-no-feedback',!feedback);
    root.classList.toggle('simce-no-results',!policy.results || policy.finish==='return');
    function retryAllowed(){return !policy.attempts || used<policy.attempts;}
    function updateRetry(){root.classList.toggle('simce-no-retry',!retryAllowed());}
    updateRetry();
    let notice;
    function showNotice(text){
        if(!notice){notice=document.createElement('div');notice.setAttribute('role','status');notice.style.cssText='position:relative;z-index:100;background:#eff6ff;color:#172554;padding:20px;margin:16px;border-radius:12px;font:16px Arial';document.body.prepend(notice);}
        notice.replaceChildren(document.createTextNode(text));return notice;
    }
    window.simceBegin=function(){root.classList.add('simce-pending','simce-finished');showNotice('Guardando resultado…').scrollIntoView({block:'center'});};
    window.simceFinish=async function(result){
        let response;
        if(context.testId){
            while(!saved){
                try {
                    const res=await fetch('resultados_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({},result,{pruebaId:context.testId,token:context.token}))});
                    response=await res.json();if(!res.ok||!response.ok)throw new Error(response.error||'No se pudo guardar el resultado.');
                    saved=true;used=Number(response.intentos_usados);policy=Object.assign(policy,response.policy||{});
                } catch(error){
                    await new Promise(resolve=>{const box=showNotice(error.message+' Tus respuestas siguen en esta página. ');const btn=document.createElement('button');btn.textContent='Reintentar guardado';btn.onclick=resolve;box.appendChild(btn);});
                }
            }
        } else {used++;saved=true;}
        root.classList.toggle('simce-no-feedback',!policy.feedback || !policy.results || policy.finish==='return');
        root.classList.toggle('simce-no-results',!policy.results || policy.finish==='return');
        root.classList.remove('simce-pending');updateRetry();
        if(policy.finish==='return' && context.testId){location.replace('portal_estudiante.php');return false;}
        const box=showNotice('Prueba finalizada. '+(context.testId?'El resultado quedó guardado.':''));
        if(context.testId){const link=document.createElement('a');link.href='portal_estudiante.php';link.textContent=' Volver a mis pruebas';box.appendChild(link);}
        if((!policy.results || policy.finish==='return' || !document.getElementById('btn-reintentar')) && retryAllowed()){const button=document.createElement('button');button.textContent='Realizar nuevamente';button.onclick=()=>{const retry=document.getElementById('btn-reintentar');if(retry)retry.click();else if(context.testId)location.reload();};box.appendChild(button);}
        return policy.results && policy.finish!=='return';
    };
    document.addEventListener('click',function(event){
        const button=event.target.closest('button');if(!button)return;
        const feedbackButton=button.classList.contains('btn-rev') || ['btn-informe','btn-download-results','btn-send-email'].includes(button.id);
        if(feedbackButton && (!saved || !policy.feedback || !policy.results || policy.finish==='return')){event.preventDefault();event.stopImmediatePropagation();return;}
        if(button.id==='btn-reintentar'){
            if(!saved || !retryAllowed()){event.preventDefault();event.stopImmediatePropagation();return;}
            if(context.testId){event.preventDefault();event.stopImmediatePropagation();location.reload();return;}
            saved=false;root.classList.remove('simce-finished');if(notice)notice.remove();notice=null;
        }
    },true);
    document.addEventListener('DOMContentLoaded',function(){
        if(context.name){const input=document.getElementById('student-name');if(input){input.value=context.name;input.readOnly=true;}}
    });
}
