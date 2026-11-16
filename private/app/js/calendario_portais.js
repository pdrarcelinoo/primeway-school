(function(){
"use strict";
document.addEventListener("DOMContentLoaded",async()=>{
 const role=document.body.dataset.perfil;
 const portal=role==="aluno"?window.PrimeWayAluno:window.PrimeWayResponsavel;
 const session=role==="aluno"?await portal.ensureStudent():await portal.ensureGuardian();
 if(!session)return;
 portal.bindLogout();
 const api="../api/"+role+"/calendario/index.php";
 const $=id=>document.getElementById(id);
 const main=$("calendarGrid"),heading=$("calendarMonth"),entries=$("calendarAgenda"),status=$("calendarStatus");
 const weekdays=["Dom","Seg","Ter","Qua","Qui","Sex","Sáb"];
 $("weekdays").replaceChildren(...weekdays.map(x=>{let d=document.createElement("span");d.textContent=x;return d}));
 let month=new Date(),events=[];
 month=new Date(month.getFullYear(),month.getMonth(),1);
 const ymd=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,"0"),String(d.getDate()).padStart(2,"0")].join("-");
 const label=d=>d.toLocaleDateString("pt-BR",{month:"long",year:"numeric"});
 const formatDate=s=>new Date(s+"T12:00:00").toLocaleDateString("pt-BR",{weekday:"long",day:"numeric",month:"long"});
 function eventCard(ev){
   const card=document.createElement("article");card.className="pwcal-item";
   const tag=document.createElement("span");tag.className="pwcal-type";tag.textContent=ev.type||"Evento";
   const title=document.createElement("strong");title.textContent=ev.title||"Sem título";
   const meta=document.createElement("p");
   const timing=[formatDate(ev.date),ev.timeStart?ev.timeStart+(ev.timeEnd?" – "+ev.timeEnd:""):"",ev.className||"Toda a escola",ev.location||""].filter(Boolean);
   meta.textContent=timing.join(" • ");
   card.append(tag,title,meta);
   if(ev.description){const detail=document.createElement("p");detail.className="pwcal-desc";detail.textContent=ev.description;card.append(detail)}
   return card;
 }
 function agenda(items){
   entries.replaceChildren();
   if(!items.length){const p=document.createElement("p");p.className="pwcal-empty";p.textContent="Não há eventos para esta seleção.";entries.append(p);return}
   items.forEach(item=>entries.append(eventCard(item)));
 }
 function render(){
   heading.textContent=label(month).replace(/^./,c=>c.toUpperCase());
   main.replaceChildren();
   const first=new Date(month.getFullYear(),month.getMonth(),1);
   const offset=first.getDay();
   const days=new Date(month.getFullYear(),month.getMonth()+1,0).getDate();
   for(let i=0;i<offset;i++){const blank=document.createElement("div");blank.className="pwcal-day blank";blank.setAttribute("aria-hidden","true");main.append(blank)}
   for(let d=1;d<=days;d++){
     const date=ymd(new Date(month.getFullYear(),month.getMonth(),d));
     const dayEvents=events.filter(e=>e.date===date);
     const day=document.createElement("button");day.type="button";day.className="pwcal-day";
     if(date===ymd(new Date()))day.classList.add("today");
     if(dayEvents.length)day.classList.add("has-event");
     const number=document.createElement("span");number.className="pwcal-number";number.textContent=d;
     const count=document.createElement("span");count.className="pwcal-count";count.textContent=dayEvents.length?dayEvents.length+" evento"+(dayEvents.length>1?"s":""):"";
     day.append(number,count);
     day.setAttribute("aria-label",d+" de "+label(month)+", "+dayEvents.length+" eventos");
     day.onclick=()=>{main.querySelectorAll(".selected").forEach(el=>el.classList.remove("selected"));day.classList.add("selected");status.textContent=formatDate(date);agenda(dayEvents)};
     main.append(day);
   }
   status.textContent=events.length+" evento(s) no mês";
   agenda(events);
 }
 async function load(){
   const key=ymd(month).slice(0,7);
   status.textContent="Carregando eventos...";
   const {response,data}=await portal.request(api+"?mes="+encodeURIComponent(key));
   if(!response.ok||!data?.success)throw Error(data?.message||"Não foi possível carregar o calendário.");
   events=Array.isArray(data.events)?data.events:[];
   render();
 }
 async function move(n){month=new Date(month.getFullYear(),month.getMonth()+n,1);try{await load()}catch(err){events=[];render();status.textContent=err.message}}
 $("previousMonth").onclick=()=>move(-1);$("nextMonth").onclick=()=>move(1);
 $("todayMonth").onclick=()=>{month=new Date(new Date().getFullYear(),new Date().getMonth(),1);move(0)};
 try{await load()}catch(err){status.textContent=err.message;agenda([])}
});
})();