/* Páginas pessoais usam o menu canônico do perfil autenticado.
   A autorização permanece exclusivamente no roteador PHP e nas APIs. */
(function(){
"use strict";
const scope=["perfil.html","preferencias.html","privacidade.html"];
const page=location.pathname.split("/").pop();
if(!scope.includes(page))return;
function markCurrent(){
    const sidebar=document.querySelector("aside.sidebar");
    if(!sidebar)return;
    const nav=sidebar.querySelector(".sidebar-menu");
    if(!nav)return;
    for(const link of nav.querySelectorAll("a")){
        link.classList.remove("active");
        link.removeAttribute("aria-current");
    }
    const href=page==="perfil.html"?"perfil.html":"preferencias.html";
    const active=nav.querySelector('a[href="'+href+'"]');
    if(active){active.classList.add("active");active.setAttribute("aria-current","page");}
}
let logoutPending=false;
async function logout(){
    if(logoutPending)return;
    logoutPending=true;
    try{
        const response=await fetch("../api/auth/session.php",{
            credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}
        });
        const session=await response.json();
        if(!response.ok||!session?.authenticated||!session.csrfToken)throw Error("Sessão expirada.");
        const result=await fetch("../api/auth/logout.php",{
            method:"POST",credentials:"same-origin",cache:"no-store",
            headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-Token":session.csrfToken},
            body:"{}"
        });
        if(!result.ok)throw Error("Não foi possível encerrar a sessão.");
        location.replace("login.html");
    }catch(error){
        logoutPending=false;
        window.PrimeWayFeedback?.error(error.message||"Falha ao sair.");
    }
}
function mount(){
    markCurrent();
    // Delegação: módulos de Professor/Secretaria podem reconstruir a sidebar.
    document.addEventListener("click",event=>{
        const button=event.target.closest?.("aside.sidebar #logoutButton");
        if(!button)return;
        event.preventDefault();
        void logout();
    });
    const sidebar=document.querySelector("aside.sidebar");
    if(sidebar){
        const observer=new MutationObserver(markCurrent);
        observer.observe(sidebar,{childList:true,subtree:true});
        window.addEventListener("pagehide",()=>observer.disconnect(),{once:true});
    }
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});
else mount();
})();
