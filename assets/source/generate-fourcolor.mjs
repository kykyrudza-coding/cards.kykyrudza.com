// Four-colour deck: the classic faces, but every suit has its own ink
// (clubs green, diamonds blue), so suits never rely on red-vs-black alone.
// Run after generate-classic.mjs / generate-midnight.mjs; it only adds a theme.
import { readFileSync, writeFileSync, mkdirSync, copyFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const put = (path, body) => { mkdirSync(dirname(resolve(root, path)), { recursive: true }); writeFileSync(resolve(root, path), body); };
const manifest = JSON.parse(readFileSync(resolve(root, 'manifest.json'), 'utf8'));
const classic = manifest.themes?.classic ?? { cards: manifest.cards, back: manifest.back, table: manifest.table };
const ink = { clubs: ['#172b30', '#1d6b3c'], diamonds: ['#b83f43', '#2a62b8'] };
const cards = classic.cards.map(card => {
  const path = card.path.replace('/classic/', '/fourcolor/');
  let art = readFileSync(resolve(root, card.path), 'utf8');
  const swap = ink[card.suit];
  if (swap) art = art.replaceAll(swap[0], swap[1]);
  put(path, art);
  return { ...card, path };
});
const back = 'cards/fourcolor/back/back.svg';
mkdirSync(dirname(resolve(root, back)), { recursive: true });
copyFileSync(resolve(root, classic.back), resolve(root, back));
manifest.themes = { ...manifest.themes, classic, fourcolor: { cards, back, table: classic.table } };
put('manifest.json', JSON.stringify(manifest, null, 2) + '\n');
console.log('Generated Four colour: 52 faces and back.');
