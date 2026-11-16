/* Turmas: criação e edição da Secretaria usando a API validada e auditada. */
(function(){
"use strict";
document.addEventListener("DOMContentLoaded",async()=>{
const api=window.PrimeWaySecretaria,add=document.querySelector("#newClassSecretaryButton");
if(!api||!add||!await api.ensureSecretary())return;
const dialog=document.createElement("dialog");dialog.className="pw-secretary-create-dialog";
dialog.innerHTML=[
'<form class="pw-secretary-create-form">',
'<div class="pw-secretary-create-header"><div><span class="pw-secretary-create-eyebrow">Secretaria • Gestão escolar</span>',
'<h2 id="pw-secretary-class-title">Nova turma</h2><p>Turmas e histórico preservados.</p></div>',
'<button type="button" data-close class="pw-secretary-create-close" aria-label="Fechar">×</button></div>',
'<div class="pw-secretary-create-grid">',
'<label class="pw-secretary-create-field wide">Nome da turma<input name="name" maxlength="50" required></label>',
'<label class="pw-secretary-create-field">Série ou ano<input name="grade" maxlength="30" required></label>',
'<label class="pw-secretary-create-field">Turno<select name="shift" required>',
'<option value="Manhã">Manhã</option><option value="Tarde">Tarde</option>',
'<option value="Integral">Integral</option></select></label>',
'<label class="pw-secretary-create-field">Sala<input name="room" maxlength="30"></label>',
'<label class="pw-secretary-create-field">Ano letivo<input name="schoolYear" type="number" min="2020" max="2100" required></label>',
'<label class="pw-secretary-create-field">Capacidade<input name="capacity" type="number" min="1" max="100" value="30" required></label>',
'<label class="pw-secretary-create-field">Situação<select name="status">',
'<option value="Ativa">Ativa</option><option value="Inativa">Inativa</option></select></label>',
'<label class="pw-secretary-create-field wide">Professor regente (opcional)',
'<select name="teacherId"><option value="">Sem professor regente</option></select></label></div>',
'<p class="pw-secretary-create-error" role="alert" hidden></p>',
'<div class="pw-secretary-create-actions">',
'<button type="button" data-close class="pw-secretary-create-cancel">Cancelar</button>',
'<button type="submit" class="secretary-primary-button">Salvar turma</button></div></form>'
].join("");
document.body.append(dialog);
const form=dialog.querySelector("form"),error=dialog.querySelector('[role="alert"]');
const save=dialog.querySelector('[type="submit"]');
let selected=null,busy=false,loading=false;
function showError(message){error.textContent=String(message);error.hidden=false;}
async function open(record=null){
if(busy||loading)return;
selected=record?.id||null;form.reset();error.hidden=true;error.textContent="";
dialog.querySelector("#pw-secretary-class-title").textContent=selected?"Editar turma":"Nova turma";
const values={name:record?.name||"",grade:record?.series||"",shift:record?.shift||"Manhã",
room:record?.room||"",capacity:record?.capacity||30,status:record?.status||"Ativa",
schoolYear:Number(document.querySelector("#schoolYearLabel")?.textContent)||""};
for(const [name,value] of Object.entries(values))form.elements.namedItem(name).value=value;
dialog.showModal();form.elements.namedItem("name").focus();loading=true;save.disabled=true;
try{
const {response,data}=await api.request("../api/secretaria/professores/index.php");
if(!response.ok||!data?.success)throw Error(data?.message||"Não foi possível consultar professores.");
const select=form.elements.namedItem("teacherId");
select.replaceChildren(new Option("Sem professor regente",""));
for(const teacher of data.teachers||[]){
if(teacher.status!=="ativo"&&Number(teacher.id)!==Number(record?.mainTeacher?.id))continue;
select.append(new Option(teacher.name,String(teacher.id)));
}
select.value=record?.mainTeacher?.id?String(record.mainTeacher.id):"";
}catch(e){showError(e.message);}finally{loading=false;save.disabled=false;}
}
add.addEventListener("click",()=>{void open();});
window.addEventListener("primeway:secretaria-editar-turma",e=>{if(e.detail?.record)void open(e.detail.record);});
for(const button of dialog.querySelectorAll("[data-close]"))button.addEventListener("click",()=>dialog.close());
dialog.addEventListener("close",()=>{form.reset();selected=null;error.hidden=true;error.textContent="";});
form.addEventListener("submit",async e=>{
e.preventDefault();if(busy||loading||!form.reportValidity())return;
const data=Object.fromEntries(new FormData(form).entries());
const payload={id:selected,name:data.name.trim(),grade:data.grade.trim(),shift:data.shift,
room:data.room.trim(),teacherId:data.teacherId?Number(data.teacherId):null,
schoolYear:Number(data.schoolYear),capacity:Number(data.capacity),status:data.status};
busy=true;save.disabled=true;error.hidden=true;
try{
const {response,data:result}=await api.requestJson("../api/turmas/salvar.php",payload);
if(!response.ok||!result?.success)throw Error(result?.message||"Não foi possível salvar turma.");
dialog.close();
window.dispatchEvent(new CustomEvent("primeway:secretaria-turma-salva"));
window.PrimeWayFeedback?.success(result.message||"Turma salva com sucesso.");
}catch(err){showError(err.message);window.PrimeWayFeedback?.error(err.message);}
finally{busy=false;save.disabled=false;}
});
});
})();