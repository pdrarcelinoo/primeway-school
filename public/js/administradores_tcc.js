document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  const LIST = '../api/administradores_tcc/index.php';
  const CREATE = '../api/administradores_tcc/criar.php';
  const CREATE_NEW = '../api/administradores_tcc/criar_novo.php';
  const RENAME = '../api/administradores_tcc/corrigir_nome.php';
  const UPDATE = '../api/administradores_tcc/alterar_acesso.php';
  const SESSION = '../api/auth/session.php';
  const allowed = new Set(['mizael', 'ana-julia', 'julia-maniero']);
  const status = document.querySelector('#pageStatus');
  const cards = document.querySelector('#accounts');
  const adminList = document.querySelector('#adminList');
  const pendingSection = document.querySelector('#pendingSection');
  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin', cache: 'no-store',
      headers: {Accept: 'application/json', ...(options.headers || {})},
      ...options
    });
    let data = null;
    try { data = await response.json(); } catch {}
    return {response, data};
  }
  function formField(form, text, type, value) {
    const label = document.createElement('label'); label.textContent = text;
    const input = document.createElement('input');
    input.type = type; input.required = true;
    if (value) input.value = value;
    if (type === 'email') {
      input.maxLength = 190;
      input.autocomplete = 'off';
    } else {
      input.minLength = 12; input.maxLength = 72;
      input.autocomplete = 'new-password';
    }
    const wrap = document.createElement('div'); wrap.className = 'field';
    wrap.append(label,input); form.append(wrap);
    return input;
  }
  // Só um Administrador autenticado pode trocar credenciais, depois de
  // confirmar sua própria senha. O sistema nunca exibe a senha antiga.
  function addEditControls(card, account) {
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'create-btn edit-toggle';
    toggle.textContent = 'Alterar e-mail ou senha';
    card.append(toggle);
    toggle.addEventListener('click', () => {
      const previous = card.querySelector('.edit-form');
      if (previous) {
        previous.replaceChildren();
        previous.remove();
        toggle.textContent = 'Alterar e-mail ou senha';
        return;
      }
      toggle.textContent = 'Cancelar alteração';
      const form = document.createElement('form');
      form.className = 'edit-form';
      form.autocomplete = 'off';
      function field(labelText, type, value, required, autocomplete) {
        const wrapper = document.createElement('div');
        wrapper.className = 'field';
        const label = document.createElement('label');
        label.textContent = labelText;
        const input = document.createElement('input');
        input.type = type;
        input.required = required;
        input.autocomplete = autocomplete;
        if (value) input.value = value;
        if (type === 'email') input.maxLength = 190;
        if (type === 'password') input.maxLength = 72;
        wrapper.append(label, input);
        form.append(wrapper);
        return input;
      }
      const email = field('E-mail de login', 'email', account.email || '', true, 'off');
      const newPassword = field('Nova senha (deixe vazio para manter)', 'password', '', false, 'new-password');
      const confirmPassword = field('Confirmar nova senha', 'password', '', false, 'new-password');
      const yourPassword = field('Sua senha de Administrador (obrigatória)', 'password', '', true, 'current-password');
      const hint = document.createElement('p');
      hint.className = 'hint';
      hint.textContent = 'A alteração exige sua senha atual de Administrador e afetará somente a conta selecionada.';
      const save = document.createElement('button');
      save.type = 'submit';
      save.className = 'create-btn';
      save.textContent = 'Salvar alterações';
      const feedback = document.createElement('p');
      feedback.className = 'feedback';
      feedback.setAttribute('role', 'status');
      form.append(hint, save, feedback);
      card.append(form);
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        feedback.textContent = '';
        feedback.className = 'feedback';
        const changedEmail = email.value.trim().toLowerCase() !== (account.email || '').toLowerCase();
        if (!changedEmail && newPassword.value.length === 0) {
          feedback.textContent = 'Altere o e-mail ou digite uma nova senha.';
          feedback.classList.add('error');
          return;
        }
        if (newPassword.value !== confirmPassword.value) {
          feedback.textContent = 'A confirmação da nova senha não corresponde.';
          feedback.classList.add('error');
          return;
        }
        const bytes = new TextEncoder().encode(newPassword.value).length;
        if (newPassword.value && (newPassword.value.length < 12 || bytes > 72)) {
          feedback.textContent = 'A nova senha deve ter pelo menos 12 caracteres e no máximo 72 bytes.';
          feedback.classList.add('error');
          return;
        }
        if (!form.reportValidity()) return;
        save.disabled = true;
        save.textContent = 'Salvando...';
        try {
          const {response, data} = await request(UPDATE, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
              ...(Number.isSafeInteger(account.targetId) ? {targetId: account.targetId} : {accountId: account.id}),
              email: email.value.trim(),
              newPassword: newPassword.value,
              adminPassword: yourPassword.value
            })
          });
          if (!response.ok || !data?.success) {
            throw new Error(data?.message || 'Não foi possível atualizar o acesso.');
          }
          await load();
          status.textContent = data.message + ' O e-mail atualizado já pode ser usado no próximo login.';
        } catch (error) {
          feedback.textContent = error.message || 'Não foi possível salvar.';
          feedback.classList.add('error');
        } finally {
          newPassword.value = '';
          confirmPassword.value = '';
          yourPassword.value = '';
          save.disabled = false;
          save.textContent = 'Salvar alterações';
        }
      });
    });
  }

  function render(accounts) {
    const pending = accounts.some(account => !account.created || account.needsRename);
    pendingSection.hidden = !pending;
    for (const account of accounts) {
      if (!allowed.has(account.id)) continue;
      const card = cards.querySelector('[data-account="'+account.id+'"]');
      if (!card) continue;
      card.hidden = account.created && !account.needsRename;
      if (card.hidden) continue;
      card.replaceChildren();
      const title = document.createElement('h2'); title.textContent = account.name;
      const state = document.createElement('p'); state.className = 'state';
      card.append(title,state);
      if (account.created) {
        state.textContent = account.needsRename
          ? 'Conta encontrada com nome anterior. Confirme a correção antes de utilizar.'
          : (account.active ? 'Conta ativa · Administrador' : 'Conta existente, porém inativa');
        state.classList.toggle('ok', account.active && !account.needsRename);
        const p=document.createElement('p');p.textContent=account.email || '';
        p.className='hint';card.append(p);
        if (account.needsRename) {
          const button=document.createElement('button');button.type='button';
          button.className='create-btn';button.textContent='Corrigir nome para '+account.name;
          const feedback=document.createElement('div');feedback.className='feedback';
          feedback.setAttribute('role','status');
          card.append(button,feedback);
          button.addEventListener('click', async () => {
            if (!window.confirm('Corrigir apenas o nome desta conta para '+account.name+'? O e-mail e a senha continuarão os mesmos.')) return;
            button.disabled=true;
            try {
              const result=await request(RENAME,{
                method:'POST',headers:{'Content-Type':'application/json'},
                body:JSON.stringify({accountId:account.id})
              });
              if(!result.response.ok || !result.data?.success) throw new Error(result.data?.message || 'Não foi possível corrigir o nome.');
              await load();
            } catch (err) {
              feedback.textContent=err.message || 'Falha na correção.';
              feedback.classList.add('error');
            } finally { button.disabled=false; }
          });
        }
        if (!account.needsRename && account.active) addEditControls(card, account);
        continue;
      }
      state.textContent = 'Cadastro ainda não realizado';
      const form = document.createElement('form');
      const email = formField(form, 'E-mail para login', 'email');
      const password = formField(form,'Senha escolhida (mínimo 12 caracteres)', 'password');
      const confirm = formField(form,'Confirmar senha', 'password');
      const hint=document.createElement('p');hint.className='hint';
      hint.textContent='Esta senha não será exibida de novo nem enviada por e-mail.';
      const button=document.createElement('button');button.type='submit';button.className='create-btn';
      button.textContent='Criar administrador';
      const feedback=document.createElement('div');feedback.className='feedback';
      feedback.setAttribute('role','status');
      form.append(hint,button,feedback);card.append(form);
      form.addEventListener('submit',async e=>{
        e.preventDefault();
        feedback.textContent='';feedback.className='feedback';
        if(password.value!==confirm.value){feedback.textContent='As senhas digitadas não são iguais.';feedback.classList.add('error');return;}
        if(!form.reportValidity())return;
        button.disabled=true;button.textContent='Cadastrando...';
        try {
          const result=await request(CREATE,{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({accountId:account.id,email:email.value.trim(),password:password.value})
          });
          // Limpar segredos da interface mesmo quando houver erro.
          password.value='';confirm.value='';
          if(!result.response.ok || !result.data?.success) throw new Error(result.data?.message || 'Não foi possível criar o acesso.');
          feedback.textContent='Conta criada!';feedback.classList.add('success');
          await load();
        }catch(err){
          feedback.textContent=err.message || 'Falha na criação da conta.';
          feedback.classList.add('error');
        }finally{password.value='';confirm.value='';button.disabled=false;button.textContent='Criar administrador';}
      });
    }
  }

  function renderAdministrators(administrators) {
    adminList.replaceChildren();
    if (!administrators.length) {
      const empty = document.createElement('p');
      empty.textContent = 'Nenhum administrador encontrado.';
      adminList.append(empty);
      return;
    }
    for (const admin of administrators) {
      const card = document.createElement('article');
      card.className = 'account-card';
      const heading = document.createElement('h2');
      heading.textContent = admin.name;
      const email = document.createElement('p');
      email.className = 'hint';
      email.textContent = admin.email;
      const state = document.createElement('p');
      state.className = admin.active ? 'state ok' : 'state';
      state.textContent = admin.active ? 'Administrador ativo' : 'Administrador inativo';
      card.append(heading, email, state);
      if (admin.active) addEditControls(card, {
        id: admin.id, targetId: admin.id, name: admin.name, email: admin.email
      });
      adminList.append(card);
    }
  }

  const newAdminButton = document.querySelector('#newAdminButton');
  const newAdminPanel = document.querySelector('#newAdminPanel');
  const newAdminForm = document.querySelector('#newAdminForm');
  const newAdminFeedback = document.querySelector('#newAdminFeedback');
  const newAdminSave = document.querySelector('#saveNewAdmin');
  newAdminButton.addEventListener('click', () => {
    const opening = newAdminPanel.hidden;
    newAdminPanel.hidden = !opening;
    newAdminButton.setAttribute('aria-expanded', String(opening));
    newAdminButton.textContent = opening ? 'Cancelar novo cadastro' : '+ Novo administrador';
    if (opening) document.querySelector('#newAdminName').focus();
    else {
      newAdminForm.reset();
      newAdminFeedback.textContent = '';
    }
  });
  newAdminForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const name = document.querySelector('#newAdminName').value.trim();
    const email = document.querySelector('#newAdminEmail').value.trim();
    const password = document.querySelector('#newAdminPassword');
    const confirm = document.querySelector('#newAdminConfirm');
    const adminPassword = document.querySelector('#newAdminAuth');
    newAdminFeedback.textContent = '';
    newAdminFeedback.className = 'feedback';
    if (password.value !== confirm.value) {
      newAdminFeedback.textContent = 'As senhas da nova conta não são iguais.';
      newAdminFeedback.classList.add('error');
      return;
    }
    const bytes = new TextEncoder().encode(password.value).length;
    if (password.value.length < 12 || bytes > 72) {
      newAdminFeedback.textContent = 'A nova senha deve ter no mínimo 12 caracteres e no máximo 72 bytes.';
      newAdminFeedback.classList.add('error');
      return;
    }
    if (!newAdminForm.reportValidity()) return;
    newAdminSave.disabled = true;
    newAdminSave.textContent = 'Cadastrando...';
    try {
      const {response, data} = await request(CREATE_NEW, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          name, email, password: password.value, adminPassword: adminPassword.value
        })
      });
      if (!response.ok || !data?.success) {
        throw new Error(data?.message || 'Não foi possível criar o administrador.');
      }
      newAdminForm.reset();
      newAdminPanel.hidden = true;
      newAdminButton.setAttribute('aria-expanded', 'false');
      newAdminButton.textContent = '+ Novo administrador';
      await load();
      status.textContent = data.message + ' O novo acesso está disponível para login.';
    } catch (error) {
      newAdminFeedback.textContent = error.message || 'Não foi possível cadastrar.';
      newAdminFeedback.classList.add('error');
    } finally {
      password.value = '';
      confirm.value = '';
      adminPassword.value = '';
      newAdminSave.disabled = false;
      newAdminSave.textContent = 'Cadastrar administrador';
    }
  });

  async function load() {
    const r=await request(LIST);
    if(!r.response.ok || !r.data?.success) throw new Error(r.data?.message || 'Não foi possível consultar as contas.');
    if (!Array.isArray(r.data.administrators) || !Array.isArray(r.data.accounts)) throw new Error('Resposta de administradores inválida.');
    render(r.data.accounts);
    renderAdministrators(r.data.administrators);
    const count=r.data.administrators.filter(x=>x.active).length;
    status.textContent=count+' conta(s) administrativa(s) ativa(s).';
  }
  try {
    const {response,data}=await request(SESSION);
    if(!response.ok || !data?.authenticated || !data.usuario){location.replace('login.html');return;}
    if(data.usuario.perfil!=='admin'){location.replace('login.html');return;}
    await load();
  }catch(error){status.textContent=error.message || 'Não foi possível iniciar o cadastro.';status.style.color='#b22745';}
});
