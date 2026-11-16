/* Atualização contínua do chat de todos os perfis, sem recarregar a página.
   2,6 s na conversa aberta; lista de conversas a cada ~7 s.
   Usa versão compacta do servidor e só busca as mensagens quando há alterações. */
(function(){
    "use strict";
    const TICK_MS=2600, INDEX_MS=7000, MAX_RETRY_MS=30000;
    const URL="../api/chat/sync.php";

    function start({getActiveConversationId,refreshMessages,refreshConversations,isBusy=()=>false}){
        if(typeof getActiveConversationId!=="function"
            ||typeof refreshMessages!=="function"
            ||typeof refreshConversations!=="function") {
            throw new TypeError("Atualização do chat sem callbacks válidos.");
        }

        let stopped=false,working=false,timer=null,failures=0;
        let trackedConversation=0,version=null,lastIndexAt=Date.now();

        const activeId=()=>{
            const id=Number(getActiveConversationId()||0);
            return Number.isSafeInteger(id)&&id>0?id:0;
        };
        const available=()=>document.visibilityState==="visible"&&navigator.onLine!==false;

        async function update(force=false){
            if(stopped||working||!available()||isBusy())return;
            working=true;
            try{
                const id=activeId();
                if(id!==trackedConversation){
                    trackedConversation=id;
                    version=null;
                }
                let changed=false;
                if(id){
                    const response=await fetch(URL+"?conversationId="+encodeURIComponent(id),{
                        credentials:"same-origin",cache:"no-store",
                        headers:{Accept:"application/json"}
                    });
                    if(response.status===401){
                        stop();
                        window.location.replace("login.html");
                        return;
                    }
                    if(!response.ok){
                        if(response.status===404||response.status===403){
                            // A conversa pode ter sido encerrada: recarrega a lista
                            // para desmarcá-la, sem insistir em uma conversa inacessível.
                            version=null;
                            lastIndexAt=0;
                            const succeeded=await refreshConversations();
                            if(succeeded!==false)lastIndexAt=Date.now();
                            failures=0;
                            return;
                        }
                        throw new Error("Falha na consulta de atualização do Chat.");
                    }
                    const data=await response.json();
                    if(!data?.success||typeof data.version!=="string"){
                        throw new Error("Resposta de atualização inválida.");
                    }
                    if(id!==activeId()||isBusy())return; // nunca mistura conversas
                    const previous=version;
                    if(previous!==data.version||force){
                        const succeeded=await refreshMessages();
                        if(succeeded!==false&&id===activeId()&&!isBusy()){
                            version=data.version;
                            changed=previous!==null&&previous!==data.version;
                        }
                    }
                }

                if(force||changed||Date.now()-lastIndexAt>=INDEX_MS){
                    if(!isBusy()){
                        const succeeded=await refreshConversations();
                        if(succeeded!==false)lastIndexAt=Date.now();
                    }
                }
                failures=0;
            }catch(error){
                failures=Math.min(failures+1,5);
                if(failures===1)console.debug("Atualização do chat temporariamente indisponível.",error);
            }finally{
                working=false;
            }
        }

        function next(delay=TICK_MS){
            if(stopped)return;
            window.clearTimeout(timer);
            timer=window.setTimeout(async()=>{
                await update();
                next(failures?Math.min(MAX_RETRY_MS,TICK_MS*(2**failures)):TICK_MS);
            },delay);
        }
        function wake(){
            if(stopped||!available()||working)return;
            window.clearTimeout(timer);
            void update(true).finally(()=>next());
        }
        function onVisible(){if(document.visibilityState==="visible")wake();}
        function stop(){
            if(stopped)return;
            stopped=true;
            window.clearTimeout(timer);
            document.removeEventListener("visibilitychange",onVisible);
            window.removeEventListener("online",wake);
            window.removeEventListener("pagehide",stop);
        }

        document.addEventListener("visibilitychange",onVisible);
        window.addEventListener("online",wake);
        window.addEventListener("pagehide",stop);
        next();
        return Object.freeze({stop,refresh:wake});
    }
    window.PrimeWayChatLiveUpdates=Object.freeze({start});
})();
