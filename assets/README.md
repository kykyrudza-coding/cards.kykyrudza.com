# Blackjack — Classic та Midnight

Два 2D-набори: по 52 лицьові сторони + сорочка, зелений і синій столи, фішки 5/25/100,
17 записаних SFX і 3 українські тестові репліки. 3D-моделі не потрібні для цього етапу.

## Перегляд і підключення

Відкрийте `assets/preview.html` у браузері — карти й аудіо працюють також локально.
У frontend `npm run dev` і `npm run build` автоматично копіюють потрібні файли
в `frontend/public/blackjack/`. Ця копія ігнорується Git; редагуйте оригінали в `assets/`.
Окреме копіювання: `cd frontend`, потім `npm run assets:sync`.
Після запуску Vite перегляд доступний за `/blackjack/preview.html`.

`manifest.json` містить усі шляхи відносно кореня набору, масті, ранги, номінали,
репліки й рекомендовану початкову гучність ефектів. Приклад у frontend:

```ts
const base = `${import.meta.env.BASE_URL}blackjack/`
const manifest = await fetch(`${base}manifest.json`).then(r => r.json())
const aceOfSpadesUrl = base + manifest.cards.find((c: { id: string }) => c.id === 'AS').path
// Викликайте play() після жесту користувача та обробляйте відхилення Promise.
const deal = new Audio(base + manifest.sfx.find((s: { id: string }) => s.id === 'cards/deal-01').path)
deal.volume = 0.5
```

## Формати

- Карти: SVG 240 × 336, прозорі кути, однаковий розмір і рамка.
  Імена: `{rank}{suit}.svg`; ранги `A,2…10,J,Q,K`, масті `C,D,H,S`.
  Валет/дама/король мають мінімалістичні корони та великі літери.
- Стіл: SVG 1920 × 1080; без написів правил і зон гравців — їх задає UI.
- Фішки: SVG 256 × 256, прозоре тло; номінал записаний на фішці.
- SFX: реальні записи Kenney Casino Audio, CC0; OGG + MP3, mono, 0.098–0.8 с.
  3 варіанти роздачі, flip/collect/shuffle, фішки та короткі UI cues.
  Джерела й розміри: `ATTRIBUTION.md` та `audio/recorded-manifest.json`.
- Голос: MP3, Microsoft `uk-UA-OstapNeural`, темп −5%:
  `twenty` — «У вас двадцять», `bust` — «Перебір», `you-win` — «Ви виграли».
  Ці файли — тестовий синтез, не запис актора.

## Походження та відтворення

Графіку створено спеціально для цього проєкту кодом у `source/generate-classic.mjs`;
сторонні графічні набори, бренди та музика не використовувалися.
Графіка використовує векторні контури мастей і системний шрифт Georgia для рангів.
Окремі шрифтові файли не постачаються; браузер має serif fallback.

`node assets/source/generate-classic.mjs` відтворює графіку та manifest.
`python assets/source/prepare-recorded-audio.py` відтворює оброблені SFX з оригіналів (потрібен `imageio-ffmpeg`).
Старі процедурні WAV видалені; генератор більше їх не створює.
Генератор не змінює MP3. Для повторного синтезу встановіть Python-пакет `edge-tts`
і запустіть `python assets/source/generate-voices.py` (потрібна мережа).
Документація інструмента: https://github.com/rany2/edge-tts
MP3 створено через онлайн-сервіс Microsoft; ліцензія коду edge-tts не є ліцензією
на голос сервісу. Права на комерційне розповсюдження голосу окремо не перевірялися.
Рантайм гри використовує готові локальні файли і не потребує TTS-сервісу.

## Midnight та додаткові звуки

Стіл і колоду можна незалежно вибрати в налаштуваннях або колекції.
Вибір зберігається локально. Перед заміною колода завантажується й декодується.
`manifest.themes` містить обидва повні набори, старі поля збережені для Classic.
Нові ефекти: turn, split, double, push, blackjack; прослуховування — у налаштуваннях звуку.
Для відтворення всіх SFX після базового `prepare-recorded-audio.py` запустіть
`python assets/source/extend-audio.py`, потім `node assets/source/generate-classic.mjs`.
Обидва аудіоскрипти потребують `imageio-ffmpeg`.
