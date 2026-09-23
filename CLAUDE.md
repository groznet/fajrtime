# FajrRise — Project Specification

> **Document purpose:** Single source of truth for the FajrRise web application.
> Written to be readable by humans (founders, developers) and by AI coding assistants.
> Sections marked **[DECIDED]** are fixed rules. Sections marked **[DEFAULT]** are working assumptions that can change. Items in **Open Decisions** must be resolved before or during development.

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
  - stake amount (min 100 ₽, max 10 000 ₽ — configurable)
  - cycle length: 7, 10, 14 or 30 days
  - visibility: public (listed on `/groups`) or private (invite link only)
- Members join via the group page or an invite link.
- Minimum participants to start a challenge: **3**. Maximum: **100** (configurable).
- A user may be in several groups and several active challenges at the same time. **One daily check-in counts for all of the user's active challenges.**

### 4.4 Challenge lifecycle [DEFAULT]

```
recruiting → active → settling → completed
     └──────────────→ cancelled (not enough participants by start date)
```

| Status | Meaning |
|---|---|
| `recruiting` | Created by the group creator with a start date. Users can join (stake is locked on join). |
| `active` | Started. No new joins. Daily check-ins run. |
| `settling` | Last day passed; system calculates results. |
| `completed` | Payouts credited. Read-only. |
| `cancelled` | Fewer than minimum participants at start → all stakes unlocked and returned. |

- A user may **leave** a challenge only while it is `recruiting` (stake unlocked). Leaving an `active` challenge = elimination.
- After completion, the creator starts a new challenge; members opt in again.

Participant statuses: `active` → `eliminated` or `survived`.

### 4.5 Daily check-in [DEFAULT]

1. Each user has a **city** → the system calculates their local Fajr time daily.
2. Each morning of an active challenge, the system picks a **random moment** for each user within the **reveal range**: from **Fajr** to **Fajr + 10 min** (configurable). Each user gets their own random moment, different every day.
3. At that moment a **6-digit code** appears on the user's dashboard / check-in page. The user must enter it within **5 minutes** (configurable). The whole thing always ends **before sunrise**.
4. The user knows the range ("the code will appear between 04:52 and 05:02") but **not the exact moment**. To be safe, they have to be up and watching the page for up to 10 minutes. This is intentional: it prevents "wake up for one second, enter code, go back to sleep".
5. Because the code is only valid for a short time after it appears, the user cannot simply open the site at the end of the range and enter it late.
6. The check-in page updates by itself (Alpine.js polls the server every ~15 seconds), so the user doesn't need to refresh. It shows the range, a "waiting for code…" state, then the code and a countdown.
7. **The code and its reveal time are never sent to the browser in advance.** The server returns the code only after `revealed_at` has passed, so it cannot be found in page source or JS.
8. Correct code in time → day confirmed. No valid entry → day missed → **elimination** from all challenges active that day.
9. The code is single-use, valid only for that user and that day.
6. **The platform does not send wake-up notifications.** Waking up is the user's responsibility (own alarm etc.). The site shows the next Fajr time and the reveal range clearly on the dashboard.
7. All times are stored in UTC and shown in the user's timezone.

### 4.6 Prayer times [DEFAULT]

- All five prayer times are displayed on the site (dashboard and a public page).
- Only **Fajr** is used for challenge logic.
- Times are calculated per user city with a configurable calculation method; the Chechen muftiyat timetable may be used for Chechnya (see Open Decisions).
- Users choose their city at registration and can change it only while not in an active challenge (to prevent abuse).

---

## 5. MVP Scope

### In MVP

- Registration / login (email + password), city selection
- Balance: top-up via payment gateway, commission, transaction history
- Groups: create, list public groups, join, invite link
- Challenges: create, join, lock stake, lifecycle, settlement
- Daily code issuance and check-in
- Prayer times display
- Dashboard: balance, active challenges, today's status, check-in history
- Basic admin panel (users, groups, challenges, payments, settings)
- Legal pages: Terms, Privacy, Offer (оферта)

### Later (not in MVP)

- Withdrawal to card (automated) — MVP may handle manually by request
- Leaderboard and streaks
- Blog / articles
- Premium features, subscriptions
- Other challenge types (all 5 prayers, 30-day challenges)
- PWA / mobile app, web push
- Optional reminders (web push / messenger) if users ask for them
- **Kotyol (котёл)** — concept to be defined

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
| Prayer times | `/prayer-times` | — | Today's prayer times by city |
| Groups | `/groups` | — | Public groups list with filters (stake, cycle length, members) |
| Group | `/groups/{slug}` | — | Group info, current/next challenge, members count, join button |
| Register / Login | `/register`, `/login` | — | Standard auth |
| Dashboard | `/dashboard` | ✅ | Balance, **today's check-in field**, active challenges, next Fajr time |
| Check-in | `/check-in` | ✅ | Minimal page with today's code and the code field (fast to open from a bookmark) |
| My groups | `/my/groups` | ✅ | Groups the user belongs to or created |
| Create group | `/my/groups/create` | ✅ | Group settings form |
| Challenge | `/challenges/{id}` | ✅ | Participants, daily progress, results |
| Wallet | `/wallet` | ✅ | Balance, top-up, transaction history, withdrawal request |
| Notifications | `/notifications` | ✅ | Site alerts history (eliminations, results, payouts) |
| Settings | `/settings` | ✅ | Profile, city |
| FAQ | `/faq` | — | Common questions |
| Contact | `/contact` | — | Telegram, email |
| Terms / Privacy / Offer | `/terms`, `/privacy`, `/offer` | — | Legal |
| Admin | `/admin/*` | admin | Management panel |

---

## 7. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP 8.3+) |
| Frontend | Blade + TailwindCSS + Alpine.js (light, simple styling) |
| Database | MySQL 8 |
| Queue / Scheduler | Laravel Queue (database or Redis driver) + Laravel Scheduler (cron every minute) |
| Notifications | Laravel database notifications (on-site) + email for results |
| Payments | YooKassa, CloudPayments or Tochka acquiring (to be chosen) |
| Prayer times | PHP calculation library or external API (e.g. Aladhan) with local caching |
| Hosting | Timeweb VPS, SSL |
| Version control | GitHub |
| Analytics | Yandex Metrika |

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
│  │  ├─ CheckInController.php
│  │  ├─ WalletController.php
│  │  ├─ PaymentWebhookController.php
│  │  ├─ PrayerTimeController.php
│  │  └─ Admin/
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
│  │  ├─ City.php
│  │  └─ Setting.php
│  ├─ Services/
│  │  ├─ WalletService.php          # all balance changes go through here
│  │  ├─ CommissionService.php
│  │  ├─ ChallengeService.php       # join, leave, start, cancel
│  │  ├─ SettlementService.php      # end-of-challenge payouts
│  │  ├─ CheckInService.php         # random reveal time + code generation + validation
│  │  ├─ PrayerTimeService.php
│  │  └─ PaymentGatewayService.php
│  ├─ Jobs/
│  │  ├─ ExpireCheckIns.php
│  │  └─ SettleChallenge.php
│  └─ Console/Commands/
│     ├─ ScheduleDailyCodes.php
│     ├─ StartDueChallenges.php
│     └─ SettleFinishedChallenges.php
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
| created_at / updated_at | TIMESTAMP | |

### `cities`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR(100) | |
| latitude / longitude | DECIMAL(9,6) | for prayer time calculation |
| timezone | VARCHAR(64) | |
| calc_method | VARCHAR(50) | prayer time calculation method |

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
| status | ENUM('recruiting','active','settling','completed','cancelled') | |
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

One row per user per day (covers all of the user's active challenges).

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
| status | ENUM('pending','succeeded','failed','refunded') | |
| created_at / updated_at | TIMESTAMP | |

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

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK → users | |
| amount | BIGINT | |
| status | ENUM('requested','approved','paid','rejected') | |
| details | JSON | payout details (handled per gateway rules) |
| processed_at | TIMESTAMP NULL | |
| created_at / updated_at | TIMESTAMP | |

### `settings`

Key–value table for: `commission_percent` (5), `reveal_range_start_min` (0, minutes after Fajr), `reveal_range_length_min` (10), `code_valid_min` (5), `min_participants` (3), `max_participants` (100), `stake_min`, `stake_max`.

Laravel default tables: `notifications`, `jobs`, `failed_jobs`, `sessions`, `password_reset_tokens`.

---

## 10. Scheduled Jobs

| Job | Frequency | What it does |
|---|---|---|
| `ScheduleDailyCodes` | daily, before the earliest Fajr | For each user with an active challenge that day: calculate Fajr, pick random `reveal_at` in the reveal range, set `expires_at`, generate code, create `check_ins` row (`pending`). |
| `ExpireCheckIns` | every minute | Rows past `expires_at` without confirmation → `missed`; eliminate user from all active challenges that day; on-site notification + email. |
| `StartDueChallenges` | every minute / hourly | `recruiting` challenges reaching start date → `active` or `cancelled` (below minimum). |
| `SettleFinishedChallenges` | every hour | Challenges past end date → `settling` → run settlement → `completed`. |

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
- Admin actions logged.

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
| 3 | Prayer time source and calculation method | Per city; muftiyat timetable for Chechnya |
| 4 | High-latitude cities (summer Fajr issues in Moscow / St Petersburg) | Not supported in MVP |
| 5 | Audience: men only, or women too (separate groups)? | Not defined |
| 6 | Can the creator make other rules (e.g. private stake, custom length)? | Only stake, length, visibility |
| 7 | Should survivors auto-join the next cycle? | No — opt in manually |
| 8 | What is the "kotyol" (котёл)? | To be defined |
| 9 | Payment gateway choice | YooKassa / CloudPayments / Tochka |

---

## 15. Timeline

| Milestone | Target date |
|---|---|
| Legal & payment model confirmed | TBD |
| Design (key screens: home, dashboard, check-in, group) | TBD |
| MVP development | TBD |
| Closed beta with friends (1–2 real groups) | TBD |
| Public launch | TBD |

---

## 16. Contacts & Channels

| Channel | Value |
|---|---|
| Website | fajrrise.ru (planned) |
| Telegram channel (news/marketing) | @fajrrise (planned) |
| Instagram / VK / YouTube | @fajrrise (planned) |
| Email | info@fajrrise.ru (planned) |
| Location | Grozny, Chechnya (online platform) |

### Developer

- **Developer:** Kerim @ GrozNet
- **Email:** info@groznet.com
- **Git:** github.com/groznet
- **Site:** groznet.com
