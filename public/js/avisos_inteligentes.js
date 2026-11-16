(function(){
"use strict";

// Sem permissões do navegador, serviços externos ou conteúdo de mensagens privadas.
// A rotina reutiliza os contadores da navegação para não duplicar consultas.
const PULSE_URL="../api/notificacoes/pulso.php";
const ROLE_LINKS={
    admin:{notifications:"notificacoes.html",chat:"admin_chat.html"},
    professor:{notifications:"professor_notificacoes.html",chat:"professor_chat.html"},
    aluno:{notifications:"aluno_notificacoes.html",chat:"aluno_chat.html"},
    responsavel:{notifications:"responsavel_notificacoes.html",chat:"responsavel_chat.html"},
    secretaria:{notifications:"secretaria_notificacoes.html",chat:"secretaria_chat.html"}
};
const seen=new Map();
let popupTimer=null;
let pulseBusy=false;
let pulseTimer=null;

function putCounts(userId,counts){
    const key="pw:avisos:"+String(userId);
    seen.set(key,counts);
    try{sessionStorage.setItem(key,JSON.stringify(counts));}catch{}
}
function readCounts(userId){
    const key="pw:avisos:"+String(userId);
    if(seen.has(key))return seen.get(key);
    try{
        const value=sessionStorage.getItem(key);
        if(value){
            const parsed=JSON.parse(value);
            if(Number.isFinite(parsed.notifications)&&Number.isFinite(parsed.messages))return parsed;
        }
    }catch{}
    return null;
}
function createLink(label,href){
    const link=document.createElement("a");
    link.href=href;
    link.textContent=label;
    link.className="pw-intelligent-link";
    return link;
}
function dismiss(){
    const node=document.querySelector("#primewayIntelligentNotice");
    if(node)node.remove();
    if(popupTimer!==null){clearTimeout(popupTimer);popupTimer=null}
}
function showAlert(role,newNotifications,newMessages){
    const paths=ROLE_LINKS[role];
    if(!paths)return;
    dismiss();
    const element=document.createElement("section");
    element.id="primewayIntelligentNotice";
    element.className="pw-intelligent-notice";
    element.setAttribute("role","status");
    element.setAttribute("aria-live","polite");
    const title=document.createElement("strong");
    title.textContent=newNotifications>0&&newMessages>0
        ?"Novidades no PrimeWay"
        :newNotifications>0?"Novas notificações":"Novas mensagens no chat";
    const description=document.createElement("p");
    const segments=[];
    if(newNotifications>0)segments.push(newNotifications===1?"1 nova notificação":newNotifications+" novas notificações");
    if(newMessages>0)segments.push(newMessages===1?"1 mensagem não lida a mais":newMessages+" mensagens não lidas a mais");
    description.textContent=segments.join(" e ")+".";
    const controls=document.createElement("div");
    controls.className="pw-intelligent-actions";
    if(newNotifications>0)controls.append(createLink("Ver notificações",paths.notifications));
    if(newMessages>0)controls.append(createLink("Abrir chat",paths.chat));
    const close=document.createElement("button");
    close.type="button";
    close.className="pw-intelligent-close";
    close.setAttribute("aria-label","Dispensar aviso");
    close.textContent="×";
    close.addEventListener("click",dismiss);
    element.append(title,description,controls,close);
    document.body.append(element);
    popupTimer=setTimeout(dismiss,12000);
}
function updateAdminBadge(selector,count,label){
    const link=document.querySelector(selector);
    if(!link)return;
    let badge=link.querySelector('[data-primeway-intelligent-badge="1"]');
    if(!badge){
        badge=document.createElement("span");
        badge.className="nav-badge";
        badge.dataset.primewayIntelligentBadge="1";
        link.append(badge);
    }
    badge.hidden=count<1;
    badge.textContent=count>99?"99+":String(count);
    if(count>0)link.setAttribute("title",String(count)+" "+label);
    else link.removeAttribute("title");
}
function processCounts(payload){
    if(!payload||!ROLE_LINKS[payload.role])return;
    const userId=Number(payload.viewerId);
    if(!Number.isSafeInteger(userId)||userId<1)return;
    const counts={
        notifications:Math.max(0,Number(payload.unreadNotifications)||0),
        messages:Math.max(0,Number(payload.unreadMessages??payload.unreadChat)||0)
    };
    if(payload.role==="admin"){
        updateAdminBadge('.sidebar-menu a[href="notificacoes.html"]',counts.notifications,"notificações não lidas");
        updateAdminBadge('.sidebar-menu a[href="admin_chat.html"]',counts.messages,"mensagens não lidas");
    }
    const prev=readCounts(userId);
    putCounts(userId,counts);
    if(!prev||document.visibilityState!=="visible")return;
    const newNotifications=Math.max(0,counts.notifications-prev.notifications);
    const newMessages=Math.max(0,counts.messages-prev.messages);
    if(newNotifications>0||newMessages>0)showAlert(payload.role,newNotifications,newMessages);
}
async function getPulse(){
    if(pulseBusy||document.hidden||navigator.onLine===false)return;
    pulseBusy=true;
    try{
        const response=await fetch(PULSE_URL,{
            credentials:"same-origin",cache:"no-store",
            headers:{Accept:"application/json"}
        });
        if(!response.ok)return;
        const data=await response.json();
        if(data?.success)processCounts(data);
    }catch{
        // Rede ou sessão temporariamente indisponíveis; sem alertas falsos.
    }finally{pulseBusy=false}
}
document.addEventListener("DOMContentLoaded",()=>{
    // Professor, aluno, responsável e secretaria já consultam /navegacao.php.
    // Na página compartilhada de Perfil e nas telas admin, usamos resumo privado.
    const common=Boolean(window.PrimeWayProfessor||window.PrimeWayAluno||
        window.PrimeWayResponsavel||window.PrimeWaySecretaria);
    window.addEventListener("primeway:navigation-counts",event=>processCounts(event.detail));
    if(!common){
        void getPulse();
        pulseTimer=setInterval(getPulse,60000);
    }
    window.addEventListener("online",()=>{if(!common)void getPulse()});
    document.addEventListener("visibilitychange",()=>{
        if(!document.hidden&&!common)void getPulse();
    });
    window.addEventListener("pagehide",()=>{
        dismiss();
        if(pulseTimer!==null)clearInterval(pulseTimer);
    },{once:true});
});
})();
