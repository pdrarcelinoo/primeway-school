/* Aviso urgente institucional e informação de cookies de sessão.
   Não coleta dados pessoais, não usa analytics nem pede consentimento fictício
   para cookies estritamente necessários à autenticação. */
(function(){
"use strict";
const root=document.documentElement;
const id=root.getAttribute("data-primeway-theme-account");
if(!/^[1-9]\d*$/.test(id||""))return;
const endpoint="../api/configuracoes/portal.php";
let timer=null,lastSignature="";
function create(tag,cls,content){
    const node=document.createElement(tag);
    if(cls)node.className=cls;
    if(content!==undefined)node.textContent=content;
    return node;
}
function showCookies(){
    const key="pw:cookies-info:v1:"+id;
    try{if(localStorage.getItem(key)==="seen")return;}catch{}
    if(document.querySelector("#pwCookieInfo"))return;
    const box=create("section","pw-global-cookie-info");
    box.id="pwCookieInfo";box.setAttribute("aria-label","Informações sobre cookies");
    box.append(
        create("strong","", "Privacidade e cookies necessários"),
        create("p","", "O PrimeWay utiliza cookies de sessão indispensáveis ao login e à proteção da conta. Não há rastreamento publicitário nesta configuração.")
    );
    const link=create("a","pw-global-cookie-link","Consultar aviso de privacidade");
    link.href="privacidade.html";
    const ok=create("button","pw-global-cookie-ok","Entendi");
    ok.type="button";
    ok.addEventListener("click",()=>{
        try{localStorage.setItem(key,"seen");}catch{}
        box.remove();
    });
    const actions=create("div","pw-global-cookie-actions");
    actions.append(link,ok);
    box.append(actions);
    document.body.append(box);
}
function showBanner(enabled,text){
    const target=document.querySelector("#pwGlobalSchoolBanner");
    if(!enabled||!text){target?.remove();return;}
    if(target?.dataset.signature===text)return;
    target?.remove();
    const box=create("section","pw-global-urgent");
    box.id="pwGlobalSchoolBanner";box.dataset.signature=text;
    box.setAttribute("role","alert");
    const icon=create("i","fa-solid fa-triangle-exclamation");
    icon.setAttribute("aria-hidden","true");
    const body=create("div","pw-global-urgent-content");
    body.append(create("strong","", "Aviso importante da escola"),create("p","",text));
    const close=create("button","pw-global-urgent-close","×");
    close.type="button";close.setAttribute("aria-label","Fechar aviso");
    close.addEventListener("click",()=>box.remove());
    box.append(icon,body,close);
    document.body.prepend(box);
}
async function refresh(){
    if(document.hidden||navigator.onLine===false)return;
    try{
        const res=await fetch(endpoint,{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
        if(!res.ok)return;
        const data=await res.json();
        if(!data?.success)return;
        const banner=data.settings||{};
        const signature=JSON.stringify([banner.bannerEnabled,banner.bannerText]);
        if(signature!==lastSignature){
            lastSignature=signature;
            showBanner(banner.bannerEnabled,banner.bannerText);
        }
        document.querySelectorAll("[data-pw-privacy-contact]").forEach(node=>{
            node.textContent=banner.privacyEmail||"Procure a administração da escola";
        });
        document.querySelectorAll("[data-pw-privacy-notice]").forEach(node=>{
            node.textContent=banner.privacyNotice||"O tratamento de dados escolares deve respeitar a finalidade educativa, a segurança e o melhor interesse de crianças e adolescentes.";
        });
    }catch{ /* Falhas de rede não interrompem a navegação. */ }
}
function mount(){
    showCookies();
    void refresh();
    timer=window.setInterval(refresh,60000);
    window.addEventListener("online",refresh);
    document.addEventListener("visibilitychange",()=>{if(!document.hidden)void refresh();});
    window.addEventListener("pagehide",()=>{if(timer!==null)clearInterval(timer);},{once:true});
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});else mount();
window.PrimeWayPortalNotice=Object.freeze({refresh});
})();
