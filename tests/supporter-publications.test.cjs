const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('wordpress/aac-member-portal/aac-member-portal.php', 'utf8');
const start = source.indexOf('function syncPublicationCardControls()');
const end = source.indexOf('function organizeMemberDetails()', start);
const script = source.slice(start, end) + '\nsyncPublicationCardControls();';

function controls(locked, nativeFields = true) {
  const names = ['aaj_preference', 'anac_preference', 'american_climbing_journal_preference', 'guidebook_preferences'];
  const rows = names.map(name => {
    let selected = 'Print'; // Exercise stale native/saved values, not just clean defaults.
    const selection = { innerHTML: '' };
    const card = { dataset: { aacDigitalOnly: String(locked) }, handlers: {},
      classList: { toggle() {} }, setAttribute() {}, querySelector() { return selection; },
      addEventListener(event, callback) { this.handlers[event] = callback; } };
    const native = { name, id: name, value: 'Print', closest() { return null; }, dispatchEvent() {} };
    const radios = ['Print', 'Digital'].map(value => ({value, dataset: {}, disabled: locked && value === 'Print', handlers: {},
      get checked() { return selected === value; }, set checked(on) { if (on) selected = value; else if (selected === value) selected = ''; },
      closest() { return card; }, addEventListener(event, callback) { this.handlers[event] = callback; },
      dispatchEvent(event) { this.handlers[event.type]?.(); } }));
    return { name, card, native, radios, selection };
  });
  const document = { querySelectorAll(selector) {
    if (selector === 'select') return nativeFields ? rows.map(row => row.native) : [];
    return rows.find(row => selector.includes(`name="${row.name}"`))?.radios || [];
  }};
  vm.runInNewContext(script, { document, Event: class { constructor(type) { this.type = type; } } });
  return rows;
}

test('Supporter cards stay Digital after mouse, Enter and Space input', () => {
  for (const row of controls(true)) {
    for (const action of [() => row.card.handlers.click(), ...['Enter', ' '].map(key => () => row.card.handlers.keydown({key, preventDefault() {}}))]) {
      action();
      assert.equal(row.native.value, 'Digital');
      assert.equal(row.radios[0].checked, false);
      assert.equal(row.radios[0].disabled, true);
      assert.equal(row.radios[1].checked, true);
      assert.equal(row.card.tabIndex, -1);
      assert.equal(row.selection.innerHTML, '<span>Digital</span>');
    }
  }
});

test('other tiers still toggle Print and Digital with mouse and keyboard', () => {
  for (const row of controls(false)) {
    assert.equal(row.native.value, 'Print');
    row.card.handlers.click();
    assert.equal(row.native.value, 'Digital');
    row.card.handlers.keydown({key:' ', preventDefault() {}});
    assert.equal(row.native.value, 'Print');
    assert.equal(row.card.tabIndex, 0);
    assert.equal(row.radios[0].disabled, false);
  }
});

test('server-rendered Supporter cards remain labeled and locked without native PMPro selects', () => {
  for (const row of controls(true, false)) {
    row.card.handlers.click();
    assert.equal(row.radios[1].checked, true);
    assert.equal(row.radios[0].disabled, true);
    assert.equal(row.selection.innerHTML, '<span>Digital</span>');
  }
});
