import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolveAddressCode, addressSelectOptions } from '../src/lib/addressOptions.js';

const countries = { US: 'United States', CA: 'Canada' };
const states = { US: { CO: 'Colorado' }, CA: { ON: 'Ontario' } };
assert.equal(resolveAddressCode('United States', countries), 'US');
assert.equal(resolveAddressCode('ca', countries), 'CA');
assert.equal(resolveAddressCode('Colorado', states.US), 'CO');
assert.deepEqual(addressSelectOptions(states.US, 'CO'), [['CO', 'Colorado']]);
assert.deepEqual(addressSelectOptions(states.US, 'Legacy region'), [['Legacy region', 'Legacy region'], ['CO', 'Colorado']]);
assert.equal(resolveAddressCode('Ontario', states[resolveAddressCode('Canada', countries)]), 'ON');
assert.deepEqual(addressSelectOptions({}, ''), []);
assert.deepEqual(addressSelectOptions({}, 'Unlisted country'), [['Unlisted country', 'Unlisted country']]);
const source = readFileSync(new URL('../src/components/tabs/AccountTab.jsx', import.meta.url), 'utf8');
assert.ok(!source.includes('Turn Off Automatic Renewal'));
assert.ok(!source.includes('handleCancel'));
assert.ok(source.includes("country: e.target.value, state: ''"));
console.log('Profile address matching, legacy values, country changes, and renewal removal checks passed.');
