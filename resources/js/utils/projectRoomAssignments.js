const normalizeText = (value) =>
    String(value || '')
        .replace(/\u00c3\u00bc/g, 'ue')
        .replace(/\u00c3\u009c/g, 'ue')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

export const ensureProjectRoomAssignmentFields = (row) => {
    row.buero_raum_ids = Array.isArray(row.buero_raum_ids) ? row.buero_raum_ids : [];
    row.default_buero_raum_id = row.default_buero_raum_id ?? null;
    row.arbeitsbereich_raum_ids = Array.isArray(row.arbeitsbereich_raum_ids) ? row.arbeitsbereich_raum_ids : [];
    row.default_arbeitsbereich_raum_id = row.default_arbeitsbereich_raum_id ?? null;
};

export const emptyRoomAssignmentFields = () => ({
    buero_raum_ids: [],
    default_buero_raum_id: null,
    arbeitsbereich_raum_ids: [],
    default_arbeitsbereich_raum_id: null,
});

export const resetRoomAssignments = (row) => {
    row.buero_raum_ids = [];
    row.default_buero_raum_id = null;
    row.arbeitsbereich_raum_ids = [];
    row.default_arbeitsbereich_raum_id = null;
};

export const roomIsBuero = (raum) => {
    const typ = normalizeText(raum?.typ);

    return ['buro', 'buero', 'arbeitsplatz'].includes(typ);
};

export const projectRoomsFor = (projects, projectId) =>
    (projects || []).find((projekt) => Number(projekt.id) === Number(projectId))?.raeume || [];

export const bueroRoomsForProject = (projects, projectId) =>
    projectRoomsFor(projects, projectId).filter(roomIsBuero);

export const arbeitsbereichRoomsForProject = (projects, projectId) =>
    projectRoomsFor(projects, projectId).filter((raum) => !roomIsBuero(raum));

export const selectedRoomsForRow = (projects, row, idsKey, optionsResolver) => {
    const selectedIds = (row[idsKey] || []).map((id) => Number(id));

    return optionsResolver(projects, row.projekt_id).filter((raum) => selectedIds.includes(Number(raum.id)));
};

export const normalizeDefaultRoom = (row, idsKey, defaultKey) => {
    const selectedIds = (row[idsKey] || []).map((id) => Number(id));

    if (selectedIds.length === 1) {
        row[defaultKey] = selectedIds[0];
        return;
    }

    if (!selectedIds.includes(Number(row[defaultKey]))) {
        row[defaultKey] = null;
    }
};

export const roomLabel = (raum) => {
    const standortName = raum?.standort?.name;
    const raumnummer = raum?.raumnummer ? ` ${raum.raumnummer}` : '';
    const name = `${raum?.name || 'Raum'}${raumnummer}`;

    return standortName ? `${name} (${standortName})` : name;
};
