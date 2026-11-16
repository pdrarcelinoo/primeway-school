document.addEventListener("DOMContentLoaded", async () => {
    "use strict";
    const q=s=>document.querySelector(s), api="../api/chat/central.php";
    const list=q(".chat-conversations"), messages=q(".chat-messages"), input=q('.chat-input-area input[type="text"]');
    const send=q(".chat-send"), file=q("#attachmentInput"), error=e=>window.PrimeWayFeedback?.error(e.message);
    const attachButton=q("#attachmentButton");
    const title=q(".conversation-person h2"), subtitle=q(".conversation-person span"), container=q(".chat-container");
    let conversations=[],active=null,csrf="",busy=false,adminSuspended=false,requestId=0;
    let conversationsSignature=null,selectedFile=null;
    list.replaceChildren();list.dataset.ready="true";messages.replaceChildren();input.disabled=true;send.disabled=true;attachButton.disabled=true;
    q("#newChatButton").hidden=true;q("#conversationOptionsButton").hidden=true;
    async function request(url,payload) {
        const response=await fetch(url,{method:payload===undefined?"GET":"POST",credentials:"same-origin",cache:"no-store",
            headers:{Accept:"application/json",...(payload===undefined?{}:{"Content-Type":"application/json","X-CSRF-Token":csrf})},
            ...(payload===undefined?{}:{body:JSON.stringify(payload)})});
        const data=await response.json();
        if(!response.ok||data.success===false)throw Error(data.message||"Não foi possível carregar o Chat.");
        return data;
    }
    const make=(tag,cls,text)=>{const el=document.createElement(tag);el.className=cls;if(text!==undefined)el.textContent=text;return el;};
    // Apresentação humana dos perfis, sem alterar permissões ou valores da API.
    const roleNames={
        admin:"Administrador",administrador:"Administrador",
        professor:"Professor",professora:"Professora",
        aluno:"Aluno",aluna:"Aluna",
        responsavel:"Responsável",secretaria:"Secretaria"
    };
    const displayRole=(value,fallback="Conversa")=>{
        const original=String(value??"").trim();
        if(!original)return fallback;
        const normalized=original.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
        return roleNames[normalized]||original;
    };
    // O administrador usa o mesmo validador de anexos dos demais perfis.
    const filesUi=window.PrimeWayChatAttachments;
    file.accept=filesUi.acceptedExtensions.map(ext=>"."+ext).join(",");
    const selected=document.createElement("div");
    selected.className="pw-chat-selected-file pw-admin-file-selected";
    selected.hidden=true;
    const selectedIcon=make("i","fa-solid fa-paperclip");
    const selectedMeta=make("span","pw-chat-selected-meta");
    const selectedName=make("strong","");
    const selectedSize=make("span","");
    selectedMeta.append(selectedName,selectedSize);
    const removeAttachment=make("button","pw-chat-remove-file");
    removeAttachment.type="button";removeAttachment.title="Remover anexo";
    removeAttachment.setAttribute("aria-label","Remover anexo selecionado");
    removeAttachment.innerHTML='<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    selected.append(selectedIcon,selectedMeta,removeAttachment);
    q(".chat-input-area").before(selected);
    function clearAttachment(){
        selectedFile=null;file.value="";selected.hidden=true;
        selectedName.textContent="";selectedSize.textContent="";
        selectedIcon.className="fa-solid fa-paperclip";
        attachButton.title="Anexar arquivo";
    }
    function showAttachment(chosen=file.files?.[0],meta={}){
        if(!chosen){clearAttachment();return;}
        const validation=filesUi.validateFile(chosen);
        if(validation){clearAttachment();error(Error(validation));return;}
        selectedFile=chosen;
        const isAudio=String(chosen.type||"").startsWith("audio/")
            ||/\.(webm|ogg|mp3|m4a|wav)$/i.test(chosen.name);
        selectedIcon.className=isAudio?"fa-solid fa-microphone":"fa-solid fa-paperclip";
        selectedName.textContent=isAudio?"Áudio gravado":chosen.name;
        selectedSize.textContent=isAudio&&Number.isFinite(meta.duration)
            ?filesUi.formatDuration(meta.duration)+" • "+filesUi.formatSize(chosen.size)
            :filesUi.formatSize(chosen.size);
        selected.hidden=false;
        attachButton.title=chosen.name;
    }
    // Reutiliza o MESMO gravador compartilhado de Aluno/Professor/Secretaria/Responsável.
    // Assim o Administrador também grava, cancela e envia áudios com proteção CSRF.
    const audio=filesUi.createAudioRecorder({
        input,container:q(".chat-input-area"),before:input,
        canStart:()=>Boolean(active)&&!busy&&!adminSuspended&&!selectedFile,
        onFileReady:(clip,meta)=>{
            if(active&&!adminSuspended){file.value="";showAttachment(clip,meta);}
        },
        onError:message=>error(Error(message)),
        onRecordingChange:recording=>{
            attachButton.disabled=recording||!active||adminSuspended||busy;
            file.disabled=recording||!active||adminSuspended||busy;
            send.disabled=recording||!active||adminSuspended||busy;
        }
    });
    audio.setEnabled(false);
    function syncComposer(){
        const enabled=Boolean(active)&&!adminSuspended&&!busy;
        if(!audio.isRecording())input.disabled=!enabled;
        audio.setEnabled(enabled);
        attachButton.disabled=!enabled||audio.isRecording();
        file.disabled=!enabled||audio.isRecording();
        send.disabled=!enabled||audio.isRecording();
        if((!active||adminSuspended)&&!audio.isRecording())clearAttachment();
    }
    removeAttachment.onclick=clearAttachment;


    try {
        const session=await request("../api/auth/session.php");
        if(!session.authenticated){location.replace("login.html");return;}
        const role=session.usuario.perfil;
        if(role!=="admin") {location.replace(({professor:"professor_chat.html",secretaria:"secretaria_chat.html",aluno:"aluno_chat.html",responsavel:"responsavel_chat.html"})[role]||"login.html");return;}
        csrf=session.csrfToken||"";
        document.body.dataset.primewayPerfil="admin";
    } catch(e){error(e);return;}
    function renderList() {
        const search=q('.chat-search input').value.toLocaleLowerCase();list.replaceChildren();
        for(const item of conversations.filter(x=>x.title.toLocaleLowerCase().includes(search))) {
            const button=make("button","conversation"+(item.id===active?" active":""));button.type="button";button.dataset.conversationId=item.id;
            const avatar=make("div","conversation-avatar");avatar.append(make("i","fa-solid fa-comments"));
            const info=make("div","conversation-info");info.append(make("strong","",item.title),make("p","",displayRole(item.role)));
            button.append(avatar,info);if(item.unread)button.append(make("span","unread-badge",String(item.unread)));
            button.onclick=()=>open(item.id).catch(error);list.append(button);
        }
        if(!list.children.length)list.append(make("p","", "Nenhuma conversa disponível."));
    }
    async function loadMessages(followEnd=false) {
        if(!active)return;const id=active,serial=++requestId;
        const data=await request(api+"?conversationId="+id);if(serial!==requestId||id!==active)return;
        title.textContent=data.conversation.title;subtitle.textContent=displayRole(data.conversation.role,"Comunicação escolar");
        window.PrimeWayChatMessageExperience.updateMessageList(messages,data.messages,item=>{
            const article=make("div","message "+(item.own?"sent":"received")),bubble=make("div","message-bubble");
            bubble.append(make("p","",item.content||""));window.PrimeWayChatAttachments.renderMessageAttachments(bubble,item.attachments||[]);
            bubble.append(make("span","message-time",new Date(item.sentAt.replace(" ","T")).toLocaleString("pt-BR")));article.append(bubble);
            window.PrimeWayChatMessageExperience.decorateMessage(article,item,{csrfToken:csrf,managePinnedBanner:false,onChanged:()=>loadMessages(),onError:message=>error(Error(message))});return article;
        },{conversationId:id,followEnd,emptyHtml:"<p>Nenhuma mensagem nesta conversa.</p>"});
        syncComposer();
        if(document.visibilityState==="visible"&&id===active){const item=conversations.find(x=>x.id===id);if(item&&Number(item.unread||0)>0){await request(api,{action:"read",conversationId:id});item.unread=0;renderList();}}
        return true;
    }
    async function open(id) {if(busy)return;audio.cancel();active=id;clearAttachment();syncComposer();renderList();container.classList.add("mobile-show-chat");await loadMessages(true);}
    async function loadIndex() {
        const data=await request(api);
        const nextConversations=Array.isArray(data.conversations)?data.conversations:[];
        const signature=JSON.stringify(nextConversations);
        conversations=nextConversations;
        adminSuspended=Boolean(data.chatSuspended);
        if(signature!==conversationsSignature){
            conversationsSignature=signature;
            renderList();
        }
        if(active&&!conversations.some(x=>x.id===active)){
            audio.cancel();active=null;requestId++;messages.replaceChildren();title.textContent="Selecione uma conversa";
        }
        syncComposer();
    }
    async function sendMessage() {
        if(busy||!active||input.disabled||audio.isRecording()||(!input.value.trim()&&!selectedFile))return;busy=true;syncComposer();
        try {
            if(selectedFile){const body=new FormData();body.set("conversationId",active);body.set("content",input.value.trim());body.set("file",selectedFile,selectedFile.name);
                const response=await fetch("../api/chat/upload.php",{method:"POST",credentials:"same-origin",headers:{"X-CSRF-Token":csrf},body});let data=null;try{data=await response.json();}catch{}if(!response.ok||!data?.success)throw Error(data?.message||(response.status===413?"O arquivo ultrapassa o limite de 20 MB.":"Falha ao enviar anexo."));
            }else await request(api,{action:"send",conversationId:active,content:input.value.trim()});
            input.value="";clearAttachment();await loadMessages(true);await loadIndex();
        }catch(e){error(e);}finally{busy=false;syncComposer();}
    }
    attachButton.onclick=()=>{if(active&&!input.disabled&&!busy&&!audio.isRecording())file.click();};
    file.onchange=()=>showAttachment();
    send.onclick=sendMessage;input.onkeydown=e=>{if(e.key==="Enter"){e.preventDefault();sendMessage();}};

    // Consulta paginada sob demanda, sem expor conversas de outros usuários.
    const contactsDialog=q("#adminContactsDialog");
    const contactsSearch=q("#adminContactSearch");
    const contactsRole=q("#adminContactRole");
    const contactsList=q("#adminContactsList");
    const contactsPageLabel=q("#adminContactsPage");
    const contactsPrevious=q("#adminContactsPrevious");
    const contactsNext=q("#adminContactsNext");
    const roleLabels={admin:"Administrador",professor:"Professor(a)",aluno:"Aluno(a)",responsavel:"Responsável",secretaria:"Secretaria"};
    let contactsPage=1,contactsSerial=0,contactsTimer=null,startingContact=false;
    const initials=value=>String(value||"?").trim().split(/\s+/).slice(0,2).map(x=>x[0]||"").join("").toLocaleUpperCase("pt-BR");

    async function loadContacts(page=1){
        if(!contactsDialog.open)return;
        const serial=++contactsSerial;
        contactsPage=Math.max(1,page);
        contactsPrevious.disabled=true;contactsNext.disabled=true;
        contactsList.replaceChildren(make("p","admin-contact-hint","Carregando usuários..."));
        const params=new URLSearchParams({
            search:contactsSearch.value.trim(),role:contactsRole.value,page:String(contactsPage)
        });
        try{
            const data=await request("../api/chat/contatos.php?"+params.toString());
            if(serial!==contactsSerial||!contactsDialog.open)return;
            contactsList.replaceChildren();
            for(const contact of data.contacts||[]){
                const button=make("button","admin-contact-row");button.type="button";
                const avatar=make("span","admin-contact-avatar",initials(contact.name));
                const info=make("span","admin-contact-meta");
                info.append(make("strong","",contact.name),make("small","",(roleLabels[contact.role]||contact.role)+" • "+contact.email));
                button.append(avatar,info);
                button.addEventListener("click",()=>void startConversation(contact.userId));
                contactsList.append(button);
            }
            if(!contactsList.children.length)
                contactsList.append(make("p","admin-contact-hint","Nenhum usuário ativo encontrado."));
            contactsPageLabel.textContent="Página "+(data.page||contactsPage);
            contactsPrevious.disabled=contactsPage<=1;
            contactsNext.disabled=!data.hasMore;
        }catch(e){
            if(serial!==contactsSerial||!contactsDialog.open)return;
            contactsList.replaceChildren(make("p","admin-contact-hint",e.message));
            error(e);
        }
    }

    async function startConversation(contactUserId){
        if(startingContact||busy)return;
        startingContact=true;
        contactsList.querySelectorAll("button").forEach(button=>button.disabled=true);
        try{
            const data=await request("../api/chat/iniciar.php",{contactUserId});
            contactsDialog.close();
            await loadIndex();
            await open(data.conversationId);
        }catch(e){error(e);}
        finally{startingContact=false;}
    }

    q("#newChatButton").hidden=false;
    q("#newChatButton").onclick=()=>{
        if(busy)return;
        contactsSearch.value="";contactsRole.value="";contactsPage=1;
        contactsDialog.showModal();
        void loadContacts(1);
        contactsSearch.focus();
    };
    q("#adminContactsClose").onclick=()=>contactsDialog.close();
    contactsDialog.addEventListener("close",()=>{contactsSerial++;clearTimeout(contactsTimer);});
    contactsSearch.addEventListener("input",()=>{
        clearTimeout(contactsTimer);
        contactsTimer=setTimeout(()=>void loadContacts(1),300);
    });
    contactsRole.addEventListener("change",()=>void loadContacts(1));
    contactsPrevious.onclick=()=>void loadContacts(contactsPage-1);
    contactsNext.onclick=()=>void loadContacts(contactsPage+1);

    q('.chat-search input').oninput=renderList;
    const back=make("button","mobile-conversations-button","Conversas");back.type="button";back.onclick=()=>container.classList.remove("mobile-show-chat");q(".conversation-header").prepend(back);
    q("#logoutButton").onclick=async()=>{try{await request("../api/auth/logout.php",{});location.replace("login.html");}catch(e){error(e);}};
    try{
        await loadIndex();
        title.textContent="Selecione uma conversa";
        window.PrimeWayChatLiveUpdates.start({
            getActiveConversationId:()=>active,
            refreshMessages:()=>loadMessages(),
            refreshConversations:()=>loadIndex(),
            isBusy:()=>busy||audio.isRecording()
        });
    }catch(e){error(e);}
});
