import assert from "node:assert/strict";
import {readFileSync} from "node:fs";
import vm from "node:vm";

const code=readFileSync("public/js/chat_live_updates.js","utf8");
let clock=0,online=true,visible="visible",busy=false,version="v1";
let nextId=1,stopped=false,networkCalls=0,messages=0,lists=0;
const timers=new Map(),listeners=new Map();
const on=(name,fn)=>listeners.set(name,fn);
const off=(name,fn)=>{if(listeners.get(name)===fn)listeners.delete(name);};
const document={
  get visibilityState(){return visible;},
  addEventListener:on,removeEventListener:off
};
const window={
  setTimeout(fn,delay){const id=nextId++;timers.set(id,{fn,delay});return id;},
  clearTimeout(id){timers.delete(id);},
  addEventListener:on,removeEventListener:off,
  location:{replace(){throw Error("Não deveria redirecionar.");}}
};
const navigator={get onLine(){return online;}};
const fetch=async(url,options)=>{
  networkCalls++;
  assert.match(url,/\/api\/chat\/sync\.php\?conversationId=25$/);
  assert.equal(options.credentials,"same-origin");
  assert.equal(options.cache,"no-store");
  return {ok:true,status:200,json:async()=>({success:true,version})};
};
const fakeDate={now:()=>clock};
vm.runInNewContext(code,{window,document,navigator,fetch,Date:fakeDate,console}, {timeout:1500});
const sync=window.PrimeWayChatLiveUpdates;
assert.equal(typeof sync.start,"function");
const handle=sync.start({
  getActiveConversationId:()=>25,
  refreshMessages:async()=>{messages++;return true;},
  refreshConversations:async()=>{lists++;return true;},
  isBusy:()=>busy
});
async function tick(){
  const [id,item]=[...timers].at(0)||[];
  assert.ok(id, "Timer de sincronização não criado");
  timers.delete(id);
  clock+=item.delay;
  await item.fn();
}
await tick();
assert.equal(networkCalls,1);
assert.equal(messages,1);
assert.equal(lists,0);
await tick();
assert.equal(networkCalls,2);
assert.equal(messages,1,"Não recarregar mensagens se versão não mudou.");
version="v2";
await tick();
assert.equal(messages,2);
assert.equal(lists,1,"Lista sincronizada com nova mensagem.");
visible="hidden";
await tick();
assert.equal(networkCalls,3,"Aba oculta não consulta o servidor.");
visible="visible";
busy=true;
await tick();
assert.equal(networkCalls,3,"Não consultar durante um envio.");
busy=false;
online=false;
await tick();
assert.equal(networkCalls,3,"Sem conexão não consultar.");
online=true;
version="v3";
const wake=listeners.get("online");
assert.ok(wake);
wake();
await new Promise(resolve=>setImmediate(resolve));
assert.equal(messages,3,"Reconexão deve atualizar a conversa imediatamente.");
assert.ok(lists>=2,"Reconexão também atualiza a lista.");
handle.stop();
assert.equal(timers.size,0);
assert.equal(listeners.has("pagehide"),false);
console.log("Agendador: mudança, ociosidade, nova mensagem, offline, pausa e reconexão OK.");
