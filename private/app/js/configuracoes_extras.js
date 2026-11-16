/* Centro de Configurações. Escrita institucional somente pelo Administrador. */
(function(){
"use strict";
const role=document.documentElement.getAttribute("data-primeway-theme-role");
const page=location.pathname.split("/").pop();
const links={
admin:[["Alunos","alunos.html"],["Professores","professores.html"],["Turmas","turmas.html"],["Disciplinas","disciplinas.html"],["Calendário","calendario.html"],["Notificações","notificacoes.html"]],
secretaria:[["Alunos","secretaria_alunos.html"],["Professores","secretaria_professores.html"],["Responsáveis","secretaria_responsaveis.html"],["Turmas","secretaria_turmas.html"],["Matrículas","secretaria_matriculas.html"],["Disciplinas","secretaria_disciplinas.html"],["Calendário","secretaria_calendario.html"],["Avisos","secretaria_notificacoes.html"]],
professor:[["Notas","professor_notas.html"],["Frequência","professor_frequencia.html"],["Atividades","professor_atividades.html"],["Calendário","professor_calendario.html"]],
aluno:[["Minhas notas","aluno_notas.html"],["Frequência","aluno_frequencia.html"],["Atividades","aluno_atividades.html"],["Calendário","aluno_calendario.html"]],
responsavel:[["Notas dos vinculados","responsavel_notas.html"],["Frequência","responsavel_frequencia.html"],["Calendário","responsavel_calendario.html"]]
};
const access=[
'<div class="pw-settings-grid">',
'<label class="pw-settings-field">Tamanho do texto<select data-pw-access="fontSize">',
'<option value="normal">Padrão</option><option value="large">Ampliado (110%)</option>',
'<option value="extra">Extra ampliado (120%)</option></select></label>',
'<div class="pw-settings-field"><label class="pw-settings-option">',
'<input type="checkbox" data-pw-access="highContrast"> Alto contraste</label>',
'<label class="pw-settings-option"><input type="checkbox" data-pw-access="reduceMotion"> Reduzir animações</label>',
'</div></div>'
].join("");
const security=[
'<div class="pw-settings-meta"><span class="pw-settings-status" data-pw-https-status>',
'Verificando HTTPS…</span><span class="pw-settings-status is-secure">Permissões protegidas</span></div>'
].join("");
function addLinks(container,kind){
if(!container)return;
for(const [name,url] of links[kind]||[]){
const node=document.createElement("a");node.href=url;node.textContent=name;container.append(node);
}
}
function htmlAdmin(){
return [
'<header class="pw-settings-hub-header"><div><span class="section-label">GESTÃO INSTITUCIONAL</span>',
'<h2>Segurança, integrações e recursos</h2>',
'<p>Configurações institucionais do Administrador. As permissões continuam aplicadas pelas APIs.</p></div></header>',
'<section class="pw-settings-panel"><h3>Segurança e privacidade</h3>',security,
'<p>HTTPS é fornecido pela hospedagem; o estado exibido corresponde à conexão atual, sem permitir modificar certificados por esta página.</p>',
'<table class="pw-settings-table"><thead><tr><th>Perfil</th><th>Permissões escolares</th></tr></thead><tbody>',
'<tr><td>Administrador</td><td>Gestão acadêmica e institucional.</td></tr>',
'<tr><td>Secretaria</td><td>Cadastros, turmas, matrículas, calendário e comunicados, sem configurações técnicas.</td></tr>',
'<tr><td>Professor</td><td>Atividades, turmas, avaliações e frequência conforme seus vínculos.</td></tr>',
'<tr><td>Aluno</td><td>Suas próprias notas, atividades, dados e calendário.</td></tr>',
'<tr><td>Responsável</td><td>Dados dos alunos vinculados, sem acesso a terceiros.</td></tr>',
'</tbody></table>',
'<p class="pw-settings-note">A matriz é informativa: editar permissões no servidor exige revisão de segurança.</p>',
'<div class="pw-settings-grid"><label class="pw-settings-field">Contato de privacidade',
'<input id="pwPrivacyEmail" type="email" maxlength="190" placeholder="privacidade@escola.com.br"></label>',
'<div class="pw-settings-field"><span>LGPD e dados de menores</span><a href="privacidade.html">Consultar aviso de privacidade</a>',
'<small class="pw-settings-note">A escola deve validar juridicamente seu aviso definitivo.</small></div>',
'<label class="pw-settings-field full">Aviso complementar da instituição',
'<textarea id="pwPrivacyNotice" maxlength="1400" placeholder="Procedimentos para exercer direitos sobre dados pessoais."></textarea>',
'</label></div></section>',
'<section class="pw-settings-panel"><h3>Integrações e Secretaria Virtual</h3>',
'<p>Portais de notas, frequência, matrículas e documentos acadêmicos já possuem acesso por perfil.</p>',
'<nav class="pw-settings-links" id="pwAdminModuleLinks"></nav>',
'<div class="pw-settings-meta"><span class="pw-settings-status is-pending">PIX, cartão e boleto: não integrados</span>',
'<span class="pw-settings-status is-pending">Formulário público de interesse: não integrado</span></div>',
'<p class="pw-settings-note">Pagamentos dependem de um provedor externo e de credenciais seguras da escola. Nenhuma cobrança é iniciada nesta tela.</p>',
'</section>',
'<section class="pw-settings-panel"><h3>Configurações técnicas e SEO</h3>',
'<div class="pw-settings-meta"><span class="pw-settings-status is-secure">Layout responsivo</span>',
'<span class="pw-settings-status is-secure">Cache e compressão HTTP dos arquivos públicos</span></div>',
'<p class="pw-settings-note">Páginas de alunos e APIs usam acesso privado e não são indexadas pelo Google.</p>',
'<div class="pw-settings-grid"><label class="pw-settings-field">Título da página pública',
'<input id="pwSeoTitle" maxlength="70" placeholder="Nome da escola | Educação"></label>',
'<label class="pw-settings-field full">Meta-descrição da página pública',
'<textarea id="pwSeoDescription" maxlength="160" placeholder="Descrição da instituição e localização."></textarea>',
'</label></div></section>',
'<section class="pw-settings-panel"><h3>Calendário e avisos urgentes</h3>',
'<p>Um aviso geral aparece para todos os perfis. Para um público específico, publique pela Central de Notificações.</p>',
'<label class="pw-settings-field">Mensagem de aviso urgente',
'<textarea id="pwBannerText" maxlength="320" placeholder="Ex.: Aulas suspensas nesta sexta-feira. Consulte a Secretaria."></textarea></label>',
'<label class="pw-settings-option"><input type="checkbox" id="pwBannerEnabled"> Ativar aviso geral até que seja desativado</label>',
'<nav class="pw-settings-links"><a href="calendario.html">Gerenciar calendário</a>',
'<a href="notificacoes.html">Publicar notificações</a></nav></section>',
'<section class="pw-settings-panel"><h3>Acessibilidade pessoal</h3>',
'<p>As preferências de leitura valem apenas para sua conta neste navegador.</p>',access,'</section>',
'<div class="pw-settings-submit"><button type="button" id="pwSaveAdvancedSettings">',
'Salvar privacidade, SEO e aviso geral</button>',
'<span id="pwAdvancedFeedback" class="pw-settings-feedback" role="status" aria-live="polite"></span></div>'
].join("");
}
function htmlPersonal(){
return [
'<section class="pw-settings-panel"><h3>Acessibilidade</h3>',access,
'<p class="pw-settings-note">As alterações são imediatas e individuais neste navegador.</p></section>',
'<section class="pw-settings-panel"><h3>Segurança e privacidade</h3>',security,
'<p>O acesso aos dados escolares é limitado ao seu perfil e aos vínculos autorizados.</p>',
'<p class="pw-settings-note">Cookies de sessão necessários ao login e à segurança são usados. Não há cookies publicitários configurados.</p>',
'<nav class="pw-settings-links"><a href="privacidade.html">Aviso de privacidade e LGPD</a>',
'<a href="perfil.html">Meus dados</a></nav></section>',
'<section class="pw-settings-panel"><h3>Recursos escolares</h3>',
'<p>Atalhos apenas para páginas autorizadas ao seu perfil.</p>',
'<nav id="pwPersonalModuleLinks" class="pw-settings-links"></nav></section>'
].join("");
}
function setHttpsStatus(){
const on=location.protocol==="https:";
document.querySelectorAll("[data-pw-https-status]").forEach(el=>{
el.textContent=on?"HTTPS ativo nesta conexão":"HTTPS não detectado nesta conexão";
el.classList.toggle("is-secure",on);
el.classList.toggle("is-pending",!on);
});
}
async function loadAdmin(hub){
const info=hub.querySelector("#pwAdvancedFeedback");
try{
const result=await fetch("../api/configuracoes/portal.php",{credentials:"same-origin",cache:"no-store"});
const data=await result.json();
if(!result.ok||!data?.success)throw Error(data?.message||"Falha ao consultar configurações.");
const s=data.settings||{};
for(const [id,key] of [
["pwPrivacyEmail","privacyEmail"],["pwPrivacyNotice","privacyNotice"],
["pwSeoTitle","seoTitle"],["pwSeoDescription","seoDescription"],
["pwBannerText","bannerText"]
])hub.querySelector("#"+id).value=String(s[key]||"");
hub.querySelector("#pwBannerEnabled").checked=s.bannerEnabled===true;
info.textContent="";
}catch(err){info.textContent=err.message;}
hub.querySelector("#pwSaveAdvancedSettings").addEventListener("click",()=>void saveAdmin(hub));
}
async function saveAdmin(hub){
const btn=hub.querySelector("#pwSaveAdvancedSettings");
const info=hub.querySelector("#pwAdvancedFeedback");
const value=id=>hub.querySelector("#"+id).value.trim();
const payload={
bannerEnabled:hub.querySelector("#pwBannerEnabled").checked,
bannerText:value("pwBannerText"),privacyEmail:value("pwPrivacyEmail"),
privacyNotice:value("pwPrivacyNotice"),seoTitle:value("pwSeoTitle"),
seoDescription:value("pwSeoDescription")
};
if(payload.bannerEnabled&&!payload.bannerText){
info.textContent="Informe o aviso urgente antes de ativá-lo.";
hub.querySelector("#pwBannerText").focus();return;
}
const email=hub.querySelector("#pwPrivacyEmail");
if(!email.checkValidity()){email.reportValidity();return;}
btn.disabled=true;info.textContent="Salvando...";
try{
const ses=await fetch("../api/auth/session.php",{credentials:"same-origin",cache:"no-store"});
const session=await ses.json();
if(!ses.ok||!session.authenticated||session.usuario?.perfil!=="admin"||!session.csrfToken){
throw Error("Sessão de Administrador expirada.");
}
const result=await fetch("../api/configuracoes/portal_salvar.php",{
method:"POST",credentials:"same-origin",cache:"no-store",
headers:{"Content-Type":"application/json","Accept":"application/json","X-CSRF-Token":session.csrfToken},
body:JSON.stringify(payload)
});
const data=await result.json();
if(!result.ok||!data?.success)throw Error(data?.message||"Não foi possível salvar.");
info.textContent="Preferências institucionais salvas com sucesso.";
window.PrimeWayPortalNotice?.refresh();
}catch(err){info.textContent=err.message||"Falha ao salvar.";}finally{btn.disabled=false;}
}
function mount(){
let hub=null;
if(page==="configuracoes.html"&&role==="admin"){
const form=document.querySelector("#settingsForm");
if(form){hub=document.createElement("section");hub.className="pw-settings-hub";
hub.setAttribute("aria-label","Segurança, integrações e recursos");
hub.innerHTML=htmlAdmin();form.after(hub);
addLinks(hub.querySelector("#pwAdminModuleLinks"),"admin");void loadAdmin(hub);}
}else if(page==="preferencias.html"&&links[role]){
const target=document.querySelector(".pw-preferences-inner");
if(target){hub=document.createElement("section");hub.className="pw-settings-hub";
hub.innerHTML=htmlPersonal();target.append(hub);
addLinks(hub.querySelector("#pwPersonalModuleLinks"),role);}
}
if(hub)document.dispatchEvent(new Event("primeway:settings-created"));
setHttpsStatus();
}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",mount,{once:true});
else mount();
})();
