// Original, deterministic SVG art; recorded sound manifest. Run with Node.js.
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const put = (path, data) => { const out = resolve(root, path); mkdirSync(dirname(out), { recursive: true }); writeFileSync(out, data); };
const svg = (w, h, body, label) => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" role="img" aria-label="${label}">${body}</svg>\n`;
const suits = {
  clubs: { code: 'C', color: '#172b30', path: 'M0 5C-18 21-30 2-17-8C-31-25-9-36 0-22C9-36 31-25 17-8C30 2 18 21 0 5L8 27H-8Z' },
  diamonds: { code: 'D', color: '#b83f43', path: 'M0-30 22 0 0 30-22 0Z' },
  hearts: { code: 'H', color: '#b83f43', path: 'M0 28C-8 15-28 2-26-14C-24-33-5-32 0-19C5-32 24-33 26-14C28 2 8 15 0 28Z' },
  spades: { code: 'S', color: '#172b30', path: 'M0-30C-8-17-29-3-26 11C-23 26-7 23 0 12L-8 30H8L0 12C7 23 23 26 26 11C29-3 8-17 0-30Z' },
};
const ranks = ['A','2','3','4','5','6','7','8','9','10','J','Q','K'];
const pip = (s, x, y, size=0.72, flip=false) => `<path d="${s.path}" fill="${s.color}" transform="translate(${x} ${y}) scale(${size})${flip ? ' rotate(180)' : ''}"/>`;
const positions = {
  2:[[120,89],[120,247]], 3:[[120,89],[120,168],[120,247]],
  4:[[78,89],[162,89],[78,247],[162,247]],
  5:[[78,89],[162,89],[120,168],[78,247],[162,247]],
  6:[[78,89],[162,89],[78,168],[162,168],[78,247],[162,247]],
  7:[[78,89],[162,89],[120,128],[78,168],[162,168],[78,247],[162,247]],
  8:[[78,89],[162,89],[120,128],[78,168],[162,168],[120,208],[78,247],[162,247]],
  9:[[78,78],[162,78],[78,138],[162,138],[120,168],[78,198],[162,198],[78,258],[162,258]],
  10:[[78,78],[162,78],[120,108],[78,138],[162,138],[78,198],[162,198],[120,228],[78,258],[162,258]],
};
const cards = [];
for (const [name,s] of Object.entries(suits)) for (const rank of ranks) {
  const corner = `<text x="27" y="39" text-anchor="middle" font-family="Georgia,serif" font-weight="bold" font-size="${rank==='10'?23:28}" fill="${s.color}">${rank}</text>${pip(s,27,60,.30)}`;
  let art;
  if (rank === 'A') art = pip(s,120,163,1.85) + `<path d="M86 241H154" stroke="#c7ad77"/>`;
  else if (positions[rank]) art = positions[rank].map(([x,y])=>pip(s,x,y,.64,y>168)).join('');
  else {
    const crown = `<path d="M83 112 77 83 99 96 120 69 141 96 163 83 157 112Z" fill="#c7ad77"/><path d="M86 120H154" stroke="${s.color}" stroke-width="3"/>`;
    art = `<rect x="58" y="64" width="124" height="208" rx="54" fill="${s.color}" opacity=".055"/><path d="M120 51 187 168 120 285 53 168Z" fill="none" stroke="#c7ad77"/>${crown}<text x="120" y="197" text-anchor="middle" font-family="Georgia,serif" font-size="77" fill="${s.color}">${rank}</text>${pip(s,120,237,.44)}`;
  }
  const path = `cards/classic/faces/${rank}${s.code}.svg`;
  put(path,svg(240,336,`<rect x="1" y="1" width="238" height="334" rx="16" fill="#fffdf5" stroke="#d7cfbd" stroke-width="2"/><rect x="9" y="9" width="222" height="318" rx="11" fill="none" stroke="#e9e2d3"/>${corner}<g transform="translate(240 336) rotate(180)">${corner}</g>${art}`,`${rank} of ${name}`));
  cards.push({id:rank+s.code,rank,suit:name,path});
}
put('cards/classic/back/back.svg',svg(240,336,`<defs><pattern id="weave" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M10 0 20 10 10 20 0 10Z" fill="none" stroke="#b89c63" stroke-width=".65"/><circle cx="10" cy="10" r="1.3" fill="#b89c63"/></pattern></defs><rect x="1" y="1" width="238" height="334" rx="16" fill="#fffdf5" stroke="#d7cfbd" stroke-width="2"/><rect x="10" y="10" width="220" height="316" rx="10" fill="#153a39"/><rect x="17" y="17" width="206" height="302" rx="7" fill="url(#weave)"/><rect x="24" y="24" width="192" height="288" rx="5" fill="none" stroke="#c7ad77"/><path d="M120 88 177 168 120 248 63 168Z" fill="#153a39" stroke="#c7ad77" stroke-width="3"/><path d="M120 108 161 168 120 228 79 168Z" fill="none" stroke="#c7ad77"/>${pip({...suits.spades,color:'#dac394'},120,168,1.1)}`,'Classic card back'));
put('tables/classic/table.svg',svg(1920,1080,`<defs><radialGradient id="felt"><stop stop-color="#23685a"/><stop offset=".65" stop-color="#164e44"/><stop offset="1" stop-color="#0b2e2a"/></radialGradient><pattern id="fibers" width="7" height="7" patternUnits="userSpaceOnUse"><path d="M0 1H7M1 0V7" stroke="#c8e8d3" stroke-opacity=".035"/><path d="M0 5H7M5 0V7" stroke="#000" stroke-opacity=".05"/></pattern><radialGradient id="rail"><stop offset=".7" stop-color="#34322c"/><stop offset="1" stop-color="#121c1b"/></radialGradient></defs><rect width="1920" height="1080" fill="url(#rail)"/><rect x="42" y="42" width="1836" height="996" rx="270" fill="url(#felt)" stroke="#aa915e" stroke-width="3"/><rect x="44" y="44" width="1832" height="992" rx="268" fill="url(#fibers)"/><rect x="68" y="68" width="1784" height="944" rx="248" fill="none" stroke="#d9c18c" stroke-opacity=".26" stroke-dasharray="3 8"/><path d="M400 457Q960 707 1520 457" fill="none" stroke="#d9c18c" stroke-opacity=".28" stroke-width="3"/><path d="M440 482Q960 717 1480 482" fill="none" stroke="#d9c18c" stroke-opacity=".12"/>`,'Classic green blackjack table'));
const chips = [];
for (const [value,color] of [[5,'#aa454a'],[25,'#24776b'],[100,'#253441']]) {
  let blocks='';
  for(let n=0;n<8;n++) blocks+=`<g transform="rotate(${n*45} 128 128)"><path d="M116 11H140L137 43H119Z" fill="#f3ead5"/><path d="M124 13H132V40H124Z" fill="#c4a465"/></g>`;
  const path=`chips/classic/chip-${value}.svg`;
  put(path,svg(256,256,`<circle cx="128" cy="128" r="125" fill="#121e21"/><circle cx="128" cy="125" r="121" fill="${color}" stroke="#e3cd9b" stroke-width="2"/>${blocks}<circle cx="128" cy="125" r="88" fill="none" stroke="#e3cd9b" stroke-width="2"/><circle cx="128" cy="125" r="79" fill="#f5eddb"/><circle cx="128" cy="125" r="70" fill="none" stroke="${color}" stroke-dasharray="2 5"/><text x="128" y="145" text-anchor="middle" font-family="Georgia,serif" font-size="58" font-weight="bold" fill="${color}">${value}</text><path d="M109 77H147M109 176H147" stroke="#b29460" stroke-width="2"/>`,'Chip '+value));
  chips.push({value,path});
}
const sounds = JSON.parse(readFileSync(resolve(root,'audio/recorded-manifest.json'),'utf8'));
const voices=[{id:'twenty',text:'У вас двадцять'},{id:'bust',text:'Перебір'},{id:'you-win',text:'Ви виграли'}].map(v=>({...v,path:`audio/voices/uk/${v.id}.mp3`,locale:'uk-UA',voice:'uk-UA-OstapNeural'}));
put('manifest.json',JSON.stringify({version:1,name:'Blackjack Classic',cardSize:{width:240,height:336},cards,back:'cards/classic/back/back.svg',table:'tables/classic/table.svg',chips,sfx:sounds.map(s=>({id:s.id,path:s.files.ogg,duration:s.duration,volume:.8})),voices},null,2)+'\n');
put('audio/voices/uk/phrases.json',JSON.stringify(voices,null,2)+'\n');
console.log(`Generated ${cards.length} faces, 1 back, 1 table, ${chips.length} chips, ${sounds.length} SFX and manifest. Voice MP3s are generated separately.`);
