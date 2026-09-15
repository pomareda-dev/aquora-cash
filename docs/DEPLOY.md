# Deployment — environments and production choice

Caja Diaria runs in three places: local WSL for development, shared hosting for
manual testing, and a production host that is **not** the shared box. Production
must support queue workers, a real scheduler, backups, and the SaaS roadmap
(AI jobs, billing webhooks, Redis/Valkey later). Shared hosting cannot.

**Production recommendation:** Laravel Cloud Starter at launch, Growth when
traffic or preview environments need it. A cheap VPS wins on cash; Cloud wins
on time and on the features this SaaS already planned. See [Production](#production).

Prices below are from Laravel Cloud docs (pricing data dated 2026-08-17) and
Hetzner list prices after the 2026-06-15 adjustment. Re-check official pages
before committing.

---

## Topology

| Role | Where | URL | Purpose |
|------|--------|-----|---------|
| Development | WSL Ubuntu local | http://localhost:8000 | Code, tests, seeders, or a copy of staging data |
| Test / staging | Shared hosting (cPanel) | https://caja-diaria.pomareda.dev | Manual QA on a public URL; **not** SaaS production |
| Production | Laravel Cloud (recommended) or VPS | TBD | Public registration, billing, AI jobs, real users |

Sandbox (v1.2) is already in the app (`is_sandbox` + `LiveScope`). It adds
database rows, not extra servers. Staging copies are useful to reproduce
sandbox bugs locally.

SQLite is the framework default in `config/database.php`. **None of these
environments use it.** Dev is MySQL 8.0, staging is MariaDB 11.4, production
must be MySQL (Laravel Cloud does not support SQLite).

---

## Development

Local environment on WSL.

| Item | Value |
|------|-------|
| OS | Ubuntu 24.04.4 LTS (WSL) |
| PHP | 8.3.33 |
| Node | v22.16.0 |
| Database | MySQL 8.0.46-0ubuntu0.24.04.4 |
| App URL | http://localhost:8000 |
| Run | `composer dev` (`php artisan dev`: server, queue, logs, Vite) |

### Daily loop

1. `composer dev`
2. Work against MySQL (same engine family as staging/production).
3. `php artisan test --compact` for the slice you touched; `composer test` before
   a larger change.

### Test data

Two valid paths:

**Seeders** — fresh, deterministic, safe to wipe:

```bash
php artisan migrate:fresh --seed
```

**Copy staging → local** — to reproduce a bug that only appears with real
shape/volume. Staging is MariaDB 11.4; local is MySQL 8.0. Prefer a
**data-only** dump after migrations are already applied locally. A full
MariaDB dump can include syntax MySQL 8.0 rejects.

```bash
# On staging (cPanel / SSH / phpMyAdmin export): data only, no CREATE SCHEMA
mysqldump -u {user} -p --no-create-info --skip-triggers {database} > staging-data.sql

# Local: schema from migrations, then data
php artisan migrate:fresh
mysql -u {local_user} -p {local_database} < staging-data.sql
```

Never copy staging `.env` secrets into git. Never point local `APP_URL` at
the staging domain.

---

## Test / staging (shared hosting)

| Item | Value |
|------|-------|
| Hosting | Shared (cPanel) |
| Web server | Apache 2.4.68 |
| PHP | 8.3 (`ea-php83`) |
| Database | MariaDB 11.4.13-MariaDB-cll-lve-log |
| App URL | https://caja-diaria.pomareda.dev |
| Document root | `{account}/public` |

Use this box to click through real HTTPS, Fortify, and MySQL/MariaDB
behavior. **Do not** put public SaaS registration, AI jobs, or billing here:
there is no Supervisor daemon, no Redis, and cron is a panel workaround.

### Staging `.env`

- [ ] `APP_ENV=staging` (or `production` if the host cannot distinguish; then
      keep `APP_DEBUG=false`)
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://caja-diaria.pomareda.dev`
- [ ] Fresh `APP_KEY` (`php artisan key:generate`) — not the local key
- [ ] `DB_CONNECTION=mysql` + host/port/database/user/password from cPanel
- [ ] `SESSION_DRIVER=database`
- [ ] `CACHE_STORE=database`
- [ ] `QUEUE_CONNECTION=database` (jobs will sit until something runs
      `queue:work`; staging has no daemon)
- [ ] `FILESYSTEM_DISK=local`
- [ ] `APP_LOCALE=es_PE`
- [ ] `php artisan migrate --force` (no `--seed` unless you intend to wipe)
- [ ] `php artisan storage:link`
- [ ] Frontend built on deploy: `npm run build`
- [ ] HTTPS (Let's Encrypt via cPanel)

After any `.env` change: `php artisan config:cache`. Stale config cache is
the usual staging “it works local” bug.

### Apache

Document root must be `public/`. Laravel ships `public/.htaccess`. If the
host denies `AllowOverride`, copy the rewrite rules into the vhost.

```
DocumentRoot /home/{user}/{subdomain}/public
```

### Cron (scheduler)

`routes/console.php` does not schedule `app:generate-projections` yet. When
it does, cPanel cron:

```
* * * * * cd /home/{user}/{subdomain} && php artisan schedule:run >> /dev/null 2>&1
```

### Permissions

| Path | Required |
|------|----------|
| `storage/` | Writable by the web user (775 or 755 on suPHP) |
| `bootstrap/cache/` | Writable |
| `public/avatars/` | Writable (profile photos) |
| `.env` | Readable by the web user, **not** web-accessible |

### Optimization after deploy

```bash
php artisan optimize
```

---

## Production

Shared hosting is out. The SaaS analysis (`docs/analisis-agente-ia-y-saas.md`)
already marked a queue worker as **blocking** before public registration. The
choice is VPS (you run Linux) vs Laravel Cloud (Laravel runs the platform).

### Verdict

| Horizon | Choice |
|---------|--------|
| Launch (alpha / first paying users) | **Laravel Cloud Starter** |
| Medium term (AI jobs, billing, more than one replica) | Stay on Cloud; move to **Growth** ($20/mo + usage) when you need autoscaling, preview environments, or more than 3 queue workers |
| Long term | Stay on Cloud until the bill is clearly larger than a well-run VPS **plus** your ops time. That is hundreds of users, not launch |

A minimum VPS is cheaper in dollars. It is more expensive in the work this
product still needs: Supervisor, backups, SSL, deploys, queue daemons, and
later Redis, object storage, and extra workers for AI.

**Do not** start on a VPS “to save money and migrate later.” The migration is
real work. The cash gap at launch is ~US$15–30/month.

### What the app actually needs

| Need | Now | SaaS roadmap |
|------|-----|----------------|
| PHP 8.3 + MySQL | Yes | Yes |
| HTTPS + custom domain | Yes | Yes |
| Scheduler | Projections command exists; not scheduled yet | Daily projections, monthly credit grants |
| Queue **daemon** | Unused (`QUEUE_CONNECTION=database`, no jobs) | **Required** for `RunAiAnalysis` (2–10 s LLM calls, retries, credit ledger) |
| Backups | Manual | Automatic (blocking before public registration) |
| Redis / Valkey | No | Cache, sessions, Horizon if you outgrow database queues |
| Object storage | Local disk | Avatars / exports that survive deploys |
| Email | Not configured | Resend/Postmark (verification, receipts) |
| Observability | Nothing | Sentry + Pulse / Nightwatch |

Sandbox v1.2 does not change this list. It makes MySQL backups more
important (more rows, `LiveScope` queries).

v2 commercial (`docs/plan-de-trabajo-v2.md`) adds customers and sales on the
same app — still one Laravel process, more write load, same hosting shape.

### Option A — Laravel Cloud

Managed compute, MySQL, scheduler, queues, SSL, Git deploys. Official Laravel
13 path.

| Plan | Base | What you get that this app cares about |
|------|------|----------------------------------------|
| **Starter** | $5/mo + usage ($5 usage credit, 30-day trial) | 1 replica, Flex compute, scheduler, 1 managed queue (max 3 workers), custom domains, SSL, hibernation, spend limits |
| **Growth** | $20/mo + usage | Autoscaling, preview environments, worker clusters, Pro compute, WAF, up to 10 replicas / 10 queues / 100 workers |
| Business | $200/mo + usage | Teams, advanced WAF, scheduled autoscaling — not needed for years |

Usage is extra. The $5 number is the **plan fee**, not the invoice.

**Honest launch bill (always-on production, US/EU region, 2026-08 pricing):**

| Line | Approx. |
|------|---------|
| Starter plan | $5 (credit covers $5 of usage) |
| App compute Flex 1 GiB, always on | ~$12–14/mo cap (region-dependent; Flex 512 MiB ~$5–7) |
| Laravel MySQL Flex + storage + 7-day backups | billed separately; treat as several USD to low tens |
| Managed queue Flex (scale to zero) | ~$0 until AI jobs run; ~$0.13/mo polling baseline per queue; $1 / million operations |
| **Likely total at launch** | **~$25–40/mo**, not $5 |

Use **spend limits** on day one.

**Fit to the roadmap**

- AI jobs: managed queues scale to zero and wake in <1 s. Flex jobs must
  finish in **90 s** (enough for an LLM analysis). Starter allows 1 queue and
  3 workers — enough until volume is real.
- App is already Laravel **13.19.0**, the minimum for current managed queues.
- Scheduler runs without cPanel cron.
- Inertia SSR is a Cloud toggle later (`npm run build:ssr`); not required now.
- Growth unlocks preview apps per PR when the team (or you) wants that.
- SQLite is unsupported on Cloud — irrelevant; this app already uses MySQL.

**Caveats**

- Flex **scale-to-zero** is for staging, not for a finance app users open
  daily. Keep production compute (and MySQL) awake.
- No Laravel Cloud region in Latin America (US, EU, APAC, Canada, UAE). A
  Hetzner VPS is also EU, not Peru. Neither option gives Peruvian data
  residency; declare cross-border processing in the privacy policy (Ley 29733).
- Usage can spike. Spend limits and the Growth jump are the controls.

Docs: [cloud.laravel.com](https://cloud.laravel.com),
[Plans and pricing](https://laravel.com/cloud/docs/pricing),
[Queues](https://laravel.com/cloud/docs/queues).

### Option B — VPS (minimum, then scale)

You rent a Linux box and become the platform.

**Minimum that actually fits PHP-FPM + MySQL + one `queue:work` on the same
machine:** 2 vCPU / 4 GB RAM. 1 GB RAM is a trap (MySQL + PHP + worker).

Reference (Hetzner, after 2026-06-15): **CX23** — 2 vCPU, 4 GB, 40 GB SSD,
€5.49/mo (~US$6), 20 TB traffic. Next step CX33 (4 vCPU / 8 GB, €8.49).

You still install and operate: Nginx (or Caddy), PHP 8.3-FPM, MySQL 8.x,
Certbot, Supervisor (`queue:work`), cron (`schedule:run`), firewall, unattended
upgrades, `spatie/laravel-backup` or snapshots, log rotation, deploy script.

**When VPS is the right call**

- You want to learn ops and accept 3 a.m. disk-full pages.
- The product stays personal / staging-like and never runs AI jobs.
- Cloud usage later exceeds ~US$80–100/mo with still-modest traffic — then
  Forge on a Hetzner box (or a split app/DB/worker) can be cheaper.

**When VPS is the wrong call for this SaaS**

- Public registration + AI in the next year (the actual plan).
- Solo founder: one missed backup or a stuck queue costs more than a year of
  Cloud-vs-VPS delta.
- Scaling “later” means a load balancer, shared session store, and object
  storage — work Cloud already productized.

Forge ($12/mo + VPS) is a middle path: still a server, less Nginx pain. It
does not remove backups, MySQL tuning, or capacity planning. Cloud still
maps better to managed queues and preview environments.

### Side-by-side

| | Laravel Cloud | VPS (Hetzner CX23 class) |
|--|---------------|---------------------------|
| Cash at launch | ~$25–40/mo | ~$6–15/mo + email |
| Queue workers | Managed, scale to zero, dashboard | Supervisor you maintain |
| Scheduler | Platform | Cron you maintain |
| Backups | MySQL snapshots (configure retention) | Snapshots + app-level backup you set up |
| Deploy | Git push | Script / Forge / rsync |
| Scale-up | Slider / Growth plan | Resize box, then split services |
| AI jobs (roadmap) | Native | Same box until it hurts |
| Your role | Product | Product + SRE |
| Data region | No LATAM (same as Hetzner) | No LATAM unless you pick a PE host |

### Production checklist (either host)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Unique `APP_KEY`, backed up offline
- [ ] `APP_URL` = the real HTTPS origin
- [ ] MySQL (not SQLite); migrations with `--force`, **no** seeders
- [ ] `SESSION_DRIVER` / `CACHE_STORE` = `database` at first; Redis/Valkey when
      it pays off
- [ ] Queue connection with a **running worker** (Cloud managed queue or
      Supervisor). Database driver without a worker is a silent black hole.
- [ ] `php artisan optimize` on every deploy
- [ ] Automated DB backups, restore tested once
- [ ] Transactional email (Resend/Postmark)
- [ ] Sentry (or equivalent) before public registration
- [ ] ToS + privacy (Ley 29733; AI provider clause when that ships)

Nginx reference if you do run a VPS (document root = `public/` only):

```nginx
server {
    listen 80;
    server_name caja-diaria.example.com;
    root /srv/caja-diaria/public;
    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Then TLS via Certbot. Never serve the project root.

---

## Security (all remote environments)

- `APP_DEBUG=false` — debug mode leaks `.env` in stack traces.
- Keep `APP_KEY` secret. Rotating it invalidates sessions and encrypted data.
- Fortify handles auth (throttled login, 2FA available). Enable
  `MustVerifyEmail` before public registration.
- Staging and production **must not** share `APP_KEY` or DB credentials with
  local.

---

## Related docs

- `docs/analisis-agente-ia-y-saas.md` — SaaS gaps (queues, VPS vs Cloud, billing)
- `docs/plan-de-trabajo-v1.2-sandbox.md` — sandbox (implemented); extra DB rows
- `docs/plan-de-trabajo-v2.md` — commercial accounts; same process, more writes
