// Copy only runtime files; keep assets/ as the single source of truth.
import { readFileSync, mkdirSync, copyFileSync, existsSync } from 'node:fs';
import { dirname, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const target=resolve(root,'../frontend/public/blackjack');
const manifest=JSON.parse(readFileSync(resolve(root,'manifest.json'),'utf8'));
const recorded=JSON.parse(readFileSync(resolve(root,'audio/recorded-manifest.json'),'utf8'));
const files=['manifest.json','preview.html','README.md',manifest.back,manifest.table,...manifest.cards.map(c=>c.path),...manifest.chips.map(c=>c.path),...manifest.sfx.map(c=>c.path),...manifest.voices.map(c=>c.path),'audio/voices/uk/phrases.json'];
files.push('audio/recorded-manifest.json',...recorded.flatMap(clip=>Object.values(clip.files)));
for (const theme of Object.values(manifest.themes ?? {})) files.push(theme.back, theme.table, ...theme.cards.map(card=>card.path));
for(const file of new Set(files)) {
  const src=resolve(root,file), dst=resolve(target,file);
  if(!src.startsWith(root+sep)||!dst.startsWith(target+sep)) throw new Error(`Invalid path: ${file}`);
  if(!existsSync(src)) throw new Error(`Missing asset: ${file}`);
}
for(const file of new Set(files)) {const dst=resolve(target,file);mkdirSync(dirname(dst),{recursive:true});copyFileSync(resolve(root,file),dst);}
console.log(`Copied ${new Set(files).size} files to frontend/public/blackjack`);
