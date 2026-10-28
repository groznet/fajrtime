# FajrRise — Deployment checklist

Target: one Timeweb VPS (Russian data centre, see 152-FZ art. 18 part 5), Nginx + PHP-FPM, MySQL, SSL.
Tick items off in order. Items marked **⚠ silent failure** break the product without any error on the site.

---

## 0. Decisions needed before going live

- [ ] **Payment gateway** chosen (YooKassa is the only one implemented). Confirm with them that the pooled-deposit model is allowed (spec §13).
- [ ] **"Everyone eliminated" rule** (open decision #1). Today the platform keeps the pool (`TODO` in `SettlementService`).
- [ ] **Legal review** of `/terms`, `/privacy`, `/offer`, `/consent` by a lawyer; accountant confirms the tax scheme (ИП/УСН vs agent scheme / nominal account).
- [ ] **Roskomnadzor notification** about personal data processing (152-FZ art. 22) filed *before* collecting data. Needs a named person responsible for processing.
- [ ] **Scholar's opinion** on the losers-pay-winners model (spec §13), or switch to the sadaqa fallback.
- [ ] **Queue vs. sync** (see §3): run a queue worker, or set `QUEUE_CONNECTION=sync`.

## 1. Server

- [ ] PHP **8.3+** with extensions: `ctype curl dom fileinfo mbstring openssl pdo_mysql tokenizer xml`. *Development and the test suite run on PHP 8.5 / MySQL 9.7 — run `php artisan test` on the exact PHP and MySQL versions you install on the server (MySQL 8.4 LTS is the spec's target).*
- [ ] Nginx with document root `…/public`; `.env`, `.git` and dotfiles not served; HTTP → HTTPS redirect; valid certificate (YooKassa requires HTTPS for webhooks).
- [ ] MySQL listening on localhost only; **dedicated DB user with a password** (the dev `.env` uses `root` with no password — never in production); database `utf8mb4`.
- [ ] Firewall allows only 22, 80, 443; SSH keys only; fail2ban.
- [ ] **⚠ Server clock synced** (`chrony` / `systemd-timesyncd`). Check-in windows are a few minutes long; a drifting clock eliminates people unfairly.
- [ ] Composer 2 and Node 22 LTS available (Vite 8 needs Node 20.19+ or 22.12+) (or build assets elsewhere and upload `public/build`, which is git-ignored).

## 2. Application

```bash
git clone <repo> /var/www/fajrrise && cd /var/www/fajrrise
composer install --no-dev --optimize-autoloader
npm ci && npm run build              # public/build is not in git
cp .env.example .env && php artisan key:generate
# edit .env (section 3), then:
php artisan migrate --force
php artisan db:seed --class=CitySeeder --force    # safe to repeat; only seeds cities
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

- [ ] First admin: `php artisan tinker` → `User::where('email', '<your email>')->update(['role' => 'admin']);` (register normally first).
- [ ] Do **not** upload `legal_docs/` or `AGENTS.md` to the server.
- [ ] Re-run the three `*:cache` commands after every `.env` or config change.

## 3. `.env` for production

| Key | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / **`false`** |
| `APP_URL` | `https://fajrrise.ru` (used in emails and payment return links) |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` (the default single file grows forever) |
| `DB_*` | the dedicated user, not `root` |
| `MAIL_*` | a real SMTP provider; **`MAIL_FROM_ADDRESS` is still `hello@example.com`** — set it to `info@fajrrise.ru`. Needed for password reset and elimination emails. |
| `QUEUE_CONNECTION` | `database` + worker (below) **or** `sync` |
| `YOOKASSA_SHOP_ID`, `YOOKASSA_SECRET_KEY` | live shop credentials (test shop for staging) |
| `YOOKASSA_SEND_RECEIPT` | `false` until the 54-ФЗ setup is agreed with the accountant |
| `OPERATOR_LEGAL_NAME`, `OPERATOR_INN`, `OPERATOR_OGRN`, `OPERATOR_ADDRESS`, `OPERATOR_EMAIL` | real owner details — published on `/contact` and in the footer (149-FZ art. 10 part 2). The default address is only the city. |

## 4. Background work — the product does not run without these

- [ ] **⚠ silent failure — Scheduler cron.** Without it nobody gets a code, nobody is eliminated, no challenge starts or settles:
  ```cron
  * * * * * cd /var/www/fajrrise && php artisan schedule:run >> /dev/null 2>&1
  ```
  Verify with `php artisan schedule:list` — expect `challenges:start-due` (every minute), `checkins:schedule` (every 5 min), `checkins:expire` (every minute), `challenges:settle` (hourly).
- [ ] **⚠ silent failure — Queue worker** (if `QUEUE_CONNECTION=database`). `ParticipantEliminated` is queued, so without a worker the elimination notice and email are never sent. Run under Supervisor: `php artisan queue:work --tries=3 --max-time=3600`, restart on deploy with `php artisan queue:restart`. With `sync` no worker is needed, but a mail outage would then interrupt the `checkins:expire` run.
- [ ] **Heartbeat monitoring** for the scheduler (e.g. healthchecks.io pinged by a scheduled task) and an uptime check on `/up`. A stopped scheduler is lenient to users, not loud — you must find out yourself.

## 5. Payments (YooKassa)

- [ ] Shop approved by YooKassa for this business model; live keys in `.env`.
- [ ] HTTP notifications enabled: URL `https://fajrrise.ru/webhooks/yookassa`, events `payment.succeeded` and `payment.canceled`. The handler never trusts the request body — it re-fetches the payment from the API, so no signature check is needed. Unknown payments get 200.
- [ ] Test shop rehearsal: top up → balance credited **once** (also if the notification is delivered twice) → commission 5% correct → ledger rows present.
- [ ] Return page works when the webhook is delayed (the return URL also syncs the payment).
- [ ] Manual withdrawal routine agreed: who checks `/admin/withdrawals`, how fast, how the payout is made.

## 6. Backups and recovery

- [ ] Nightly `mysqldump` to storage **outside** the VPS, plus Timeweb snapshots; keep at least 14 days.
- [ ] **Restore tested once** on a scratch database. A backup that was never restored is not a backup — this database holds user money.
- [ ] `.env` stored safely (password manager), separately from the repo.

## 7. Smoke test on the live server (before inviting anyone)

- [ ] Register with the consent checkboxes; city list shows 7 cities; `/contact`, `/terms`, `/privacy`, `/offer`, `/consent` show the real owner details.
- [ ] Top up through YooKassa → balance correct.
- [ ] Create a group, open a challenge for tomorrow, join with 3 accounts; leave and rejoin (stake unlocks and locks).
- [ ] After midnight Moscow: `php artisan challenges:start-due` (or wait a minute) → challenge `active`; `php artisan checkins:schedule` → check-in rows exist before Fajr.
- [ ] At Fajr: the code appears only inside the range; wrong code rejected; right code confirms; skipping one account leads to elimination after expiry, with email.
- [ ] After the last day: the challenge settles; payouts match the worked example on `/how-it-works`.
- [ ] Admin: reject a withdrawal (money returns), mark one paid, restore an eliminated participant; the audit log shows each action.
- [ ] `storage/logs` contains no errors; `APP_DEBUG=false` (a deliberate 404 shows no stack trace).

## 8. Launch order

1. Staging run on the same stack with the YooKassa **test** shop.
2. Closed beta with 1–2 real friend groups (spec §15) and a small stake; watch the first full cycle end to end.
3. Public launch only after the legal items in §0 are closed.

## Known limits to remember in operation

- If the scheduler or queue is down, the failure mode is *lenient* (no eliminations), not harsh — but payouts and starts are delayed. Check the heartbeat first when users report "no code".
- Cities where Fajr cannot be calculated are not offered; if one is added, that day is skipped for its users (open decision #4).
- Far East users: Fajr falls before Moscow midnight, when challenges activate, so they effectively skip day 1 (lenient).
- Admin can restore an eliminated participant only while the challenge is `active`; after settlement the stake is already distributed.
