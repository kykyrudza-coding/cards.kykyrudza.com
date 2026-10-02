// Original vector variant: cool white stock, sapphire ink and Art Deco back.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const put = (path, body) => { mkdirSync(dirname(resolve(root,path)), {recursive:true}); writeFileSync(resolve(root,path),body); };
const manifest = JSON.parse(readFileSync(resolve(root,'manifest.json'),'utf8'));
const cards = manifest.cards.map(card => {
  const path = card.path.replace('/classic/', '/midnight/');
  const art = readFileSync(resolve(root,card.path),'utf8')
    .replaceAll('#fffdf5','#f7faff').replaceAll('#172b30','#17345f')
    .replaceAll('#b83f43','#b62e57').replaceAll('#c7ad77','#809bc2')
    .replaceAll('#d7cfbd','#a6b9d3').replaceAll('#e9e2d3','#cfdded')
    .replaceAll('Georgia,serif','Verdana,sans-serif');
  put(path,art);
  return {...card,path};
});
const back = 'cards/midnight/back/back.svg';
put(back, `<svg xmlns="http://www.w3.org/2000/svg" width="240" height="336" viewBox="0 0 240 336"><defs><pattern id="deco" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M0 20 20 0 40 20 20 40Z M10 20 20 10 30 20 20 30Z" fill="none" stroke="#7f9ec6" stroke-width=".8"/></pattern><linearGradient id="blue" x2="1" y2="1"><stop stop-color="#244777"/><stop offset="1" stop-color="#0c1836"/></linearGradient></defs><rect x="1" y="1" width="238" height="334" rx="16" fill="#f7faff" stroke="#a6b9d3" stroke-width="2"/><rect x="10" y="10" width="220" height="316" rx="10" fill="url(#blue)"/><rect x="18" y="18" width="204" height="300" rx="6" fill="url(#deco)"/><path d="M30 70V30H70 M170 30H210V70 M210 266V306H170 M70 306H30V266" fill="none" stroke="#d7e4f5" stroke-width="3"/><path d="M120 76 183 168 120 260 57 168Z" fill="#132a50" stroke="#adc7e9" stroke-width="3"/><path d="M120 97 168 168 120 239 72 168Z" fill="none" stroke="#adc7e9"/><path d="M120 127 148 168 120 209 92 168Z" fill="#d7e4f5"/><path d="M120 142 138 168 120 194 102 168Z" fill="#41669b"/></svg>`);
const table = 'tables/midnight/table.svg';
put(table, readFileSync(resolve(root,manifest.table),'utf8').replaceAll('#23685a','#305580').replaceAll('#164e44','#1b365b').replaceAll('#0b2e2a','#0c1c35').replaceAll('#aa915e','#a7bad5').replaceAll('#d9c18c','#b8cce8').replaceAll('#34322c','#28354c').replaceAll('#121c1b','#0d1627'));
manifest.themes = { classic: { cards: manifest.cards, back:manifest.back, table:manifest.table }, midnight:{cards,back,table} };
put('manifest.json', JSON.stringify(manifest,null,2)+'\n');
console.log('Generated Midnight: 52 faces, back and table.');
