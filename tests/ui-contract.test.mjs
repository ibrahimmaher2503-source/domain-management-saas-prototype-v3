import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/app.js', import.meta.url), 'utf8');

test('dashboard domain deep-links are wired to their requested detail tab', () => {
  assert.match(source, /querySelectorAll\('\[data-open-domain-tab\]'\)/);
});
