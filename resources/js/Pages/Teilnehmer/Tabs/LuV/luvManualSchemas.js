const competenceFields = (mode = 'start') => {
    const competencies = [
        ['school', 'Schulische Basiskompetenzen'],
        ['personal', 'Personale Kompetenz'],
        ['methodical', 'Methodische Kompetenz'],
        ['social', 'Sozial-kommunikative Kompetenz'],
        ['technical', 'Fachliche Basiskompetenzen / Erprobung in Berufsfeldern'],
    ];

    return competencies.flatMap(([key, label]) => mode === 'start'
        ? [
            { key: `competence.${key}.assessment`, label: `${label} – Einschätzung`, type: 'textarea' },
            { key: `competence.${key}.support_need`, label: `${label} – Förderbedarf`, type: 'textarea' },
        ]
        : [
            { key: `competence.${key}.previous_need`, label: `${label} – bisheriger Förderbedarf`, type: 'textarea' },
            { key: `competence.${key}.current_need`, label: `${label} – aktueller Förderbedarf`, type: 'textarea' },
        ]);
};

const sequenceFields = [
    { key: 'sequences.foundations', label: 'Allgemeiner Grundlagenbereich', type: 'textarea' },
    { key: 'sequences.language', label: 'Sprachförderung', type: 'textarea' },
    { key: 'sequences.key_competencies', label: 'Schlüssel- und Selbstlernkompetenzen', type: 'textarea' },
    { key: 'sequences.digital', label: 'Digitale sowie IT- und Medienkompetenzen', type: 'textarea' },
    { key: 'sequences.orientation', label: 'Berufsorientierung, Berufsfelder und Berufswahl', type: 'textarea' },
    { key: 'sequences.company_phases', label: 'Betriebsnahe/betriebliche Qualifizierungsphasen', type: 'textarea' },
    { key: 'sequences.work_social', label: 'Arbeits- und Sozialverhalten / betriebliche Grundfertigkeiten', type: 'textarea' },
    { key: 'sequences.applications', label: 'Bewerbungstraining', type: 'textarea' },
    { key: 'sequences.vocational', label: 'Berufsspezifische Qualifizierung / Übergangsmanagement', type: 'textarea' },
    { key: 'sequences.school_certificate', label: 'Erwerb Hauptschulabschluss / Berufsschulunterricht', type: 'textarea' },
];

const taskFields = [
    { key: 'tasks.participant', label: 'Aufgaben der teilnehmenden Person', type: 'textarea' },
    { key: 'tasks.case_management', label: 'Bildungsbegleitung / Case-Management', type: 'textarea' },
    { key: 'tasks.trainer', label: 'Ausbilderin/Ausbilder', type: 'textarea' },
    { key: 'tasks.teacher', label: 'Lehrkraft', type: 'textarea' },
    { key: 'tasks.social_worker', label: 'Sozialpädagogin/Sozialpädagoge', type: 'textarea' },
    { key: 'tasks.psychologist', label: 'Psychologin/Psychologe', type: 'textarea' },
    { key: 'tasks.other_staff', label: 'Weiteres Fachpersonal', type: 'textarea' },
    { key: 'tasks.residential_staff', label: 'Lernort Wohnen/Internat (nur BvB 3)', type: 'textarea' },
    { key: 'tasks.joint', label: 'Gemeinsame Aufgaben', type: 'textarea' },
];

const commonGroups = [
    {
        key: 'master_data',
        heading: '1. Daten zur teilnehmenden Person',
        description: 'Teilnehmerstammdaten werden automatisch aus dem aktiven Projekt übernommen.',
        fields: [
            { key: 'report.report_date', label: 'Leistungs- und Verhaltensbeurteilung vom', type: 'date', required: true },
            { key: 'report.residential_learning', label: 'Lernort Wohnen / Internat', type: 'boolean' },
            { key: 'contact.name', label: 'Kontaktperson beim Maßnahmeträger', type: 'text' },
            { key: 'contact.phone', label: 'Telefonnummer', type: 'text' },
            { key: 'contact.email', label: 'E-Mail', type: 'email' },
        ],
    },
];

export const manualLuvSchemas = {
    Start: [
        ...commonGroups,
        { key: 'initial_situation', heading: '2. Darstellung der individuellen Ausgangssituation', description: 'Nur dokumentierte Beobachtungen eintragen; keine Diagnosen oder Vermutungen.', fields: [...competenceFields('start'), { key: 'competence.notes', label: 'Ergänzende Erläuterungen', type: 'textarea' }] },
        { key: 'funding_sequences', heading: '3. Förderzielbereiche und Förder-/Qualifizierungssequenzen', description: 'Je Eintrag möglichst geplanten Zeitraum und konkrete Sequenz nennen.', fields: sequenceFields },
        { key: 'integration_goal', heading: '4. Eingliederungsziel', fields: [{ key: 'integration.goal', label: '(Ausbildungs-)Zielberuf und Alternativen', type: 'textarea', required: true }] },
        { key: 'goal_steps', heading: '5. Schritte zur Zielerreichung', fields: taskFields },
        { key: 'decision', heading: '6. Andere entscheidungsbedürftige Aspekte', fields: [{ key: 'decision.notes', label: 'Begründeter Entscheidungsbedarf / bereits erfolgte Aktivitäten', type: 'textarea' }] },
        { key: 'discussion', heading: '7. Gespräch und Aushändigung', fields: [{ key: 'report.discussed_on', label: 'Mit der teilnehmenden Person besprochen am', type: 'date' }, { key: 'report.copy_handed_out', label: 'Kopie wurde ausgehändigt', type: 'boolean' }] },
    ],
    Verlauf: [
        ...commonGroups,
        { key: 'reason', heading: 'Anlass der Verlauf-LuV', fields: [{ key: 'reason.type', label: 'Anlass', type: 'select', required: true, options: ['6 Monate nach Maßnahmebeginn', '7 Monate nach Maßnahmebeginn (BvB 3)', '6 Wochen vor Maßnahmeende', 'Maßnahmeverlängerung', 'Sonstiger Anlass'] }, { key: 'reason.details', label: 'Ergänzung zum Anlass', type: 'text' }] },
        { key: 'development', heading: '2. Individuelle Entwicklung während der Maßnahme', description: 'Bisherigen und aktuellen Förderbedarf nachvollziehbar gegenüberstellen.', fields: [{ key: 'development.compared_to', label: 'Gegenüber der LuV vom', type: 'date' }, ...competenceFields('progress'), { key: 'development.notes', label: 'Ergänzende Erläuterungen', type: 'textarea' }] },
        { key: 'funding_sequences', heading: '3. Förderzielbereiche und Förder-/Qualifizierungssequenzen', fields: [...sequenceFields, { key: 'sequences.completed_notes', label: 'Abgeschlossene Sequenzen / Nachweise', type: 'textarea' }] },
        { key: 'integration_goal', heading: '4. Eingliederungsziel', fields: [{ key: 'integration.goal', label: '(Ausbildungs-)Zielberuf und Alternativen', type: 'textarea', required: true }] },
        { key: 'goal_steps', heading: '5. Schritte zur Zielerreichung', fields: taskFields },
        { key: 'decision', heading: '6. Andere entscheidungsbedürftige Aspekte', fields: [{ key: 'decision.notes', label: 'Abbruch, Teilzeit, Verlängerung oder sonstiger Entscheidungsbedarf', type: 'textarea' }] },
        { key: 'discussion', heading: '7. Gespräch und Aushändigung', fields: [{ key: 'report.discussed_on', label: 'Besprochen am', type: 'date' }, { key: 'report.copy_handed_out', label: 'Kopie wurde ausgehändigt', type: 'boolean' }] },
    ],
    Abschluss: [
        ...commonGroups,
        { key: 'completion_reason', heading: 'Anlass der Abschluss-LuV', fields: [{ key: 'completion.reason', label: 'Beendigungsart', type: 'select', required: true, options: ['Reguläres Ende der Maßnahme', 'Vorzeitige Beendigung der Maßnahme (Abbruch)'] }] },
        { key: 'results', heading: '2. Ergebnisse der BvB', fields: [
            { key: 'results.school_certificate', label: 'Hauptschulabschluss bzw. vergleichbarer Abschluss', type: 'select', options: ['Erreicht', 'Nicht erreicht', 'Nicht angestrebt'] },
            { key: 'results.training_maturity', label: 'Allgemeine Ausbildungsreife erreicht', type: 'boolean' },
            { key: 'results.suitable_occupations', label: 'Berufseignung / Qualifikationsniveau', type: 'textarea' },
            { key: 'results.modules', label: 'Qualifizierungs-/Ausbildungsbausteine', type: 'textarea' },
            { key: 'results.placement_ability', label: 'Aussagen zur Vermittlungsfähigkeit', type: 'textarea' },
            { key: 'results.integration', label: 'Eingliederungsergebnis (Betrieb, Beruf, Zeitpunkt)', type: 'textarea' },
        ] },
        { key: 'support', heading: 'Unterstützungsbedarf und Stabilisierung', fields: [
            { key: 'support.required', label: 'Weiterer Unterstützungsbedarf', type: 'boolean' },
            { key: 'support.description', label: 'Beschreibung des Unterstützungsbedarfs', type: 'textarea' },
            { key: 'support.stabilization', label: 'Absprachen zur Stabilisierung und Festigung', type: 'textarea' },
            { key: 'support.recommendations', label: 'Ergänzende Erläuterungen / Empfehlungen', type: 'textarea' },
        ] },
        { key: 'discussion', heading: '3. Gespräch und Aushändigung', fields: [{ key: 'report.discussed_on', label: 'Besprochen am', type: 'date' }, { key: 'report.copy_handed_out', label: 'Kopie wurde ausgehändigt', type: 'boolean' }] },
    ],
};

export const fieldsForType = (type) => (manualLuvSchemas[type] || manualLuvSchemas.Start)
    .flatMap((group) => group.fields.map((field) => ({ ...field, groupKey: group.key, groupHeading: group.heading })));
