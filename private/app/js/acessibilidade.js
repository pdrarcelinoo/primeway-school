/* Preferências de acessibilidade: individuais, sem permissões de gestão. */
(function(){
"use strict";
const root=document.documentElement;
const id=root.getAttribute("data-primeway-theme-account")||"";
const role=root.getAttribute("data-primeway-theme-role")||"";
if(!/^[1-9]\d*$/.test(id)||!["admin","secretaria","professor","aluno","responsavel"].includes(role))return;
const KEY="primeway:accessibility:v1:"+id;
const defaults={fontSize:"normal",highContrast:false,reduceMotion:false};
function read(){
    try{
        const s=JSON.parse(localStorage.getItem(KEY)||"{}");
        return {
            fontSize:["normal","large","extra"].includes(s.fontSize)?s.fontSize:"normal",
            highContrast:s.highContrast===true,
            reduceMotion:s.reduceMotion===true
        };
    }catch{return {...defaults};}
}
function apply(data,persist=false){
    const value={
        fontSize:["normal","large","extra"].includes(data.fontSize)?data.fontSize:"normal",
        highContrast:data.highContrast===true,
        reduceMotion:data.reduceMotion===true
    };
    root.dataset.pwFontSize=value.fontSize;
    root.dataset.pwHighContrast=value.highContrast?"true":"false";
    root.dataset.pwReduceMotion=value.reduceMotion?"true":"false";
    if(persist){
        try{localStorage.setItem(KEY,JSON.stringify(value));}catch{}
    }
    document.querySelectorAll('[data-pw-access="fontSize"]').forEach(node=>{node.value=value.fontSize;});
    for(const key of ["highContrast","reduceMotion"]){
        document.querySelectorAll('[data-pw-access="'+key+'"]').forEach(node=>{node.checked=value[key];});
    }
    return value;
}
// Preferência restaurada antes da pintura.
apply(read());
function mount(){
    const inputs=[...document.querySelectorAll("[data-pw-access]")];
    for(const input of inputs){
        if(input.dataset.pwAccessBound==="1")continue;
        input.dataset.pwAccessBound="1";
        input.addEventListener("change",()=>{
            const next=read();
            const key=input.dataset.pwAccess;
            next[key]=key==="fontSize"?input.value:input.checked;
            apply(next,true);
        });
    }
    apply(read());
}
document.addEventListener("primeway:settings-created",mount);
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});
else mount();
window.addEventListener("storage",ev=>{if(ev.key===KEY)apply(read());});
window.PrimeWayAccessibility=Object.freeze({get current(){return read();},update:settings=>apply({...read(),...settings},true)});
})();
