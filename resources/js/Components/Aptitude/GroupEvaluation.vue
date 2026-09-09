<script setup>
import { ref,computed,watch,onBeforeUnmount } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import ScoreItems from './ScoreItems.vue';
const props=defineProps({groupId:Number,profileId:Number,participantIds:{type:Array,default:()=>[]}});
const active=computed(()=>usePage().props.currentProjekt?.features?.aptitude_tests===true && !!props.profileId);
const data=ref(null),personId=ref(null),form=ref(null),busy=ref(false),error=ref(''),message=ref(''),draft=ref(null),aiBusy=ref(false);
const statusLabels={started:'Begonnen',completed:'Abgeschlossen',approved:'Fachlich freigegeben'};
const aiTarget=ref(null),aiProgress=ref(0),aiError=ref('');
const reportSectionLabel=section=>section?.key==='german'?'Deutschkenntnisse':section?.label||'';
const aiTargetLabel=computed(()=>`${reportSectionLabel(data.value?.profile?.definition.sections.find(s=>s.key===aiTarget.value?.section))} · ${reportFields[aiTarget.value?.field]?.label||''}`);
function showAiField(){activeTab.value='report';requestAnimationFrame(()=>document.getElementById(`aptitude-${props.groupId}-${aiTarget.value.section}-${aiTarget.value.field}`)?.scrollIntoView({behavior:'smooth',block:'center'}));}
const activeTab=ref('evaluation');
const tabs=[{key:'evaluation',label:'Auswertung'},{key:'report',label:'Bericht'}];
const reportFields={
 observation:{label:'Beobachtungen',hint:'Was war während der Bearbeitung konkret zu sehen oder zu hören? Beschreiben Sie Arbeitsweise, Rückfragen und benötigte Hilfen. Halten Sie nur tatsächlich Beobachtetes fest; kurze Stichpunkte sind möglich.'},
 assessment:{label:'Einschätzung',hint:'Was zeigen die Einzelwerte und Beobachtungen über die geprüften Fähigkeiten? Benennen Sie Stärken und Schwierigkeiten in 2–4 sachlichen Sätzen und beziehen Sie sich auf konkrete Aufgaben. Vermeiden Sie pauschale Aussagen über die Person.'},
 support_need:{label:'Förderbedarf',hint:'Bei welchen konkreten Fähigkeiten wird Unterstützung benötigt? Beschreiben Sie, was geübt oder gefestigt werden soll, und begründen Sie dies mit den Ergebnissen. Wenn kein Bedarf erkennbar ist, halten Sie das ausdrücklich fest.'},
 next_steps:{label:'Förderziel / nächste Schritte',hint:'Was soll bis wann besser gelingen und wie wird daran gearbeitet? Nennen Sie ein überprüfbares Ziel, die geplante Übung, Häufigkeit und Zuständigkeit sowie einen Termin zur Überprüfung.'},
};
const reportExamples={
 german:{observation:'Bei der Schreibaufgabe fragte die teilnehmende Person zweimal nach der Aufgabenstellung. Der Text enthielt mehrere fehlende Satzzeichen. Nach einem Hinweis wurden zwei Satzenden selbstständig ergänzt.',assessment:'Das Erkennen von Wortbedeutungen gelang überwiegend sicher. Im eigenen Text traten wiederholt Fehler bei der Satzzeichensetzung auf. Hier besteht noch Übungsbedarf.',support_need:'Satzgrenzen erkennen und Satzzeichen sicher setzen. Die wiederholten Auslassungen im eigenen Text zeigen, dass die Anwendung noch gefestigt werden sollte.',next_steps:'Ziel für die nächsten vier Wochen: In zwei kurzen Übungstexten jeweils mindestens 8 von 10 Satzenden richtig markieren. Die Lehrkraft übt dies zweimal wöchentlich für 15 Minuten und prüft den Stand nach vier Wochen.'},
 math:{observation:'Bei der schriftlichen Subtraktion wurde der Übertrag mehrfach ausgelassen. Nach einem Hinweis notierte die teilnehmende Person die Zwischenschritte und löste die nächste Aufgabe selbstständig.',assessment:'Einfache Additionsaufgaben wurden sicher gelöst. Bei der schriftlichen Subtraktion mit Übertrag traten wiederholt Fehler auf. Das Rechenverfahren ist noch nicht sicher verfügbar.',support_need:'Die schriftliche Subtraktion mit Übertrag festigen und eine Kontrolle der Zwischenschritte einüben. Dies knüpft an die wiederholten Übertragsfehler im Test an.',next_steps:'Ziel für die nächsten vier Wochen: Mindestens 8 von 10 vergleichbaren Subtraktionsaufgaben mit Übertrag selbstständig richtig lösen. Die Lehrkraft begleitet zweimal wöchentlich 15 Minuten Übung; nach vier Wochen folgt eine kurze Lernstandskontrolle.'},
 default:{observation:'Bei einer mehrteiligen Aufgabe wurden einzelne Arbeitsschritte ausgelassen. Nach einer Rückfrage wurde der nächste Schritt selbstständig bearbeitet.',assessment:'Einzelne Arbeitsschritte gelangen selbstständig. Bei mehrteiligen Aufgaben war Unterstützung nötig, um die Reihenfolge einzuhalten.',support_need:'Die selbstständige Planung und Kontrolle mehrteiliger Aufgaben üben. Grundlage sind die beobachteten ausgelassenen Arbeitsschritte.',next_steps:'In den nächsten vier Wochen einmal wöchentlich eine dreiteilige Aufgabe mit einer Checkliste üben. Die zuständige Fachkraft prüft anschließend, ob alle Schritte selbstständig durchgeführt werden.'},
};
const reportExample=(section,field)=>(reportExamples[section.key]||reportExamples.default)[field];
const reportFieldId=(section,field)=>`aptitude-${props.groupId}-${section.key}-${field}`;
const selectedPerson=computed(()=>data.value?.participants.find(p=>Number(p.id)===Number(personId.value)));
const copy=x=>JSON.parse(JSON.stringify(x));
const participation=computed(()=>data.value?.participations.find(p=>Number(p.personen_id)===Number(personId.value)));
const attempts=computed(()=>data.value?.attempts.filter(a=>Number(a.project_person_id)===Number(participation.value?.id))||[]);
const locked=computed(()=>!data.value?.can_update||form.value?.status==='approved');
const explain=e=>Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||'Die Aktion konnte nicht abgeschlossen werden.';
let loadVersion=0;
async function load(){const version=++loadVersion;error.value='';try{const response=await axios.get(route('aptitude.group',props.groupId));if(cancelled||version!==loadVersion)return;data.value=response.data;if(!selectedPerson.value)selectPerson(data.value.participants[0]?.id??null);}catch(e){if(version===loadVersion)error.value=explain(e);}}
function selectPerson(id=personId.value){if(form.value&&Number(id)===Number(personId.value))return;personId.value=id;form.value=null;draft.value=null;if(attempts.value.length)edit(attempts.value[0]);else if(personId.value)newAttempt();}
function edit(attempt){form.value=copy(attempt);prepare();draft.value=null;message.value='';error.value='';}
function prepare(){for(const s of data.value.profile.definition.sections){form.value.scores[s.key] ||= {};form.value.reports[s.key] ||= {observation:'',assessment:'',support_need:'',next_steps:''};}}
function newAttempt(){form.value={id:null,revision:0,tested_on:new Date().toLocaleDateString('sv-SE'),status:'started',scores:{},reports:{},results:{}};prepare();draft.value=null;message.value='';}
async function save(status){busy.value=true;error.value='';message.value='';try{const {data:response}=await axios.post(route('aptitude.attempt.save',[props.groupId,personId.value]),{...form.value,status});form.value=copy(response.attempt);prepare();await load();message.value=status==='approved'?'Fachlich freigegeben. Die Angaben können in der LuV verwendet werden.':'Auswertung gespeichert.';return true;}catch(e){error.value=explain(e);return false;}finally{busy.value=false;}}
let cancelled=false;let timer=null;let pollGeneration=0;
onBeforeUnmount(()=>{cancelled=true;pollGeneration++;clearTimeout(timer);});
async function generate(section,field){if(aiBusy.value)return;aiBusy.value=true;draft.value=null;aiTarget.value={section,field};aiProgress.value=0;aiError.value='';const generation=++pollGeneration;
 if(!await save(form.value.status)){aiBusy.value=false;aiError.value=error.value;return;}
 const attemptId=form.value.id,revision=form.value.revision;
 try {const {data:run}=await axios.post(route('aptitude.generate',attemptId),{section,field});let checks=0;
  const poll=async()=>{if(cancelled||generation!==pollGeneration)return;try{const {data:r}=await axios.get(route('aptitude.generate.status',[attemptId,run.run_id]));aiProgress.value=Math.min(100,Math.max(0,Number(r.progress_percent)||0));if(r.status==='completed'){draft.value={section,field,text:r.content,attemptId,revision};aiBusy.value=false;return;}if(r.status==='failed'||++checks>220){error.value='Die KI konnte keinen Entwurf erstellen. Deine Eingaben sind gespeichert.';aiError.value=error.value;aiBusy.value=false;return;}timer=setTimeout(poll,3000);}catch(e){error.value=explain(e);aiError.value=error.value;aiBusy.value=false;}};await poll();
 }catch(e){error.value=explain(e);aiError.value=error.value;aiBusy.value=false;}}
function acceptDraft(){if(draft.value.attemptId!==form.value.id||draft.value.revision!==form.value.revision){error.value='Der Entwurf gehört zu einem anderen Bearbeitungsstand. Bitte erneut erzeugen.';return;}form.value.reports[draft.value.section][draft.value.field]=draft.value.text;draft.value=null;message.value='Entwurf übernommen. Bitte fachlich prüfen und speichern.';}
watch(()=>[active.value,props.groupId,props.participantIds.map(Number).sort((a,b)=>a-b).join(',')],()=>{if(active.value)load();},{immediate:true});
</script>
<template>
 <section v-if="active" class="my-6 rounded-xl border bg-white p-5 shadow-sm">
  <h2 class="text-xl font-semibold">Eignungstest</h2>
  <p v-if="error" role="alert" class="my-3 rounded bg-red-50 p-3 text-red-800">{{error}}</p>
  <p v-if="message" role="status" class="my-3 text-green-800">{{message}}</p>
  <template v-if="data?.profile">
   <p v-if="!data.participants.length" class="mt-4 text-sm text-gray-500">Bitte zuerst Teilnehmer zur Gruppe hinzufügen.</p>
   <div v-else class="mt-4 grid items-start gap-4 lg:grid-cols-[260px_1fr]">
    <div class="rounded border border-gray-200 bg-white" aria-label="Teilnehmer im Eignungstest">
     <button v-for="p in data.participants" :key="p.id" type="button" :disabled="busy||aiBusy" :aria-pressed="Number(personId)===Number(p.id)" class="flex w-full items-center justify-between border-b border-gray-100 px-4 py-3 text-left text-sm last:border-b-0 hover:bg-gray-50 disabled:opacity-50" :class="Number(personId)===Number(p.id)?'bg-zbbTrp text-zbb':'text-gray-700'" @click="selectPerson(p.id)">
      <span>{{p.vorname}} {{p.nachname}}</span><i class="la la-chevron-right text-xs" aria-hidden="true"></i>
     </button>
    </div>
    <div v-if="form" class="min-w-0 space-y-4">
     <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
      <h3 class="font-semibold">{{selectedPerson?.vorname}} {{selectedPerson?.nachname}}</h3>
      <p class="text-xs text-gray-500">{{data.profile.name}} · Version {{data.profile.version}} · {{statusLabels[form.status]}}</p>
      <div class="mt-3 flex flex-wrap items-end gap-3">
       <label class="text-sm">Testdatum<input v-model="form.tested_on" :disabled="locked||busy||aiBusy" type="date" class="mt-1 block rounded border-gray-300"/></label>
       <label v-if="attempts.length" class="text-sm">Durchführung<select :value="form.id||''" :disabled="busy||aiBusy" class="mt-1 block rounded border-gray-300" @change="edit(attempts.find(a=>Number(a.id)===Number($event.target.value)))"><option v-if="!form.id" value="">Neue Durchführung</option><option v-for="a in attempts" :key="a.id" :value="a.id">{{a.tested_on.slice(0,10)}} · {{statusLabels[a.status]}}</option></select></label>
       <button v-if="attempts.length&&data.can_update" :disabled="busy||aiBusy" class="rounded border px-3 py-2 text-sm" @click="newAttempt">Neue Durchführung / Wiederholung</button>
      </div>
     </div>
     <div role="tablist" aria-label="Eignungstest bearbeiten" class="flex gap-2 border-b border-gray-200 pb-2">
      <button v-for="tab in tabs" :key="tab.key" :id="`aptitude-${groupId}-${tab.key}`" type="button" role="tab" :aria-selected="activeTab===tab.key" :aria-controls="`aptitude-${groupId}-${tab.key}-panel`" class="rounded-lg px-4 py-2 text-sm font-medium" :class="activeTab===tab.key?'bg-zbb text-white':'bg-gray-50 text-gray-700 hover:bg-gray-100'" @click="activeTab=tab.key">{{tab.label}}</button>
     </div>
     <p v-if="form.status==='approved'" class="text-sm text-green-800">Fachlich freigegeben. Für Änderungen bitte eine neue Durchführung anlegen.</p>
     <div v-show="activeTab==='evaluation'" role="tabpanel" :id="`aptitude-${groupId}-evaluation-panel`" :aria-labelledby="`aptitude-${groupId}-evaluation`">
      <p class="text-sm text-gray-500">Fehlende oder nicht bearbeitete Kriterien bleiben leer.</p>
      <div v-for="section in data.profile.definition.sections" :key="section.key" class="mt-4 rounded-lg border p-4">
       <h4 class="text-lg font-semibold">{{section.label}}</h4>
       <div v-if="form.results?.[section.key]" class="my-2 text-sm"><strong>{{form.results[section.key].points}} / {{form.results[section.key].max}}</strong><span v-if="form.results[section.key].complete"> · {{form.results[section.key].percent}} %</span><span v-else> · unvollständig</span><span v-if="form.results[section.key].grade"> · Note {{form.results[section.key].grade}}</span><p class="text-xs text-gray-500">Berechneter Stand der letzten Speicherung.</p></div>
       <ScoreItems :items="section.items" :scores="form.scores[section.key]" :disabled="locked||busy||aiBusy"/>
      </div>
      <div v-if="!locked" class="mt-4 flex flex-wrap gap-3"><button :disabled="busy||aiBusy" class="rounded bg-zbb px-4 py-2 text-white disabled:opacity-50" @click="save('started')">Auswertung speichern</button><button :disabled="busy||aiBusy" class="rounded border px-4 py-2 disabled:opacity-50" @click="save('completed')">Test abschließen</button></div>
     </div>
     <div v-show="activeTab==='report'" role="tabpanel" :id="`aptitude-${groupId}-report-panel`" :aria-labelledby="`aptitude-${groupId}-report`">
      <p class="mb-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">Schreiben Sie kurz, konkret und wertschätzend. Beziehen Sie Aussagen auf diesen Test. Die Formulierungsbeispiele sind fiktiv und dienen nur als Schreibhilfe. KI-Entwürfe bitte anhand der tatsächlichen Ergebnisse und Beobachtungen prüfen.</p>
      <div v-for="section in data.profile.definition.sections" :key="section.key" class="mb-4 rounded-lg border p-4">
       <h4 class="text-lg font-semibold">{{reportSectionLabel(section)}}</h4>
       <p v-if="form.results?.[section.key]?.below_threshold" class="mt-2 text-sm text-amber-800">Unter der Hinweisgrenze. Förderbedarf anhand der Einzelwerte prüfen.</p>
       <div class="mt-4 grid gap-5 xl:grid-cols-2">
        <div v-for="(info,field) in reportFields" :key="field" class="text-sm">
         <label :for="reportFieldId(section,field)" class="font-semibold">{{info.label}} <span v-if="['observation','next_steps'].includes(field)" class="font-normal text-gray-500">(optional)</span></label>
         <p :id="reportFieldId(section,field)+'-hint'" class="mt-1 text-xs leading-relaxed text-gray-600">{{info.hint}}</p>
         <details class="my-2 text-xs text-gray-600"><summary class="cursor-pointer text-zbb">Formulierungsbeispiel anzeigen</summary><p class="mt-2 rounded bg-gray-50 p-2 leading-relaxed"><strong>Fiktives Beispiel:</strong> {{reportExample(section,field)}}</p></details>
         <textarea :id="reportFieldId(section,field)" v-model="form.reports[section.key][field]" :aria-describedby="reportFieldId(section,field)+'-hint'" :disabled="locked||busy||aiBusy" maxlength="6000" rows="4" class="mt-1 w-full rounded border-gray-300"/>
         <button v-if="!locked&&['assessment','support_need'].includes(field)" :disabled="busy||aiBusy" class="text-xs text-zbb underline disabled:opacity-50" @click="generate(section.key,field)">{{aiBusy&&aiTarget?.section===section.key&&aiTarget?.field===field?'KI erstellt den Entwurf …':'KI-Entwurf erstellen'}}</button>
         <div v-if="draft?.section===section.key&&draft?.field===field" class="my-3 rounded border border-blue-200 bg-blue-50 p-3"><h5 class="font-semibold">KI-Entwurf · fachlich prüfen</h5><p class="my-3 whitespace-pre-wrap">{{draft.text}}</p><button class="rounded bg-zbb px-3 py-2 text-white" @click="acceptDraft">In das Feld übernehmen</button><button class="ml-3" @click="draft=null">Verwerfen</button></div>
        </div>
       </div>
      </div>
      <div v-if="!locked" class="flex flex-wrap gap-3"><button :disabled="busy||aiBusy" class="rounded bg-zbb px-4 py-2 text-white disabled:opacity-50" @click="save(form.status)">Bericht speichern</button><button :disabled="busy||aiBusy" class="rounded bg-green-700 px-4 py-2 text-white disabled:opacity-50" @click="save('approved')">Fachlich freigeben</button></div>
      <p class="mt-2 text-xs text-gray-500">Fachlich freigegebene Einschätzungen und Förderbedarfe können in der LuV verwendet werden.</p>
     </div>
    </div>
   </div>
  </template>
 </section>
 <Teleport to="body">
  <aside v-if="aiBusy||draft||aiError" role="status" aria-live="polite" class="fixed bottom-4 right-4 z-[100] w-96 max-w-[calc(100vw-2rem)] rounded-xl border border-blue-200 bg-white p-4 text-sm shadow-xl">
   <p class="font-semibold">{{aiBusy?'KI-Entwurf wird erstellt …':aiError?'KI-Entwurf konnte nicht erstellt werden':'KI-Entwurf ist fertig'}}</p>
   <p class="mt-1 text-gray-600">{{aiTargetLabel}}</p>
   <template v-if="aiBusy"><p class="mt-2">{{aiProgress>0?`Bearbeitungsstand: ${aiProgress} %`:'Angaben werden gespeichert und die Generierung wird vorbereitet …'}}</p><p class="mt-1 text-xs text-gray-500">Dies kann einige Minuten dauern. Sie können auf der Seite weiter scrollen.</p></template>
   <p v-if="aiError" class="mt-2 text-red-700">{{aiError}}</p>
   <button v-if="draft" class="mt-3 rounded bg-zbb px-3 py-2 text-white" @click="showAiField">Entwurf am Feld ansehen</button>
   <button v-if="aiError" class="mt-3 underline" @click="aiError=''">Schließen</button>
  </aside>
 </Teleport>
</template>
