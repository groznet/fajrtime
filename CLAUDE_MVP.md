# FajrRise — Project Specification (MVP-trimmed)

> **Document purpose:** Single source of truth for the FajrRise web application.
> Written to be readable by humans (founders, developers) and by AI coding assistants.
> Sections marked **[DECIDED]** are fixed rules. Sections marked **[DEFAULT]** are working assumptions that can change. Items in **Open Decisions** must be resolved before or during development.

> **This is `CLAUDE_MVP.md`** — a copy of `CLAUDE.md` with everything removed that is not part of the core loop
> (register → top up → join challenge → daily code check-in → settlement).
> Structure and numbering are unchanged. Every removal is marked inline with `<!-- REMOVED: ... -->`
> and summarised in the change log directly below. If something is not in this file, **do not build it**.

---

## MVP trim — change log

| # | What was removed / simplified | Why | Already in code? |
|---|---|---|---|
| 1 | **Public `/prayer-times` page** and display of all five prayers. Only Fajr (and sunrise, as the check-in deadline) is calculated and shown, on the dashboard. | Only Fajr is used by challenge logic. | Yes — page exists, safe to delete later |
| 2 | **`/faq` page.** | `/how-it-works` already explains rules and money flow; merge the FAQ into it. | Placeholder only |
| 3 | **Notifications**: `/notifications` page, database notifications, result emails. Users see eliminations, payouts and balance changes on the dashboard, challenge page and wallet history. | Not needed to run a challenge. | Yes — built, safe to delete later |
| 4 | **Queue.** Nothing needs background workers once notifications/emails are gone; all jobs run from the Laravel Scheduler. | Fewer moving parts on the server. | Queue config is Laravel default; just unused |
| 5 | **Dedicated `/check-in` page.** The check-in field lives on the dashboard only. The two JSON endpoints it needs (state, submit) stay. | "Bookmark page" is a convenience, not core. | Yes — page exists, safe to delete later |
| 6 | **`/my/groups` page.** The dashboard lists the user's groups and challenges. Group creation moves to `/groups/create`. | One less page. | Yes — page exists |
| 7 | **Group catalog filters** (stake, cycle length, members). `/groups` is a plain list of public groups. | Few groups at launch. | Yes — filters exist |
| 8 | **Challenge daily-progress grid and dashboard check-in history.** Challenge page shows participants, status and results only. | Not needed to run a challenge. | Not built |
| 9 | **`settings` DB table + admin-editable settings.** Values live in `config/fajr.php` (changed by deploy). | No admin UI is needed for them. | Yes — table and `Setting` model exist |
| 10 | **`reveal_range_start_min` setting** (always 0: the range starts at Fajr). | Never changed. | Yes — setting exists |
| 11 | **Challenge status `settling`.** Settlement is one atomic, idempotent transaction: `active → completed`. | An intermediate status adds nothing. | Yes — enum value exists |
| 12 | **Payment status `refunded`.** No refunds in MVP. | Not in core flow. | Yes — enum value exists |
| 13 | **Admin panel reduced** from "users, groups, challenges, payments, settings" to two things: process withdrawal requests, and restore a wrongly eliminated participant (with a logged reason). **"Admin actions logged"** reduced to the same two actions. | Smallest admin that keeps money and disputes manageable. | Not built |
| 14 | **Muftiyat timetable for Chechnya** and per-city calculation method choice. One calculation method for all cities. | Open decision #3 is kept, but the extra source is not built. | No |
| 15 | **Aladhan / external prayer-time API.** Local PHP calculation only. | One source, no network dependency. | Local calculation already used |
| 16 | **Yandex Metrika.** | Analytics is not a feature. | Not built |
| 17 | **One challenge at a time per user** (was: several simultaneous challenges, one check-in counting for all). A user may join a new challenge only when they are not in another `recruiting` or `active` one. | Removes multi-challenge edge cases in check-in, elimination and withdrawals. **This is the most opinionated cut — revert it by restoring the original sentence in 4.3 if you want parallel challenges.** | Code supports several; add a join-time check |
| 18 | **Kotyol (котёл)** (undefined concept) and open decision #8. | Not defined, so nothing to build. | No |
| 19 | **`Jobs/` folder, `CommissionService`, `PrayerTimeController`, `Setting` model, `Admin/` CRUD** from the project structure. Commands do the scheduled work; commission math lives in `WalletService`. | Follows from the cuts above. | Partly |
| 20 | **Instagram / VK / YouTube** rows in Contacts. | Marketing channels, not application features. | n/a |

Kept on purpose (small but essential): legal pages (required by law), private groups with invite links (needed for the closed beta with friends), manual withdrawal requests (users must be able to get money out), the ledger, rate limiting, and the Risks section.

---

## 1. Overview

| Field | Value |
|---|---|
| Project name | FajrRise |
| Founders | Kerim & Yasin |
| Developer | Kerim @ GrozNet |
| Type | Web application (community + accountability + payments) |
| Status | Planning |
| UI language | Russian |
| Initial market | Russia & CIS, Russian-speaking Muslims |
| Target audience | Muslims aged ~18–40 (students, working professionals, entrepreneurs) who want to pray Fajr consistently |
| Domain | fajrrise.ru (planned) |

### One-sentence summary

FajrRise helps Muslims wake up for Fajr prayer through group challenges: members put down a deposit, confirm every morning with a code, and those who miss a day lose their deposit to the members who stayed consistent.

### Design principle

**Simple first.** One deposit, one daily check-in, one clear rule. Every feature that does not directly support "wake up for Fajr and confirm it" is postponed.

---

## 2. Core Loop

```
Register → Set city → Top up balance
   → Join or create a group → Challenge starts
   → Every morning: open the site at Fajr → wait for the code to appear at a random moment → enter it within 5 min
   → Miss a day = eliminated, deposit forfeited
   → Challenge ends → survivors receive their deposit + share of forfeited deposits
   → Balance stays on the platform → join the next challenge
```

---

## 3. Glossary

| Term | Meaning |
|---|---|
| **Balance** | User's money on the platform (after commission). Split into *available* and *locked*. |
| **Top-up** | A real payment from the user's card into their balance. |
| **Commission** | Platform fee, 5% of every top-up. |
| **Group** | A community created by a user. Has its own rules (stake amount, cycle length). Runs challenges. |
| **Creator** | The user who created the group. |
| **Challenge (cycle)** | One time-limited run of a group, e.g. 7 days. Has a start and end date. |
| **Participant** | A user taking part in a specific challenge. |
| **Stake** | The deposit amount a participant locks from their balance to join a challenge. Set by the group creator. |
| **Check-in** | Daily confirmation of waking for Fajr by entering a code. |
| **Code** | A short one-time code that appears on the user's dashboard at a random moment in the reveal range and must be entered within a few minutes. |
| **Reveal range** | The 10-minute range around Fajr in which the code appears at a random, unannounced moment. |
| **Elimination** | Removal from the challenge after a missed check-in. The stake is forfeited. |
| **Survivor** | A participant who completed every check-in in the challenge. |
| **Forfeit pool** | Sum of stakes of all eliminated participants in a challenge. |
| **Settlement** | End-of-challenge calculation and payout. |

---

## 4. Business Rules

### 4.1 Money [DECIDED]

1. The user tops up their balance with real money (e.g. 500 ₽).
2. **5% commission is taken from every top-up.** 500 ₽ top-up → 475 ₽ credited to balance, 25 ₽ platform revenue.
3. To join a challenge, the stake amount is **locked** from the available balance.
4. **A missed day forfeits the whole stake.** No partial penalties, no grace days (MVP).
5. At settlement, **the forfeit pool is divided equally among survivors.** Each survivor receives their own stake back plus an equal share of the pool.
6. **If nobody misses**, every participant gets their stake back in full.
7. Winnings are credited to the balance **with no additional commission** (commission is only charged on top-ups).
8. **The balance stays on the platform** to be used in the next challenge. Withdrawal to a card is available only when the user is not in any active challenge (see Open Decisions).
9. All amounts are stored in **kopecks as integers** (never floats).
10. Rounding: the pool is divided in whole kopecks; any leftover kopecks go to the platform.

### 4.2 Worked example

| Step | Value |
|---|---|
| Participants | 30 |
| Top-up per person | 500 ₽ |
| Commission per person (5%) | 25 ₽ → platform total 750 ₽ |
| Stake per person | 475 ₽ |
| Total locked in challenge | 14 250 ₽ |
| Eliminated | 10 → forfeit pool = 4 750 ₽ |
| Survivors | 20 |
| Share per survivor | 4 750 ÷ 20 = 237.50 ₽ |
| **Each survivor receives** | 475 + 237.50 = **712.50 ₽** |

> Note: the stake amount set by the creator is what gets locked from the balance. The creator should set a stake the user can reach after commission (e.g. stake 475 ₽ for a 500 ₽ top-up). The UI must show the user the exact top-up needed: `top_up = ceil(stake / 0.95)`.

### 4.3 Groups [DEFAULT]

- Any registered user can **create** a group or **join** an existing one.
- The creator sets:
  - group name and description
  - stake amount (min 100 ₽, max 10 000 ₽ — config value)
  - cycle length: 7, 10, 14 or 30 days
  - visibility: public (listed on `/groups`) or private (invite link only)
- Members join via the group page or an invite link.
- Minimum participants to start a challenge: **3**. Maximum: **100** (config values).
- A user may be in several groups, but in **only one `recruiting` or `active` challenge at a time**. <!-- REMOVED: "A user may be in several groups and several active challenges at the same time. One daily check-in counts for all of the user's active challenges." Original behaviour can be restored by deleting the join-time check. -->

### 4.4 Challenge lifecycle [DEFAULT]

```
recruiting → active → completed
     └──────────────→ cancelled (not enough participants by start date)
```
<!-- REMOVED: status `settling`. Settlement runs as one atomic, idempotent DB transaction, so `active → completed` is enough. -->

| Status | Meaning |
|---|---|
| `recruiting` | Created by the group creator with a start date. Users can join (stake is locked on join). |
| `active` | Started. No new joins. Daily check-ins run. |
| `completed` | Last day passed, payouts credited. Read-only. |
| `cancelled` | Fewer than minimum participants at start → all stakes unlocked and returned. |

- A user may **leave** a challenge only while it is `recruiting` (stake unlocked). Leaving an `active` challenge = elimination.
- After completion, the creator starts a new challenge; members opt in again.

Participant statuses: `active` → `eliminated` or `survived` (`left` if they leave while `recruiting`).

### 4.5 Daily check-in [DEFAULT]

1. Each user has a **city** → the system calculates their local Fajr time daily.
2. Each morning of an active challenge, the system picks a **random moment** for each user within the **reveal range**: from **Fajr** to **Fajr + 10 min** (config value). Each user gets their own random moment, different every day.
3. At that moment a **6-digit code** appears on the user's dashboard. The user must enter it within **5 minutes** (config value). The whole thing always ends **before sunrise**.
4. The user knows the range ("the code will appear between 04:52 and 05:02") but **not the exact moment**. To be safe, they have to be up and watching the page for up to 10 minutes. This is intentional: it prevents "wake up for one second, enter code, go back to sleep".
5. Because the code is only valid for a short time after it appears, the user cannot simply open the site at the end of the range and enter it late.
6. The dashboard check-in block updates by itself (Alpine.js polls the server every ~15 seconds), so the user doesn't need to refresh. It shows the range, a "waiting for code…" state, then the code and a countdown. <!-- REMOVED: the separate `/check-in` bookmark page. The dashboard is the only place for check-in. -->
7. **The code and its reveal time are never sent to the browser in advance.** The server returns the code only after `revealed_at` has passed, so it cannot be found in page source or JS.
8. Correct code in time → day confirmed. No valid entry → day missed → **elimination** from all challenges active that day.
9. The code is single-use, valid only for that user and that day.
6. **The platform does not send wake-up notifications.** Waking up is the user's responsibility (own alarm etc.). The site shows the next Fajr time and the reveal range clearly on the dashboard.
7. All times are stored in UTC and shown in the user's timezone.

### 4.6 Prayer times [DEFAULT]

- **Only Fajr** (and sunrise, used as the check-in deadline) is calculated and shown, on the dashboard. <!-- REMOVED: displaying all five prayers on the dashboard and on a public page. -->
- Times are calculated locally per user city with **one** calculation method for all cities. <!-- REMOVED: configurable method per city and the Chechen muftiyat timetable. Open decision #3 remains as a question only. -->
- Users choose their city at registration and can change it only while not in an active challenge (to prevent abuse).

---

## 5. MVP Scope

### In MVP

- Registration / login (email + password), city selection
- Balance: top-up via payment gateway, commission, transaction history
- Withdrawal: a **manual request** form; an admin pays it out by hand
- Groups: create, list public groups, join, invite link
- Challenges: create, join, lock stake, lifecycle, settlement
- Daily code issuance and check-in (on the dashboard)
- Fajr time display (dashboard)
- Dashboard: balance, my groups and challenges, today's check-in field, next Fajr time
- Minimal admin: process withdrawal requests; restore a wrongly eliminated participant (with a logged reason)
- Legal pages: Terms, Privacy, Offer (оферта), Consent to personal data processing
- Pages needed to explain the product: How it works (includes FAQ), Contact

### Later (not in MVP)

- Automated withdrawal to card
- Leaderboard and streaks
- Blog / articles
- Premium features, subscriptions
- Other challenge types (all 5 prayers, 30-day challenges)
- PWA / mobile app, web push
- Reminders and notifications (on-site, email, web push, messenger)
- Public prayer times page for all five prayers
- Full admin panel (users, groups, challenges, settings editor)
- Several simultaneous challenges per user
- Analytics (Yandex Metrika)

<!-- REMOVED from "Later": Kotyol (котёл) — concept was never defined. -->

### Removed from original plan

- CRM (Bitrix24 / AmoCRM) — unnecessary for MVP
- Live chat widgets (JivoChat / Tawk.to) — Telegram support is enough
- Stripe — not available for Russia

---

## 6. Pages

| Page | URL | Auth | Purpose |
|---|---|---|---|
| Home | `/` | — | What FajrRise is, how it works in 3 steps, CTA to register |
| How it works | `/how-it-works` | — | Rules, worked example, FAQ-style explanation of money flow |
| Groups | `/groups` | — | Public groups list (plain list, no filters) |
| Group | `/groups/{slug}` | — | Group info, current/next challenge, members count, join button |
| Invite | `/invite/{token}` | — | Group page opened by invite link (for private groups) |
| Register / Login | `/register`, `/login` | — | Standard auth |
| Dashboard | `/dashboard` | ✅ | Balance, **today's check-in field**, my groups and challenges, next Fajr time |
| Create group | `/groups/create` | ✅ | Group settings form |
| Challenge | `/challenges/{id}` | ✅ | Participants, status, results |
| Wallet | `/wallet` | ✅ | Balance, top-up, transaction history, withdrawal request |
| Settings | `/settings` | ✅ | Profile, city |
| Contact | `/contact` | — | Owner details, Telegram, email |
| Terms / Privacy / Offer / Consent | `/terms`, `/privacy`, `/offer`, `/consent` | — | Legal |
| Admin | `/admin/*` | admin | Withdrawal requests; restore a participant |

Endpoints without their own page: `GET /check-in/state` (polled by the dashboard), `POST /check-in` (submit code).

<!-- REMOVED pages: `/prayer-times`, `/faq`, `/check-in` (page), `/my/groups`, `/notifications`. -->

---

## 7. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP 8.3+) |
| Frontend | Blade + TailwindCSS + Alpine.js (light, simple styling) |
| Database | MySQL 8 |
| Scheduler | Laravel Scheduler (cron every minute). No queue workers needed. |
| Payments | YooKassa, CloudPayments or Tochka acquiring (to be chosen) |
| Prayer times | Local PHP calculation (no external API) |
| Hosting | Timeweb VPS, SSL |
| Version control | GitHub |

<!-- REMOVED: Laravel Queue (database/Redis), database + email notifications, Aladhan API option, Yandex Metrika. -->

---

## 8. Project Structure (Laravel)

```bash
fajrrise/
├─ app/
│  ├─ Http/Controllers/
│  │  ├─ HomeController.php
│  │  ├─ DashboardController.php
│  │  ├─ GroupController.php
│  │  ├─ ChallengeController.php
│  │  ├─ CheckInController.php        # state + submit endpoints only
│  │  ├─ WalletController.php
│  │  ├─ PaymentWebhookController.php
│  │  └─ Admin/                       # withdrawals + restore participant only
│  ├─ Models/
│  │  ├─ User.php
│  │  ├─ Group.php
│  │  ├─ GroupMember.php
│  │  ├─ Challenge.php
│  │  ├─ ChallengeParticipant.php
│  │  ├─ CheckIn.php
│  │  ├─ Payment.php
│  │  ├─ Transaction.php
│  │  ├─ Withdrawal.php
│  │  └─ City.php
│  ├─ Services/
│  │  ├─ WalletService.php          # all balance changes go through here (includes commission math)
│  │  ├─ ChallengeService.php       # join, leave, start, cancel
│  │  ├─ SettlementService.php      # end-of-challenge payouts
│  │  ├─ CheckInService.php         # random reveal time + code generation + validation
│  │  ├─ PrayerTimeService.php
│  │  └─ PaymentGatewayService.php
│  └─ Console/Commands/
│     ├─ ScheduleDailyCodes.php
│     ├─ ExpireCheckIns.php
│     ├─ StartDueChallenges.php
│     └─ SettleFinishedChallenges.php
├─ config/fajr.php                    # commission %, stake limits, participant limits, reveal/code timings
├─ database/migrations/
├─ resources/
│  ├─ views/
│  │  ├─ layouts/
│  │  ├─ components/
│  │  ├─ pages/
│  │  ├─ dashboard/
│  │  ├─ groups/
│  │  ├─ challenges/
│  │  ├─ wallet/
│  │  └─ admin/
│  ├─ css/app.css
│  └─ js/app.js
├─ routes/
│  ├─ web.php
│  └─ console.php
└─ README.md
```

<!-- REMOVED from structure: PrayerTimeController, Setting model, CommissionService (merged into WalletService), Jobs/ (ExpireCheckIns, SettleChallenge — the scheduled commands do the work directly). -->

---

## 9. Database Schema

> Money columns are `BIGINT` in **kopecks**. Timestamps are UTC.

### `users`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR(100) | |
| email | VARCHAR(150) UNIQUE | |
| password | VARCHAR(255) | |
| role | ENUM('user','admin') | default `user` |
| city_id | BIGINT FK → cities | |
| timezone | VARCHAR(64) | e.g. `Europe/Moscow` |
| balance_available | BIGINT | kopecks, cached from ledger |
| balance_locked | BIGINT | kopecks, cached from ledger |
| terms_accepted_at | TIMESTAMP NULL | acceptance of terms and offer |
| personal_data_consent_at | TIMESTAMP NULL | separate consent to personal data processing |
| created_at / updated_at | TIMESTAMP | |

### `cities`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR(100) | |
| latitude / longitude | DECIMAL(9,6) | for prayer time calculation |
| timezone | VARCHAR(64) | |

<!-- REMOVED: `calc_method` column (one calculation method for all cities). If the existing migration has it, it can stay unused. -->

### `groups`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| creator_id | BIGINT FK → users | |
| name | VARCHAR(150) | |
| slug | VARCHAR(160) UNIQUE | |
| description | TEXT NULL | |
| stake_amount | BIGINT | kopecks |
| cycle_days | TINYINT | 7 / 10 / 14 / 30 |
| visibility | ENUM('public','private') | |
| invite_token | VARCHAR(64) UNIQUE | for invite links |
| created_at / updated_at | TIMESTAMP | |

### `group_members`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| group_id | BIGINT FK → groups | |
| user_id | BIGINT FK → users | |
| joined_at | TIMESTAMP | |
| | | UNIQUE(group_id, user_id) |

### `challenges`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| group_id | BIGINT FK → groups | |
| stake_amount | BIGINT | copied from group at creation (rules frozen) |
| start_date | DATE | first check-in day |
| end_date | DATE | last check-in day |
| status | ENUM('recruiting','active','completed','cancelled') | |
| forfeit_pool | BIGINT | filled at settlement |
| survivor_share | BIGINT | filled at settlement |
| created_at / updated_at | TIMESTAMP | |

### `challenge_participants`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| challenge_id | BIGINT FK → challenges | |
| user_id | BIGINT FK → users | |
| status | ENUM('active','eliminated','survived','left') | |
| eliminated_on | DATE NULL | day of the missed check-in |
| payout_amount | BIGINT NULL | stake + share, set at settlement |
| created_at / updated_at | TIMESTAMP | |
| | | UNIQUE(challenge_id, user_id) |

### `check_ins`

One row per user per day (covers the user's active challenge).

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | |
| date | DATE | local date of the Fajr |
| fajr_at | TIMESTAMP | calculated Fajr time |
| code | CHAR(6) | generated in advance, returned to the browser only after reveal_at |
| reveal_at | TIMESTAMP | random moment between Fajr and Fajr + 10 min; never exposed before it passes |
| expires_at | TIMESTAMP | reveal_at + 5 min, capped at sunrise |
| confirmed_at | TIMESTAMP NULL | when correct code was entered |
| attempts | TINYINT | wrong entries counter (limit e.g. 5) |
| status | ENUM('pending','confirmed','missed') | |
| | | UNIQUE(user_id, date) |

### `payments` (top-ups)

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | |
| gateway | VARCHAR(30) | e.g. `yookassa` |
| gateway_payment_id | VARCHAR(100) UNIQUE | |
| amount_gross | BIGINT | what the user paid |
| commission | BIGINT | 5% |
| amount_net | BIGINT | credited to balance |
| status | ENUM('pending','succeeded','failed') | |
| created_at / updated_at | TIMESTAMP | |

<!-- REMOVED: payment status `refunded` (no refunds in MVP). -->

### `transactions` (ledger — every balance change)

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | |
| type | ENUM('topup','stake_lock','stake_unlock','forfeit','payout','withdrawal','adjustment') | |
| amount | BIGINT | signed, kopecks |
| affects | ENUM('available','locked') | which balance changes |
| challenge_id | BIGINT NULL FK | |
| payment_id | BIGINT NULL FK | |
| withdrawal_id | BIGINT NULL FK | |
| description | VARCHAR(255) NULL | |
| created_at | TIMESTAMP | immutable, no updates |

### `withdrawals`

Manual in MVP: the user submits a request, an admin pays it by hand and marks it paid.

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | |
| amount | BIGINT | |
| status | ENUM('requested','approved','paid','rejected') | |
| details | JSON | payout details (phone / bank) |
| processed_at | TIMESTAMP NULL | |
| created_at / updated_at | TIMESTAMP | |

### Configuration (replaces the `settings` table)

Defined in `config/fajr.php` and changed by deploy: `commission_percent` (5), `reveal_range_length_min` (10), `code_valid_min` (5), `checkin_max_attempts` (5), `min_participants` (3), `max_participants` (100), `stake_min` (100 ₽), `stake_max` (10 000 ₽).

<!-- REMOVED: `settings` key–value table and admin-editable settings; `reveal_range_start_min` (always 0). -->

Laravel default tables: `jobs`, `failed_jobs`, `sessions`, `password_reset_tokens` (none of them are used beyond sessions and password reset).
<!-- REMOVED: `notifications` table. -->

---

## 10. Scheduled Jobs

All jobs are Artisan commands run by the Laravel Scheduler (cron every minute).

| Command | Frequency | What it does |
|---|---|---|
| `ScheduleDailyCodes` | every few minutes (idempotent) | For each user with an active challenge that day whose Fajr is coming up: calculate Fajr, pick random `reveal_at` in the reveal range, set `expires_at`, generate code, create `check_ins` row (`pending`). |
| `ExpireCheckIns` | every minute | Rows past `expires_at` without confirmation → `missed`; eliminate the user from the active challenge that day. |
| `StartDueChallenges` | every minute | `recruiting` challenges reaching start date → `active` or `cancelled` (below minimum). |
| `SettleFinishedChallenges` | every hour | `active` challenges past end date → run settlement → `completed`. |

<!-- REMOVED: on-site notification + email on elimination; the intermediate `settling` status. -->

---

## 11. Key Logic (pseudocode)

### Top-up (payment webhook succeeded)

```
commission = round(gross * commission_percent / 100)
net = gross - commission
DB transaction:
  payments.status = succeeded
  ledger: +net (topup, available)
  user.balance_available += net
```

### Join challenge

```
require challenge.status == recruiting
require user is not in another recruiting/active challenge
require user.balance_available >= stake
DB transaction:
  ledger: -stake (stake_lock, available), +stake (stake_lock, locked)
  create challenge_participant(status = active)
```

### Settlement

```
eliminated = participants where status = eliminated
survivors  = participants where status = active
pool = count(eliminated) * stake

if survivors == 0: see Open Decisions (all participants failed)
share = floor(pool / count(survivors))
remainder = pool - share * count(survivors)   → platform

for each eliminated: ledger -stake (forfeit, locked)
for each survivor:
  ledger -stake (stake_unlock, locked)
  ledger +stake + share (payout, available)
  participant.status = survived, payout_amount = stake + share
challenge.status = completed
```

All money operations run inside a single DB transaction and are idempotent (safe to re-run).

---

## 12. Security & Integrity

- HTTPS everywhere.
- Rate-limit code entry (max 5 wrong attempts per day).
- Payment webhooks verified by gateway signature; idempotent by `gateway_payment_id`.
- Balances only change through `WalletService` and are always backed by ledger rows.
- Challenge rules (stake, dates) are frozen when the challenge is created.
- City change blocked during active challenges.
- Daily DB backups (Timeweb snapshots + mysqldump to separate storage).
- Admin actions (withdrawal processing, restoring a participant) are logged with the admin and a reason.

### Known limits of check-in verification (accepted for MVP)

- A code proves the user was awake and active, not that they prayed. This is a trust-based platform.
- Account sharing (a friend entering the code) cannot be fully prevented. Later options: device binding, second check-in, group-member verification.

---

## 13. Risks to Verify Before Launch

| Area | Risk | Action |
|---|---|---|
| Legal (RU) | Redistributing forfeited deposits to other users may be classified as a gambling/betting activity. | Consult a lawyer; draft offer (оферта) and terms accordingly. |
| Tax / payments | If deposits go to an ИП account on УСН, the full amount may be treated as income, not just the 5%. | Consult an accountant; consider an agent scheme, nominal account (номинальный счёт) or safe-deal product from the bank/gateway. |
| Payment gateway | Gateway may refuse a platform with pooled user funds. | Confirm the business model with the gateway before integration. |
| Religious | Scholars may view losers-pay-winners as a form of wager (maysir). | Obtain an opinion from a trusted scholar; fallback model: forfeited stakes go to sadaqa. |
| Disputes | Site downtime or wrong prayer time → unfair elimination. | Clear terms; monitoring; admin ability to restore a participant with a logged reason. |

---

## 14. Open Decisions

| # | Question | Current default |
|---|---|---|
| 1 | What happens to the pool if **all** participants are eliminated? | Not defined — options: platform, sadaqa, refund minus penalty |
| 2 | Withdrawal rules: when and how can users withdraw? | Manual request, only when not in an active challenge |
| 3 | Prayer time calculation method | One method for all cities |
| 4 | High-latitude cities (summer Fajr issues in Moscow / St Petersburg) | Not supported in MVP: if Fajr cannot be calculated, that day is skipped (no check-in, no elimination) |
| 5 | Audience: men only, or women too (separate groups)? | Not defined |
| 6 | Can the creator make other rules (e.g. private stake, custom length)? | Only stake, length, visibility |
| 7 | Should survivors auto-join the next cycle? | No — opt in manually |
| 8 | Payment gateway choice | YooKassa / CloudPayments / Tochka |

<!-- REMOVED: former #8 "What is the kotyol (котёл)?" — the concept was never defined; former #9 renumbered to #8. -->

---

## 15. Timeline

| Milestone | Target date |
|---|---|
| Legal & payment model confirmed | TBD |
| Design (key screens: home, dashboard, group) | TBD |
| MVP development | TBD |
| Closed beta with friends (1–2 real groups) | TBD |
| Public launch | TBD |

---

## 16. Contacts & Channels

| Channel | Value |
|---|---|
| Website | fajrrise.ru (planned) |
| Telegram channel (news/marketing) | @fajrrise (planned) |
| Email | info@fajrrise.ru (planned) |
| Location | Grozny, Chechnya (online platform) |

<!-- REMOVED: Instagram / VK / YouTube row (marketing channels, not application features). -->

### Developer

- **Developer:** Kerim @ GrozNet
- **Email:** info@groznet.com
- **Git:** github.com/groznet
- **Site:** groznet.com
