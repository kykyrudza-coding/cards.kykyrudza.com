# poker.kykyrudza.com

Мультиплеєрна вебплатформа для гри в карткові ігри з друзями. У системі немає реальних грошей —
використовуються лише віртуальні фішки, що створюються для конкретного лобі/матчу і не мають
жодної реальної вартості.

Першою грою буде **Blackjack**, далі — Texas Hold'em, Three Card Poker, Дурак, Сундучок та інші.

Реалізовано: базова структура backend/frontend, health-check, **реєстрація/авторизація (Sanctum
Bearer token)**, **система лобі з realtime-оновленнями через Laravel Reverb** і **повністю робочий
Blackjack** (Match + Blackjack Engine): роздача карт, черга ходів, Hit/Stand/Double/Split,
дилер, виплати, наступний раунд — усе authoritative на backend.

## Структура проєкту

```
poker.kykyrudza.com/
├── backend/          Laravel API, auth, lobby, realtime, game engine
├── frontend/          Vue 3 + Vite + TypeScript
├── assets/            Вихідні графічні/аудіо матеріали (не потрапляють у білд напряму)
├── docker-compose.yml Postgres + Redis + backend для розробки
└── .gitignore
```

### Backend (`backend/`)

Laravel застосунок, відповідає за REST API, авторизацію, лобі, matchmaking, WebSocket/realtime,
правила карткових ігор, фішки, статистику, історію матчів. Frontend ніколи не визначає результат
гри самостійно — сервер є єдиним джерелом істини.

Стек: **Laravel 13, PostgreSQL, Redis, Laravel Reverb (WebSocket), Sanctum (API токени)**.

Game Engine — `backend/app/Game/`:

```
app/Game/
├── Contracts/GameEngine.php   загальний контракт (start/handleAction/allowedActions/isFinished)
├── Core/                      (заготовка під спільну логіку майбутніх ігор)
├── Blackjack/                 повна реалізація
│   ├── Card.php                immutable value object (rank/suit)
│   ├── Deck.php                52 карти, shuffle, draw, Deck::fromCards() для детерм. тестів
│   ├── BlackjackHand.php       карти/бет/статус/результат, ace-aware score()
│   ├── BlackjackPlayer.php     chips/status/hands
│   ├── BlackjackState.php      увесь стан партії — серіалізується 1:1 у matches.state (jsonb)
│   ├── BlackjackRules.php      canHit/canStand/canDouble/canSplit
│   ├── BlackjackEngine.php     deal/applyAction/nextRound/publicState — без HTTP/DB/Reverb
│   └── BlackjackActionException.php
└── Poker/                     (заготовка)
```

`BlackjackEngine` — чиста функція `(state, action) → new state`, нічого не знає про Eloquent,
контролери чи broadcasting; це дозволяє unit-тестувати правила напряму, підставляючи заздалегідь
підготовлену колоду (`Deck::fromCards([...])`) замість реального shuffle.

**Lobby vs Match:** Lobby — це збір гравців і налаштування (незмінний з попереднього етапу).
Match — окрема сутність, що створюється при `Start Lobby` і містить реальний ігровий стан
(`app/Services/Match/MatchService.php`): створення матчу, блокування рядка (`lockForUpdate`),
виклик `BlackjackEngine`, збереження стану, broadcast. Контролери (`LobbyController`,
`MatchController`) лишаються тонкими.

### Auth API

Sanctum, **Bearer token** (без cookie-сесій, без Blade).

| Method | Endpoint             | Auth | Опис                          |
|--------|-----------------------|------|--------------------------------|
| POST   | `/api/auth/register`  | —    | `username`, `email`, `password`, `password_confirmation` → `{ user, token }` |
| POST   | `/api/auth/login`     | —    | `email`, `password` → `{ user, token }` |
| POST   | `/api/auth/logout`    | ✓    | видаляє поточний токен |
| GET    | `/api/auth/me`        | ✓    | поточний користувач |

### Lobby API

Усі маршрути захищені `auth:sanctum`.

| Method | Endpoint                      | Опис                                    |
|--------|--------------------------------|------------------------------------------|
| POST   | `/api/lobbies`                | створити лобі (host додається автоматично) |
| GET    | `/api/lobbies/{code}`         | деталі лобі (без password) |
| POST   | `/api/lobbies/{code}/join`    | приєднатися (`password`, якщо приватне) |
| POST   | `/api/lobbies/{code}/leave`   | вийти (host мігрує до найстарішого гравця, або лобі закривається) |
| POST   | `/api/lobbies/{code}/ready`   | `{ "ready": true/false }` |
| POST   | `/api/lobbies/{code}/start`   | тільки host, усі мають бути ready → створює Match і повертає його (`201`) |

`POST .../start` тепер: перевіряє lobby rules → створює `Match` + `MatchPlayer` (chips = lobby
`starting_chips`) → роздає карти через `BlackjackEngine` → переводить lobby у `started` →
broadcast `LobbyUpdated` (з `match_id`) і `MatchUpdated` → повертає `MatchResource`.

`default_bet` (нове поле лобі): автоматична ставка кожного раунду; `2 ≤ default_bet ≤
starting_chips`, парне число (щоб виплата blackjack 3:2 завжди була цілою).

Бізнес-логіка — у `app/Services/Lobby/LobbyService.php`, контролер лише делегує. Формат помилок:
`{"message": "..."}`, для валідації — `{"message": "...", "errors": {...}}`.

### Match API

Усі маршрути захищені `auth:sanctum`; переглядати/діяти може лише учасник матчу
(`match_players`), дії — лише поточний гравець, `next-round`/`finish` — лише host лобі.

| Method | Endpoint                              | Опис                              |
|--------|-----------------------------------------|-------------------------------------|
| GET    | `/api/matches/{id}`                    | поточний стан (персоналізований) |
| POST   | `/api/matches/{id}/actions/hit`        | взяти карту |
| POST   | `/api/matches/{id}/actions/stand`      | зупинитись |
| POST   | `/api/matches/{id}/actions/double`     | подвоїти ставку, +1 карта, hand завершується |
| POST   | `/api/matches/{id}/actions/split`      | розділити пару на 2 руки (макс. 2, без re-split) |
| POST   | `/api/matches/{id}/bet`                | підтвердити `{amount, expected_round}`; роздача після підтвердження всіх гравців |
| POST   | `/api/matches/{id}/next-round`         | застарілий endpoint: повертає 422, не обходить підтвердження ставок |
| POST   | `/api/matches/{id}/finish`             | тільки host, завершує матч |

**Приховування даних:** закрита карта дилера повертається як `{"hidden": true}` без rank/suit,
поки не настане `dealer_turn`/`round_finished`. Карти гравців завжди відкриті (на відміну від
майбутнього Poker). `allowed_actions` рахується сервером окремо для кожного viewer — якщо зараз
не його хід, масив порожній; frontend ніколи сам не вирішує, чи доступний Split/Double.

**Хід гри:** дилер грає і раунд settle'иться **синхронно, в тому ж HTTP-запиті**, що й остання дія
останнього гравця (не окремий endpoint і не queue job) — відповідає вимозі "dealer logic
відпрацьовує миттєво". Split: дві карти однакової вартості (J+K, 10+Q також дозволені), макс. 2 руки на гравця, спліт тузів автоматично stand (без hit),
double дозволений після split (крім тузів). Dealer: hit поки `<17`, stand на soft 17 і вище.
Payouts: win 1:1, blackjack 3:2, push повертає ставку.

**Concurrency:** кожна дія — `DB::transaction()` + `GameMatch::lockForUpdate()`, рядок матчу
завжди перечитується за id всередині транзакції (route-model-binding інстанс не переюзається),
тож два майже одночасні action на один Match не можуть побитись.

### Realtime (Laravel Reverb)

Дві події, обидві `ShouldBroadcastNow` (без черги/`queue:work`):

- `App\Events\LobbyUpdated` → приватний канал `lobby.{code}`. Payload:
  `{ "reason": "player_joined" | "player_left" | "ready_changed" | "host_changed" | "started",
  "lobby": {...} }` (з `match_id`, коли лобі вже стартувало).
- `App\Events\MatchUpdated` → приватний канал `match.{id}`. Payload навмисно тонкий —
  `{ "match_id": ..., "version": ... }` — щоб не розсилати повний internal state всім підряд;
  кожен клієнт після цього сам робить `GET /api/matches/{id}` і отримує свій персоналізований
  вигляд (з коректно прихованою картою дилера).

Авторизація каналів (`routes/channels.php`) перевіряє членство в лобі/матчі. Broadcasting auth
(`/broadcasting/auth`) працює через Sanctum Bearer token, не через cookie-сесію
(`Broadcast::routes(['middleware' => ['auth:sanctum']])` в `AppServiceProvider`).

### Frontend (`frontend/`)

Vue 3 + Vite + TypeScript, з `vue-router` та `pinia`.

```
frontend/src/
├── components/   views/   router/   composables/   types/
├── services/     api.ts (Bearer-інтерцептор), auth.ts, lobby.ts, match.ts
├── stores/       auth.ts, lobby.ts, match.ts (Pinia)
├── realtime/     echo.ts — Laravel Echo + Reverb (pusher-js транспорт)
├── game/
│   ├── core/       спільна game-логіка на клієнті
│   ├── blackjack/  (заготовка)
│   ├── poker/      (заготовка)
│   └── shared/
└── audio/        заготовка під Howler.js
```

Views: `HomeView`, `LoginView`, `RegisterView`, `DashboardView` (Create/Join Lobby, з
`default_bet`), `LobbyView` (`/lobby/:code` — players list, ready/start/leave, copy code/link),
`MatchView` (`/match/:id` — dealer/hands/score/bet, Hit/Stand/Double/Split, результати раунду,
підтвердження ставок усіма гравцями / Finish для host). Маршрути `/dashboard`, `/lobby/:code`, `/match/:id` захищені router
guard'ом (`meta.requiresAuth`), неавторизованих редіректить на `/login`.

Game-логіка на фронтенді відповідає лише за відображення стану, який присилає сервер — score,
дозволені дії, результат раунду завжди рахує backend; frontend лише форматує (напр. `+100`/`-100`
з `bet`+`result`), ніколи не вирішує сам.

### Assets (`assets/`)

Вихідні матеріали (3D-моделі, текстури, аудіо, іконки). Не всі файли звідси потрапляють у браузер:
оптимізовані версії, які реально використовує frontend, копіюються у `frontend/public/assets/`.

```
assets/
├── models/{tables,chips,cards,props}
├── textures/{cards,tables,chips,environment}
├── audio/{sfx,music,voices/uk}
├── icons/
└── source/   вихідні файли (.blend, .psd, .wav тощо)
```

`assets/source/` потенційно варто тримати під **Git LFS** — важкі бінарники (`.blend`, `.psd`,
`.wav`, `.fbx`) зараз виключені з git через `.gitignore`, поки не буде окремого рішення щодо LFS.

## Вимоги

- PHP 8.3+, Composer
- Node.js 20+, npm
- PostgreSQL 16 (або через Docker)
- Redis 7 (або через Docker)
- Docker + Docker Compose (опційно, для Postgres/Redis/backend)

## Backend: запуск

```bash
cd backend
composer install
cp .env.example .env      # якщо .env ще немає
php artisan key:generate
php artisan migrate
php artisan serve
```

Backend буде доступний на `http://localhost:8000`.

Перевірка health-check:

```bash
curl http://localhost:8000/api/health
# {"status":"ok"}
```

Тести (SQLite in-memory, не залежать від Postgres) — 74 тести, включно з Blackjack Engine
(deck/score/hit/double/split/split aces/dealer/settlement/next round, на детермінованих колодах
через `Deck::fromCards()`) і Match Feature-тестами (авторизація, realtime broadcast, конкурентні
дії):

```bash
php artisan test
```

### Laravel Reverb (WebSocket)

```bash
cd backend
php artisan reverb:start
```

Конфігурація в `.env` (`REVERB_*`, `BROADCAST_CONNECTION=reverb`) вже підготовлена. Порт за
замовчуванням — `8080`, має збігатися з `VITE_WS_PORT` у frontend `.env`.

## Frontend: запуск

```bash
cd frontend
npm install
cp .env.example .env      # якщо .env ще немає
npm run dev
```

Frontend буде доступний на `http://localhost:5173` і звертається до backend через `VITE_API_URL`.

## Docker

У корені проєкту є `docker-compose.yml` із сервісами `backend`, `postgres`, `redis` — зручно для
розробки без локально встановлених Postgres/Redis. Frontend під час розробки запускається окремо
через Vite (`npm run dev`), у compose він не входить.

```bash
docker compose up -d
```

Це піднімає:

- `backend` — Laravel на `http://localhost:8000`
- `postgres` — PostgreSQL на порту `5432`
- `redis` — Redis на порту `6379`

Порти 80/443 та production nginx-конфігурація навмисно не налаштовуються на цьому етапі.

## Environment

### `backend/.env`

Ключові змінні:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=poker
DB_USERNAME=poker
DB_PASSWORD=poker

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_HOST=localhost
REVERB_PORT=8080

CORS_ALLOWED_ORIGINS=http://localhost:5173
```

При запуску через `docker compose`, `DB_HOST`/`REDIS_HOST` автоматично переозначаються на
`postgres`/`redis` (імена сервісів у мережі compose).

### `frontend/.env`

```env
VITE_API_URL=http://localhost:8000
VITE_WS_HOST=localhost
VITE_WS_PORT=8080
VITE_REVERB_APP_KEY=          # має збігатися з REVERB_APP_KEY у backend/.env
```

`VITE_WS_HOST`/`VITE_WS_PORT` мають відповідати тому, де реально працює `reverb:start`.

URL backend ніколи не хардкодиться у Vue-компонентах — лише через ці змінні. Токен авторизації
зберігається у `localStorage` (просто, без refresh-token логіки — для pet-проєкту достатньо).

## Ручна перевірка (acceptance test)

1. Відкрити `http://localhost:5173` у двох вкладках/браузерах (A і B), backend + Reverb запущені.
2. **A**: Register → Dashboard → Create Lobby → отримати код (напр. `ABC123`) → Ready.
3. **B**: Register → Dashboard → Join Lobby за кодом `ABC123` → Ready.
4. **A**, без перезавантаження сторінки, повинен побачити появу B у списку гравців та його
   Ready-статус (приходить через WebSocket, канал `lobby.ABC123`).
5. **A** (host) тисне Start Game → обидва браузери без reload переходять на `/match/:id`
   (host — одразу з REST-відповіді, B — реактивно через `LobbyUpdated`), бачать роздані карти,
   закриту карту дилера.
6. По черзі (seat 0 → seat 1 → dealer) Hit/Stand/Double/Split — інший браузер бачить кожну зміну
   без reload (`MatchUpdated` → refetch).
7. Після ходу останнього гравця дилер відпрацьовує миттєво, обидва бачать `ROUND FINISHED` з
   результатами (win/lose/push/blackjack) і оновленими chips.
8. Host тисне `NEXT ROUND` → round++, нові карти, нова ставка — обидва бачать без reload.
9. Refresh сторінки матчу в одному з браузерів: сесія відновлюється з токена в `localStorage`,
   match підвантажується через REST, WebSocket-підписка відновлюється, гру можна продовжити.

Перевірено вручну через реальний REST + WebSocket flow (без Docker/Postgres — Postgres на цій
машині недоступний, тому це прогонялося на тимчасовій SQLite + `reverb:start`, як і радить ТЗ):
реєстрація, create/join/ready/start (справжній випадковий shuffle), turn-based hit/stand,
403 при спробі ходити не в свою чергу, dealer auto-play, settlement, і живий WebSocket-клієнт,
що отримав `MatchUpdated` одразу після чужого action.

## Критерій готовності

- [x] Окремі `backend` і `frontend`
- [x] `assets/` зі структурою під майбутні матеріали
- [x] PostgreSQL і Redis запускаються (нативно або через Docker)
- [x] Laravel запускається (`php artisan serve`)
- [x] Vue запускається (`npm run dev`)
- [x] `GET /api/health` → `{"status":"ok"}`
- [x] Register / Login / Logout / Me через Sanctum Bearer token
- [x] Create / Join / Leave / Ready / Start Lobby з серверною валідацією всіх дій
- [x] `Start Lobby` створює Match і роздає карти через Blackjack Engine
- [x] Hit / Stand / Double / Split з повною серверною валідацією (черга ходу, стан руки, chips)
- [x] Dealer logic (hit < 17, stand на soft 17+), settlement (win/lose/push/blackjack 3:2)
- [x] Підтвердження індивідуальних ставок перед наступним раундом / Finish Match (тільки host)
- [x] Realtime-оновлення лобі й матчу через Laravel Reverb (private channels, без reload)
- [x] Concurrency-захист (`DB::transaction` + `lockForUpdate`, завжди re-fetch за id)
- [x] `php artisan test` — 74/74 passed
- [x] `npm run build` — без помилок
- [x] Game Engine (`backend/app/Game/Blackjack/`) без HTTP/DB/Reverb залежностей, тестований на
      детермінованих колодах

Далі — Poker/інші ігри за тим самим контрактом `GameEngine`; ручний вибір ставки, insurance,
surrender, re-split, achievements, статистика, matchmaking, spectators — свідомо поки не
реалізовані (див. §80 ТЗ).


### Оновлений перехід між раундами

Після inline результатів та збору карт кожен активний гравець підтверджує власну ставку.
Мінімум 100; до 1000 включно крок 100, вище — 500; максимум обмежений балансом.
`POST /api/matches/{id}/bet` приймає `amount` та `expected_round`. Сервер зберігає
`confirmed_bets` у JSON стану матчу. Поки інші гравці не підтвердили ставки, кошти
не списуються та раунд не змінюється. Останнє підтвердження в транзакції з блокуванням
запису запускає рівно одну роздачу з індивідуальними сумами. Підтверджена ставка
незмінна; повторення тієї самої суми до роздачі ідемпотентне. Застарілий номер раунду
дає 409. Гравці зі статусом out або балансом менше 100 не блокують роздачу.
Першу роздачу, як і раніше, запускає Start game зі ставкою лобі.

У верхній рамці показано раунд, з'єднання, баланс, фактичну ставку та етап гри.
Кнопки на ПК розташовані ліворуч: H — Hit, S — Stand, D — Double, P — Split.
Шорткати можна вимкнути в налаштуваннях. Вони не працюють під час анімацій,
не свого ходу, у діалогах та полях вводу.

`game/cardImages.ts` попередньо завантажує й декодує всі 52 лицьові сторони та сорочку.
`CardAnimation` додатково чекає `HTMLImageElement.decode()` конкретних карт перед
роздачею та flip. Повернення до вже зіграного раунду відновлює стан без повторної роздачі.
