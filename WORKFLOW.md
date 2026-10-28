# Daily Workflow: local → GitHub → server (SSH)

Repo: `github.com/groznet/fajrtime` · Server path: `/home/c/ci21392/fajrtime` (`public_html -> public`)

**Rules:** never edit code on the server. `.env`, `vendor/`, `node_modules/`, `public/build` and `storage/` are not in git. Migrations are the only way the DB changes.

---

## 0. One-time: turn the server folder into a git checkout

The server copy was uploaded by hand (no `.git`), so first back up, then connect it to GitHub:

```bash
cd ~/fajrtime
cp .env ~/env.backup                      # keep secrets
mysqldump -u USER -p DBNAME > ~/db_$(date +%F).sql
rm -rf __MACOSX public_html_backup        # upload junk
git init -b main
git remote add origin https://github.com/groznet/fajrtime.git
git fetch origin
git reset --hard origin/main              # code now matches GitHub; ignored files (.env, vendor, storage) stay
```

Use a GitHub deploy key or a personal access token for `git fetch` (private repo).
Check: `git status` is clean and `git log -1` matches GitHub. Then `composer install --no-dev -o`, `php artisan migrate --force`, and the cache step from section 3.

---

## 1. Local (every change)

```bash
git pull                                  # start from latest
# ...code...
php artisan test                          # must pass
npm run build                             # only if CSS/JS/Blade classes changed
git add -A && git status                  # review: no .env, no Archive.zip
git commit -m "Short, clear message"
git push
```

**DB change?** Never edit the DB by hand. Create a migration and commit it:

```bash
php artisan make:migration add_x_to_y_table
php artisan migrate                       # test locally
php artisan migrate:rollback && php artisan migrate   # make sure it reverses
```

Rules: never edit a migration that was already pushed (add a new one); make migrations backward-compatible (add columns nullable/with default); new reference data goes in a seeder that is safe to re-run.

---

## 2. Build assets (shared hosting usually has no Node)

`public/build` is git-ignored. Either run `npm ci && npm run build` on the server if Node 20.19+/22 exists, or build locally and upload:

```bash
rsync -avz --delete public/build/ ci21392@vh436:~/fajrtime/public/build/
```

Skip when no frontend files changed.

---

## 3. Deploy (server, over SSH)

```bash
ssh ci21392@vh436
cd ~/fajrtime

mysqldump -u USER -p DBNAME > ~/db_$(date +%F_%H%M).sql   # backup BEFORE any deploy with migrations
php artisan down                          # maintenance mode (optional for tiny changes)

git pull --ff-only origin main            # fails loudly if server has local edits
composer install --no-dev --optimize-autoloader      # always safe; needed if composer.lock changed
php artisan migrate --force               # only changes pending migrations
php artisan db:seed --class=CitySeeder --force       # only if cities/seed data changed

php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart                 # if a queue worker is running

php artisan up
```

Pre-check anytime: `git fetch && git log HEAD..origin/main --oneline` shows what a pull would bring; `php artisan migrate:status` shows pending migrations.

---

## 4. After every deploy (1 minute)

- Open the site, log in, check dashboard and check-in page.
- `tail -n 50 storage/logs/laravel.log` — no new errors.
- If `.env` changed (new key): edit it on the server by hand, then `php artisan config:cache`.

## 5. Rollback

```bash
php artisan down
git log --oneline -5
git reset --hard <previous-commit>        # code
php artisan migrate:rollback --step=1 --force   # only if the bad deploy ran a migration
# or restore the dump: mysql -u USER -p DBNAME < ~/db_YYYY-MM-DD_HHMM.sql
composer install --no-dev -o && php artisan optimize:clear && php artisan up
```

## Checklist (nothing skipped)

| Changed | Must run |
|---|---|
| PHP code | `git pull`, `optimize:clear`, recache |
| `composer.json/lock` | `composer install --no-dev -o` |
| Migration | DB backup, `migrate --force` |
| Seed data | `db:seed --class=… --force` |
| CSS/JS/Blade classes | `npm run build` + upload `public/build` |
| `.env` / `config/*` | edit `.env` on server, `config:cache` |
| Queued jobs/listeners | `queue:restart` |
| Scheduler/commands | check cron: `* * * * * cd ~/fajrtime && php artisan schedule:run >> /dev/null 2>&1` |
