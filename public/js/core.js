(function () {
    "use strict";
    const CSRF_STORAGE_KEY = "primewayCsrfToken";
    const RETIRED_STORAGE_KEYS = new Set([
        "primewayGuardians", "primewayClasses", "primewayChatProfessor",
        "primewayNotifications", "primewayCalendarEvents", "primewaySubjects",
        "primewayStudents", "primewaySettings", "primewayDataModelVersion"
    ]);
    const originalFetch = window.fetch.bind(window);
    const originalSetItem = Storage.prototype.setItem;
    const originalRemoveItem = Storage.prototype.removeItem;
    // Somente caches aposentados; sessão e dados relacionais permanecem nas APIs.
    for (const key of RETIRED_STORAGE_KEYS) originalRemoveItem.call(window.localStorage, key);
    Storage.prototype.setItem = function primewaySetItem(key, value) {
        if (this === window.localStorage && RETIRED_STORAGE_KEYS.has(String(key))) return;
        originalSetItem.call(this, key, value);
    };
    function isApiRequest(input) {
        try {
            const rawUrl =
                input instanceof Request
                    ? input.url
                    : String(input);
            const url = new URL(rawUrl, document.baseURI);

            return (
                url.origin === window.origin &&
                url.pathname.includes("/api/")
            );
        } catch (error) {
            return false;
        }
    }

    function storeCsrfToken(payload) {
        const token = payload?.csrfToken;

        if (
            typeof token === "string" &&
            token.length === 64
        ) {
            sessionStorage.setItem(
                CSRF_STORAGE_KEY,
                token
            );
        }
    }

    window.fetch = async function primewayFetch(input, init = {}) {
        const apiRequest = isApiRequest(input);
        const requestMethod = String(
            init.method ||
            (input instanceof Request ? input.method : "GET")
        ).toUpperCase();
        const options = { ...init };

        if (
            apiRequest &&
            !["GET", "HEAD", "OPTIONS"].includes(requestMethod)
        ) {
            const headers = new Headers(
                init.headers ||
                (input instanceof Request ? input.headers : undefined)
            );
            const token = sessionStorage.getItem(CSRF_STORAGE_KEY);

            if (token) {
                headers.set("X-CSRF-Token", token);
            }

            options.headers = headers;
        }

        const response = await originalFetch(input, options);

        if (apiRequest) {
            try {
                storeCsrfToken(
                    await response.clone().json()
                );
            } catch (error) {
                // Respostas sem JSON não participam do protocolo de sessão.
            }
        }

        return response;
    };

/*====================================================
            FEEDBACK GLOBAL - PRIMEWAY
====================================================*/

const FEEDBACK_TYPES =
    new Set([
        "success",
        "error",
        "warning",
        "info"
    ]);


const FEEDBACK_ICONS = {
    success:
        "fa-solid fa-circle-check",

    error:
        "fa-solid fa-circle-exclamation",

    warning:
        "fa-solid fa-triangle-exclamation",

    info:
        "fa-solid fa-circle-info"
};


function getFeedbackContainer() {

    let container =
        document.querySelector(
            ".primeway-toast-container"
        );


    if (container) {

        return container;
    }


    container =
        document.createElement(
            "div"
        );


    container.className =
        "primeway-toast-container";


    container.setAttribute(
        "aria-live",
        "polite"
    );


    container.setAttribute(
        "aria-atomic",
        "false"
    );


    document.body.appendChild(
        container
    );


    return container;
}


function removeFeedback(
    toast
) {

    if (
        !toast ||
        toast.classList.contains(
            "is-leaving"
        )
    ) {

        return;
    }


    toast.classList.add(
        "is-leaving"
    );


    window.setTimeout(
        () => {

            toast.remove();

        },
        180
    );
}


function showFeedback(
    message,
    type = "info",
    options = {}
) {

    const text =
        String(
            message ?? ""
        ).trim();


    if (!text) {

        return null;
    }


    const normalizedType =
        FEEDBACK_TYPES.has(type)
            ? type
            : "info";


    const container =
        getFeedbackContainer();


    const toast =
        document.createElement(
            "div"
        );


    toast.className =
        `primeway-toast ${normalizedType}`;


    toast.setAttribute(
        "role",
        normalizedType === "error"
            ? "alert"
            : "status"
    );


    /* ÍCONE */

    const iconBox =
        document.createElement(
            "div"
        );


    iconBox.className =
        "primeway-toast-icon";


    const icon =
        document.createElement(
            "i"
        );


    icon.className =
        FEEDBACK_ICONS[
            normalizedType
        ];


    icon.setAttribute(
        "aria-hidden",
        "true"
    );


    iconBox.appendChild(
        icon
    );


    /* CONTEÚDO */

    const content =
        document.createElement(
            "div"
        );


    content.className =
        "primeway-toast-content";


    if (
        options.title
    ) {

        const title =
            document.createElement(
                "strong"
            );


        title.className =
            "primeway-toast-title";


        title.textContent =
            String(
                options.title
            );


        content.appendChild(
            title
        );
    }


    const messageElement =
        document.createElement(
            "span"
        );


    messageElement.className =
        "primeway-toast-message";


    messageElement.textContent =
        text;


    content.appendChild(
        messageElement
    );


    /* FECHAR */

    const closeButton =
        document.createElement(
            "button"
        );


    closeButton.type =
        "button";


    closeButton.className =
        "primeway-toast-close";


    closeButton.setAttribute(
        "aria-label",
        "Fechar mensagem"
    );


    closeButton.innerHTML = `
        <i
            class="fa-solid fa-xmark"
            aria-hidden="true"
        ></i>
    `;


    closeButton.addEventListener(
        "click",
        () =>
            removeFeedback(
                toast
            )
    );


    toast.append(
        iconBox,
        content,
        closeButton
    );


    container.appendChild(
        toast
    );


    const defaultDuration =
        normalizedType === "error"
            ? 6000
            : 4000;


    const duration =
        Number.isFinite(
            options.duration
        )
            ? options.duration
            : defaultDuration;


    if (
        duration > 0
    ) {

        window.setTimeout(
            () =>
                removeFeedback(
                    toast
                ),
            duration
        );
    }


    return toast;
}


function clearFeedbacks() {

    document
        .querySelectorAll(
            ".primeway-toast"
        )
        .forEach(
            removeFeedback
        );
}


window.PrimeWayFeedback =
    Object.freeze({

        show:
            showFeedback,


        success(
            message,
            options = {}
        ) {

            return showFeedback(
                message,
                "success",
                options
            );
        },


        error(
            message,
            options = {}
        ) {

            return showFeedback(
                message,
                "error",
                options
            );
        },


        warning(
            message,
            options = {}
        ) {

            return showFeedback(
                message,
                "warning",
                options
            );
        },


        info(
            message,
            options = {}
        ) {

            return showFeedback(
                message,
                "info",
                options
            );
        },


        clear:
            clearFeedbacks
    });


/*====================================================
            CONFIRMAÇÃO GLOBAL - PRIMEWAY
====================================================*/

let primewayConfirmCounter = 0;


function primewayConfirm(
    message,
    options = {}
) {

    const text =
        String(
            message ?? ""
        ).trim();


    if (!text) {

        return Promise.resolve(
            false
        );
    }


    const type =
        ["info", "warning", "danger"]
            .includes(
                options.type
            )
                ? options.type
                : "info";


    const title =
        String(
            options.title ||
            (
                type === "danger"
                    ? "Confirmar ação"
                    : type === "warning"
                        ? "Atenção"
                        : "Confirmação"
            )
        );


    const confirmText =
        String(
            options.confirmText ||
            "Confirmar"
        );


    const cancelText =
        String(
            options.cancelText ||
            "Cancelar"
        );


    const iconClass =
        type === "danger"
            ? "fa-solid fa-triangle-exclamation"
            : type === "warning"
                ? "fa-solid fa-circle-exclamation"
                : "fa-solid fa-circle-question";


    primewayConfirmCounter += 1;


    const confirmId =
        `primewayConfirmTitle${primewayConfirmCounter}`;


    const messageId =
        `primewayConfirmMessage${primewayConfirmCounter}`;


    return new Promise(
        resolve => {

            const previousFocus =
                document.activeElement;


            const previousOverflow =
                document.body.style.overflow;


            const root =
                document.createElement(
                    "div"
                );


            root.className =
                `primeway-confirm ${type}`;


            root.innerHTML = `
                <div
                    class="primeway-confirm-backdrop"
                    data-primeway-confirm-cancel
                ></div>

                <div
                    class="primeway-confirm-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="${confirmId}"
                    aria-describedby="${messageId}"
                >

                    <div
                        class="primeway-confirm-content"
                    >

                        <div
                            class="primeway-confirm-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="${iconClass}"
                            ></i>
                        </div>


                        <div
                            class="primeway-confirm-text"
                        >

                            <h2
                                class="primeway-confirm-title"
                                id="${confirmId}"
                            ></h2>

                            <p
                                class="primeway-confirm-message"
                                id="${messageId}"
                            ></p>

                        </div>

                    </div>


                    <div
                        class="primeway-confirm-actions"
                    >

                        <button
                            type="button"
                            class="
                                primeway-confirm-button
                                primeway-confirm-cancel
                            "
                            data-primeway-confirm-cancel
                        >
                            Cancelar
                        </button>


                        <button
                            type="button"
                            class="
                                primeway-confirm-button
                                primeway-confirm-accept
                            "
                            data-primeway-confirm-accept
                        >
                            Confirmar
                        </button>

                    </div>

                </div>
            `;


            const titleElement =
                root.querySelector(
                    ".primeway-confirm-title"
                );


            const messageElement =
                root.querySelector(
                    ".primeway-confirm-message"
                );


            const acceptButton =
                root.querySelector(
                    "[data-primeway-confirm-accept]"
                );


            const cancelButton =
                root.querySelector(
                    ".primeway-confirm-cancel"
                );


            titleElement.textContent =
                title;


            messageElement.textContent =
                text;


            acceptButton.textContent =
                confirmText;


            cancelButton.textContent =
                cancelText;


            document.body.appendChild(
                root
            );


            document.body.style.overflow =
                "hidden";


            let finished =
                false;


            function finish(
                result
            ) {

                if (finished) {

                    return;
                }


                finished =
                    true;


                document.removeEventListener(
                    "keydown",
                    handleKeydown
                );


                root.classList.remove(
                    "show"
                );


                window.setTimeout(
                    () => {

                        root.remove();


                        document.body.style.overflow =
                            previousOverflow;


                        if (
                            previousFocus &&
                            typeof previousFocus.focus ===
                            "function" &&
                            document.contains(
                                previousFocus
                            )
                        ) {

                            previousFocus.focus();
                        }

                    },
                    200
                );


                resolve(
                    result
                );
            }


            function handleKeydown(
                event
            ) {

                if (
                    event.key ===
                    "Escape"
                ) {

                    event.preventDefault();

                    finish(
                        false
                    );

                    return;
                }


                if (
                    event.key ===
                    "Enter"
                ) {

                    event.preventDefault();

                    finish(
                        true
                    );
                }
            }


            root.addEventListener(
                "click",
                event => {

                    const accept =
                        event.target.closest(
                            "[data-primeway-confirm-accept]"
                        );


                    if (accept) {

                        finish(
                            true
                        );

                        return;
                    }


                    const cancel =
                        event.target.closest(
                            "[data-primeway-confirm-cancel]"
                        );


                    if (cancel) {

                        finish(
                            false
                        );
                    }
                }
            );


            document.addEventListener(
                "keydown",
                handleKeydown
            );


            window.requestAnimationFrame(
                () => {

                    root.classList.add(
                        "show"
                    );


                    cancelButton.focus();
                }
            );
        }
    );
}


window.PrimeWayConfirm =
    Object.freeze({

        show:
            primewayConfirm,


        async info(
            message,
            options = {}
        ) {

            return primewayConfirm(
                message,
                {
                    ...options,
                    type: "info"
                }
            );
        },


        async warning(
            message,
            options = {}
        ) {

            return primewayConfirm(
                message,
                {
                    ...options,
                    type: "warning"
                }
            );
        },


        async danger(
            message,
            options = {}
        ) {

            return primewayConfirm(
                message,
                {
                    ...options,
                    type: "danger"
                }
            );
        }
    });



    window.PrimeWay = Object.freeze({
        csrfStorageKey: CSRF_STORAGE_KEY,
        fetch: window.fetch
    });


    // Saudação segura, compartilhada entre as páginas iniciais dos cinco perfis.
    const homeGreetings = Object.freeze({
        admin: ["#dashboardTitle", "Administrador"],
        professor: ["#professorGreeting", "Professor(a)"],
        aluno: ["#studentGreeting", "Aluno(a)"],
        responsavel: ["#guardianGreeting", "Responsável"],
        secretaria: ["#secretaryGreeting", "Secretaria"]
    });
    window.PrimeWayGreeting = Object.freeze({
        render(role, name) {
            const config = homeGreetings[role];
            if (!config) return;
            const heading = document.querySelector(config[0]);
            if (!heading) return;
            const registeredName = String(name ?? "").trim().replace(/\s+/g, " ");
            heading.textContent = `Olá, ${config[1]}${registeredName ? " " + registeredName : ""}!`;
        }
    });

    // Componente universal: cada conta acessa a mesma página protegida /pages/perfil.html.
    // O servidor usa a sessão/MySQL; nenhum ID de outro usuário entra nas requisições.
    function primewayMountUserProfile() {
        if (!document.querySelector("aside.sidebar")) return;
        const stylesheet = document.createElement("link");
        stylesheet.rel = "stylesheet";
        stylesheet.href = "../css/perfil_global.css?v=20261027-1";
        document.head.appendChild(stylesheet);
        let profile=null,version=0;
        const roles={admin:"Administrador",professor:"Professor(a)",aluno:"Aluno(a)",responsavel:"Responsável",secretaria:"Secretaria"};
        const initials=name=>String(name||"PW").trim().split(/\s+/).slice(0,2).map(s=>s.charAt(0)).join("").toLocaleUpperCase("pt-BR");
        function avatar(img, fallback) {
            if (!profile?.hasPhoto) {if(img)img.hidden=true;if(fallback)fallback.hidden=false;return}
            if(fallback)fallback.hidden=true;
            if(img){
                img.hidden=false;
                img.onerror=()=>{img.hidden=true;if(fallback)fallback.hidden=false};
                img.src="../api/perfil/foto.php?v="+version;
            }
        }
        function mountSidebar() {
            const sidebar=document.querySelector("aside.sidebar"),nav=sidebar?.querySelector(".sidebar-menu");
            if(!sidebar||!nav||!profile)return;
            let link=nav.querySelector('a[href="perfil.html"]');
            if(!link) {
                link=document.createElement("a");link.href="perfil.html";
                const i=document.createElement("i");i.className="fa-solid fa-user-pen";i.setAttribute("aria-hidden","true");
                const label=document.createElement("span");label.textContent="Perfil";
                link.append(i,label);nav.append(link);
            }
            if(location.pathname.endsWith("/perfil.html")){link.classList.add("active");link.setAttribute("aria-current","page")}
            let badge=sidebar.querySelector(".primeway-side-profile");
            if(!badge){
                badge=document.createElement("a");badge.href="perfil.html";badge.className="primeway-side-profile";
                badge.setAttribute("aria-label","Abrir meu perfil");
                const avatarContainer=document.createElement("span");avatarContainer.className="primeway-avatar";
                const placeholder=document.createElement("span");placeholder.className="primeway-avatar-initials";
                const photo=document.createElement("img");photo.alt="Foto do perfil";photo.hidden=true;
                avatarContainer.append(placeholder,photo);
                const info=document.createElement("span");info.className="primeway-identity";
                const name=document.createElement("strong"),role=document.createElement("small");
                info.append(name,role);badge.append(avatarContainer,info);
                const bottom=sidebar.querySelector(".sidebar-bottom");
                if(bottom)sidebar.insertBefore(badge,bottom);else sidebar.append(badge);
            }
            const name=badge.querySelector("strong"),role=badge.querySelector("small");
            if(name.textContent!==profile.name)name.textContent=profile.name;
            if(role.textContent!==(roles[profile.role]||profile.role))role.textContent=roles[profile.role]||profile.role;
            const initial=badge.querySelector(".primeway-avatar-initials");
            if(initial.textContent!==initials(profile.name))initial.textContent=initials(profile.name);
            const img=badge.querySelector(".primeway-avatar img");
            if(profile.hasPhoto && img.dataset.version!==String(version)){
                img.dataset.version=String(version);avatar(img,initial);
            } else if(!profile.hasPhoto)avatar(img,initial);
        }
        const legacySelectors=".usuario > img, #dashboardUserName, #secretaryName, .secretary-profile-icon";
        function mountExisting() {
            document.querySelectorAll(legacySelectors).forEach(node=>{
                if(node.dataset.primewayProfileLink==="1")return;
                node.dataset.primewayProfileLink="1";node.setAttribute("role","link");node.tabIndex=0;
                node.setAttribute("title","Abrir meu perfil");
                node.addEventListener("click",()=>location.assign("perfil.html"));
                node.addEventListener("keydown",event=>{if(event.key==="Enter"||event.key===" "){event.preventDefault();location.assign("perfil.html")}});
            });
            const oldName=document.querySelector("#dashboardUserName,#secretaryName");
            if(oldName && oldName.textContent.trim()!==profile.name)oldName.textContent=profile.name;
            const oldAvatar=document.querySelector(".usuario > img");
            if(oldAvatar&&profile.hasPhoto)oldAvatar.src="../api/perfil/foto.php?v="+version;
        }
        async function refresh(){
            try{
                const response=await fetch("../api/perfil/index.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
                if(!response.ok)return;
                const payload=await response.json();
                if(!payload.success||!payload.profile)return;
                profile=payload.profile;version++;
                mountSidebar();mountExisting();
                window.PrimeWayGreeting.render(profile.role, profile.name);
            }catch(error){console.debug("Perfil indisponível.",error)}
        }
        const sidebar=document.querySelector("aside.sidebar");
        const watcher=new MutationObserver(()=>{
            if(profile && (!sidebar.querySelector(".primeway-side-profile")||!sidebar.querySelector('.sidebar-menu a[href="perfil.html"]')))mountSidebar();
        });
        watcher.observe(sidebar,{childList:true,subtree:true});
        window.addEventListener("pagehide",()=>watcher.disconnect(),{once:true});
        window.addEventListener("primeway:profile-updated",refresh);
        refresh();
    }
    document.addEventListener("DOMContentLoaded",primewayMountUserProfile);
})();
