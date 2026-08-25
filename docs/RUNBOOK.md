# Bebbo CMS — Operational Runbook

> **Audience:** developers and operators running, maintaining, and troubleshooting Bebbo locally and on Acquia.
> **Scope:** day-to-day operational procedures — local lifecycle, per-site Drush, config import/export, code-quality gate, custom Drush commands, maintenance scripts, deploy steps, troubleshooting.
> **Verified 2026-07-03.** Every command, alias, script path, and binary below was confirmed in the repo. Items that do **not** work as a naive reader might expect are flagged ⚠️. Deep dives live in the sibling docs linked in [§17](#17-related-docs).

---

## 1. Quick Reference

| Task | Command |
|------|---------|
| Start / stop local env | `ddev start` · `ddev stop` · `ddev restart` · `ddev poweroff` |
| Install PHP deps | `ddev composer install` |
| Rebuild cache (default site) | `ddev drush @ddev.bebbo cr` |
| Import config (a site) | `ddev drush @ddev.<alias> cim -y` |
| One-time admin login link | `ddev drush @ddev.<alias> uli` |
| Run full quality gate | see [§6.2](#62-code-quality-checks) |
| Secret scan setup | auto via `composer install`; see [§6.1](#61-secret-scanning-pre-commit-hook) |
| Open phpMyAdmin | `ddev phpmyadmin` |
| Project info / URLs | `ddev describe` |

> `<alias>` is one of the **7 local Drush aliases** — they are **not** the site directory names. See [§3](#3-site--alias-map).

---

## 2. Local Environment Lifecycle (DDEV)

Source: `.ddev/config.yaml` (project `bebbo.app`, PHP 8.4, Apache-FPM, MariaDB 10.11). See [`ENVIRONMENTS.md`](ENVIRONMENTS.md) §2.

```bash
ddev start                 # boot containers
ddev composer install      # install dependencies (Drush is installed via composer)
ddev describe              # show per-site URLs and DB ports
ddev launch                # open default site in browser
ddev stop                  # stop this project
ddev poweroff              # stop ALL ddev projects
```

- Local databases are **per-site** (`bangladesh_db`, `turkey_db`, …); the default site uses DDEV's base `db`. Created by `.ddev/mysql/init-databases.sql`.
- phpMyAdmin: `ddev phpmyadmin` (custom host command — opens phpMyAdmin on the DDEV-assigned port).
- Run a raw SQL file against a site DB, e.g. truncate content: `ddev mysql <db_name> < scripts/truncate_content.sql` (see [§8](#8-maintenance-scripts)).

---

## 3. Site ↔ Alias Map

The local Drush alias key, the site directory, and the config folder do **not** all match — but each site now resolves by **both** its short alias and its directory name. Source: `drush/sites/ddev.site.yml`, `docroot/sites/`, `config/`.

| Site | Local Drush aliases (both work) | `docroot/sites/` dir | `config/` folder |
|------|----------------------------------|----------------------|------------------|
| Default (Bebbo) | `@ddev.bebbo` · `@ddev.default` | `default` | `bebbo` |
| Bangladesh | `@ddev.bangla` · `@ddev.bangladesh` | `bangladesh` | `bangla` |
| Ecuador | `@ddev.ec` · `@ddev.ecuador` | `ecuador` | `ecuador` |
| PK | `@ddev.pakistan` | `pakistan` | `pakistan` |
| Pacific Islands (Bebbo Pacific) | `@ddev.ws` · `@ddev.somoa` | `somoa` | `somoa` |
| Turkey | `@ddev.tr` · `@ddev.turkey` | `turkey` | `turkey` |
| Zimbabwe | `@ddev.zw` · `@ddev.zimbabwe` | `zimbabwe` | `zimbabwe` |

> Both the short key and the site-directory name are defined in `drush/sites/ddev.site.yml`, so `@ddev.bangla` and `@ddev.bangladesh` are equivalent.
>
> Remote Acquia aliases (`@parentbuddy2.dev|test|prod`) live in `drush/sites/parentbuddy2.site.yml` and are for cloud envs only — see [`CICD_DEPLOYMENT.md`](CICD_DEPLOYMENT.md) §3.

---

## 4. Cache & Routine Drush Operations

Run against any single site with its alias:

```bash
ddev drush @ddev.tr cr            # rebuild cache
ddev drush @ddev.tr cron          # run cron
ddev drush @ddev.tr uli           # admin one-time login link
ddev drush @ddev.tr updb -y       # run pending DB updates
ddev drush @ddev.tr cst           # show config status (drift)
```

To run across all 7 locally, loop the aliases (`bebbo bangla ec pakistan ws tr zw`).

---

## 5. Configuration Import / Export

Config layering (shared `config/sync` + per-site split) is documented in [`CONFIGURATION.md`](CONFIGURATION.md) and [`ENVIRONMENTS.md`](ENVIRONMENTS.md) §6.

### Import (apply committed config to a site)

```bash
ddev drush @ddev.<alias> cim -y
ddev drush @ddev.<alias> cr
```

A clean state is when `cim` is a no-op (no pending changes) and `cst` shows no drift.

### Export (only when you intend to change committed config)

```bash
ddev drush @ddev.bebbo cex -y     # export from the DEFAULT site
```

Then commit **only** the YAML files for the change you made — not the full diff.

> **Adding a module to ONE site** (verified file targets): declare it in that site's split entity `config/sync/config_split.config_split.<site>_site.yml` (the `module:` field), place the module's config YAML under that site's `config/<folder>/`, then `cim -y` on that site only. Do **not** edit `core.extension.yml` (it is the shared source of truth for all 7 sites) and do **not** touch other splits. Note the split entity is named after the **directory** (`bangladesh_site`), while the default site's split is `bebbo_site`.

---

## 6. Code Quality Gate (pre-commit)

### 6.1 Secret scanning (pre-commit hook)

A git pre-commit hook scans staged files for hardcoded secrets (API keys, tokens, passwords, private keys). It is **auto-installed** by `composer install` / `composer update` via the `install-git-hooks` script in `composer.json`.

- Source: `scripts/git-hooks/pre-commit`
- Installed to: `.git/hooks/pre-commit`
- No external dependencies — uses `grep` only
- Blocks commit if secrets detected; bypass with `git commit --no-verify` (use with caution)
- Also blocks forbidden files (`.env`, `auth.json`, `*.pem`, `*.key`, etc.)

To manually re-install if needed:

```bash
cp scripts/git-hooks/pre-commit .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit
```

### 6.2 Code quality checks

The checks CI runs (`.github/workflows/pipelines.yml`, job `ci-checks`). CI invokes `phpcs`/`phplint` with **no arguments** — scope and standard come from `phpcs.xml.dist`:

```bash
composer validate --no-check-all --ansi
ddev composer install
ddev exec ./vendor/bin/phpcs
ddev exec ./vendor/bin/drupal-check -d docroot/modules/custom
ddev exec ./vendor/bin/phplint
```

The local convenience form `phpcs docroot/modules/custom --standard=Drupal,DrupalPractice` passes the path/standard explicitly; CI relies on `phpcs.xml.dist` instead. All four must pass — `deploy-dev`/`deploy-stage` are gated on `ci-checks`.

---

## 7. Custom Drush Commands

All 11 are **legacy-style** (`@command` annotations) across three custom modules. Run with a site alias, e.g. `ddev drush @ddev.bangla <command>`. Most mutating commands are **dry-run by default** and need `--execute` to write — verify before running on real data.

### `file_sanitizer`

| Command | Alias | Action | Key args/options |
|---------|-------|--------|------------------|
| `node:touch` | — | Re-save published nodes to bump `changed` across translations (email suppressed) | `--nid`, `--limit`, `--type` |
| `file-sanitizer:scan` | — | Scan cover-image files for unsafe filenames (dry-run) | `--execute`, `--limit` |
| `file-sanitizer:scan-mime` | — | Find files whose extension ≠ actual MIME type | `--execute`, `--limit` |
| `file-sanitizer:scan-body` | `fssb` | Scan body-embedded media for unsafe filenames | arg `content_type`; `--execute`, `--limit` |

### `custom_article`

| Command | Alias | Action | Key args/options |
|---------|-------|--------|------------------|
| `custom-article:copy-keyword` | `copy-keyword` | Copy taxonomy keyword names into `field_meta_keywords` (200/run) | arg `node_type`; arg `offset` (default 0) |
| `custom-article:custom-article-update` | `custom-article-update` | Set `field_do_not_feature` = inverse of `field_suggest_as_daily_reads` on non-English published articles | — |
| `custom-article:delete-terms` | `dlt` | Delete a hardcoded babuni-specific list of taxonomy TIDs | — |

### `bebbo_serializer`

| Command | Alias | Action | Key args/options |
|---------|-------|--------|------------------|
| `embedded-images:populate` | `eip` | Populate `field_embedded_images` from body HTML image URLs | arg `content_type`; `--limit`, `--dry-run` |
| `body-rendered:populate` | `brp` | Populate `field_body_rendered` from body HTML | arg `content_type`; `--limit`, `--dry-run` |
| `file-paths:fix` | `fpf` | Rewrite `/sites/default/files/` refs to current site + copy files (⚠️ refuses to run on default site) | arg `content_type`; `--limit`, `--dry-run`, `--taxonomy` |
| `bebbo:remove-tr-translations` | `rm-tr` | Remove Turkish (`tr`) translations from nodes/terms/media + path aliases (dry-run) | `--execute`, `--force-delete-default`, `--entity-type`, `--batch-size` (50) |

---

## 8. Maintenance Scripts

Source: `scripts/`.

### `scripts/setup-databases.sh`
Imports Acquia DB dumps into the per-site DDEV databases for initial local setup. Expects `.sql.gz`/`.sql` files in a `database/` folder at the repo root, matches each dump to a site by a filename keyword pattern (newest first if multiple), creates the site DB if missing, imports, then runs `drush updb -y / cr / cim -y` against the site alias. Skips sites with no matching dump; exits non-zero if any import fails. Requires `ddev start` first.

```bash
ddev start && ddev composer install
./scripts/setup-databases.sh
```

### `scripts/truncate_content.sql`
Truncates content tables (node, content_moderation_state, media, files, taxonomy, groups, menu_link_content, block_content, path_alias, shortcut) inside `SET FOREIGN_KEY_CHECKS=0/1`. Plain SQL — **destructive**. Invoke against a target DB:

```bash
ddev mysql <db_name> < scripts/truncate_content.sql
```

---

## 9. Deployment

Full pipeline walkthrough: [`CICD_DEPLOYMENT.md`](CICD_DEPLOYMENT.md). Operator summary (verified from `pipelines.yml` + `hooks/`):

| To deploy to… | Do this | Result |
|---------------|---------|--------|
| **Dev** | push / merge to `develop` | CI runs, then `acli push:artifact @parentbuddy2.dev` (PHP 8.4) |
| **Stage** | push / merge to `stage` | CI runs, then `acli push:artifact @parentbuddy2.test` (runner PHP 8.4) |
| **Prod** | **manual only** | no `deploy-prod` job; cloud hook **skips** DB/config on prod |

- `main` is **not** a deploy trigger.
- Post-deploy (non-prod), the Acquia cloud hook loops all 7 sites with labeled progress (`Site N/7`, steps `[1/5]`–`[5/5]`) running `drush cr / updb -y / cim -y` (with `cim` run twice). See [`ENVIRONMENTS.md`](ENVIRONMENTS.md) §8.
- ⚠️ `acli` is **not** in `composer.json`/`vendor/bin` — CI downloads it from GitHub releases at runtime. There is no repo-provided `acli`; install it globally if deploying by hand.

---

## 10. Rollback Procedures

### 10.1 Code rollback (Dev / Stage)

Dev and Stage deploy via `acli push:artifact` triggered by pushes to `develop` / `stage`. To roll back a bad deploy:

**Option A — Revert and redeploy (preferred):**

```bash
# 1. On the source branch (develop or stage), revert the bad commit(s)
git revert <bad-commit-sha>
git push origin develop       # or: git push origin stage
# 2. CI runs automatically → new artifact deployed → cloud hook runs updb + cim on all 7 sites
```

This is the safest path — it creates a forward-moving history and goes through the full CI gate.

**Option B — Redeploy a previous artifact via Acquia Cloud UI:**

1. Log in to Acquia Cloud Console → Application `parentbuddy2` → target environment (Dev or Test)
2. Under **Code**, switch to a previous artifact tag/branch
3. The cloud hook (`code-deploy.sh`) fires automatically and runs the 5-step post-deploy sequence (cr → updb → cim → cr+cim → cr) across all 7 sites

> Acquia retains previous artifact commits on its Git remote. Switching code in the Cloud UI is equivalent to deploying an older artifact without going through GitHub Actions.

**Post-rollback verification (both options):**

```bash
# SSH to the environment or use Acquia CLI
# Verify code version
drush @parentbuddy2.dev status | grep 'Drupal version'

# Check all 7 sites have clean config state
for site in default bangladesh ecuador pakistan somoa turkey zimbabwe; do
  echo "=== $site ==="
  drush @parentbuddy2.dev -l $site cst
done
```

### 10.2 Config rollback

Bebbo uses config splits across 7 sites: shared base in `config/sync/` + per-site overrides in `config/{folder}/`. Config is imported by the cloud hook post-deploy (twice per site with cache rebuild between).

**Scenario: Bad config was committed and deployed**

```bash
# 1. Revert the config YAML changes in Git
git revert <config-commit-sha>
git push origin develop    # triggers redeploy → cloud hook runs cim across all 7 sites
```

**Scenario: Config was changed manually on the environment (not in Git)**

```bash
# Re-import committed config to overwrite manual changes (per site)
drush @parentbuddy2.dev -l <site_name> cim -y
drush @parentbuddy2.dev -l <site_name> cr
```

**Key rules:**
- **Export only from `bebbo`** (default site) — never `cex` the other 6 sites (sweeps unrelated drift into config)
- Per-site split changes go in `config/{folder}/`, not `config/sync/`
- After rollback, run `cst` on each site to confirm zero drift

### 10.3 Production rollback

Prod has **no automated deploy job** and the cloud hook explicitly **skips** DB/config steps (`code-deploy.sh` prints `"Manually do the deployment activity."` when `target_env == prod`). All prod operations are manual.

**Code rollback on Prod:**

1. Switch code to a previous artifact tag in the Acquia Cloud Console (the cloud hook will fire but skip DB/config steps on prod)
2. Manually run post-deploy steps per site if needed:

```bash
DRUSH="php -d memory_limit=1024M vendor/drush/drush/drush.php @parentbuddy2.prod -l <site_name>"
$DRUSH cr
$DRUSH updb -y
$DRUSH cim -y
$DRUSH cr && $DRUSH cim -y
$DRUSH cr
```

3. Repeat for all 7 sites: `default bangladesh ecuador pakistan somoa turkey zimbabwe`

**Database restore (last resort):**

If the bad deploy ran update hooks that altered the database schema, a code-only rollback may not be sufficient. In that case:
1. Restore the database from Acquia's automated backup (Acquia Cloud Console → Environment → Databases → Backups)
2. Then switch code to the matching previous artifact
3. Run `drush cr` on all 7 sites

### 10.4 When code rollback is NOT enough

These scenarios require database restore in addition to code rollback:

| Scenario | Why code revert alone fails | Action |
|----------|----------------------------|--------|
| **Update hooks ran** (`hook_update_N`) that added/altered/dropped tables or columns | Reverting code removes the hook but not its DB effects | Restore DB backup, then deploy old code |
| **Content created after deploy** depends on new fields/entities | Rolling back code leaves orphaned data referencing removed schema | Restore DB to pre-deploy state; content created post-deploy is lost |
| **Entity Share pull in progress** when rollback starts | Partial content sync may leave broken entity references | Complete or fully revert the pull before rolling back code |
| **Config split entity was modified** (e.g. module added to a split) | `cim` with old code may fail if the module's config is partially present | Restore DB, then deploy old code, then `cim` |

> **Always take a DB backup before deploying to Prod.** Acquia provides automated daily backups, but create an on-demand backup immediately before any risky deploy via Acquia Cloud Console or `acli api:environments:database-backup-create`.

### 10.5 Rollback decision tree

```
Deploy failed or bad behavior detected
  │
  ├─ Were any update hooks (updb) executed?
  │    ├─ NO → Git revert + redeploy (§10.1 Option A)
  │    └─ YES → Did the hooks alter DB schema?
  │         ├─ NO (data-only hooks) → Git revert + redeploy, verify data
  │         └─ YES (schema changes) → Restore DB backup + deploy old code
  │
  ├─ Is this Prod?
  │    ├─ YES → Manual steps only (§10.3), take backup first
  │    └─ NO → Automated via push to develop/stage
  │
  └─ Is there new content created since deploy?
       ├─ NO → Safe to restore DB backup if needed
       └─ YES → Weigh content loss vs. broken schema; may need selective fix
```

---

## 11. Environment Synchronization

### 11.1 How config flows between environments

Config is **code-driven** — it lives in Git and is applied on deploy. There is no environment-to-environment config sync at runtime.

```
Developer workstation          Git repo                 Acquia environment
┌──────────────┐          ┌──────────────┐          ┌──────────────┐
│ ddev drush   │  commit  │ config/sync/ │  deploy  │ cloud hook   │
│ @ddev.bebbo  │───────→  │ config/{site}│───────→  │ drush cim -y │
│ cex -y       │          │              │          │ (×7 sites)   │
└──────────────┘          └──────────────┘          └──────────────┘
```

**The flow:**

1. **Export** config locally from `@ddev.bebbo` only (`ddev drush @ddev.bebbo cex -y`)
2. **Commit** only the YAML files for your change (not full diff)
3. **Push** to `develop` or `stage` branch
4. **CI** runs quality checks → `acli push:artifact` sends code to Acquia
5. **Cloud hook** runs `drush cim -y` on all 7 sites (twice, with cache rebuild between)

### 11.2 Config split architecture

Bebbo is a **7-site multisite**. All sites share a base config, with per-site overrides via `config_split`:

| Layer | Path | What lives here |
|-------|------|-----------------|
| Shared base | `config/sync/` (1,586 files) | Core settings, content types, fields, views, permissions, Entity Share channels/remotes — everything common to all 7 sites |
| Per-site split | `config/{folder}/` | Site-specific overrides: language entities, blocks, system.site, timezone patches, landing pages / language redirects, mobile app links |

Each site activates its own split via `docroot/sites/{site}/site.splits.php`:
```php
$config['config_split.config_split.{name}_site']['status'] = TRUE;
```

| Site | Split entity | Config folder |
|------|-------------|---------------|
| Default (Bebbo) | `bebbo_site` | `config/bebbo/` |
| Bangladesh | `bangladesh_site` | `config/bangla/` |
| Ecuador | `ecuador_site` | `config/ecuador/` |
| PK | `pakistan_site` | `config/pakistan/` |
| Pacific Islands | `somoa_site` | `config/somoa/` |
| Turkey | `turkey_site` | `config/turkey/` |
| Zimbabwe | `zimbabwe_site` | `config/zimbabwe/` |

**How splits work on import (`cim`):** Drupal reads `config/sync/` as the base, then the active split patches/creates/deletes config items from `config/{folder}/`. A split can override a shared config item or add site-only config that doesn't exist in `config/sync/`.

**Key rules:**
- Export only from `@ddev.bebbo` — never `cex` the other 6 sites (pulls in unrelated drift)
- For the other 6 sites, hand-edit their split YAML and verify with `drush cim -y`
- Adding a module to **one** site: declare it in that site's split entity (`config/sync/config_split.config_split.{name}_site.yml`), place module config in `config/{folder}/`
- There is **no** per-environment config split (`config/envs/` contains only a README.md, no config YAML) — environment differences come from Acquia env detection and deploy pipeline, not from config files

### 11.3 Content synchronization (Entity Share)

Content is **not** shared at the database level — each site has its own DB (locally: per-site `*_db`; Acquia: separate DB per site). Content moves between sites and environments via **Entity Share**:

| Direction | Method |
|-----------|--------|
| Prod → Dev/Local | Entity Share pull (JSON:API channels on Prod, configured remote on target) |
| Site A → Site B | Entity Share pull (each site exposes channels for its content) |

Entity Share channel and remote configuration is standardized and lives in shared `config/sync/` (42 server channels; `prod` and `stage` client remotes). The Basic-Auth credential is environment-managed via a key-level `config_ignore` entry. There is no automated content sync on deploy — it is always a manual pull operation.

See [`DEPENDENCIES.md`](DEPENDENCIES.md) §3.7 and [`ENVIRONMENTS.md`](ENVIRONMENTS.md) §9 for channel mechanics.

---

## 12. Email (Symfony Mailer + Office 365 OAuth)

Email is sent via `drupal/symfony_mailer` with the `drupal/symfony_mailer_office365` transport, using OAuth 2.0 Authorization Code flow against Microsoft 365 (`smtp.office365.com:587`). The `smtp` module is uninstalled on all sites — Basic Auth is blocked by M365 Security Defaults at the tenant level (the `drupal/smtp` Composer package remains declared in `composer.json`).

### 12.1 Post-deployment OAuth setup (required per environment)

After deploying to a new environment (Stage, Prod), email will **not** work until OAuth is configured.

**Part 1 — Microsoft Entra (client's M365 admin):**
1. Register an app in Entra admin center → **App registrations** → **New registration**
   - Name: **Bebbo Site Mailer**
   - Supported account types: **Single tenant**
   - Redirect URI: `https://<env-domain>/office365/oauth/callback`
2. Copy Application (client) ID + Directory (tenant) ID
3. Create a client secret under **Certificates & secrets** (note the expiry)
4. Under **API permissions** → Office 365 Exchange Online → Delegated → `SMTP.Send` → Grant admin consent
5. Confirm Authenticated SMTP is enabled on `admin@bebbo.app` mailbox

**Part 2 — Drupal configuration:**
1. Navigate to `/admin/config/system/mailer/office365`
2. Enter: Tenant ID, Client ID, Client Secret
3. Save and complete the interactive OAuth authorization flow in the browser
4. Send test email from `/admin/config/system/mailer/transport/office_365_oauth`
5. Confirm Drupal cron is running regularly (token auto-refresh depends on it)

**Part 3 — Verification:**
- Test email on all 7 sites: system test email, password reset, content moderation notifications
- Monitor `/admin/reports/dblog` for mailer errors in first 24 hours
- Note client secret expiry date for future renewal

### 12.2 Config protection

The OAuth credential keys are in `config_ignore` at **key level**, so `drush cim` never overwrites live credentials while the rest of the transport config stays in Git:
- `symfony_mailer.mailer_transport.office_365_oauth:configuration.client_id` / `:configuration.client_secret` / `:configuration.tenant_id`
- `symfony_mailer_office365.config:client_id` / `:client_secret` / `:tenant_id`

### 12.3 Troubleshooting email

| Symptom | Cause / Fix |
|---------|-------------|
| "Client not authenticated to send mail" | OAuth token expired or never authorized. Re-run OAuth flow at `/admin/config/system/mailer/office365` |
| Token refresh failing | Cron not running. Check `drush cron` and `ultimate_cron` status |
| No emails after deploy | OAuth not configured on this environment. Follow §12.1 |
| Client secret expired | Rotate secret in Entra, update at `/admin/config/system/mailer/office365` |
| `554 5.2.0 STOREDRV.Submission.Exception:SendAsDeniedException` | O365 rejecting because From address differs from the authenticated mailbox (`admin@bebbo.app`). The `MailerSenderOverride` subscriber should override From to `admin@bebbo.app` on all outgoing mail. If still failing: (1) verify the composer patch `symfony_mailer_office365-pass-event-dispatcher.patch` is applied (`composer install` output should show it), (2) run `drush cr` to rebuild the service container, (3) confirm the subscriber fires by checking dblog for `mailer_sender_override` entries. Root cause: the upstream module creates its SMTP transport without the event dispatcher, so `MessageEvent` subscribers never fire — the patch fixes this. |

---

## 13. Multi-Factor Authentication (Email TFA)

All user logins require email-based OTP verification via `drupal/email_tfa`. After entering username/password, users are redirected to `/tfa/verify/{uid}/{hash}` where they enter a 6-digit code sent to their email. On successful verification, users are redirected to `/dashboard`.

### 13.1 Configuration

Admin settings at `/admin/config/people/email-tfa` (permission: `administer email tfa`):
- Scope: globally enabled (all users, no role exclusions)
- OTP: 6 digits, 300s timeout, 5 attempts per hour
- Dev mode: disabled

### 13.2 Email notification policy

Only two types of user-facing emails are enabled:

| Email | Source | When |
|---|---|---|
| MFA OTP code | `email_tfa` | Every login |
| Password reset link | `user.settings` (`password_reset`) | User-initiated via `/user/password` |

All content moderation notifications are **disabled** (`status: false`). All other user notifications (`cancel_confirm`, `status_activated`, `status_blocked`, `status_canceled`, `register_admin_created`, `register_no_approval_required`, `register_pending_approval`) are **disabled**.

Because `register_admin_created` is off, a newly created account receives no one-time-login mail. Set a password on the user-add form and hand it to the person out-of-band; they change it after first login.

### 13.3 Dependencies

Email TFA depends on working email delivery. If Symfony Mailer / OAuth is not configured, OTP codes cannot be sent and users will be locked out after login. Always verify email delivery before enabling TFA on a new environment.

### 13.4 Troubleshooting MFA

| Symptom | Cause / Fix |
|---------|-------------|
| "OTP not received" | Email delivery broken — check §12 above |
| User locked out (flood limit) | 5 attempts per hour. Wait, or clear flood table: `drush sqlq "DELETE FROM flood WHERE event = 'email_tfa.failed_login'"` |
| Redirect loop after login | Anonymous guard in `pb_custom_field_user_login_form_submit()` should prevent this — check if hook is firing |

---

## 14. Troubleshooting

| Symptom | Cause / Fix |
|---------|-------------|
| `@ddev.<site>` "alias not found" | Both short keys and directory names are defined in `drush/sites/ddev.site.yml`. If still failing, run from project root and check the alias file (see [§3](#3-site--alias-map)). |
| Config keeps re-importing / drift after `cim` | Confirm you exported from `@ddev.bebbo` and committed only the intended YAML; check the change belongs to `config/sync` vs the right `config/<folder>` split. |
| Country API 500s with `Undefined array key "table"` (Views handler) | A config_split partial `removing:` block applied as a stale partial removal, leaving a Views handler (sort/filter/field) stub with no `table` key. **Fix:** drop the offending split patch and re-import that site's config so the split matches `config/sync` (handler then inherits the complete definition). Re-import: `ddev drush @ddev.<alias> cim -y && ddev drush @ddev.<alias> cr`. See [§16](#16-api-quick-reference-operators). |
| Custom Drush command "writes nothing" | Most are dry-run by default — add `--execute` (file-sanitizer/rm-tr) once verified. |
| `file-paths:fix` refuses to run | By design it will not run on the default site — target a country alias. |
| Container / port issues | `ddev restart`, then `ddev poweroff && ddev start`. Check `ddev describe`. |

---

## 15. Site URLs Reference

Per-site domains by environment (verified from `docroot/sites/sites.php`). Use these when checking a specific country site or pointing an Acquia `-l <site_name>` Drush call at the right environment.

| Site | Prod | Stage | Dev | DDEV local |
|------|------|-------|-----|------------|
| Default (Bebbo) | bebbo.app | (bare Acquia env URL) | (bare Acquia env URL) | bebbo.app.ddev.site |
| Bangladesh | babuni.app · bangla.bebbo.app | bangla-stage.bebbo.app | bangla-dev.bebbo.app | bangla.bebbo.app.ddev.site |
| Turkey | merhababebek.app · tr.bebbo.app | tr-stage.bebbo.app | tr-dev.bebbo.app | tr.bebbo.app.ddev.site |
| Ecuador | wawamor.ec · ec.bebbo.app | ec-stage.bebbo.app | ec-dev.bebbo.app | ec.bebbo.app.ddev.site |
| PK | pk.bebbo.app | pk-stage.bebbo.app | pk-dev.bebbo.app | pk.bebbo.app.ddev.site |
| Pacific Islands (Bebbo Pacific) | ws.bebbo.app · bebbopacific.app | ws-stage.bebbo.app | ws-dev.bebbo.app | ws.bebbo.app.ddev.site |
| Zimbabwe | umntwana.app · zw.bebbo.app · rerai.umntwana.app | zw-stage.bebbo.app | zw-dev.bebbo.app | zw.bebbo.app.ddev.site |

> The **Default (Bebbo)** site has no `-stage`/`-dev` vanity host in `sites.php` — it resolves via the bare Acquia environment URL. Stage/Dev hosts for the country sites follow the pattern `<slug>-{stage,dev}.bebbo.app` (slugs `bangla tr ec pk ws zw`).

---

## 16. API Quick Reference (operators)

Two REST surfaces are served by the `bebbo_serializer` module across all 7 sites. Full reference: [`API_REFERENCE.md`](API_REFERENCE.md) and [`API_SECURITY.md`](API_SECURITY.md).

| Surface | Path prefix | Auth | Notes |
|---------|-------------|------|-------|
| **V1** | `/api/*` | **Public** (no JWT) | Serializer style `bebbo_v1_serializer`; view `bebbo_v1_apis` (22 displays) |
| **V2** | `/v2/api/*` | **JWT** (Bearer) | Serializer style `bebbo_serializer`; view `bebbo_v2_apis` (20 displays) |

JWT enforcement: the `bebbo_api_security` event subscriber protects **only** `/v2/api/*`. Clients obtain the token via the public device-security POST endpoints under `/api/security/*` (`register`, `device/register`, `device/verify`, `refresh`, `revoke`).

**Common operator endpoints:**

| Endpoint | Auth | Backing |
|----------|------|---------|
| `/api/check-update/{country}` | Public | `CheckUpdateController` (route `bebbo_serializer.v1_check_update`) |
| `/v2/api/check-update/{country}` | JWT | Same controller; route has `no_cache: TRUE` (never page-cached) |
| `/api/country-groups/{slug}` | Public | View `country_listing`; `{slug}` is an app/site slug (e.g. `wawamor`), **not** a language |
| `/v2/api/country-groups/{slug}` | JWT | Same view, V2 display |
| `/api/taxonomies/{lang}/all` | Public | View `tax`; `all` returns terms for every vocabulary |

> `{country}` for check-update is a numeric group/country id (e.g. `126`). `{lang}` is a language code (`en`, `bn`, …).

**When a country API 500s:**

1. **`Undefined array key "table"`** (Views handler stub) → a config_split partial `removing:` block left a sort/filter/field handler without its `table` key. **Fix:** delete the stale split patch and re-import that site's config so the split matches `config/sync` (`ddev drush @ddev.<alias> cim -y && cr`). See [§14](#14-troubleshooting).
2. **Duplicate or orphan group translations** → duplicate/orphan group-translation rows surface as 500s or duplicated `country-groups` output. The `country_listing` default display carries a `default_langcode = 1` filter and an orphan-translation purge — confirm the filter is present and re-import config.
3. **Check the dblog first:** `ddev drush @ddev.<alias> ws --count=50` (or `/admin/reports/dblog`) to see the exact handler/route that threw.

---

## 17. Related Docs

| Topic | Doc |
|-------|-----|
| Environments, settings load, cloud hooks | [`ENVIRONMENTS.md`](ENVIRONMENTS.md) |
| CI/CD pipeline & deploy internals | [`CICD_DEPLOYMENT.md`](CICD_DEPLOYMENT.md) |
| Config reference (roles, fields, workflow, groups) | [`CONFIGURATION.md`](CONFIGURATION.md) |
| Dependencies & third-party services | [`DEPENDENCIES.md`](DEPENDENCIES.md) |
| Custom modules reference | [`MODULES.md`](MODULES.md) |
| System architecture | [`ARCHITECTURE.md`](ARCHITECTURE.md) |
| REST API endpoints (V1/V2) | [`API_REFERENCE.md`](API_REFERENCE.md) |
| API auth, JWT, device security | [`API_SECURITY.md`](API_SECURITY.md) |
