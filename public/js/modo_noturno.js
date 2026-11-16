/* Modo noturno pessoal do PRIMEWay, em todas as páginas autenticadas.
   A preferência é separada por conta e permanece neste navegador. */
(function(){
    "use strict";
    const doc=document.documentElement;
    const account=doc.getAttribute("data-primeway-theme-account")||"";
    const role=doc.getAttribute("data-primeway-theme-role")||"";
    if(!/^[1-9]\d*$/.test(account)||
       !["admin","professor","aluno","responsavel","secretaria"].includes(role))return;
    const key="primeway:appearance:v1:"+account;
    function read(){
        try{return window.localStorage.getItem(key)==="dark";}
        catch{return false;}
    }
    function setDark(enabled,persist=true){
        const dark=Boolean(enabled);
        doc.dataset.primewayTheme=dark?"dark":"light";
        doc.style.colorScheme=dark?"dark":"light";
        if(persist){
            try{window.localStorage.setItem(key,dark?"dark":"light");}
            catch{ /* Em navegação privada, o tema funciona até sair da página. */ }
        }
        for(const input of document.querySelectorAll("[data-primeway-theme-toggle]")){
            if(input.checked!==dark)input.checked=dark;
            input.setAttribute("aria-checked",String(dark));
        }
        for(const label of document.querySelectorAll("[data-primeway-theme-state]")){
            label.textContent=dark?"Modo noturno ativado":"Modo claro ativado";
        }
        document.dispatchEvent(new CustomEvent("primeway:theme-changed",{
            detail:{theme:dark?"dark":"light"}
        }));
    }
    // Executado no <head>, antes de pintar qualquer página: evita piscar em modo claro.
    setDark(read(),false);

    function addSettingsLink(){
        const nav=document.querySelector("aside.sidebar .sidebar-menu");
        if(!nav)return;
        const href=role==="admin"?"configuracoes.html":"preferencias.html";
        const page=location.pathname.split("/").pop();
        let anchor=nav.querySelector('a[href="preferencias.html"],a[href="configuracoes.html"]');
        if(!anchor){
            anchor=document.createElement("a");
            anchor.href=href;
            const icon=document.createElement("i");
            icon.className="fa-solid fa-gear";
            icon.setAttribute("aria-hidden","true");
            const title=document.createElement("span");
            title.textContent="Configurações";
            anchor.append(icon,title);
            const profileLink=nav.querySelector('a[href="perfil.html"]');
            if(profileLink)nav.insertBefore(anchor,profileLink);
            else nav.append(anchor);
        }
        // As Configurações institucionais do Admin são mantidas sem alteração.
        if(page==="preferencias.html"||page==="configuracoes.html"){
            anchor.classList.add("active");
            anchor.setAttribute("aria-current","page");
        }
    }

    function mount(){
        addSettingsLink();
        for(const input of document.querySelectorAll("[data-primeway-theme-toggle]")){
            if(input.dataset.themeBound==="1")continue;
            input.dataset.themeBound="1";
            input.checked=read();
            input.setAttribute("aria-checked",String(input.checked));
            input.addEventListener("change",()=>setDark(input.checked));
        }
        setDark(read(),false);
        const back=document.querySelector("[data-primeway-preferences-back]");
        if(back){
            back.href={
                admin:"dashboard.html",professor:"professor.html",
                aluno:"aluno_portal.html",responsavel:"responsavel.html",
                secretaria:"secretaria.html"
            }[role];
        }
        const watcher=document.querySelector("aside.sidebar");
        if(watcher){
            const observer=new MutationObserver(()=>{
                if(!watcher.querySelector('a[href="preferencias.html"],a[href="configuracoes.html"]')){
                    addSettingsLink();
                }
            });
            observer.observe(watcher,{childList:true,subtree:true});
            window.addEventListener("pagehide",()=>observer.disconnect(),{once:true});
        }
    }
    if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});
    else mount();
    window.addEventListener("storage",event=>{
        if(event.key===key)setDark(read(),false);
    });
    window.PrimeWayTheme=Object.freeze({
        get current(){return doc.dataset.primewayTheme||"light";},
        set(theme){setDark(theme==="dark");}
    });
})();
