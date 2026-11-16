/* Formularios da Secretaria: criar aluno/professor/responsavel e editar aluno/professor. */
(function(){
"use strict";
document.addEventListener("DOMContentLoaded",async()=>{
const button=document.querySelector("[data-secretaria-cadastro]");
const api=window.PrimeWaySecretaria;
if(!button||!api||!await api.ensureSecretary())return;
const role=button.dataset.secretariaCadastro;
if(!["aluno","professor","responsavel"].includes(role))return;
const student=role==="aluno",teacher=role==="professor",guardian=role==="responsavel";
const singular=student?"aluno":teacher?"professor":"responsável";
const endpoint=student?"../api/alunos/salvar.php":teacher?"../api/professores/salvar.php":"../api/responsaveis/salvar.php";
let editing=null,sending=false,loadingOptions=false;
const dialog=document.createElement("dialog");
dialog.className="pw-secretary-create-dialog";
dialog.setAttribute("aria-labelledby","pw-secretary-create-title");
const form=document.createElement("form");
form.id="pw-secretary-create-form";form.className="pw-secretary-create-form";
const header=document.createElement("div");header.className="pw-secretary-create-header";
const heading=document.createElement("div");
const eyebrow=document.createElement("span");eyebrow.className="pw-secretary-create-eyebrow";eyebrow.textContent="Secretaria • Gestão cadastral";
const title=document.createElement("h2");title.id="pw-secretary-create-title";
const subtitle=document.createElement("p");
subtitle.textContent="Dados protegidos, salvos no sistema e registrados na auditoria.";
heading.append(eyebrow,title,subtitle);
const close=document.createElement("button");close.type="button";close.className="pw-secretary-create-close";
close.setAttribute("aria-label","Fechar formulário");close.textContent="×";close.onclick=()=>dialog.close();
header.append(heading,close);
const grid=document.createElement("div");grid.className="pw-secretary-create-grid";
const fields=[
{name:"name",label:"Nome completo",type:"text",required:true,max:120,wide:true,autocomplete:"name"},
{name:"email",label:"E-mail de acesso",type:"email",required:true,max:190,autocomplete:"email"},
{name:"password",label:"Senha inicial",type:"password",required:true,min:8,max:200,autocomplete:"new-password"},
{name:"phone",label:"Telefone",type:"tel",max:30,autocomplete:"tel"},
{name:"document",label:"Documento",type:"text",max:30},
{name:"birthDate",label:"Data de nascimento",type:"date"}
];
if(student){
fields.push({name:"registration",label:"Número de matrícula",type:"text",required:true,max:50});
fields.push({name:"classId",label:"Turma",type:"select",wide:true});
fields.push({name:"status",label:"Situação",type:"select",values:[["ativo","Ativo"],["pendente","Pendente"],["inativo","Inativo"]]});
}else if(teacher){
fields.push({name:"registration",label:"Registro funcional",type:"text",max:50});
fields.push({name:"admissionDate",label:"Data de admissão",type:"date"});
fields.push({name:"status",label:"Situação",type:"select",editOnly:true,values:[["ativo","Ativo"],["inativo","Inativo"]]});
}else{
fields.push({name:"status",label:"Situação inicial",type:"select",values:[["ativo","Ativo"],["pendente","Pendente"],["inativo","Inativo"]]});
fields.push({name:"relationship",label:"Parentesco ou vínculo",type:"text",required:true,max:50});
fields.push({name:"studentIds",label:"Alunos vinculados (Ctrl para selecionar vários)",type:"multi",required:true,wide:true});
}
const refs=new Map();
for(const field of fields){
const label=document.createElement("label");label.className="pw-secretary-create-field"+(field.wide?" wide":"");label.textContent=field.label;
const input=document.createElement(["select","multi"].includes(field.type)?"select":"input");
input.name=field.name;input.id="pw-secretary-"+field.name;
if(!["select","multi"].includes(field.type))input.type=field.type;
if(field.type==="multi"){input.multiple=true;input.size=5;}
if(field.required)input.required=true;
if(field.min)input.minLength=field.min;
if(field.max)input.maxLength=field.max;
if(field.autocomplete)input.autocomplete=field.autocomplete;
for(const pair of field.values||[]){const o=document.createElement("option");o.value=pair[0];o.textContent=pair[1];input.append(o);}
label.append(input);grid.append(label);refs.set(field.name,{input,label,def:field});
}
if(guardian){
const group=document.createElement("div");group.className="pw-secretary-create-checks";
for(const [name,labelText] of [["authorizedPickup","Autoriza retirada"],["primaryContact","Contato principal"],["financial","Responsável financeiro"]]){
const label=document.createElement("label"),box=document.createElement("input");
box.type="checkbox";box.name=name;box.value="1";
label.append(box,document.createTextNode(labelText));group.append(label);
}
grid.append(group);
}
const hint=document.createElement("p");hint.className="pw-secretary-create-hint";
const error=document.createElement("p");error.className="pw-secretary-create-error";error.setAttribute("role","alert");error.hidden=true;
const actions=document.createElement("div");actions.className="pw-secretary-create-actions";
const cancel=document.createElement("button");cancel.type="button";cancel.className="pw-secretary-create-cancel";cancel.textContent="Cancelar";cancel.onclick=()=>dialog.close();
const submit=document.createElement("button");submit.type="submit";submit.className="secretary-primary-button";
actions.append(cancel,submit);form.append(header,grid,hint,error,actions);dialog.append(form);document.body.append(dialog);

function warn(message){error.textContent=String(message||"Não foi possível salvar o cadastro.");error.hidden=false;}
function choices(name,items){
const input=refs.get(name).input;input.replaceChildren();
for(const [id,name] of items){const o=document.createElement("option");o.value=String(id);o.textContent=name;input.append(o);}
return input;
}
async function loadChoices(record){
if(!student&&!guardian)return;
loadingOptions=true;submit.disabled=true;
try{
if(student){
const {response,data}=await api.request("../api/secretaria/turmas/index.php");
if(!response.ok||!data?.success)throw Error(data?.message||"Não foi possível consultar turmas.");
const current=Number(record?.class?.id||0);
const list=[["","Sem turma — matricular depois"]];
for(const cls of data.classes||[]){
if(cls.status!=="Ativa"&&Number(cls.id)!==current)continue;
if(Number(cls.studentCount)>=Number(cls.capacity)&&Number(cls.id)!==current)continue;
list.push([cls.id,String(cls.name||"Turma")+" • "+String(cls.series||"")+" • "+String(cls.shift||"")+" ("+cls.studentCount+"/"+cls.capacity+")"]);
}
choices("classId",list).value=current?String(current):"";
}
if(guardian){
const {response,data}=await api.request("../api/secretaria/alunos/index.php");
if(!response.ok||!data?.success)throw Error(data?.message||"Não foi possível consultar alunos.");
const list=(data.students||[]).map(item=>[item.id,String(item.name)+" • Matrícula "+String(item.registration)]);
const linked=new Set((record?.links||[]).filter(link=>link.active).map(link=>Number(link.studentId)));
const input=choices("studentIds",list);
for(const option of input.options)option.selected=linked.has(Number(option.value));
if(!list.length)warn("Cadastre um aluno antes de adicionar um responsável.");
}
}catch(e){warn(e.message);}finally{loadingOptions=false;submit.disabled=false;}
}
async function open(record=null){
editing=record?{...record}:null;
form.reset();error.hidden=true;error.textContent="";
title.textContent=(editing?"Editar cadastro de ":"Cadastrar ")+singular;
submit.textContent=(editing?"Salvar alterações de ":"Cadastrar ")+singular;
const pw=refs.get("password");pw.input.required=!editing;
pw.label.firstChild.textContent=editing?"Nova senha (opcional)":"Senha inicial";
for(const [name,field] of refs){
field.label.hidden=Boolean(field.def.editOnly&&!editing);
if(name==="password"||name==="classId"||name==="studentIds")continue;
if(editing){
const value=name==="status"&&student?(editing.studentStatus||"ativo"):editing[name];
field.input.value=value==null?"":String(value);
}
}
if(guardian){
const links=(editing?.links||[]).filter(link=>link.active);
if(editing)refs.get("relationship").input.value=String(links[0]?.relationship||"");
for(const name of ["authorizedPickup","primaryContact","financial"]){
const input=form.elements.namedItem(name);
if(input)input.checked=Boolean(links[0]?.[name]);
}
}
hint.textContent=editing?"Deixe a senha vazia para manter a atual. As alterações ficam registradas na auditoria."
:student?"A turma é opcional; a matrícula pode ser vinculada depois."
:teacher?"Vínculos com turmas e disciplinas são gerenciados separadamente."
:"Selecione pelo menos um aluno e informe o parentesco ou vínculo.";
dialog.showModal();refs.get("name").input.focus();
if(student||guardian)await loadChoices(editing);
}
button.addEventListener("click",()=>{void open();});
window.addEventListener("primeway:secretaria-editar",event=>{
if(!["aluno","professor","responsavel"].includes(role))return;
if(event.detail?.type!==role||!event.detail.record)return;
void open(event.detail.record);
});
dialog.addEventListener("close",()=>{editing=null;form.reset();error.hidden=true;error.textContent="";});

form.addEventListener("submit",async event=>{
event.preventDefault();
if(sending||loadingOptions||!form.reportValidity())return;
sending=true;submit.disabled=true;
const textBefore=submit.textContent;
submit.textContent="Salvando...";error.hidden=true;
try{
const payload=Object.fromEntries(new FormData(form).entries());
if(editing)payload.id=Number(editing.id);
if(student){payload.classId=payload.classId?Number(payload.classId):null;}
if(guardian){
const selected=[...refs.get("studentIds").input.selectedOptions].map(o=>Number(o.value)).filter(id=>Number.isInteger(id)&&id>0);
if(!selected.length)throw Error("Selecione ao menos um aluno.");
payload.studentIds=selected;
for(const field of ["authorizedPickup","primaryContact","financial"]){
payload[field]=Boolean(form.elements.namedItem(field)?.checked);
}
}
const wasEdit=Boolean(editing);
const {response,data}=await api.requestJson(endpoint,payload);
if(!response.ok||!data?.success)throw Error(data?.message||"Não foi possível salvar o cadastro.");
dialog.close();
window.dispatchEvent(new CustomEvent("primeway:secretaria-cadastro-criado",{detail:{type:role,edited:wasEdit}}));
window.PrimeWayFeedback?.success(data.message||"Cadastro salvo com sucesso.");
}catch(e){warn(e.message);window.PrimeWayFeedback?.error(e.message);}finally{
sending=false;submit.disabled=false;submit.textContent=textBefore;
}
});
});
})();