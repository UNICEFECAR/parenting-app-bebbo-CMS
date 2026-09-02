# CI/CD & Deployment

> **Verified 2026-07-03** against `.github/workflows/pipelines.yml` and `hooks/common/code-deploy.sh`.

Reference for the build, test, and deploy pipeline. Every claim below is sourced **directly from the files in the repo** — `.github/workflows/pipelines.yml`, the Acquia Cloud hooks under `hooks/`, `drush/sites/`, `buildspec.yml`, `composer.json`, and `phpcs.xml.dist`. Nothing is inferred from convention; where the repo contradicts itself (stale comments, version drift), that is called out explicitly.

---

## 1. Overview

| Stage | Tool | Source file | Trigger |
|---|---|---|---|
| CI checks | GitHub Actions | `.github/workflows/pipelines.yml` (job `ci-checks`) | every PR to `feature/**`,`bug/**`,`hotfix/**`,`develop`,`stage`; every push to `develop`/`stage` |
| Deploy → Acquia Dev | GitHub Actions + Acquia CLI | job `deploy-dev` | push to `develop` |
| Deploy → Acquia Stage | GitHub Actions + Acquia CLI | job `deploy-stage` | push to `stage` |
| Post-deploy DB/config | Acquia Cloud Hooks | `hooks/common/code-deploy.sh` | runs on Acquia after code lands on an environment |
| Docker image → ECR | AWS CodeBuild | `buildspec.yml` | (CodeBuild project; no trigger defined in repo) |

There is **no production deploy job** in GitHub Actions. Prod is handled manually (see §5).

---

## 2. GitHub Actions — `.github/workflows/pipelines.yml`

Workflow name: **`Acquia CI/CD Pipeline`**.

### Triggers (`on:`)
```yaml
push:
  branches: [ develop, stage ]
pull_request:
  branches: [ 'feature/**', 'bug/**', 'hotfix/**', develop, stage ]
workflow_dispatch:        # manual run button
```

### Job 1 — `ci-checks` (runs on every PR and every push)
| Step | Command |
|---|---|
| PHP setup | `shivammathur/setup-php@v2`, **PHP 8.4**, extensions: mbstring, pdo, xml, gd, curl, zip, bcmath, intl |
| Composer | `composer validate --no-check-all --ansi` then `composer install --prefer-dist --no-progress --no-interaction` |
| PHPCS | `./vendor/bin/phpcs` (no args → uses `phpcs.xml.dist`, see §6) |
| Drupal Check | `./vendor/bin/drupal-check -d docroot/modules/custom` |
| PHPLint | `./vendor/bin/phplint` |

All quality gates (composer validate, PHPCS, drupal-check, PHPLint) must pass; both deploy jobs declare `needs: ci-checks`.

> **Local secret scanning:** A pre-commit hook (`scripts/git-hooks/pre-commit`) scans staged files for hardcoded secrets before they reach CI. Auto-installed via `composer install`. See [`RUNBOOK.md`](RUNBOOK.md) §6.1 for details.

### Job 2 — `deploy-dev`
- **Condition:** `github.event_name == 'push' && github.ref == 'refs/heads/develop'`
- PHP **8.4**
- Installs Acquia CLI (`acli.phar`, latest release)
- SSH via `webfactory/ssh-agent@v0.9.0` using secret `ACQUIA_SSH_PRIVATE_KEY`; `ssh-keyscan` of `ACQUIA_SSH_KNOWN_HOSTS`
- Git author set to `GitHub Actions <github-actions+bebbo@unicef.org>`
- `acli auth:login` with `ACQUIA_API_KEY_ID` / `ACQUIA_API_KEY_SECRET`
- Clean state: `git reset --hard` + `git clean -fdx`
- **Deploy:** step `Deploy develop build to Dev` → `acli push:artifact @parentbuddy2.dev --no-interaction -vvv`

> ℹ️ No `composer install` step here by design — `acli push:artifact` builds the artifact with its own `composer install --no-dev --optimize-autoloader`, and `ci-checks` already validated/installed this commit. `deploy-stage` matches.

### Job 3 — `deploy-stage`
- **Condition:** `github.event_name == 'push' && github.ref == 'refs/heads/stage'`
- PHP **8.4** (same as `ci-checks` and `deploy-dev`)
- No composer step (acli builds the artifact; `ci-checks` already validated/installed)
- Same Acquia CLI install / SSH / auth steps as `deploy-dev`
- Clean state: `git reset --hard` + `git clean -fd` (note: **no `-x`**, unlike dev — open, see §10)
- **Deploy:** step `Deploy stage build to Stage`, echoes `Deploying branch <ref> to Stage` → `acli push:artifact @parentbuddy2.test --no-interaction`

### Secrets referenced
`ACQUIA_SSH_PRIVATE_KEY`, `ACQUIA_SSH_KNOWN_HOSTS`, `ACQUIA_API_KEY_ID`, `ACQUIA_API_KEY_SECRET` (GitHub repo/org secrets — not in repo).

---

## 3. Acquia Drush Aliases — `drush/sites/parentbuddy2.site.yml`

Application `parentbuddy2`, realm `devcloud`. Three environments:

| Alias | Env | Docroot | SSH host |
|---|---|---|---|
| `@parentbuddy2.dev` | dev | `/var/www/html/parentbuddy2.dev/docroot` | `parentbuddy2fz6bm64mba.ssh.devcloud.acquia-sites.com` |
| `@parentbuddy2.test` | test (Stage) | `/var/www/html/parentbuddy2.test/docroot` | `parentbuddy2uyawgitzuw…` |
| `@parentbuddy2.prod` | prod | `/var/www/html/parentbuddy2.prod/docroot` | `parentbuddy28zewabzfia.ssh.devcloud.acquia-sites.com` |

Each has a `*.livedev` child alias pointing at `/mnt/gfs/parentbuddy2.{env}/livedev/docroot`. A separate `@ddev.*` alias set lives in `drush/sites/ddev.site.yml` for local DDEV.

---

## 4. Acquia Cloud Hooks — `hooks/`

Run on Acquia infrastructure after code lands on an environment (not in GitHub Actions).

| File | Role |
|---|---|
| `hooks/sites.sh` | Defines the multisite list: `SITES=(default bangladesh ecuador pakistan somoa turkey zimbabwe)` — 7 sites |
| `hooks/common/code-deploy.sh` | The actual deploy logic (below) |
| `hooks/common/post-code-deploy/post-code-deploy.sh` | Sources `code-deploy.sh` |
| `hooks/common/post-code-update/post-code-update.sh` | Sources `code-deploy.sh` |

### `code-deploy.sh` behaviour
Args (Acquia-supplied): `site target-env source-branch deployed-tag repo-url repo-type`. `set -e` (exit on error).

- **If `target_env != prod`:** `cd /var/www/html/$site.$target_env`, source `sites.sh`, then for **each** of the 7 sites run (with progress `Site N/7: name`), using `php -d memory_limit=1024M vendor/drush/drush/drush.php @$site.$target_env -l $site_name`:
  1. `drush cr` — cache rebuild
  2. `drush updb -y` — database updates
  3. `drush cim -y` — config import (pass 1)
  4. `drush cr` + `drush cim -y` — cache rebuild + config import (pass 2)
  5. `drush bebbo:menu-sync` — apply the canonical editorial menu (menu links are content entities, so config import alone never applies them; runs after `cim` so it reads the freshly imported canon)
  6. `drush cr` — final cache rebuild
- **If `target_env == prod`:** prints `"Manually do the deployment activity."` — **no automated DB update or config import on prod.**

> Note: both `post-code-deploy.sh` and `post-code-update.sh` carry a header comment reading "Cloud Hook: post-code-update" — a copy-paste artifact; both simply delegate to `code-deploy.sh`.

---

## 5. Production Deployment

Prod is **manual**. Neither GitHub Actions nor the cloud hooks deploy or update prod automatically:
- No `deploy-prod` job exists in `pipelines.yml`.
- `code-deploy.sh` explicitly skips DB/config steps when `target_env == prod`.

`@parentbuddy2.prod` is defined for manual drush operations only.

---

## 6. Code Quality Configuration — `phpcs.xml.dist`

The `./vendor/bin/phpcs` CI step (no args) reads this ruleset:

| Setting | Value |
|---|---|
| Standards | `Drupal` + `DrupalPractice` (excludes `DrupalPractice.InfoFiles.NamespacedDependency`) |
| Scanned paths | `docroot/modules/custom`, `docroot/themes/custom`, `tests` |
| Excluded | `*/behat`, `*/node_modules`, `*/vendor` |
| Extensions | php, module, inc, install, test, profile, theme, info, yml |
| Ignored extensions | css, md, txt, png, gif, jpeg, jpg, svg |
| Fail on warnings/errors | yes (`ignore_*_on_exit = 0`) |

Other quality tooling (from `composer.json` `require-dev`): `drupal/coder`, `mglaman/drupal-check`, `mglaman/phpstan-drupal`, `overtrue/phplint`, `phpstan/phpstan` (+ deprecation rules, extension-installer), `phpunit/phpunit`, `symfony/phpunit-bridge`, `phpspec/prophecy-phpunit`, `mikey179/vfsstream`. `phpstan.neon` is present at repo root.

---

## 7. AWS CodeBuild — `buildspec.yml`

Separate from the Acquia pipeline. Builds and pushes a Docker image to Amazon ECR.

| Phase | Commands |
|---|---|
| `pre_build` | ECR login via `aws ecr get-login --no-include-email --region $AWS_DEFAULT_REGION` |
| `build` | `docker build -t $IMAGE_NAME:latest .` then tag as `817747646454.dkr.ecr.us-west-2.amazonaws.com/$IMAGE_NAME:latest` |
| `post_build` | `docker push 817747646454.dkr.ecr.us-west-2.amazonaws.com/$IMAGE_NAME:latest` |

`version: 0.2`. Region `us-west-2`, ECR account `817747646454`. Env vars `IMAGE_NAME`, `AWS_DEFAULT_REGION` supplied by the CodeBuild project. The trigger and purpose of this image are **not defined in the repo** — only the build spec is present.

---

## 8. Platform Facts

- **Drupal core:** `drupal/core-recommended: ^11.2` (`composer.json`)
- **PHP (GitHub runner):** 8.4 in all three jobs — `ci-checks`, `deploy-dev` and `deploy-stage` (`pipelines.yml`, `shivammathur/setup-php`). This is the PHP that builds and pushes the artifact; the PHP each Acquia environment runs is configured in Acquia Cloud and is not defined in this repository.
- **Hosting:** Acquia Cloud, application `parentbuddy2`, realm `devcloud`
- **Multisite:** 7 sites (`hooks/sites.sh`): default, bangladesh, ecuador, pakistan, somoa, turkey, zimbabwe
- **Artifact deploy:** Acquia CLI `acli push:artifact` (builds a deploy artifact and pushes to the Acquia Git env)

---

*Generated from repo source: `.github/workflows/pipelines.yml`, `hooks/`, `drush/sites/parentbuddy2.site.yml`, `phpcs.xml.dist`, `buildspec.yml`, `composer.json`. Every value traced to a file; no conventions assumed.*
