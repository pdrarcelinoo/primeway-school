document.addEventListener("DOMContentLoaded",async()=>{
"use strict";
const $=selector=>document.querySelector(selector);
const ROLES={admin:"Administrador",professor:"Professor(a)",aluno:"Aluno(a)",responsavel:"Responsável",secretaria:"Secretaria"};
const HOMES={admin:"dashboard.html",professor:"professor.html",aluno:"aluno_portal.html",responsavel:"responsavel.html",secretaria:"secretaria.html"};
const endpoint="../api/perfil/index.php",photoEndpoint="../api/perfil/foto.php";
const status=$("#profileStatus"),form=$("#profileForm"),photoForm=$("#photoForm"),photo=$("#profilePhoto");
const btnSave=$("#saveProfile"),btnPhoto=$("#savePhoto");
let csrf="",hasPhoto=false,preview=null;
const message=(msg,kind="")=>{status.textContent=msg;status.dataset.kind=kind};
function setPhoto(url){
 photo.hidden=!url;$("#profileInitials").hidden=!!url;
 if(url)photo.src=url;else photo.removeAttribute("src");
}
photo.onerror=()=>setPhoto(null);
async function request(url,options={}){
 const r=await fetch(url,{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json",...(options.method?{"X-CSRF-Token":csrf}:{})},...options});
 const data=await r.json().catch(()=>({}));
 if(!r.ok||data.success===false)throw new Error(data.message||"Não foi possível concluir a solicitação.");
 return data;
}
function update(profile){
 $("#profileName").value=profile.name||"";
 $("#profilePhone").value=profile.phone||"";
 $("#profileContactEmail").value=profile.contactEmail||"";
 $("#profileLogin").value=profile.loginEmail||"";
 $("#profileType").value=ROLES[profile.role]||profile.role;
 $("#profileRole").textContent=ROLES[profile.role]||profile.role;
 $("#profileDisplayName").textContent=profile.name||"Meu perfil";
 $("#profileInitials").textContent=(profile.name||"P").trim().split(/\s+/).slice(0,2).map(x=>x[0]).join("").toLocaleUpperCase("pt-BR");
 $("#profileHome").href=HOMES[profile.role]||"chat.html";
 hasPhoto=!!profile.hasPhoto;
 $("#removePhoto").hidden=!hasPhoto;
 setPhoto(hasPhoto?photoEndpoint+"?v="+Date.now():null);
}
try{
 const data=await request(endpoint);
 csrf=data.csrfToken||"";
 update(data.profile);
 message("");
}catch(error){message(error.message,"error");form.querySelectorAll("input,button").forEach(x=>x.disabled=true);photoForm.querySelectorAll("input,button").forEach(x=>x.disabled=true);return}

form.addEventListener("submit",async event=>{
 event.preventDefault();if(!form.reportValidity())return;
 btnSave.disabled=true;message("Salvando alterações…");
 try{
 const data=await request(endpoint,{method:"POST",headers:{"Content-Type":"application/json","Accept":"application/json","X-CSRF-Token":csrf},
  body:JSON.stringify({name:$("#profileName").value,phone:$("#profilePhone").value,contactEmail:$("#profileContactEmail").value})});
 update(data.profile);message("Dados atualizados com sucesso.","success");
 window.dispatchEvent(new Event("primeway:profile-updated"));
 }catch(error){message(error.message,"error")}finally{btnSave.disabled=false}
});
$("#photoInput").addEventListener("change",()=>{
 const file=$("#photoInput").files?.[0];btnPhoto.disabled=!file;
 $("#photoSelection").textContent=file?file.name:"Nenhuma foto selecionada.";
 if(preview){URL.revokeObjectURL(preview);preview=null}
 if(file&&file.type.startsWith("image/")){preview=URL.createObjectURL(file);setPhoto(preview)}
 else setPhoto(hasPhoto?photoEndpoint+"?v="+Date.now():null);
});
photoForm.addEventListener("submit",async event=>{
 event.preventDefault();const file=$("#photoInput").files?.[0];if(!file)return;
 if(!["image/jpeg","image/png"].includes(file.type)||file.size>2*1024*1024){message("Escolha uma foto JPG ou PNG de até 2 MB.","error");return}
 btnPhoto.disabled=true;message("Enviando sua foto…");
 try{
  const body=new FormData();body.set("photo",file);
  await request(photoEndpoint,{method:"POST",headers:{"Accept":"application/json","X-CSRF-Token":csrf},body});
  hasPhoto=true;$("#removePhoto").hidden=false;$("#photoInput").value="";$("#photoSelection").textContent="Nenhuma foto selecionada.";
  if(preview){URL.revokeObjectURL(preview);preview=null}
  setPhoto(photoEndpoint+"?v="+Date.now());message("Foto de perfil atualizada.","success");
  window.dispatchEvent(new Event("primeway:profile-updated"));
 }catch(error){message(error.message,"error");btnPhoto.disabled=false}
});
$("#removePhoto").addEventListener("click",async()=>{
 if(!hasPhoto)return;$("#removePhoto").disabled=true;
 try{
  await request(photoEndpoint,{method:"DELETE",headers:{"Accept":"application/json","X-CSRF-Token":csrf}});
  hasPhoto=false;setPhoto(null);$("#removePhoto").hidden=true;$("#photoInput").value="";
  $("#photoSelection").textContent="Nenhuma foto selecionada.";message("Foto removida.","success");
  window.dispatchEvent(new Event("primeway:profile-updated"));
 }catch(error){message(error.message,"error")}finally{$("#removePhoto").disabled=false}
});
// Navegação pessoal unificada gerencia o logout em todas as páginas pessoais.
window.addEventListener("pagehide",()=>{if(preview)URL.revokeObjectURL(preview)});
});
