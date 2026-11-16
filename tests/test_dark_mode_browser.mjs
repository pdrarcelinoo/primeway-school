import assert from "node:assert/strict";
import vm from "node:vm";
import {readFileSync} from "node:fs";

const src=readFileSync("public/js/modo_noturno.js","utf8");
const memory=new Map();
function page({id,role="professor",initial=null}){
  const listeners=new Map();
  const dispatched=[];
  const toggles=[];
  const back={href:""};
  const root={
    dataset:{},style:{},
    getAttribute(key){
      if(key==="data-primeway-theme-account")return id;
      if(key==="data-primeway-theme-role")return role;
      return null;
    }
  };
  const document={
    documentElement:root,readyState:"loading",
    addEventListener(name,fn){listeners.set("document:"+name,fn);},
    dispatchEvent(e){dispatched.push(e);},
    querySelector(s){if(s==="aside.sidebar")return null;if(s==="[data-primeway-preferences-back]")return back;return null;},
    querySelectorAll(s){return s.includes("toggle")?toggles:[];}
  };
  const window={
    localStorage:{
      getItem(k){return memory.get(k)??null;},
      setItem(k,v){memory.set(k,v);}
    },
    addEventListener(name,fn){listeners.set("window:"+name,fn);}
  };
  const CustomEvent=class{constructor(type,{detail}){this.type=type;this.detail=detail;}};
  vm.runInNewContext(src,{document,window,location:{pathname:"/pages/preferencias.html"},CustomEvent,MutationObserver:class{}},{timeout:1500});
  const toggle={
    checked:false,dataset:{},setAttribute(k,v){this[k]=v;},
    addEventListener(name,fn){this[name]=fn;}
  };
  toggles.push(toggle);
  listeners.get("document:DOMContentLoaded")?.();
  return {window,document,root,toggle,listeners,dispatched,back};
}
const first=page({id:"12",role:"aluno"});
assert.equal(first.root.dataset.primewayTheme,"light");
assert.equal(first.toggle.checked,false);
assert.equal(first.back.href,"aluno_portal.html");
first.toggle.checked=true;
first.toggle.change();
assert.equal(first.root.dataset.primewayTheme,"dark");
assert.equal(first.root.style.colorScheme,"dark");
assert.equal(memory.get("primeway:appearance:v1:12"),"dark");
assert.equal(first.dispatched.at(-1).detail.theme,"dark");

const another=page({id:"13",role:"secretaria"});
assert.equal(another.root.dataset.primewayTheme,"light","Não copiar preferências de outra conta.");
another.toggle.checked=true;
another.toggle.change();
const returnToFirst=page({id:"12",role:"aluno"});
assert.equal(returnToFirst.root.dataset.primewayTheme,"dark","Persistir ao navegar e reabrir.");
assert.equal(returnToFirst.toggle.checked,true);
returnToFirst.toggle.checked=false;
returnToFirst.toggle.change();
assert.equal(memory.get("primeway:appearance:v1:12"),"light");
assert.equal(memory.get("primeway:appearance:v1:13"),"dark","Não alterar a outra conta.");
memory.set("primeway:appearance:v1:12","dark");
returnToFirst.listeners.get("window:storage")({key:"primeway:appearance:v1:12"});
assert.equal(returnToFirst.root.dataset.primewayTheme,"dark","Sincronizar abas abertas.");
returnToFirst.listeners.get("window:storage")({key:"primeway:appearance:v1:13"});
assert.equal(returnToFirst.root.dataset.primewayTheme,"dark","Ignorar alteração de outra conta.");

for(const role of ["admin","professor","responsavel"]){
  const item=page({id:String(50+["admin","professor","responsavel"].indexOf(role)),role});
  assert.equal(typeof item.window.PrimeWayTheme.set,"function");
  item.window.PrimeWayTheme.set("dark");
  assert.equal(item.root.dataset.primewayTheme,"dark",role);
}
const bad=page({id:"not-a-real-account"});
assert.equal(bad.window.PrimeWayTheme,undefined);
console.log("Tema por conta: alternância instantânea, persistência, isolamento e sincronização entre abas OK.");
