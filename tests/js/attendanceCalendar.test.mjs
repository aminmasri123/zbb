import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { parse, compileScript } from '@vue/compiler-sfc';
import { attendanceDayAllowed } from '../../resources/js/utils/attendanceCalendar.mjs';

const holidays = { 2026: { '2026-05-14': 'Christi Himmelfahrt', '2026-08-15': 'Mariä Himmelfahrt' }, 2027: { '2027-01-01': 'Neujahr' } };

test('defaults exclude Saturdays, Sundays and holidays; independent exceptions can be switched back off', () => {
  const options = {};
  const allowed = date => attendanceDayAllowed(date, holidays, options);
  assert.equal(allowed('2026-09-18'), true);
  for (const date of ['2026-09-19', '2026-09-20', '2026-05-14', '2026-08-15', '2027-01-01']) assert.equal(allowed(date), false);
  options.includeSaturday = true;
  assert.equal(allowed('2026-09-19'), true);
  assert.equal(allowed('2026-08-15'), false);
  options.includeHolidays = true;
  assert.equal(allowed('2026-08-15'), true);
  options.includeSaturday = false;
  assert.equal(allowed('2026-09-19'), false);
  assert.equal(allowed('2026-08-15'), false);
  assert.equal(attendanceDayAllowed('2028-01-04', holidays), false, 'An unloaded year must not bypass holiday filtering.');
});

for (const type of ['BIBB', 'PA']) {
  test(`${type}: existing draft days obey live filters and export selection without mutating saved data`, () => {
    const file = `../../resources/js/Pages/Partner/BOP/ModalAnwesenheitsliste${type}Digital.vue`;
    const { descriptor } = parse(readFileSync(new URL(file, import.meta.url), 'utf8'));
    const script = compileScript(descriptor, { id: 'calendar-regression' });
    const names = ['visibleDays', 'selectedDays', ...(type === 'BIBB' ? ['programDays', 'selectedProgramDaysPayload'] : ['selectedDaysPayload'])];
    const source = script.scriptSetupAst.filter(node => node.type === 'VariableDeclaration' && names.includes(node.declarations[0].id.name))
      .map(node => descriptor.scriptSetup.content.slice(node.start, node.end)).join('\n');
    const days = Array.from({ length: 14 }, (_, i) => ({ id: `day-${i}`, date: `2026-09-${14 + i}`, selected: true, type: 'group_day' }));
    const before = JSON.stringify(days);
    const options = {};
    const ctx = { days: { value: days }, computed: get => ({ get value() { return get(); } }), allowsDay: date => attendanceDayAllowed(date, holidays, options) };
    vm.createContext(ctx);
    vm.runInContext(source + `\nthis.exportDays = ${type === 'BIBB' ? 'selectedProgramDaysPayload' : 'selectedDaysPayload'};`, ctx);
    const exported = () => Array.from(ctx.exportDays(), day => day.date);
    assert.equal(exported().length, 10);
    assert.equal(exported().at(-1), '2026-09-25');
    assert.equal(exported().includes('2026-09-19'), false);
    options.includeSaturday = true;
    assert.equal(exported().includes('2026-09-19'), true);
    options.includeSaturday = false;
    assert.equal(exported().includes('2026-09-19'), false);
    assert.equal(JSON.stringify(days), before);
  });
}
