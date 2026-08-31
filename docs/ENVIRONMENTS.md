# Bebbo CMS — Environments & Environment Synchronization

> **Audience:** developers, maintainers, operations, release managers.
> **Scope:** the four environments (Local / Dev / Stage / Prod), how Drupal settings resolve per environment, multisite domains per environment, the deploy pipeline + Acquia cloud hooks, and cross-site content sync.
> **Verified 2026-07-03.** Every value below was read from the live files named in each section — `.ddev/config.yaml`, `docroot/sites/`, `hooks/`, `.github/workflows/pipelines.yml` — the per-site language/group tables (§11–§12) were DB-verified 2026-06-29. Where a setting lives on Acquia's servers (outside this repo) it is marked as such and **not** guessed.

---

## 1. Environment Matrix

| Environment | Where | PHP | Deploy trigger | Acquia target | Config source |
|-------------|-------|-----|----------------|---------------|---------------|
| **Local** | DDEV (`bebbo.app`) | 8.4 | n/a (manual `ddev start`) | — | `config/sync` + active split |
| **Dev** | Acquia Cloud | **8.4** | push to `develop` | `@parentbuddy2.dev` | `config/sync` + active split |
| **Stage** | Acquia Cloud | **8.4**² | push to `stage` | `@parentbuddy2.test` | `config/sync` + active split |
| **Prod** | Acquia Cloud | **8.4**¹ | **manual only** | `@parentbuddy2.prod`¹ | `config/sync` + active split |

> Acquia Dev runs MySQL 5.7.44 — below Drupal 11's MySQL 8.0 minimum. The `drupal/mysql57` contrib module backports the database driver. Its settings include is loaded in `post.settings.php` after the Acquia include so `$databases` is already populated.

² `pipelines.yml` pins `php-version: 8.4` for all three jobs (`ci-checks`, `deploy-dev`, `deploy-stage`). That is the GitHub runner's PHP, used to build and push the artifact. The PHP version each Acquia environment runs is set in Acquia Cloud and is not defined in this repository — confirm it there before relying on it.

¹ There is **no** `deploy-prod` job and **no** `@parentbuddy2.prod` reference in `pipelines.yml`. Prod is deployed manually; the cloud hook explicitly **skips** DB/config steps on prod (see [§6](#6-acquia-cloud-hooks)).

---

## 2. Local Development (DDEV)

Source: `.ddev/config.yaml`.

| Key | Value |
|-----|-------|
| `name` | `bebbo.app` |
| `type` | `drupal11` |
| `docroot` | `docroot` |
| `php_version` | `8.4` |
| `webserver_type` | `apache-fpm` |
| `database` | MariaDB **10.11** (local only; Acquia Cloud runs MySQL — see the callout above) |
| `xdebug_enabled` | `false` |
| `composer_version` | `2` |
| `corepack_enable` | `false` |
| `use_dns_when_possible` | `true` |

> **`type: drupal11`** matches the installed core (**11.3.x**, `composer.lock`). DDEV v1.25.1 supports the `drupal11` type. (Previously `drupal10`; corrected.)

### 2.1 JWT key opt-in (commented by default)

`.ddev/config.yaml` documents an opt-in `web_environment` entry for `BEBBO_JWT_PRIVATE_KEY` (used by the `bebbo_api_security` V2 JWT layer). It is **commented out** by default — enable locally if testing the V2 secure API.

### 2.2 Local databases

Each site gets its own database locally. `.ddev/mysql/init-databases.sql` creates:

`bangladesh_db` · `turkey_db` · `ecuador_db` · `pakistan_db` · `somoa_db` · `zimbabwe_db`

The **default (bebbo)** site uses DDEV's base `db` database (no override). Each country `settings.php` sets its DB name only when `IS_DDEV_PROJECT == 'true'`. Note `somoa_db` serves the **Pacific Islands (Bebbo Pacific)** site.

---

## 3. Multisite Domains per Environment

Source: `docroot/sites/sites.php` (`$sites` map: domain → site directory). The **default** site (`bebbo.app`) has **no** entry — it falls through to `sites/default/`.

### 3.0 Complete per-site URL map (all environments)

Verified against `docroot/sites/sites.php` lines 58–93 on **2026-06-29**. PROD = vanity domains; STAGE/DEV/DDEV follow the fixed per-site slug.

| Site (dir / config folder) | PROD | STAGE | DEV | DDEV (local) |
|----------------------------|------|-------|-----|--------------|
| Default (`default` / `bebbo`) | `bebbo.app` | — (main env URL)* | — (main env URL)* | `bebbo.app.ddev.site` |
| Bangladesh (`bangladesh` / `bangla`) | `babuni.app`, `bangla.bebbo.app` | `bangla-stage.bebbo.app` | `bangla-dev.bebbo.app` | `bangla.bebbo.app.ddev.site` |
| Turkey (`turkey` / `turkey`) | `merhababebek.app`, `tr.bebbo.app` | `tr-stage.bebbo.app` | `tr-dev.bebbo.app` | `tr.bebbo.app.ddev.site` |
| Ecuador (`ecuador` / `ecuador`) | `wawamor.ec`, `ec.bebbo.app` | `ec-stage.bebbo.app` | `ec-dev.bebbo.app` | `ec.bebbo.app.ddev.site` |
| PK (`pakistan` / `pakistan`) | `pk.bebbo.app` | `pk-stage.bebbo.app` | `pk-dev.bebbo.app` | `pk.bebbo.app.ddev.site` |
| Pacific Islands (`somoa` / `somoa`) | `ws.bebbo.app`, `bebbopacific.app` | `ws-stage.bebbo.app` | `ws-dev.bebbo.app` | `ws.bebbo.app.ddev.site` |
| Zimbabwe (`zimbabwe` / `zimbabwe`) | `umntwana.app`, `zw.bebbo.app`, `rerai.umntwana.app` | `zw-stage.bebbo.app` | `zw-dev.bebbo.app` | `zw.bebbo.app.ddev.site` |

> *The **default (bebbo)** site has **no** dedicated stage/dev subdomain entry in `sites.php` — it resolves via the bare Acquia environment URL / `bebbo.app`.

### 3.1 Production domains (verified, exact)

| Site dir | Production domain(s) |
|----------|----------------------|
| `bangladesh` | `babuni.app`, `bangla.bebbo.app` |
| `turkey` | `merhababebek.app`, `tr.bebbo.app` |
| `ecuador` | `wawamor.ec`, `ec.bebbo.app` |
| `pakistan` | `pk.bebbo.app` |
| `somoa` (Pacific Islands / Bebbo Pacific) | `ws.bebbo.app`, `bebbopacific.app` |
| `zimbabwe` | `umntwana.app`, `rerai.umntwana.app`, `zw.bebbo.app` |

> The **`somoa`** directory is the **Pacific Islands (Bebbo Pacific)** site (serving Fiji + Samoa) — both `ws.bebbo.app` and `bebbopacific.app` route to it. `somoa` is a historical directory name, not a separate Samoa site. There is **no** `pacific_islands` directory entry anywhere in `sites.php`.

### 3.2 Stage / Dev / Local patterns

`sites.php` also maps environment-prefixed hosts, all to the same six country directories using a fixed slug per site (`bangla`, `tr`, `ec`, `pk`, `ws`, `zw`):

| Environment | Host pattern | Example |
|-------------|--------------|---------|
| Stage | `{slug}-stage.bebbo.app` | `tr-stage.bebbo.app` → `turkey` |
| Dev | `{slug}-dev.bebbo.app` | `pk-dev.bebbo.app` → `pakistan` |
| Local (DDEV) | `{slug}.bebbo.app.ddev.site` | `ec.bebbo.app.ddev.site` → `ecuador` |

---

## 4. Settings Load Order

The real chain is **not** flat — a country `settings.php` is a thin wrapper that delegates to `default/settings.php`, which is the actual orchestrator.

### 4.1 Country site (e.g. `docroot/sites/turkey/settings.php`)

```php
$acquia_settings_file_name = 'prod_pbturkey-settings.inc';   // per-site Acquia include name
require_once $app_root . '/sites/default/settings.php';       // runs the common stack (below)
if (DDEV) { $databases['default']['default']['database'] = 'turkey_db'; }
include $app_root . '/' . $site_path . '/site.splits.php';    // activate this site's split
```

### 4.2 `docroot/sites/default/settings.php` (the orchestrator)

```php
require_once common_settings/common.settings.php;   // shared base
if (IS_DDEV_PROJECT) require_once settings.ddev.php; // local-only
require_once common_settings/post.settings.php;      // "Keep post.settings.php LAST" — Acquia detection
include site.splits.php;                              // activate split
```

> **Idempotent double-include:** for a country site, `site.splits.php` runs both inside `default/settings.php` and again at the end of the country file. It only sets a config flag `TRUE`, so this is harmless.

### 4.3 What each shared file actually sets (verified)

- **`common.settings.php`** — is essentially stock Drupal `default.settings.php`. It does **not** set production `trusted_host_patterns`, real `hash_salt`, or `config_sync_directory` (those are commented stock docs). It *does* set: `update_free_access = FALSE`, `file_scan_ignore_directories`, `entity_update_batch_size = 50`, `entity_update_backup = TRUE`, `state_cache = TRUE`, container services yaml.
- **`docroot/sites/default/services.yml`** — sets `session.cookie_samesite: Lax` (security hardening).
- **`post.settings.php`** — the real environment logic:
  - `config_sync_directory = '../config/sync'` (always).
  - `hash_salt = hash('sha256', $app_root . '/' . $site_path)` (always).
  - tmgmt translator API keys are present but **commented out**.

> **Trusted hosts:** in-repo, `trusted_host_patterns` is set only in **DDEV** (`['.*']`). Production host validation is handled by the Acquia per-site include (`/var/www/site-php/{group}/{file}.inc`) which lives **on Acquia's servers, not in this repo** — so it is not documented here.

---

## 5. Acquia Environment Detection & Config

Source: `docroot/sites/common_settings/post.settings.php`.

- `ini_set('memory_limit', '-1')` runs **unconditionally** at the top of `post.settings.php` (first line of the file), **before** the Acquia block opens — it is not gated by env detection.
- Detection reads env vars: `AH_SITE_GROUP` and `AH_SITE_ENVIRONMENT`. The Acquia block runs only `if ($ah_group && $ah_env)`.
- **No per-environment branching** on `prod`/`test`/`dev`/`ode` inside this file — it does the same for every Acquia env. (The per-site `*.inc` name is hardcoded in each site's `settings.php`, e.g. `prod_pbturkey-settings.inc`; if a site leaves `$acquia_settings_file_name` unset, it falls back to `AH_SITE_GROUP . '-settings.inc'`.)
- Sets within the Acquia block:
  - Acquia include: `require_once "/var/www/site-php/{group}/{settings_file}"` if readable.
  - MySQL 5.7 backport: `require modules/contrib/mysql57/settings.inc` if it exists (after the Acquia include populates `$databases`).
  - DB: `acquia_hosting_settings_autoconnect = FALSE`, `READ-COMMITTED` transaction isolation, `acquia_hosting_db_choose_active()`.
  - Memcache: `require_once common_settings/cloud-memcache-d8+.php` if readable.
  - `file_private_path = /mnt/files/{group}.{env}/{site_path}/files-private`.
  - `file_temp_path = /mnt/tmp/{group}.{env}`.
  - Optional API keys: `/mnt/gfs/{group}.{env}/nobackup/bebbo_app_apikeys.php` if readable.

---

## 6. Configuration per Environment

| Layer | Path | Role |
|-------|------|------|
| Shared base | `config/sync/` | All 7 sites |
| Per-site override | `config/{folder}/` | Activated by the site's split |
| Split activation | `docroot/sites/{site}/site.splits.php` | `$config['config_split.config_split.{name}_site']['status'] = TRUE;` |

All 7 `site.splits.php` follow the identical one-line pattern, each enabling its own split:

| Site dir | Split entity |
|----------|--------------|
| `default` | `bebbo_site` ⚠️ (named `bebbo_site`, **not** `default_site`) |
| `bangladesh` | `bangladesh_site` |
| `turkey` | `turkey_site` |
| `ecuador` | `ecuador_site` |
| `pakistan` | `pakistan_site` |
| `somoa` | `somoa_site` |
| `zimbabwe` | `zimbabwe_site` |

> The split **entity** name follows the *directory* name (`bangladesh_site`, `somoa_site`), even though the config *folder* may differ (`config/bangla/`, `config/somoa/`).

### 6.1 `config/envs/` is an empty placeholder

`config/envs/` contains **only** `README.md` — no `dev/`, `test/`, or `prod/` subdirectories and no `*.yml`. The README is a legacy placeholder describing environment-based config_split directories that were never populated here. **There is no committed per-environment config override.** Environment differences come from Acquia env detection (§5) and the deploy pipeline, not from `config/envs/`.

---

## 7. Deployment Pipeline (CI/CD)

Source: `.github/workflows/pipelines.yml`. Triggers (`on:`): push to `develop` / `stage`; PRs to `feature/**`, `bug/**`, `hotfix/**`, `develop`, `stage`; plus `workflow_dispatch`.

| Job | Gate (`if:`) | PHP | Action |
|-----|--------------|-----|--------|
| `ci-checks` | every push + PR | 8.4 | `composer validate --no-check-all` → `composer install` → `phpcs` → `drupal-check -d docroot/modules/custom` → `phplint` |
| `deploy-dev` | `push` && `refs/heads/develop` | 8.4 | `acli push:artifact @parentbuddy2.dev --no-interaction -vvv` |
| `deploy-stage` | `push` && `refs/heads/stage` | 8.4 | `acli push:artifact @parentbuddy2.test --no-interaction` |

- **Branch → env:** `develop` → Dev; `stage` → Stage/Test. `main` is **not** a trigger anywhere.
- Deploy jobs `needs: ci-checks` (CI must pass first).
- Minor diff: `deploy-dev` runs `git clean -fdx`, `deploy-stage` runs `git clean -fd` (no `-x`).
- **No prod job** — prod is deployed manually.

---

## 8. Acquia Cloud Hooks

Source: `hooks/`. The post-code-deploy and post-code-update scripts are **identical thin wrappers** that source `hooks/common/code-deploy.sh` — all logic lives there.

### 8.1 Site list — `hooks/sites.sh`

```bash
SITES=(default bangladesh ecuador pakistan somoa turkey zimbabwe)
```

Exactly the 7 active sites (no `pacific_islands`).

### 8.2 `hooks/common/code-deploy.sh`

- **Prod skip:** `if [ "$target_env" != 'prod' ]; then … else echo "Manually do the deployment activity." fi`. Prod runs **no** automated DB/config steps.
- For non-prod, loops every site in `SITES` with labeled progress (`Site N/7: name`, steps `[1/5]`–`[5/5]`), and runs per site:

```bash
DRUSH="php -d memory_limit=1024M vendor/drush/drush/drush.php @$site.$target_env -l $site_name"
$DRUSH cr              # [1/5] cache rebuild
$DRUSH updb -y         # [2/5] database updates
$DRUSH cim -y          # [3/5] config import (pass 1)
$DRUSH cr && $DRUSH cim -y   # [4/5] cache rebuild + config import (pass 2)
$DRUSH cr              # [5/5] final cache rebuild
```

> `cim -y` runs **twice** per site (with a cache rebuild between passes) — a deliberate belt-and-suspenders import to settle config that depends on freshly-imported config.

### 8.3 Acquia scheduled jobs (cron)

Configured in Acquia Cloud, not in this repository (`acli api:environments:cron-job-list parentbuddy2.<env>` lists them). Each Drupal site needs its own cron entry because one Drush process bootstraps one site.

| Environment | Job | Schedule | Command |
|---|---|---|---|
| **Dev** | Drupal cron × 7 (`BEBBO`, `Bangla`, `Eductor`, `Turkey`, `PK`, `Bebbo Pacific`, `Zimbabwe`) | every 15 min | `/usr/local/bin/cron-wrapper.sh parentbuddy2.test https://<site>-dev.bebbo.app` |
| **Dev** | `DEV API Warmer` | `30 */2 * * *` (every 2 h at :30) | `cd /var/www/html/parentbuddy2.dev && vendor/bin/drush @parentbuddy2.dev -l dev.bebbo.app bebbo:warm-all --env=dev` |
| **Dev** | Database backup | daily 19:50 + Acquia `ah-db-backup` 08:02 | `drush sql-dump` to `/mnt/gfs/…/backups/on-demand/` |
| **Stage** | Drupal cron × 6 (`Bebbo`, `Bangla`, `Eductor`, `Turkey`, `Pk`, `Zimbabwe`) | every 15 min | `/usr/local/bin/cron-wrapper.sh parentbuddy2.test https://<site>-stage.bebbo.app` |
| **Stage** | `Stage API Warmer` | `30 */2 * * *` | `cd /var/www/html/parentbuddy2.test/docroot && ../vendor/bin/drush -l stage.bebbo.app bebbo:warm-all --env=test` |
| **Stage** | Database backup | every 8 h + `ah-db-backup` 08:12 | `drush sql-dump` to `/mnt/gfs/…/backups/on-demand/` |
| **Prod** | `Drupal Cron - 22/12` | daily 00:00 | `/usr/local/bin/cron-wrapper.sh parentbuddy2.prod http://bebbo.app` (default site only) |
| **Prod** | `ah-db-backup` | daily 06:52 | Acquia-managed backup |

The API warmer job runs `bebbo:warm-all`, which warms the 7 sites one after another through their public hostnames for that environment (`--env` picks the host map in `bebbo_custom_general.warmer`). A pass that starts while the previous one still holds the `bebbo_warm_all` lock exits without doing anything, so the 2-hour interval and a long cold pass cannot overlap. Prod has no warmer job. Drupal-side cron work (purge queue, TMGMT, mailer token refresh, analytics, security cleanup) is scheduled by `ultimate_cron` inside those per-site cron runs.

---

## 9. Cross-Environment / Cross-Site Content Sync

Content is **not** shared at the database level — each site has its own DB (local: per-site `*_db`; Acquia: separate DB per site). Moving content **between sites or between environments** is done with the **Entity Share** module (`drupal/entity_share`, present in `composer.json`; see [`DEPENDENCIES.md`](DEPENDENCIES.md) §3.7).

Entity Share exposes channels and pulls content over JSON:API from a remote (e.g. pulling Prod content down to a local/Dev site). Channel and remote configuration is standardized across all 7 sites and lives in shared `config/sync/` — 42 server channels plus `prod`/`stage` client remotes; only the Basic-Auth credential is environment-managed (key-level `config_ignore`). See [`CONFIGURATION.md`](CONFIGURATION.md) §13.

---

## 10. Post-Deployment: Email OAuth Setup

Email delivery via Symfony Mailer + Office 365 OAuth requires per-environment configuration after deploy. OAuth credentials are managed via the admin UI and protected by `config_ignore` — they are **not** in Git.

All outgoing emails are sent as `admin@bebbo.app` regardless of the per-site `system.site.mail` address. The `MailerSenderOverride` event subscriber (in `bebbo_custom_general`) reads the address from `symfony_mailer_office365.config` → `mail` and forces it as From / Sender / Envelope sender. The original per-site address is preserved as Reply-To. This is required because the O365 mailbox only has SendAs permission for `admin@bebbo.app` — sending as any other address (e.g. `info@babuni.app`) triggers a `SendAsDeniedException`.

After deploying to a new environment:
1. Microsoft Entra app registration (client's M365 admin)
2. Drupal credential entry at `/admin/config/system/mailer/office365`
3. Interactive OAuth authorization in browser
4. Verification: test email on all 7 sites

Full step-by-step procedure: [`RUNBOOK.md`](RUNBOOK.md) §12.

---

## 11. Per-Site Languages

DB-verified **2026-06-29**. Total across all sites: **46** distinct language entries.

| Site | Count | Languages (code — name) |
|------|-------|-------------------------|
| Default (Bebbo) | 28 | `en` English · `ru` Russian · `sq` Albanian · `al-sq` Albania-Albanian · `by-be` Belarus-Belarusian · `by-ru` Belarus-Russian · `bg-bg` Bulgaria-Bulgarian · `gr-el` Greek · `xk-sq` Kosovo-Albanian · `xk-rs` Kosovo-Serbian · `kg-ky` Kyrgyzstan-Kyrgyz · `kg-ru` Kyrgyzstan-Russian · `md-ro` Moldova-Romanian · `me-cnr` Montenegro-Montenegrin · `mk-mk` North Macedonia-Macedonian · `mk-sq` North Macedonia-Albanian · `ro` Romanian · `ro-ro` Romania-Romanian · `sr` Serbian · `rs-sr` Serbia-Serbian · `rs-en` Serbia-English · `sk` Slovak · `tj-tg` Tajikistan-Tajik · `tj-ru` Tajikistan-Russian · `uk` Ukrainian · `uz-uz` Uzbekistan-Uzbek · `uz-ru` Uzbekistan-Russian · `uz-kaa` Uzbekistan-Karakalpak |
| Bangladesh | 2 | `en` English · `bn` Bengali |
| Turkey | 2 | `en` English · `tr` Turkish |
| Ecuador | 3 | `en` English · `es` Spanish · `ec-es` Ecuador-Spanish |
| PK | 2 | `en` English · `ur` Urdu |
| Pacific Islands (`somoa`) | 5 | `en` Global English · `fj-fj` Fijian · `fj-en` Fiji-English · `ws-sm` Samoan · `ws-en` Samoa-English |
| Zimbabwe | 4 | `en` Global English · `zw-en` Zimbabwe-English · `zw-sn` Zimbabwe-Shona · `zw-nd` Zimbabwe-Ndebele |

---

## 12. Per-Site Groups / App Names

DB-verified **2026-06-29**. The group `id` equals the in-app "CountryID"; each non-default site numbers its groups locally.

### 12.1 Default (Bebbo) — 18 groups

| id | Group | App name |
|----|-------|----------|
| 6 | Albania | Bebbo |
| 11 | Bulgaria | Bebbo |
| 16 | Greece | Bebbo |
| 21 | Kosovo | Foleja |
| 26 | Kyrgyzstan | Bebbo |
| 31 | Montenegro | Bebbo |
| 36 | North Macedonia | Bebbo |
| 41 | Serbia | Bebbo |
| 46 | Tajikistan | Bebbo |
| 51 | Uzbekistan | Bebbo |
| 106 | Belarus | Bebbo |
| 126 | Global - English | Bebbo |
| 131 | Global - Russian | Bebbo |
| 136 | Ukraine | Bebbo |
| 141 | Romania | Bebbo |
| 146 | Moldova | Bebbo |
| 151 | Slovakia | Bebbo |
| 161 | India | Bebbo |

> The default site has **no** group `156`/Türkiye — Türkiye is the separate `turkey` site (group 1, app `merhababebek`).

### 12.2 Other sites

| Site | id | Group | App name |
|------|----|-------|----------|
| Bangladesh | 1 | Bangladesh | Babuni |
| Turkey | 1 | Türkiye | merhababebek |
| Ecuador | 1 | Ecuador | Wawamor |
| PK | 1 | Pakistan | pakistan |
| Pacific Islands | 1 | Samoa | BebboPacific |
| Pacific Islands | 6 | Fiji | BebboPacific |
| Zimbabwe | 1 | Zimbabwe | reraiumntwana |

---

## 13. Related Docs

| Topic | Doc |
|-------|-----|
| System architecture & multisite topology | [`ARCHITECTURE.md`](ARCHITECTURE.md) |
| Dependencies & third-party services | [`DEPENDENCIES.md`](DEPENDENCIES.md) |
| CI/CD pipeline deep dive | [`CICD_DEPLOYMENT.md`](CICD_DEPLOYMENT.md) |
| Config splits & per-site overrides | [`CONFIGURATION.md`](CONFIGURATION.md) |
| V2 API device security / JWT | [`API_SECURITY.md`](API_SECURITY.md) |
