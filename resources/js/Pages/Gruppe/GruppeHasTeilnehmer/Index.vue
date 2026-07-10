<script setup>
    import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
    import { Head, Link, router } from '@inertiajs/vue3'
    import AppLayout from '@/Layouts/AppLayout.vue'
    import InputText from 'primevue/inputtext'
    import FloatLabel from 'primevue/floatlabel'
    import Select from 'primevue/select'
    import MultiSelect from 'primevue/multiselect'
    import Dialog from 'primevue/dialog'
    import Button from 'primevue/button' // ✅ FEHLTE
    import Swal from 'sweetalert2'
    import axios from 'axios' // ✅ FEHLTE
    import { formatTime } from '@/utils/timeFormat'

    // --- Props ---
    const props = defineProps({
    gruppe: { type: Object, required: true },
    teilnehmer: { type: Array, required: true },
    anwesenheitsstatuten: { type: Array, required: true },
    bopLegacyExporte: { type: Array, default: () => [] },
    potenzialanalyse: { type: Object, default: () => ({ aktiv: false, tage: null, uebungen: [], teilnehmer: {} }) },
    })
    console.log('Props:', props.gruppe    )
    // Modal-Steuerung + Auswahl
    const showTeilnehmerModal = ref(false)
    const showExportDialog = ref(false)
    const exportSuche = ref('')
    const legacyExportLoading = ref(null)
    const selectedTeilnehmerIds = ref([])
    const isSubmittingTeilnehmer = ref(false)
    const paTeilnehmerDaten = ref(JSON.parse(JSON.stringify(props.potenzialanalyse?.teilnehmer || {})))
    const selectedPaTeilnehmerId = ref(null)
    const paSaving = ref(false)
    const paAutoSaveStatus = ref('idle')
    const paAutoSaveBereit = ref(false)
    const paAutoSaveTimers = new Map()
    const paSaveVersions = new Map()
    const paSaveInFlight = new Set()
    const paSavePending = new Set()
    const paDirtyTeilnehmerIds = new Set()
    const klassenbuchErlaubt = computed(() => Boolean(props.gruppe?.projekt?.klassenbuch_aktiv))
    const erstesKlassenbuch = computed(() => props.gruppe?.klassenbuecher?.[0] || null)
    const klassenbuchHref = computed(() =>
      erstesKlassenbuch.value
        ? route('klassenbuch.show', erstesKlassenbuch.value.id)
        : route('klassenbuch.index', { gruppe_id: props.gruppe.id })
    )

    // --- Hilfsfunktion für Farben je nach Status ---
const statusFarbe = (statusName) => {
  if (!statusName) return { backgroundColor: '#d1d5db' } // grau fallback

  const item = props.anwesenheitsstatuten.find(
    s => s.status?.toLowerCase() === statusName.toLowerCase()
  );

  return item?.farben
    ? { backgroundColor: item.farben }  // ← HEX Wert aus DB!
    : { backgroundColor: '#d1d5db' };
}




const zeitgeplantStart = ref();
const zeitgeplantEnd = ref();
const tatstartTime  = ref();
const tatendTime = ref();
const datumgeplantStart = ref();
const datumgeplantEnd = ref();

zeitgeplantStart.value = formatTime(props.gruppe.startzeit);
zeitgeplantEnd.value = formatTime(props.gruppe.endzeit);
datumgeplantStart.value = props.gruppe.anfangsdatum;
datumgeplantEnd.value =props.gruppe.enddatum;


// Funktion, um nach Klick auf „Übernehmen“ die ausgewählten Teilnehmer hinzuzufügen
const confirmTeilnehmer = async () => {
  if (isSubmittingTeilnehmer.value) {
    return;
  }

  if (selectedTeilnehmerIds.value.length === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Keine Auswahl',
      text: 'Bitte wähle mindestens einen Teilnehmer aus.',
    });
    return;
  }

  isSubmittingTeilnehmer.value = true;

  try {
    const response = await axios.post(route('gruppeHasTeilnehmer.store'), {
      gruppe_id: props.gruppe.id,
      teilnehmer: selectedTeilnehmerIds.value,
      startzeit: zeitgeplantStart.value,
      endzeit: zeitgeplantEnd.value,
      startdatum: datumgeplantStart.value,
      enddatum: datumgeplantEnd.value,
    });

    const data = response.data;
    console.log('✅ RESPONSE:', data);

    // --- Modal zuerst schließen ---
    showTeilnehmerModal.value = false;
    selectedTeilnehmerIds.value = [];

    // --- Jetzt DOM-Update abwarten, bevor SweetAlert geöffnet wird ---
    await new Promise(resolve => setTimeout(resolve, 300));

    // --- SweetAlert anzeigen ---
    let message = data.message;
    if (data.added?.length) {
      message += `\n✅ Hinzugefügt: ${data.added.map(t => `${t.vorname} ${t.nachname}`).join(', ')}`;
    }
    if (data.already?.length) {
      message += `\n⚠️ Bereits vorhanden: ${data.already.map(t => `${t.vorname} ${t.nachname}`).join(', ')}`;
    }

    await Swal.fire({
      icon: 'success',
      title: 'Teilnehmer aktualisiert',
      text: message,
      confirmButtonText: 'OK',
    });

    // --- Tabelle sofort aktualisieren ---
    if (data.added?.length) {
      data.added.forEach(nt => {
        const existiert = gruppenTeilnehmer.value.some(t => t.id === nt.id);
        if (!existiert) {
          gruppenTeilnehmer.value.push({
            ...nt,
            anwesenheit: tage.value.map(() => 'unentschuldigt'),
            zeiten: tage.value.map(() => ({
              start: zeitgeplantStart.value,
              ende: zeitgeplantEnd.value,
            })),
          });
          ensurePaEintrag(nt.id);
          if (!selectedPaTeilnehmerId.value) {
            selectedPaTeilnehmerId.value = nt.id;
          }
        }
      });
    }

  } catch (error) {
    console.error('❌ Fehler:', error);

    showTeilnehmerModal.value = false; // sicherheitshalber
    await new Promise(resolve => setTimeout(resolve, 300));

    await Swal.fire({
      icon: 'error',
      title: 'Fehler',
      text: error.response?.data?.message || 'Teilnehmer konnten nicht hinzugefügt werden.',
    });
  } finally {
    isSubmittingTeilnehmer.value = false;
  }
};



// --- Datumsbereich (inkl. Enddatum) ---
function generateDateRangeInclusive(start, end) {
  const result = []
  const startDate = new Date(start + 'T00:00:00')
  const endDate = new Date(end + 'T00:00:00')

  let current = new Date(startDate)
  let index = 1

  while (current <= endDate) {
    const weekday = current.toLocaleDateString('de-DE', { weekday: 'short' })
    const day = current.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })

    const localDate = [
      current.getFullYear(),
      String(current.getMonth() + 1).padStart(2, '0'),
      String(current.getDate()).padStart(2, '0'),
    ].join('-')

    result.push({
      label: `Tag ${index}`,
      wochentag: weekday,
      kurzdatum: day,
      datum: `${weekday}, ${day}.`,
      date: localDate,
    })

    current.setDate(current.getDate() + 1)
    index++
  }

  return result
}

const tage = computed(() =>
  props.gruppe?.anfangsdatum && props.gruppe?.enddatum
    ? generateDateRangeInclusive(props.gruppe.anfangsdatum, props.gruppe.enddatum)
    : []
)

const selectedWocheIndex = ref(0)

const parseLocalDate = (value) => new Date(`${value}T00:00:00`)

const formatKurzDatum = (value) =>
  parseLocalDate(value).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })

const formatDateKey = (date) => [
  date.getFullYear(),
  String(date.getMonth() + 1).padStart(2, '0'),
  String(date.getDate()).padStart(2, '0'),
].join('-')

const kalenderwoche = (date) => {
  const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
  const dayNumber = target.getUTCDay() || 7
  target.setUTCDate(target.getUTCDate() + 4 - dayNumber)
  const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1))
  return Math.ceil((((target - yearStart) / 86400000) + 1) / 7)
}

const wochen = computed(() => {
  const gruppiert = []

  tage.value.forEach((tag, index) => {
    const date = parseLocalDate(tag.date)
    const monday = new Date(date)
    const weekday = monday.getDay() || 7
    monday.setDate(monday.getDate() - weekday + 1)
    const key = formatDateKey(monday)

    let woche = gruppiert.find((entry) => entry.key === key)
    if (!woche) {
      woche = {
        key,
        kw: kalenderwoche(date),
        start: tag,
        end: tag,
        tage: [],
      }
      gruppiert.push(woche)
    }

    woche.end = tag
    woche.tage.push({ ...tag, index })
  })

  return gruppiert.map((woche, index) => ({
    ...woche,
    value: index,
    label: `KW ${woche.kw} | ${formatKurzDatum(woche.start.date)} - ${formatKurzDatum(woche.end.date)}`,
  }))
})

const aktiveWoche = computed(() =>
  wochen.value[selectedWocheIndex.value] || wochen.value[0] || { tage: [], label: '' }
)

const sichtbareTage = computed(() => aktiveWoche.value?.tage || [])

const wechselWoche = (richtung) => {
  const ziel = selectedWocheIndex.value + richtung
  if (ziel >= 0 && ziel < wochen.value.length) {
    selectedWocheIndex.value = ziel
  }
}

const geheZuAktuellerWoche = () => {
  const heute = formatDateKey(new Date())
  const index = wochen.value.findIndex((woche) =>
    woche.tage.some((tag) => tag.date === heute)
  )

  selectedWocheIndex.value = index >= 0 ? index : 0
}

const exportVorlagen = computed(() =>
  (props.gruppe?.projekt?.dokumente || []).filter((dokument) =>
    dokument.dateipfad &&
    dokument.pivot?.gruppen_export &&
    dokument.pivot?.serienbrief
  )
)

const exportFormate = (dokument) => {
  if (Array.isArray(dokument.ausgabeformate) && dokument.ausgabeformate.length) {
    return dokument.ausgabeformate
  }

  if (dokument.typ === 'excel') return ['xlsx', 'pdf']
  if (dokument.typ === 'pdf') return ['pdf']
  return ['docx', 'pdf']
}

const gefilterteExportVorlagen = computed(() => {
  const suche = exportSuche.value.trim().toLowerCase()
  if (!suche) return exportVorlagen.value

  return exportVorlagen.value.filter((dokument) =>
    [dokument.name, dokument.typ, dokument.kontext, ...(dokument.ausgabeformate || [])]
      .filter(Boolean)
      .some((wert) => String(wert).toLowerCase().includes(suche))
  )
})

const bopLegacyExporte = computed(() => props.bopLegacyExporte || [])
const paAktiv = computed(() => Boolean(props.potenzialanalyse?.aktiv))
const paUebungen = computed(() => props.potenzialanalyse?.uebungen || [])
const paBerichtStatusOptionen = [
  { label: 'Entwurf', value: 'entwurf' },
  { label: 'In Bearbeitung', value: 'in_bearbeitung' },
  { label: 'Fertig', value: 'fertig' },
  { label: 'Geprueft', value: 'geprueft' },
]
const paTabs = [
  { key: 'selbst', label: 'Selbsteinschaetzung' },
  { key: 'uebungen', label: 'Uebungen' },
  { key: 'kompetenzen', label: 'Kompetenzen' },
  { key: 'bericht', label: 'Bericht' },
]
const activePaTab = ref(paTabs[0].key)
const activePaTabIndex = computed(() => {
  const index = paTabs.findIndex((tab) => tab.key === activePaTab.value)
  return index >= 0 ? index : 0
})
const paBewertungWerte = [1, 2, 3, 4, 5]
const paMerkmale = [
  { key: 'feinmotorik', label: 'Feinmotorik', kategorie: 'BP' },
  { key: 'grobmotorik', label: 'Grobmotorik', kategorie: 'BP' },
  { key: 'wahrnehmung_symmetrie', label: 'Wahrnehmung und Symmetrie', kategorie: 'BP' },
  { key: 'analyse_problemloesefaehigkeit', label: 'Analyse- und Problemloesefaehigkeit', kategorie: 'MP' },
  { key: 'arbeitsplanung', label: 'Arbeitsplanung', kategorie: 'MP' },
  { key: 'motivation_leistungsbereitschaft', label: 'Motivation und Leistungsbereitschaft', kategorie: 'PP' },
  { key: 'durchhaltevermoegen', label: 'Durchhaltevermoegen', kategorie: 'PP' },
  { key: 'sorgfalt', label: 'Sorgfalt', kategorie: 'PP' },
  { key: 'kommunikation', label: 'Kommunikation', kategorie: 'SP' },
  { key: 'teamfaehigkeit', label: 'Teamfaehigkeit', kategorie: 'SP' },
  { key: 'umgangsformen', label: 'Umgangsformen', kategorie: 'SP' },
]

const paKompetenzBemerkungTexte = {
  feinmotorik: [
    'Mit Werkzeugen an vorgegebenen Grenzen oder Linien entlang zu arbeiten, ist bedingt möglich; gefühlvoller Werkzeugeinsatz und sichere Steuerung gelingen teilweise.',
    'Mit Werkzeugen an vorgegebenen Grenzen oder Linien entlang zu arbeiten, gelingt teilweise; Werkzeuge werden zunehmend sicherer gesteuert.',
    'Mit Werkzeugen an vorgegebenen Grenzen oder Linien entlang zu arbeiten, gelingt meist; der Werkzeugeinsatz ist überwiegend sicher.',
    'Werkzeuge gefühlvoll und sicher zu steuern, gelingt gut und häufig fehlerfrei.',
    'Werkzeuge gefühlvoll und sicher zu steuern, gelingt sehr gut; in der Regel kann fehlerfrei gearbeitet werden.',
  ],
  grobmotorik: [
    'Kraftvolles und formgebendes Arbeiten mit komplexeren Werkzeugen gelingt bedingt und benötigt Unterstützung.',
    'Kraftvolles und formgebendes Arbeiten mit komplexeren Werkzeugen gelingt teilweise.',
    'Kraftvolles und formgebendes Arbeiten gelingt meist; Werkzeuge können kontrolliert geführt werden.',
    'Kraftvolles und formgebendes Arbeiten gelingt gut; die Werkzeuge werden kontrolliert geführt.',
    'Kraftvolles und formgebendes Arbeiten gelingt sehr gut; Werkzeuge werden kontrolliert und sicher geführt.',
  ],
  wahrnehmung_symmetrie: [
    'Benötigt Unterstützung beim Abschätzen von Abständen und Herstellen von Symmetrien; der Abgleich mit Vorgaben gelingt bedingt.',
    'Das Abschätzen von Abständen, Herstellen von Symmetrien und der Abgleich mit Vorgaben gelingen teilweise.',
    'Das Abschätzen von Abständen, Herstellen von Symmetrien und der Abgleich mit Vorgaben gelingen meist.',
    'Das Abschätzen von Abständen, Herstellen von Symmetrien und der Abgleich mit Vorgaben gelingen gut; Formen werden erkannt.',
    'Das Abschätzen von Abständen, Herstellen von Symmetrien und der Abgleich mit Vorgaben gelingen sehr gut und mit hoher Genauigkeit.',
  ],
  analyse_problemloesefaehigkeit: [
    'Benötigt Unterstützung, um Problemstellungen zu erkennen und Lösungen zu entwickeln; logische Zusammenhänge können bedingt hergestellt werden.',
    'Problemstellungen werden in der Regel erkannt; Lösungen werden teilweise strukturiert und zielgerichtet umgesetzt.',
    'Problemstellungen werden erkannt, logische Zusammenhänge hergestellt und Lösungen strukturiert umgesetzt.',
    'Problemstellungen werden sicher erkannt; Lösungen und alternative Vorgehensweisen werden zielgerichtet entwickelt.',
    'Problemstellungen werden sicher erkannt; auf unterschiedliche Aufgabenstellungen wird angemessen flexibel und lösungsorientiert reagiert.',
  ],
  arbeitsplanung: [
    'Benötigt Unterstützung, um Hilfsmittel vorzubereiten und Aufgaben in sinnvolle Teilschritte zu untergliedern.',
    'Aufgaben können teilweise in sinnvolle Teilschritte untergliedert werden; Hilfsmittel werden zunehmend genutzt.',
    'Aufgaben werden in sinnvolle Teilschritte untergliedert; Hilfsmittel werden vorbereitet und genutzt.',
    'Aufgaben werden strukturiert bearbeitet; auf vorhandenes Wissen kann zugegriffen werden.',
    'Aufgaben werden sehr strukturiert bearbeitet; es besteht Bereitschaft, sich weiteres Wissen anzueignen.',
  ],
  motivation_leistungsbereitschaft: [
    'Setzt sich nach Aufforderung mit gestellten Aufgaben auseinander; das Streben nach guten Ergebnissen ist bedingt vorhanden.',
    'Setzt sich mit Unterstützung mit Aufgaben auseinander und strebt in erkennbarem Maß nach guten Ergebnissen.',
    'Setzt sich mit den gestellten Aufgaben auseinander, strebt nach guten Ergebnissen und erkennt, was dafür nötig ist.',
    'Setzt sich motiviert mit Aufgaben auseinander, strebt nach guten Ergebnissen und entwickelt hierzu eigene Ideen.',
    'Zeigt hohe Motivation und Leistungsbereitschaft auch bei schwierigen Aufgabenstellungen und sucht aktiv neue Herausforderungen.',
  ],
  durchhaltevermoegen: [
    'Benötigt Unterstützung, um Aufgaben bei Schwierigkeiten oder Misserfolgen weiterzubearbeiten.',
    'Beendet Aufgaben in der Regel erst nach Fertigstellung und benötigt kaum Anstöße von außen.',
    'Widmet sich auch schwierigen Aufgaben mit angemessener Intensität und gibt bei Misserfolgen nicht schnell auf.',
    'Bleibt auch bei schwierigen Aufgaben dran und kämpft gegen Motivationsschwankungen an.',
    'Zeigt sehr gutes Durchhaltevermögen; belastende Situationen werden erkannt und Lösungsmöglichkeiten entwickelt.',
  ],
  sorgfalt: [
    'Benötigt Unterstützung, um Aufgaben gewissenhaft zu bearbeiten; fehlerfreies Arbeiten scheint zweitrangig.',
    'Ist um genaues und gewissenhaftes Arbeiten bemüht und geht größtenteils sorgsam mit Materialien um.',
    'Arbeitet gewissenhaft und genau, geht sorgsam mit Materialien um und überprüft die Qualität meist.',
    'Arbeitet genau, kontrolliert Ergebnisse, korrigiert Fehler und hält sich an Hinweise und Vorschriften.',
    'Arbeitet außerordentlich gewissenhaft und genau; Ergebnisse werden mit den vorgegebenen Zielen überprüft.',
  ],
  kommunikation: [
    'Benötigt Unterstützung bei der Kontaktaufnahme, beim Ausdruck eigener Gedanken und beim angemessenen Reagieren auf Botschaften.',
    'Kontaktaufnahme, Interpretation von Botschaften und angemessenes Reagieren sind weitgehend vorhanden.',
    'Kontaktaufnahme, Interpretation von Botschaften und angemessenes Reagieren sind deutlich erkennbar.',
    'Kommunikative Fähigkeiten sind in hohem Maß vorhanden; Austausch gelingt sachlich und angemessen.',
    'Kommunikative Fähigkeiten sind in sehr hohem Maß vorhanden; es wird sachlich argumentiert und angemessen nachgefragt.',
  ],
  teamfaehigkeit: [
    'Ist bedingt in der Lage, konstruktiv und aufgabenorientiert in einer Gruppe zu arbeiten.',
    'Kann eigene Ziele weitgehend mit den Zielen anderer abstimmen und arbeitet teilweise konstruktiv in der Gruppe.',
    'Kann eigene Ziele mit den Zielen anderer abstimmen; konstruktive Gruppenarbeit ist deutlich erkennbar.',
    'Arbeitet in hohem Maß konstruktiv und aufgabenorientiert in der Gruppe und kann eigene Interessen zurückstellen.',
    'Arbeitet sehr konstruktiv in der Gruppe; Anregungen werden aufgenommen und eigenes Wissen wird eingebracht.',
  ],
  umgangsformen: [
    'Benötigt Unterstützung, um in unterschiedlichen Situationen angemessen und respektvoll zu agieren.',
    'Ist weitgehend in der Lage, in unterschiedlichen Situationen angemessen und respektvoll zu agieren.',
    'Ist erkennbar in der Lage, angemessen und respektvoll zu agieren und zeigt die erwartete Höflichkeit.',
    'Agiert angemessen und respektvoll, verhält sich höflich und nimmt Rücksicht auf andere.',
    'Agiert stets angemessen und respektvoll, nimmt Rücksicht auf andere und setzt Mimik, Gestik und Blickkontakt passend ein.',
  ],
}

const defaultPaBewertung = () => ({ bewertung: null, bemerkung: '' })
const defaultPaUebungErgebnis = () => ({ punkte: null, zeit: null, zeit_min: 0, zeit_sec: 0 })

const defaultPaBericht = () => ({
  status: 'entwurf',
  staerken: '',
  entwicklungsfelder: '',
  empfehlung: '',
  bericht_text: '',
  fertiggestellt_at: null,
})

const ensurePaEintrag = (personenId) => {
  const key = String(personenId)

  if (!paTeilnehmerDaten.value[key]) {
    paTeilnehmerDaten.value[key] = {
      uebungen: {},
      selbsteinschaetzung: {},
      kompetenzen: {},
      beurteilungen: {},
      selbsteinschaetzungen: {},
      bericht: defaultPaBericht(),
    }
  }

  const eintrag = paTeilnehmerDaten.value[key]
  eintrag.uebungen ||= {}
  eintrag.selbsteinschaetzung ||= {}
  eintrag.kompetenzen ||= {}
  eintrag.beurteilungen ||= {}
  eintrag.selbsteinschaetzungen ||= {}
  eintrag.bericht ||= defaultPaBericht()

  paUebungen.value.forEach((uebung) => {
    const uebungKey = String(uebung.id)
    eintrag.uebungen[uebungKey] ||= defaultPaUebungErgebnis()
  })

  paMerkmale.forEach((merkmal) => {
    eintrag.selbsteinschaetzung[merkmal.key] ||= defaultPaBewertung()
    eintrag.kompetenzen[merkmal.key] ||= defaultPaBewertung()
  })

  return eintrag
}

const paEintrag = (personenId) => ensurePaEintrag(personenId)

const paUebungErgebnis = (personenId, uebungId) => {
  const eintrag = ensurePaEintrag(personenId)
  const key = String(uebungId)
  eintrag.uebungen[key] ||= defaultPaUebungErgebnis()
  return eintrag.uebungen[key]
}

const paKompetenzBemerkungText = (merkmalKey, wert) =>
  paKompetenzBemerkungTexte[merkmalKey]?.[Number(wert) - 1] || ''

const setzePaBewertung = (personenId, feld, merkmalKey, wert, autoSave = true) => {
  const eintrag = ensurePaEintrag(personenId)
  eintrag[feld][merkmalKey].bewertung = wert

  if (feld === 'kompetenzen') {
    eintrag[feld][merkmalKey].bemerkung = paKompetenzBemerkungText(merkmalKey, wert)
  }

  if (autoSave) {
    planePotenzialanalyseSpeichern({ personenId, sofort: true })
  }
}

const setzePaBewertungSpalte = (feld, wert) => {
  const teilnehmer = selectedPaTeilnehmer.value

  if (!teilnehmer) {
    return
  }

  paMerkmale.forEach((merkmal) => {
    setzePaBewertung(teilnehmer.id, feld, merkmal.key, wert, false)
  })

  planePotenzialanalyseSpeichern({ personenId: teilnehmer.id, sofort: true })
}

const normalisierePaZahl = (value, { min = 0, max = null } = {}) => {
  if (value === '' || value === null || value === undefined) {
    return null
  }

  const zahl = Number(value)
  if (!Number.isFinite(zahl)) {
    return null
  }

  let normalisiert = Math.trunc(zahl)
  normalisiert = Math.max(min, normalisiert)

  if (max !== null && Number.isFinite(max)) {
    normalisiert = Math.min(normalisiert, max)
  }

  return normalisiert
}

const normalisierePaUebungswerte = (personenId) => {
  paUebungen.value.forEach((uebung) => {
    const ergebnis = paUebungErgebnis(personenId, uebung.id)
    const maxPunkte = Number(uebung.hoechstwert)

    ergebnis.punkte = normalisierePaZahl(ergebnis.punkte, {
      min: 0,
      max: Number.isFinite(maxPunkte) ? maxPunkte : null,
    })
    ergebnis.zeit_min = normalisierePaZahl(ergebnis.zeit_min, { min: 0, max: 999 }) ?? 0
    ergebnis.zeit_sec = normalisierePaZahl(ergebnis.zeit_sec, { min: 0, max: 59 }) ?? 0
    ergebnis.zeit = (ergebnis.zeit_min * 60) + ergebnis.zeit_sec
  })
}

const clampUebungPunkte = (personenId, uebung) => {
  const ergebnis = paUebungErgebnis(personenId, uebung.id)
  if (ergebnis.punkte === '' || ergebnis.punkte === undefined) {
    ergebnis.punkte = null
    planePotenzialanalyseSpeichern({ personenId, sofort: true })
    return
  }

  if (ergebnis.punkte === null) {
    planePotenzialanalyseSpeichern({ personenId, sofort: true })
    return
  }

  const max = Number(uebung.hoechstwert)
  const wert = Math.max(0, Number(ergebnis.punkte || 0))
  ergebnis.punkte = Number.isFinite(max) && max >= 0 ? Math.min(wert, max) : wert
  planePotenzialanalyseSpeichern({ personenId, sofort: true })
}

const selectedPaTeilnehmer = computed(() =>
  gruppenTeilnehmer.value.find((teilnehmer) => teilnehmer.id === selectedPaTeilnehmerId.value)
  || gruppenTeilnehmer.value[0]
  || null
)

const paAutoSaveStatusText = computed(() => {
  if (paAutoSaveStatus.value === 'pending') return 'Aenderungen werden gespeichert ...'
  if (paAutoSaveStatus.value === 'saving') return 'Speichert automatisch ...'
  if (paAutoSaveStatus.value === 'saved') return 'Automatisch gespeichert'
  if (paAutoSaveStatus.value === 'error') return 'Auto-Speichern fehlgeschlagen'

  return 'Auto-Speichern aktiv'
})

const paAutoSaveStatusClass = computed(() => {
  if (paAutoSaveStatus.value === 'error') return 'text-red-600'
  if (paAutoSaveStatus.value === 'saved') return 'text-green-700'
  if (paAutoSaveStatus.value === 'saving') return 'text-zbb'
  if (paAutoSaveStatus.value === 'pending') return 'text-amber-700'

  return 'text-gray-500'
})

const resolvePaTeilnehmerId = (personenId = null) =>
  personenId ?? selectedPaTeilnehmer.value?.id ?? null

const paBewertungPayload = (eintrag, feld) =>
  paMerkmale.reduce((payload, merkmal) => {
    const wert = eintrag?.[feld]?.[merkmal.key] || defaultPaBewertung()
    const bewertung = normalisierePaZahl(wert.bewertung, { min: 1, max: 5 })

    payload[merkmal.key] = {
      bewertung,
      bemerkung: wert.bemerkung || '',
    }

    return payload
  }, {})

const paUebungenPayload = (eintrag) =>
  paUebungen.value.reduce((payload, uebung) => {
    const key = String(uebung.id)
    const wert = eintrag?.uebungen?.[key] || defaultPaUebungErgebnis()

    payload[key] = {
      punkte: normalisierePaZahl(wert.punkte, {
        min: 0,
        max: Number.isFinite(Number(uebung.hoechstwert)) ? Number(uebung.hoechstwert) : null,
      }),
      zeit_min: normalisierePaZahl(wert.zeit_min, { min: 0, max: 999 }) ?? 0,
      zeit_sec: normalisierePaZahl(wert.zeit_sec, { min: 0, max: 59 }) ?? 0,
    }

    return payload
  }, {})

const paEintragSnapshot = (personenId) => {
  const eintrag = paEintrag(personenId)
  const selbsteinschaetzung = paBewertungPayload(eintrag, 'selbsteinschaetzung')
  const kompetenzen = paBewertungPayload(eintrag, 'kompetenzen')

  return {
    uebungen: paUebungenPayload(eintrag),
    selbsteinschaetzung,
    kompetenzen,
    beurteilungen: JSON.parse(JSON.stringify(eintrag.beurteilungen || {})),
    selbsteinschaetzungen: JSON.parse(JSON.stringify(eintrag.selbsteinschaetzungen || {})),
    bericht: JSON.parse(JSON.stringify(eintrag.bericht || defaultPaBericht())),
    merkmale_snapshot: {
      selbsteinschaetzung,
      kompetenzen,
    },
  }
}

const csrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const speicherePotenzialanalyse = async ({ personenId = null, silent = false, version = null } = {}) => {
  const teilnehmerId = resolvePaTeilnehmerId(personenId)

  if (!teilnehmerId) {
    return false
  }

  const key = String(teilnehmerId)
  const geplanterTimer = paAutoSaveTimers.get(key)

  if (geplanterTimer) {
    clearTimeout(geplanterTimer)
    paAutoSaveTimers.delete(key)
  }

  if (paSaveInFlight.has(key)) {
    paSavePending.add(key)
    return false
  }

  paSaveInFlight.add(key)
  paSaving.value = true
  paAutoSaveStatus.value = 'saving'

  const saveVersion = version ?? (paSaveVersions.get(key) || 0)

  try {
    normalisierePaUebungswerte(teilnehmerId)
    const payload = paEintragSnapshot(teilnehmerId)

    const response = await axios.put(route('potenzialanalyse.gruppe.teilnehmer.update', {
      gruppe: props.gruppe.id,
      personen: teilnehmerId,
    }), payload)

    if ((paSaveVersions.get(key) || 0) === saveVersion) {
      paDirtyTeilnehmerIds.delete(key)
      paAutoSaveStatus.value = 'saved'
    } else {
      paAutoSaveStatus.value = 'pending'
    }

    if (!silent) {
      await Swal.fire({
        icon: 'success',
        title: 'Gespeichert',
        text: response.data?.message || 'Potenzialanalyse wurde gespeichert.',
        timer: 1600,
        showConfirmButton: false,
      })
    }

    return true
  } catch (error) {
    paAutoSaveStatus.value = 'error'

    if (!silent) {
      await Swal.fire({
        icon: 'error',
        title: 'Fehler',
        text: error.response?.data?.message || 'Potenzialanalyse konnte nicht gespeichert werden.',
      })
    } else {
      console.error('Potenzialanalyse Auto-Save fehlgeschlagen:', error)
    }

    return false
  } finally {
    paSaveInFlight.delete(key)
    paSaving.value = paSaveInFlight.size > 0

    if (paSavePending.has(key)) {
      paSavePending.delete(key)
      window.setTimeout(() => {
        speicherePotenzialanalyse({
          personenId: teilnehmerId,
          silent: true,
          version: paSaveVersions.get(key) || 0,
        })
      }, 0)
    }
  }
}

const planePotenzialanalyseSpeichern = ({ personenId = null, sofort = false } = {}) => {
  const teilnehmerId = resolvePaTeilnehmerId(personenId)

  if (!teilnehmerId) {
    return
  }

  const key = String(teilnehmerId)
  const nextVersion = (paSaveVersions.get(key) || 0) + 1
  const geplanterTimer = paAutoSaveTimers.get(key)

  if (!paAutoSaveBereit.value) {
    return
  }

  if (geplanterTimer) {
    clearTimeout(geplanterTimer)
  }

  paSaveVersions.set(key, nextVersion)
  paDirtyTeilnehmerIds.add(key)
  paAutoSaveStatus.value = sofort ? 'saving' : 'pending'

  const timer = window.setTimeout(() => {
    paAutoSaveTimers.delete(key)
    speicherePotenzialanalyse({
      personenId: teilnehmerId,
      silent: true,
      version: nextVersion,
    })
  }, sofort ? 0 : 650)

  paAutoSaveTimers.set(key, timer)
}

const speichereOffenePaAenderungen = () => {
  const offeneIds = new Set([
    ...paDirtyTeilnehmerIds,
    ...paAutoSaveTimers.keys(),
  ])

  if (!offeneIds.size) {
    return
  }

  paAutoSaveTimers.forEach((timer) => clearTimeout(timer))
  paAutoSaveTimers.clear()

  offeneIds.forEach((key) => {
    const teilnehmerId = Number(key)

    if (!teilnehmerId) {
      return
    }

    normalisierePaUebungswerte(teilnehmerId)

    try {
      fetch(route('potenzialanalyse.gruppe.teilnehmer.update', {
        gruppe: props.gruppe.id,
        personen: teilnehmerId,
      }), {
        method: 'PUT',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(paEintragSnapshot(teilnehmerId)),
      })
    } catch (error) {
      console.error('Potenzialanalyse konnte vor dem Verlassen nicht gespeichert werden:', error)
    }
  })
}

watch(paTeilnehmerDaten, () => {
  if (!paAutoSaveBereit.value) {
    return
  }

  const teilnehmerId = selectedPaTeilnehmer.value?.id

  if (!teilnehmerId) {
    return
  }

  planePotenzialanalyseSpeichern({ personenId: teilnehmerId })
}, { deep: true })

const waehlePaTeilnehmer = (teilnehmer) => {
  selectedPaTeilnehmerId.value = teilnehmer.id
  ensurePaEintrag(teilnehmer.id)
}

const setPaTab = (tabKey) => {
  activePaTab.value = tabKey
}

const wechselPaTab = (richtung) => {
  const nextIndex = activePaTabIndex.value + richtung

  if (nextIndex >= 0 && nextIndex < paTabs.length) {
    activePaTab.value = paTabs[nextIndex].key
  }
}

const paMerkmalBerichtPhrasen = {
  feinmotorik: [
    'du konntest bei den praktischen Aufgaben durch eine gute Feinmotorik überzeugen',
    'bei den praktischen Übungen war deine gute Feinmotorik deutlich zu erkennen',
    'du hast feinmotorische Aufgaben geschickt und sicher bearbeitet',
  ],
  grobmotorik: [
    'du hast bei den praktischen Aufgaben eine gute Koordination gezeigt',
    'bei bewegungsbezogenen Aufgaben hast du sicher und koordiniert gearbeitet',
    'du konntest deine Bewegungen gut einsetzen und praktisch umsetzen',
  ],
  wahrnehmung_symmetrie: [
    'du hast Aufgaben aufmerksam wahrgenommen und sorgfältig bearbeitet',
    'du hast genau hingeschaut und wichtige Details erkannt',
    'bei den Aufgaben hast du eine gute Wahrnehmung und Aufmerksamkeit gezeigt',
  ],
  analyse_problemloesefaehigkeit: [
    'du konntest Probleme erkennen und passende Lösungen finden',
    'du hast dich mit Aufgaben auseinandergesetzt und eigene Lösungswege entwickelt',
    'bei herausfordernden Aufgaben hast du überlegt gehandelt und Lösungen gesucht',
  ],
  arbeitsplanung: [
    'du hast deine Aufgaben strukturiert geplant und bearbeitet',
    'deine Arbeitsschritte waren gut überlegt und nachvollziehbar',
    'du bist planvoll an die Aufgaben herangegangen',
  ],
  motivation_leistungsbereitschaft: [
    'du bist motiviert an die Aufgaben herangegangen und hast Leistungsbereitschaft gezeigt',
    'du hast Einsatzbereitschaft gezeigt und wolltest gute Ergebnisse erreichen',
    'deine Motivation war während der Aufgaben gut erkennbar',
  ],
  durchhaltevermoegen: [
    'du hast auch bei anspruchsvolleren Aufgaben Durchhaltevermögen gezeigt',
    'du bist drangeblieben, auch wenn Aufgaben schwieriger wurden',
    'deine Ausdauer hat dir geholfen, Aufgaben konsequent weiterzubearbeiten',
  ],
  sorgfalt: [
    'du hast sorgfältig gearbeitet und auf genaue Ergebnisse geachtet',
    'bei der Bearbeitung hast du Genauigkeit und Sorgfalt gezeigt',
    'du hast deine Aufgaben ordentlich und aufmerksam umgesetzt',
  ],
  kommunikation: [
    'du hast dich verständlich mit anderen ausgetauscht',
    'im Austausch mit anderen konntest du dich klar einbringen',
    'du hast Informationen verständlich weitergegeben und aufgenommen',
  ],
  teamfaehigkeit: [
    'du konntest gut im Team arbeiten und dich kooperativ einbringen',
    'in der Zusammenarbeit hast du dich hilfsbereit und teamorientiert gezeigt',
    'du hast mit anderen zusammengearbeitet und zum gemeinsamen Ziel beigetragen',
  ],
  umgangsformen: [
    'du bist anderen respektvoll und freundlich begegnet',
    'dein Umgang mit anderen war freundlich und wertschätzend',
    'du hast dich im Kontakt mit anderen respektvoll verhalten',
  ],
}

const paMerkmalEntwicklungPhrasen = {
  feinmotorik: ['deine Feinmotorik weiter zu üben', 'feinmotorische Aufgaben noch sicherer zu bearbeiten'],
  grobmotorik: ['bei praktischen Aufgaben noch sicherer zu werden', 'deine Bewegungsabläufe weiter zu festigen'],
  wahrnehmung_symmetrie: ['Aufgaben noch genauer wahrzunehmen', 'Details noch bewusster zu beachten'],
  analyse_problemloesefaehigkeit: ['Lösungswege noch selbstständiger zu entwickeln', 'bei Problemen noch gezielter nach Lösungen zu suchen'],
  arbeitsplanung: ['deine Arbeitsschritte noch klarer zu planen', 'Aufgaben noch strukturierter vorzubereiten'],
  motivation_leistungsbereitschaft: ['deine Motivation auch bei schwierigen Aufgaben zu halten', 'auch bei weniger beliebten Aufgaben weiter engagiert zu bleiben'],
  durchhaltevermoegen: ['auch bei Schwierigkeiten weiter dranzubleiben', 'anspruchsvolle Aufgaben noch ausdauernder zu bearbeiten'],
  sorgfalt: ['weiter auf Genauigkeit und Sorgfalt zu achten', 'Ergebnisse noch genauer zu kontrollieren'],
  kommunikation: ['dich noch klarer mit anderen auszutauschen', 'deine Gedanken noch deutlicher mitzuteilen'],
  teamfaehigkeit: ['deine Ideen im Team noch stärker einzubringen', 'dich in Gruppenaufgaben noch aktiver einzubringen'],
  umgangsformen: ['weiter auf einen respektvollen Umgang zu achten', 'deine freundliche Umgangsweise weiter bewusst einzusetzen'],
}

const paUebungsSaetze = {
  stark: [
    'Bei den Übungen hast du insgesamt gute Ergebnisse erzielt.',
    'Die Übungsergebnisse zeigen, dass du viele Aufgaben sicher bearbeitet hast.',
    'In den Übungen konntest du deine Fähigkeiten gut einsetzen.',
  ],
  solide: [
    'Bei den Übungen hast du solide Ergebnisse erzielt und engagiert mitgearbeitet.',
    'Die Ergebnisse der Übungen zeigen eine ordentliche Grundlage, auf der du weiter aufbauen kannst.',
    'In den Übungen hast du viele Aufgaben nachvollziehbar bearbeitet.',
  ],
  ausbau: [
    'Bei den Übungen hast du dich bemüht und kannst durch weiteres Üben noch sicherer werden.',
    'Die Übungen zeigen, dass du bereits mitarbeitest und durch weitere Übung noch mehr Sicherheit gewinnen kannst.',
    'Bei den Aufgaben konntest du Erfahrungen sammeln, auf denen du weiter aufbauen kannst.',
  ],
}

const paStaerkenPrefixes = [
  'Besonders positiv aufgefallen sind',
  'Stark gezeigt hast du',
  'Gut erkennbar waren bei dir',
]

const paEntwicklungPrefixes = [
  'Weiterentwickeln kannst du',
  'Für deinen nächsten Schritt kannst du an',
  'Noch sicherer werden kannst du bei',
]

const paEmpfehlungPrefixes = [
  'Als nächsten Schritt empfehlen wir dir',
  'Für deine weitere Entwicklung ist hilfreich',
  'Weiterbringen kann dich',
]

const paFallbackSaetze = [
  'du hast die Aufgaben der Potenzialanalyse bearbeitet und dich mit deinen Fähigkeiten auseinandergesetzt.',
  'du hast dich auf die Aufgaben der Potenzialanalyse eingelassen und dabei verschiedene Fähigkeiten gezeigt.',
  'du hast während der Potenzialanalyse mitgearbeitet und unterschiedliche Aufgaben kennengelernt.',
]

const paAbschlussSaetze = [
  'Für deinen weiteren schulischen und beruflichen Weg wünschen wir dir alles Gute und viel Erfolg.',
  'Wir wünschen dir für deinen weiteren Weg in Schule und Beruf alles Gute und viel Erfolg.',
  'Für deine weitere schulische und berufliche Zukunft wünschen wir dir viel Erfolg und alles Gute.',
]
const paBerichtGenerierungZaehler = ref(0)

const bereinigeText = (value) => String(value || '').replace(/\s+/g, ' ').trim()

const hashText = (value) =>
  Array.from(String(value || '')).reduce((hash, char) =>
    ((hash << 5) - hash + char.charCodeAt(0)) >>> 0
  , 0)

const waehleVariante = (varianten, seed, offset = 0) => {
  const liste = Array.isArray(varianten) ? varianten.filter(Boolean) : [varianten].filter(Boolean)

  if (!liste.length) {
    return ''
  }

  return liste[(hashText(`${seed}-${offset}`) + offset) % liste.length]
}

const rotiereListe = (items, steps = 0) => {
  if (!items.length) {
    return items
  }

  const start = Math.abs(steps) % items.length
  return [...items.slice(start), ...items.slice(0, start)]
}

const normalisiereGeschlecht = (value) =>
  String(value || '')
    .trim()
    .toLowerCase()
    .replace('ä', 'ae')

const kleinschreibeAbsatzanfang = (value) => {
  const text = String(value || '')

  return text.replace(/^(\s*)([A-ZÄÖÜ])/, (match, leerraum, ersterBuchstabe) =>
    `${leerraum}${ersterBuchstabe.toLowerCase()}`
  )
}

const alsSatz = (value) => {
  const text = bereinigeText(value)
  if (!text) return ''

  return /[.!?]$/.test(text) ? text : `${text}.`
}

const joinMitUnd = (items) => {
  const werte = items.filter(Boolean)

  if (werte.length <= 1) return werte[0] || ''
  if (werte.length === 2) return `${werte[0]} und ${werte[1]}`

  return `${werte.slice(0, -1).join(', ')} und ${werte[werte.length - 1]}`
}

const textWirktWieSatz = (value) =>
  /\b(du|dein|deine|hast|bist|zeigst|arbeitest|konntest|warst|bleibst|kannst|gehst)\b/i.test(value)

const formatiereFreitextFuerBericht = (value, prefix) => {
  const text = bereinigeText(value)

  if (!text) return ''
  if (textWirktWieSatz(text)) return alsSatz(text)

  return alsSatz(`${prefix} ${text}`)
}

const paBewertungenAlsListe = (eintrag, feld) =>
  paMerkmale
    .map((merkmal) => ({
      ...merkmal,
      bewertung: Number(eintrag?.[feld]?.[merkmal.key]?.bewertung),
      bemerkung: bereinigeText(eintrag?.[feld]?.[merkmal.key]?.bemerkung),
    }))
    .filter((item) => Number.isFinite(item.bewertung))

const paUebungsQuote = (eintrag) => {
  const quoten = paUebungen.value
    .map((uebung) => {
      const ergebnis = eintrag?.uebungen?.[String(uebung.id)]
      const punkte = Number(ergebnis?.punkte)
      const max = Number(uebung.hoechstwert)

      if (!Number.isFinite(punkte) || !Number.isFinite(max) || max <= 0) {
        return null
      }

      return Math.max(0, Math.min(1, punkte / max))
    })
    .filter((quote) => quote !== null)

  if (!quoten.length) {
    return null
  }

  return quoten.reduce((summe, quote) => summe + quote, 0) / quoten.length
}

const paUebungsSatz = (eintrag, seed, variante = 0) => {
  const quote = paUebungsQuote(eintrag)

  if (quote === null) return ''
  if (quote >= 0.8) return waehleVariante(paUebungsSaetze.stark, `${seed}-uebungen-stark`, variante)
  if (quote >= 0.6) return waehleVariante(paUebungsSaetze.solide, `${seed}-uebungen-solide`, variante)

  return waehleVariante(paUebungsSaetze.ausbau, `${seed}-uebungen-ausbau`, variante)
}

const paBerichtAnrede = (teilnehmer) => {
  const vorname = teilnehmer?.vorname || 'Teilnehmer'
  const geschlecht = normalisiereGeschlecht(teilnehmer?.geschlecht)

  if (['w', 'weiblich', 'frau', 'f', 'female'].includes(geschlecht)) return `Liebe ${vorname},`
  if (['m', 'maennlich', 'mann', 'herr', 'male'].includes(geschlecht)) return `Lieber ${vorname},`

  return `Liebe/r ${vorname},`
}

const paSelbststaerkenSatz = (items, seed, variante = 0) => {
  const staerken = joinMitUnd(items.map((item) => item.label))

  return waehleVariante([
    `Auch in deiner Selbsteinschätzung wird deutlich, dass du ${staerken} als Stärke wahrnimmst.`,
    `Deine Selbsteinschätzung zeigt ebenfalls, dass du deine Stärke in ${staerken} siehst.`,
    `Du nimmst selbst besonders ${staerken} als Stärke wahr.`,
  ], `${seed}-selbststaerken`, variante)
}

const sortiereBerichtswerte = (items, seed, absteigend = true) =>
  [...items].sort((a, b) => {
    const bewertung = absteigend ? b.bewertung - a.bewertung : a.bewertung - b.bewertung

    if (bewertung !== 0) {
      return bewertung
    }

    return hashText(`${seed}-${a.key}`) - hashText(`${seed}-${b.key}`)
  })

const bauePaBerichtstext = (teilnehmer, eintrag, variante = 0) => {
  const bericht = eintrag.bericht || defaultPaBericht()
  const seed = `${teilnehmer?.id || ''}-${teilnehmer?.vorname || ''}-${teilnehmer?.nachname || ''}-${variante}`
  const kompetenzen = paBewertungenAlsListe(eintrag, 'kompetenzen')
  const selbsteinschaetzung = paBewertungenAlsListe(eintrag, 'selbsteinschaetzung')
  const starkeKompetenzen = rotiereListe(
    sortiereBerichtswerte(kompetenzen.filter((item) => item.bewertung >= 4), `${seed}-stark`),
    variante
  )
  const entwicklungsKompetenzen = rotiereListe(
    sortiereBerichtswerte(kompetenzen.filter((item) => item.bewertung <= 2), `${seed}-entwicklung`, false),
    variante
  )
  const selbstStaerken = rotiereListe(
    sortiereBerichtswerte(selbsteinschaetzung.filter((item) => item.bewertung >= 4), `${seed}-selbst`),
    variante
  )

  const hauptsaetze = []
  const manuelleStaerken = formatiereFreitextFuerBericht(
    bericht.staerken,
    waehleVariante(paStaerkenPrefixes, `${seed}-staerken-prefix`, variante)
  )

  if (manuelleStaerken) {
    hauptsaetze.push(manuelleStaerken)
  }

  starkeKompetenzen
    .slice(0, manuelleStaerken ? 1 : 2)
    .forEach((item, index) => {
      hauptsaetze.push(alsSatz(
        waehleVariante(paMerkmalBerichtPhrasen[item.key], `${seed}-${item.key}`, variante + index)
        || `du hast ${item.label} gezeigt`
      ))
    })

  const uebungsSatz = paUebungsSatz(eintrag, seed, variante)
  if (uebungsSatz && hauptsaetze.length < 4) {
    hauptsaetze.push(uebungsSatz)
  }

  const nichtSchonGenannt = selbstStaerken
    .filter((item) => !starkeKompetenzen.some((kompetenz) => kompetenz.key === item.key))
    .slice(0, 2)

  if (nichtSchonGenannt.length && hauptsaetze.length < 4) {
    hauptsaetze.push(paSelbststaerkenSatz(nichtSchonGenannt, seed, variante))
  }

  if (!hauptsaetze.length) {
    hauptsaetze.push(waehleVariante(paFallbackSaetze, `${seed}-fallback`, variante))
  }

  const entwicklungsSaetze = []
  const manuelleEntwicklung = formatiereFreitextFuerBericht(
    bericht.entwicklungsfelder,
    waehleVariante(paEntwicklungPrefixes, `${seed}-entwicklung-prefix`, variante)
  )

  if (manuelleEntwicklung) {
    entwicklungsSaetze.push(manuelleEntwicklung)
  } else if (entwicklungsKompetenzen.length) {
    entwicklungsSaetze.push(
      alsSatz(`Weiter üben kannst du besonders daran, ${joinMitUnd(
        entwicklungsKompetenzen
          .slice(0, 2)
          .map((item, index) =>
            waehleVariante(paMerkmalEntwicklungPhrasen[item.key], `${seed}-entwicklung-${item.key}`, variante + index)
            || item.label.toLowerCase()
          )
      )}`)
    )
  }

  const empfehlung = formatiereFreitextFuerBericht(
    bericht.empfehlung,
    waehleVariante(paEmpfehlungPrefixes, `${seed}-empfehlung-prefix`, variante)
  )

  if (empfehlung) {
    entwicklungsSaetze.push(empfehlung)
  }

  const haupttext = kleinschreibeAbsatzanfang(hauptsaetze.join(' '))

  return [
    paBerichtAnrede(teilnehmer),
    haupttext,
    entwicklungsSaetze.join(' '),
    waehleVariante(paAbschlussSaetze, `${seed}-abschluss`, variante),
  ].filter(Boolean).join('\n\n')
}

const generierePaBerichtstext = async () => {
  const teilnehmer = selectedPaTeilnehmer.value

  if (!teilnehmer) {
    return
  }

  const eintrag = paEintrag(teilnehmer.id)
  const vorhandenerText = bereinigeText(eintrag.bericht?.bericht_text)

  if (vorhandenerText) {
    const bestaetigung = await Swal.fire({
      icon: 'question',
      title: 'Berichtstext ersetzen?',
      text: 'Der vorhandene Berichtstext wird durch einen neuen Vorschlag ersetzt.',
      showCancelButton: true,
      confirmButtonText: 'Ja, neu generieren',
      cancelButtonText: 'Abbrechen',
    })

    if (!bestaetigung.isConfirmed) {
      return
    }
  }

  paBerichtGenerierungZaehler.value += 1
  eintrag.bericht.bericht_text = bauePaBerichtstext(
    teilnehmer,
    eintrag,
    paBerichtGenerierungZaehler.value + Date.now()
  )
  activePaTab.value = 'bericht'
  planePotenzialanalyseSpeichern({ personenId: teilnehmer.id, sofort: true })
}

const gefilterteBopLegacyExporte = computed(() => {
  const suche = exportSuche.value.trim().toLowerCase()
  if (!suche) return bopLegacyExporte.value

  return bopLegacyExporte.value.filter((item) =>
    [item.name, item.typ, item.format]
      .filter(Boolean)
      .some((wert) => String(wert).toLowerCase().includes(suche))
  )
})

const exportTreffer = computed(() => gefilterteExportVorlagen.value.length + gefilterteBopLegacyExporte.value.length)
const exportGesamt = computed(() => exportVorlagen.value.length + bopLegacyExporte.value.length)

const formatLabel = (format) => String(format).toUpperCase()

const exportHref = (dokument, format) =>
  route('gruppe.export.serienbrief', {
    gruppe: props.gruppe.id,
    dokument: dokument.id,
    format,
  })

const fileNameFromResponse = (response, fallback) => {
  const disposition = response.headers?.['content-disposition'] || ''
  const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1]
  if (encoded) return decodeURIComponent(encoded)

  const plain = disposition.match(/filename="?([^";]+)"?/i)?.[1]
  return plain || fallback || 'export'
}

const downloadBlob = (response, fallbackName) => {
  const url = window.URL.createObjectURL(new Blob([response.data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', fileNameFromResponse(response, fallbackName))
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

const startBopLegacyExport = async (item) => {
  if (!item?.url) return

  if ((item.method || 'get').toLowerCase() !== 'post') {
    window.location.href = item.url
    return
  }

  legacyExportLoading.value = item.id

  try {
    const response = await axios.post(item.url, item.payload || {}, {
      responseType: 'blob',
    })
    downloadBlob(response, item.fileName || `${item.name}.${String(item.format || 'docx').toLowerCase()}`)
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Export fehlgeschlagen.', 'error')
  } finally {
    legacyExportLoading.value = null
  }
}


// --- Teilnehmer vorbereiten ---
const gruppenTeilnehmer = ref([])
console.log(gruppenTeilnehmer);
const tag = ref([])

const statusUebersicht = computed(() => {
  const zaehler = new Map(props.anwesenheitsstatuten.map((status) => [status.status, 0]))

  gruppenTeilnehmer.value.forEach((teilnehmer) => {
    sichtbareTage.value.forEach((tag) => {
      const status = teilnehmer.anwesenheit?.[tag.index] || 'unentschuldigt'
      zaehler.set(status, (zaehler.get(status) || 0) + 1)
    })
  })

  return props.anwesenheitsstatuten.map((status) => ({
    status: status.status,
    count: zaehler.get(status.status) || 0,
  }))
})

onMounted(() => {
  geheZuAktuellerWoche()

  const gruppiert = {}
  props.gruppe.teilnehmer.forEach(t => {
    if (!gruppiert[t.id]) gruppiert[t.id] = []
    gruppiert[t.id].push(t)
  })

  gruppenTeilnehmer.value = Object.values(gruppiert).map(teilnehmerGruppe => {
    const basis = teilnehmerGruppe[0]

    return {
      ...basis,
      anwesenheit: tage.value.map(tag => {
        const eintrag = teilnehmerGruppe.find(tt => tt.pivot?.tag?.datum === tag.date)
        return eintrag?.pivot?.status?.status || 'unentschuldigt'
      }),
      zeiten: tage.value.map(tag => {
        const eintrag = teilnehmerGruppe.find(tt => tt.pivot?.tag?.datum === tag.date)
        return {
          start: eintrag?.pivot?.zeittatsaechlich?.startzeit || props.gruppe.startzeit,
          ende: eintrag?.pivot?.zeittatsaechlich?.endzeit || props.gruppe.endzeit,
        }
      }),
    }
  })

  if (paAktiv.value && gruppenTeilnehmer.value.length) {
    selectedPaTeilnehmerId.value = gruppenTeilnehmer.value[0].id
    gruppenTeilnehmer.value.forEach((teilnehmer) => ensurePaEintrag(teilnehmer.id))
  }

  window.setTimeout(() => {
    paAutoSaveBereit.value = true
  }, 0)

  window.addEventListener('beforeunload', speichereOffenePaAenderungen)
})

onBeforeUnmount(() => {
  speichereOffenePaAenderungen()
  window.removeEventListener('beforeunload', speichereOffenePaAenderungen)
  paAutoSaveTimers.forEach((timer) => clearTimeout(timer))
  paAutoSaveTimers.clear()
})


//Anwesenheit speichern
const speichernSofort = async (tID, ttag, statusName, tatstartTime, tatendTime) => {
  try {
    const teilnehmerId = tID
    const tag = ttag.date

    const status = props.anwesenheitsstatuten.find(s => s.status === statusName)
    if (!status) return

    console.log('speichernSofort:', { teilnehmerId, tag, statusName, tatstartTime , tatendTime })

    router.post(route('anwesenheit.update'), {
      personen_id: teilnehmerId,
      gruppe_id: props.gruppe.id,
      tag: tag,
      tatstartTime: formatTime(tatstartTime),
      tatendTime: formatTime(tatendTime),
      anwesenheitsstatuten_id: status.id,
      bemerkung: null,
    })

    Swal.fire({
      icon: 'success',
      title: 'Gespeichert',
      text: `Status: ${statusName}, Zeit: ${tatstartTime} – ${tatendTime}`,
      timer: 1500,
      showConfirmButton: false,
    })
  } catch (error) {
    console.error(error)
    Swal.fire({
      icon: 'error',
      title: 'Fehler beim Speichern',
      text: error.response?.data?.message || 'Unbekannter Fehler.',
    })
  }
}

const entferneTeilnehmer = async (teilnehmer) => {
  const name = `${teilnehmer.vorname} ${teilnehmer.nachname}`.trim()

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Teilnehmer entfernen?',
    text: `${name} wird mit allen Anwesenheitstagen aus dieser Gruppe entfernt.`,
    showCancelButton: true,
    confirmButtonText: 'Entfernen',
    cancelButtonText: 'Abbrechen',
    confirmButtonColor: '#dc2626',
  })

  if (!result.isConfirmed) {
    return
  }

  try {
    const response = await axios.delete(route('gruppeHasTeilnehmer.destroyTeilnehmer', {
      gruppe: props.gruppe.id,
      personen: teilnehmer.id,
    }))

    gruppenTeilnehmer.value = gruppenTeilnehmer.value.filter((item) => item.id !== teilnehmer.id)

    await Swal.fire({
      icon: 'success',
      title: 'Entfernt',
      text: response.data?.message || 'Teilnehmer wurde aus der Gruppe entfernt.',
      timer: 1800,
      showConfirmButton: false,
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Fehler',
      text: error.response?.data?.message || 'Teilnehmer konnte nicht entfernt werden.',
    })
  }
}

const exportMitTag = async () => {
    showExportDialog.value = false;

    const options = tage.value.map(t => ({
        value: t.date,
        label: `${t.label} (${t.datum})`
    }));

    const { value: ausgewahlt } = await Swal.fire({
        title: "Welchen Tag exportieren?",
        input: "select",
        inputOptions: options.reduce((acc, t) => {
            acc[t.value] = t.label;
            return acc;
        }, {}),
        inputPlaceholder: "Bitte einen Tag auswählen",
        showCancelButton: true,
        confirmButtonText: "Export starten",
        cancelButtonText: "Abbrechen",
    });

    if (!ausgewahlt) return;

    // Direkt Download starten
    window.location.href = route("export.anwesenheitslite_V1", {
        id: props.gruppe.id,
        tag: ausgewahlt
    });
};

</script>

<template>
  <Head title="Teilnehmer verwalten" />

  <AppLayout >
    <template #header>
            Teilnehmerverwaltung | {{props.gruppe.bereich.name}} ({{ new Date(props.gruppe.anfangsdatum).toLocaleDateString('de-DE') }} – {{ new Date(props.gruppe.enddatum).toLocaleDateString('de-DE') }}),   von {{ formatTime(props.gruppe.startzeit) }} bis {{ formatTime(props.gruppe.endzeit) }}

    </template>

    <div class="p-6 space-y-8 bg-white rounded-lg shadow-sm ">
      <!-- Teilnehmer hinzufügen -->
      <div class="bg-gray-50 rounded-lg p-4 border shadow-sm">
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h3 class="font-semibold text-gray-700">Teilnehmer hinzufügen</h3>
          <Link
            v-if="klassenbuchErlaubt"
            :href="klassenbuchHref"
            class="inline-flex h-10 items-center justify-center gap-2 rounded border border-zbb bg-white px-4 text-sm font-semibold text-zbb hover:bg-zbb hover:text-white"
          >
            <i class="las la-book-open text-lg"></i>
            Klassenbuch
          </Link>
        </div>

        <Button
          label="➕ Teilnehmer hinzufügen"
          icon="pi pi-users"
          class="w-full !bg-orange-500 hover:!bg-orange-600 border-none"
          @click="showTeilnehmerModal = true"
        />

        <Dialog
          v-model:visible="showTeilnehmerModal"
          modal
          header="➕ Teilnehmer hinzufügen"
          :style="{ width: '700px', maxWidth: '95vw' }"
          :draggable="false"
          appendTo="body"
          dismissableMask
        >
          <div class="space-y-4">
             <div class="flex gap-2">
                <div class="w-full">
                    <label for="abteilungDelete">Von*</label>
                    <InputText type="time" v-model="zeitgeplantStart" class="w-full" />
                </div>

                <div class="w-full">
                    <label for="abteilungDelete">Bis*</label>
                    <InputText type="time" v-model="zeitgeplantEnd" class="w-full" />
                </div>
            </div>


            <div class="flex gap-2">
                <div class="w-full">
                    <label for="abteilungDelete">Von*</label>
                    <InputText type="date" v-model="datumgeplantStart" class="w-full" />
                </div>

                <div class="w-full">
                    <label for="abteilungDelete">Bis*</label>
                    <InputText type="date" v-model="datumgeplantEnd" class="w-full" />
                </div>
            </div>
            <FloatLabel variant="on">
              <MultiSelect
                v-model="selectedTeilnehmerIds"
                :options="props.teilnehmer"
                :filter="true"
                display="chip"
                optionValue="id"
                :optionLabel="(t) => `${t.vorname} ${t.nachname}`"
                placeholder="Teilnehmer auswählen"
                class="w-full"
                appendTo="body"
                panelClass="z-[9999]"
              />
            </FloatLabel>



            <div class="flex justify-end gap-2 pt-2">
              <Button
                label="Abbrechen"
                class="p-button-text hover:!bg-zbbTrp !text-zbb"
                :disabled="isSubmittingTeilnehmer"
                @click="showTeilnehmerModal = false"
              />
              <Button
                label="Übernehmen"
                icon="pi pi-check"
                class="!bg-zbb hover:!bg-zbb/80 border-none"
                :disabled="isSubmittingTeilnehmer"
                @click="confirmTeilnehmer"
              />
            </div>
          </div>
        </Dialog>
      </div>

      <!-- Anwesenheit -->
      <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold text-gray-700">Anwesenheit verwalten</h3>
            <Button
              label="Exportieren"
              icon="pi pi-download"
              class="!bg-zbb hover:!bg-zbb/80 border-none"
              @click="showExportDialog = true"
            />
        </div>

        <Dialog
          v-model:visible="showExportDialog"
          modal
          header="Exportieren"
          :style="{ width: '760px', maxWidth: '94vw' }"
          :draggable="false"
          appendTo="body"
          dismissableMask
        >
          <div class="space-y-4">
            <button
              type="button"
              class="flex w-full items-center justify-between rounded border border-gray-200 bg-white px-4 py-3 text-left text-sm hover:border-zbb hover:bg-zbbTrp"
              @click="exportMitTag"
            >
              <span>
                <span class="block font-semibold text-gray-800">Anwesenheitsliste</span>
                <span class="block text-xs text-gray-500">{{ tage.length }} Tage</span>
              </span>
              <i class="las la-file-export text-xl text-zbb"></i>
            </button>

            <div class="flex items-center gap-2">
              <InputText
                v-model="exportSuche"
                class="w-full"
                placeholder="Vorlage suchen"
              />
              <span class="shrink-0 rounded bg-gray-100 px-3 py-2 text-xs text-gray-600">
                {{ exportTreffer }} / {{ exportGesamt }}
              </span>
            </div>

            <div v-if="gefilterteBopLegacyExporte.length" class="rounded border border-gray-200 bg-white">
              <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                BOP-Funktionen
              </div>
              <div
                v-for="item in gefilterteBopLegacyExporte"
                :key="item.id"
                class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 hover:bg-gray-50"
              >
                <div class="min-w-0">
                  <div class="truncate font-medium text-gray-800" :title="item.name">{{ item.name }}</div>
                  <div class="mt-1 flex flex-wrap gap-1 text-xs text-gray-500">
                    <span class="rounded bg-gray-100 px-2 py-0.5">{{ item.typ }}</span>
                    <span class="rounded bg-gray-100 px-2 py-0.5">{{ item.format }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  class="inline-flex h-9 min-w-20 items-center justify-center rounded border border-zbb/30 px-3 text-xs font-semibold text-zbb hover:bg-zbb hover:text-white disabled:opacity-60"
                  :disabled="legacyExportLoading === item.id"
                  @click="startBopLegacyExport(item)"
                >
                  {{ legacyExportLoading === item.id ? 'Lädt...' : item.format }}
                </button>
              </div>
            </div>

            <div class="max-h-[52vh] overflow-y-auto rounded border border-gray-200 bg-white">
              <div v-if="gefilterteExportVorlagen.length" class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                Vorlagen
              </div>
              <div
                v-for="dok in gefilterteExportVorlagen"
                :key="dok.id"
                class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 hover:bg-gray-50"
              >
                <div class="min-w-0">
                  <div class="truncate font-medium text-gray-800" :title="dok.name">{{ dok.name }}</div>
                  <div class="mt-1 flex flex-wrap gap-1 text-xs text-gray-500">
                    <span class="rounded bg-gray-100 px-2 py-0.5">{{ dok.typ?.toUpperCase() }}</span>
                    <span v-if="dok.kontext" class="rounded bg-gray-100 px-2 py-0.5">{{ dok.kontext }}</span>
                  </div>
                </div>
                <div class="flex shrink-0 flex-wrap justify-end gap-2">
                  <a
                    v-for="format in exportFormate(dok)"
                    :key="dok.id + '-' + format"
                    class="inline-flex h-9 min-w-16 items-center justify-center rounded border border-zbb/30 px-3 text-xs font-semibold text-zbb hover:bg-zbb hover:text-white"
                    :href="exportHref(dok, format)"
                  >
                    {{ formatLabel(format) }}
                  </a>
                </div>
              </div>

              <div v-if="exportTreffer === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                Keine Export-Vorlagen
              </div>
            </div>
          </div>
        </Dialog>

        <!-- Anwesenheitsstatuten Agenda-->
        <div class="flex items-center gap-6 bg-zbbTrp border p-3 rounded">
          <div
            v-for="s in props.anwesenheitsstatuten"
            :key="s.status"
            class="flex items-center gap-2 text-sm"
          >
            <span class="w-3 h-3 rounded-full " :style="statusFarbe(s.status)"></span>
            {{ s.status }}
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 overflow-hidden rounded border border-gray-200 bg-white px-4 py-3">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded border border-gray-300 text-gray-700 hover:border-zbb hover:text-zbb disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="selectedWocheIndex === 0"
              title="Vorherige Woche"
              @click="wechselWoche(-1)"
            >
              <i class="la la-chevron-left"></i>
            </button>
            <Select
              v-model="selectedWocheIndex"
              :options="wochen"
              optionLabel="label"
              optionValue="value"
              class="w-72 max-w-full"
            />
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded border border-gray-300 text-gray-700 hover:border-zbb hover:text-zbb disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="selectedWocheIndex >= wochen.length - 1"
              title="Naechste Woche"
              @click="wechselWoche(1)"
            >
              <i class="la la-chevron-right"></i>
            </button>
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:border-zbb hover:text-zbb"
              title="Aktuelle Woche"
              @click="geheZuAktuellerWoche"
            >
              <i class="la la-calendar-day"></i>
              Heute
            </button>
          </div>

          <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-2">
            <span
              v-for="item in statusUebersicht"
              :key="item.status"
              class="inline-flex items-center gap-2 rounded bg-gray-50 px-2.5 py-1 text-xs text-gray-700"
            >
              <span class="h-2.5 w-2.5 rounded-full" :style="statusFarbe(item.status)"></span>
              {{ item.status }}: {{ item.count }}
            </span>
          </div>
        </div>

        <!-- Tabelle -->
        <div class="max-w-full overflow-hidden rounded border border-gray-200">
          <table class="w-full table-fixed text-sm border-collapse shadow-sm">
            <colgroup>
              <col class="w-52 xl:w-60" />
              <col
                v-for="tag in sichtbareTage"
                :key="'col-' + tag.date"
              />
            </colgroup>
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="sticky left-0 z-20 border bg-gray-100 px-3 py-2 text-left">Teilnehmer</th>
                <th
                  v-for="tag in sichtbareTage"
                  :key="tag.date"
                  class="border px-2 py-2 text-center"
                >
                  <div class="flex flex-col items-center">
                    <span class="font-semibold">{{ tag.wochentag }}</span>
                    <span class="text-xs text-gray-500">{{ tag.kurzdatum }}</span>
                    <span class="text-[11px] text-gray-400">{{ tag.label }}</span>
                  </div>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(t, tIndex) in gruppenTeilnehmer"
                :key="t.id"
                class="hover:bg-gray-50"
              >
                <td class="sticky left-0 z-10 border bg-white px-4 py-3 font-medium text-gray-800">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="truncate">{{ t.vorname }} {{ t.nachname }}</p>
                      <span class="text-sm text-zbb">{{ formatTime(t.pivot?.zeitgeplant?.startzeit) }} - {{formatTime(t.pivot?.zeitgeplant?.endzeit)}}</span>
                    </div>
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"
                      title="Teilnehmer aus Gruppe entfernen"
                      @click="entferneTeilnehmer(t)"
                    >
                      <i class="la la-trash"></i>
                    </button>
                  </div>
                </td>
                <td v-for="tttag in sichtbareTage" :key="tttag.date" class="border px-2 py-3 text-center align-top">
                    <div class="flex flex-col gap-1 items-center">

                        <!-- Anwesenheitsstatus -->

                        <Select
                        v-model="gruppenTeilnehmer[tIndex].anwesenheit[tttag.index]"
                        :options="props.anwesenheitsstatuten"
                        optionLabel="status"
                        optionValue="status"
                        class="w-full min-w-0 text-xs"
                       @change="speichernSofort(
                            t.id,
                            tttag,
                            gruppenTeilnehmer[tIndex].anwesenheit[tttag.index],
                            t.zeiten[tttag.index].start,
                            t.zeiten[tttag.index].ende
                        )"
                    >
                    <template #value="slotProps">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="h-3 w-3 shrink-0 rounded-full inline-block" :style="statusFarbe(slotProps.value)" >

                            </span>
                            <span class="truncate">{{ slotProps.value }}</span>
                        </div>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full inline-block" :style="statusFarbe(slotProps.option.status)" ></span>
                            {{ slotProps.option.status }}
                        </div>
                    </template>
                </Select>


                        <!-- Zeiten -->
                        <div class="mt-1 grid w-full grid-cols-2 gap-1">
                        <InputText
                            type="time"
                            v-model="t.zeiten[tttag.index].start"
                            class="min-w-0 !w-full px-1 text-xs"
                            @blur="speichernSofort(
                                t.id,
                                tttag,
                                gruppenTeilnehmer[tIndex].anwesenheit[tttag.index],
                                t.zeiten[tttag.index].start,
                                t.zeiten[tttag.index].ende
                            )"
                        />

                        <InputText
                            type="time"
                            v-model="t.zeiten[tttag.index].ende"
                            class="min-w-0 !w-full px-1 text-xs"
                            @blur="speichernSofort(
                                t.id,
                                tttag,
                                gruppenTeilnehmer[tIndex].anwesenheit[tttag.index],
                                t.zeiten[tttag.index].start,
                                t.zeiten[tttag.index].ende
                            )"
                        />

                        </div>
                    </div>
                </td>

              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="paAktiv" class="space-y-4 border-t border-gray-200 pt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="font-semibold text-gray-700">Potenzialanalyse</h3>
            <p class="text-sm text-gray-500">{{ props.potenzialanalyse?.tage || '?' }} Tage</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-medium" :class="paAutoSaveStatusClass">
              {{ paAutoSaveStatusText }}
            </span>
            <Button
              label="Jetzt speichern"
              icon="pi pi-save"
              class="!bg-zbb hover:!bg-zbb/80 border-none"
              :disabled="paSaving || !selectedPaTeilnehmer"
              @click="speicherePotenzialanalyse()"
            />
          </div>
        </div>

        <div v-if="!paUebungen.length" class="rounded border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
          Für dieses Projekt sind noch keine PA-Übungen angelegt.
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-[260px_1fr]">
          <div class="rounded border border-gray-200 bg-white">
            <button
              v-for="teilnehmerItem in gruppenTeilnehmer"
              :key="'pa-' + teilnehmerItem.id"
              type="button"
              class="flex w-full items-center justify-between border-b border-gray-100 px-4 py-3 text-left text-sm last:border-b-0 hover:bg-gray-50"
              :class="selectedPaTeilnehmer?.id === teilnehmerItem.id ? 'bg-zbbTrp text-zbb' : 'text-gray-700'"
              @click="waehlePaTeilnehmer(teilnehmerItem)"
            >
              <span class="min-w-0 truncate">{{ teilnehmerItem.vorname }} {{ teilnehmerItem.nachname }}</span>
              <i class="la la-chevron-right text-xs"></i>
            </button>
          </div>

          <div v-if="selectedPaTeilnehmer" class="space-y-2">
            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
              <h4 class="text-sm font-semibold text-gray-800">
                {{ selectedPaTeilnehmer.vorname }} {{ selectedPaTeilnehmer.nachname }}
              </h4>
              <p class="text-xs text-gray-500">Selbsteinschaetzung, Uebungsergebnisse, Kompetenzbewertung und Bericht</p>
            </div>

            <div class="rounded border border-gray-200 bg-white px-2 pt-2">
              <div class="flex gap-2 overflow-x-auto">
                <button
                  v-for="(tab, index) in paTabs"
                  :key="'pa-tab-' + tab.key"
                  type="button"
                  class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-2 pb-2 text-xs font-medium transition"
                  :class="activePaTab === tab.key ? 'border-zbb text-zbb' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                  @click="setPaTab(tab.key)"
                >
                  <span
                    class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold"
                    :class="activePaTab === tab.key ? 'bg-zbb text-white' : 'bg-gray-100 text-gray-500'"
                  >
                    {{ index + 1 }}
                  </span>
                  <span class="whitespace-nowrap">{{ tab.label }}</span>
                </button>
              </div>
            </div>

            <div v-show="activePaTab === 'selbst'" class="rounded border border-gray-200 bg-white">
              <div class="border-b border-gray-100 bg-gray-50 px-3 py-2">
                <h5 class="text-sm font-semibold text-gray-800">Schritt 1: Selbsteinschaetzung</h5>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-xs">
                  <thead class="bg-white text-xs uppercase text-gray-500">
                    <tr>
                      <th class="border-b px-2 py-1.5 text-left">Merkmal</th>
                      <th class="border-b px-2 py-1.5 text-center">Kat.</th>
                      <th v-for="wert in paBewertungWerte" :key="'selbst-head-' + wert" class="border-b px-1 py-1 text-center">
                        <button
                          type="button"
                          class="inline-flex h-6 w-6 items-center justify-center rounded text-xs font-semibold text-gray-600 hover:bg-zbb hover:text-white"
                          :title="`Alle Merkmale mit ${wert} bewerten`"
                          @click="setzePaBewertungSpalte('selbsteinschaetzung', wert)"
                        >
                          {{ wert }}
                        </button>
                      </th>
                      <th class="border-b px-2 py-1.5 text-left">Bemerkung</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="merkmal in paMerkmale" :key="'selbst-' + merkmal.key" class="border-b last:border-b-0">
                      <td class="px-2 py-1.5 font-medium text-gray-800">{{ merkmal.label }}</td>
                      <td class="px-2 py-1.5 text-center text-xs text-gray-500">{{ merkmal.kategorie }}</td>
                      <td v-for="wert in paBewertungWerte" :key="'selbst-' + merkmal.key + '-' + wert" class="px-2 py-1.5 text-center">
                        <input
                          v-model.number="paEintrag(selectedPaTeilnehmer.id).selbsteinschaetzung[merkmal.key].bewertung"
                          type="radio"
                          :name="`pa-selbst-${selectedPaTeilnehmer.id}-${merkmal.key}`"
                          :value="wert"
                          class="h-3.5 w-3.5 text-zbb focus:ring-zbb"
                          @change="setzePaBewertung(selectedPaTeilnehmer.id, 'selbsteinschaetzung', merkmal.key, wert)"
                        />
                      </td>
                      <td class="px-2 py-1.5">
                        <textarea
                          v-model="paEintrag(selectedPaTeilnehmer.id).selbsteinschaetzung[merkmal.key].bemerkung"
                          rows="1"
                          class="h-8 min-h-0 w-full resize-none rounded border-gray-300 py-1 text-xs leading-tight"
                          @input="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                        ></textarea>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-show="activePaTab === 'uebungen'" class="rounded border border-gray-200 bg-white">
              <div class="border-b border-gray-100 bg-gray-50 px-3 py-2">
                <h5 class="text-sm font-semibold text-gray-800">Schritt 2: Uebungen</h5>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                  <thead class="bg-white text-xs uppercase text-gray-500">
                    <tr>
                      <th class="border-b px-3 py-2 text-left">Uebung</th>
                      <th class="border-b px-3 py-2 text-left">Punkte</th>
                      <th class="border-b px-3 py-2 text-left">Zeit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="uebung in paUebungen" :key="'pa-uebung-' + uebung.id" class="border-b last:border-b-0">
                      <td class="px-3 py-3 align-top">
                        <div class="flex flex-wrap items-center gap-2">
                          <p class="font-medium text-gray-800">{{ uebung.name }}</p>
                          <span v-if="uebung.tag" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Tag {{ uebung.tag }}</span>
                          <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Max. {{ uebung.hoechstwert ?? '-' }}</span>
                          <span v-if="uebung.auswertbar" class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-700">Auswertbar</span>
                        </div>
                        <p v-if="uebung.beschreibung" class="mt-1 text-xs text-gray-500">{{ uebung.beschreibung }}</p>
                      </td>
                      <td class="px-3 py-3 align-top">
                        <div class="flex items-center gap-2">
                          <InputText
                            v-model.number="paUebungErgebnis(selectedPaTeilnehmer.id, uebung.id).punkte"
                            type="number"
                            min="0"
                            :max="uebung.hoechstwert ?? undefined"
                            class="w-28"
                            @update:modelValue="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                            @blur="clampUebungPunkte(selectedPaTeilnehmer.id, uebung)"
                          />
                          <span class="text-sm text-gray-500">/ {{ uebung.hoechstwert ?? '-' }}</span>
                        </div>
                      </td>
                      <td class="px-3 py-3 align-top">
                        <div class="flex items-center gap-2">
                          <InputText
                            v-model.number="paUebungErgebnis(selectedPaTeilnehmer.id, uebung.id).zeit_min"
                            type="number"
                            min="0"
                            class="w-24"
                            @update:modelValue="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                          />
                          <span class="text-xs text-gray-500">Min.</span>
                          <InputText
                            v-model.number="paUebungErgebnis(selectedPaTeilnehmer.id, uebung.id).zeit_sec"
                            type="number"
                            min="0"
                            max="59"
                            class="w-20"
                            @update:modelValue="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                          />
                          <span class="text-xs text-gray-500">Sek.</span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-show="activePaTab === 'kompetenzen'" class="rounded border border-gray-200 bg-white">
              <div class="border-b border-gray-100 bg-gray-50 px-3 py-2">
                <h5 class="text-sm font-semibold text-gray-800">Schritt 3: Einschaetzung der Kompetenzen</h5>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-xs">
                  <thead class="bg-white text-xs uppercase text-gray-500">
                    <tr>
                      <th class="border-b px-2 py-1.5 text-left">Merkmal</th>
                      <th class="border-b px-2 py-1.5 text-center">Kat.</th>
                      <th v-for="wert in paBewertungWerte" :key="'kompetenz-head-' + wert" class="border-b px-1 py-1 text-center">
                        <button
                          type="button"
                          class="inline-flex h-6 w-6 items-center justify-center rounded text-xs font-semibold text-gray-600 hover:bg-zbb hover:text-white"
                          :title="`Alle Kompetenzen mit ${wert} bewerten`"
                          @click="setzePaBewertungSpalte('kompetenzen', wert)"
                        >
                          {{ wert }}
                        </button>
                      </th>
                      <th class="border-b px-2 py-1.5 text-left">Bemerkung</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="merkmal in paMerkmale" :key="'kompetenz-' + merkmal.key" class="border-b last:border-b-0">
                      <td class="px-2 py-1.5 font-medium text-gray-800">{{ merkmal.label }}</td>
                      <td class="px-2 py-1.5 text-center text-xs text-gray-500">{{ merkmal.kategorie }}</td>
                      <td v-for="wert in paBewertungWerte" :key="'kompetenz-' + merkmal.key + '-' + wert" class="px-2 py-1.5 text-center">
                        <input
                          v-model.number="paEintrag(selectedPaTeilnehmer.id).kompetenzen[merkmal.key].bewertung"
                          type="radio"
                          :name="`pa-kompetenz-${selectedPaTeilnehmer.id}-${merkmal.key}`"
                          :value="wert"
                          class="h-3.5 w-3.5 text-zbb focus:ring-zbb"
                          @change="setzePaBewertung(selectedPaTeilnehmer.id, 'kompetenzen', merkmal.key, wert)"
                        />
                      </td>
                      <td class="px-2 py-1.5">
                        <textarea
                          v-model="paEintrag(selectedPaTeilnehmer.id).kompetenzen[merkmal.key].bemerkung"
                          rows="1"
                          class="h-8 min-h-0 w-full resize-none rounded border-gray-300 py-1 text-xs leading-tight"
                          @input="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                        ></textarea>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-show="activePaTab === 'bericht'" class="rounded border border-gray-200 bg-white p-4">
              <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h5 class="font-semibold text-gray-800">Bericht</h5>
                <div class="flex flex-wrap items-center gap-2">
                  <Button
                    label="Text generieren"
                    icon="pi pi-pencil"
                    severity="secondary"
                    outlined
                    @click="generierePaBerichtstext"
                  />
                  <Select
                    v-model="paEintrag(selectedPaTeilnehmer.id).bericht.status"
                    :options="paBerichtStatusOptionen"
                    optionLabel="label"
                    optionValue="value"
                    class="w-56 max-w-full"
                    @update:modelValue="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id, sofort: true })"
                  />
                </div>
              </div>

              <div class="grid gap-3 md:grid-cols-2">
                <label class="text-sm text-gray-600">
                  Staerken
                  <textarea
                    v-model="paEintrag(selectedPaTeilnehmer.id).bericht.staerken"
                    rows="4"
                    class="mt-1 w-full rounded border-gray-300 text-sm"
                    @input="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                  ></textarea>
                </label>
                <label class="text-sm text-gray-600">
                  Entwicklungsfelder
                  <textarea
                    v-model="paEintrag(selectedPaTeilnehmer.id).bericht.entwicklungsfelder"
                    rows="4"
                    class="mt-1 w-full rounded border-gray-300 text-sm"
                    @input="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                  ></textarea>
                </label>
                <label class="text-sm text-gray-600 md:col-span-2">
                  Empfehlung
                  <textarea
                    v-model="paEintrag(selectedPaTeilnehmer.id).bericht.empfehlung"
                    rows="3"
                    class="mt-1 w-full rounded border-gray-300 text-sm"
                    @input="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                  ></textarea>
                </label>
                <label class="text-sm text-gray-600 md:col-span-2">
                  Berichtstext
                  <textarea
                    v-model="paEintrag(selectedPaTeilnehmer.id).bericht.bericht_text"
                    rows="6"
                    class="mt-1 w-full rounded border-gray-300 text-sm"
                    @input="planePotenzialanalyseSpeichern({ personenId: selectedPaTeilnehmer.id })"
                  ></textarea>
                </label>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded border border-gray-200 bg-white px-3 py-2">
              <Button
                label="Zurueck"
                icon="pi pi-arrow-left"
                severity="secondary"
                outlined
                :disabled="activePaTabIndex === 0"
                @click="wechselPaTab(-1)"
              />
              <span class="text-sm text-gray-500">
                Schritt {{ activePaTabIndex + 1 }} von {{ paTabs.length }}
              </span>
              <Button
                label="Weiter"
                icon="pi pi-arrow-right"
                iconPos="right"
                class="!bg-zbb hover:!bg-zbb/80 border-none"
                :disabled="activePaTabIndex === paTabs.length - 1"
                @click="wechselPaTab(1)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
