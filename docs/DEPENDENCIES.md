# Bebbo CMS — Dependencies & Third-Party Services

> **Audience:** maintainers, code reviewers, onboarding developers, operations.
> **Scope:** every Composer-managed dependency (runtime + dev), version constraints, applied patches, Composer tooling configuration, and the external services the codebase talks to.
> **Verified:** declared constraints were read from `composer.json`; locked versions were read from `composer.lock`. Patch file existence was confirmed on disk. Nothing below is copied from older documentation — where this doc disagrees with any older note, the live files (`composer.json` / `composer.lock`) win.
> **Verified 2026-07-03**: composer facts re-confirmed against `composer.json` / `composer.lock` and custom-module `dependencies:` re-read from each `.info.yml`.

---

## 1. At a Glance

| Property | Value | Source |
|----------|-------|--------|
| Drupal core | **11.3.13** | `composer.lock` (`drupal/core`) |
| Core constraint | `drupal/core-recommended ^11.2` | `composer.json` `require` |
| PHP | **>= 8.4** (platform pinned `8.4`) | `composer.json` `require.php`, `config.platform.php` |
| Drush | **13.7.3** (constraint `^13`) | `composer.lock`, `composer.json` |
| Locked runtime packages | **263** (incl. transitive) | `composer.lock` `packages` |
| Locked dev packages | **52** (incl. transitive) | `composer.lock` `packages-dev` |
| Stability | `minimum-stability: dev`, `prefer-stable: true` | `composer.json` |
| Patches applied | **39** entries (31 local files + 8 remote URLs) | `composer.json` `extra.patches` |
| Content hash | `afe646e7271863fec441f548a24a4208` | `composer.lock` |

> **Counts are total locked packages** (direct + transitive dependencies), not the count of `require` entries. `composer.json` directly declares 120 runtime + 11 dev packages; Composer resolves the rest.

---

## 2. How Dependencies Are Managed

All dependencies flow through Composer. There is **no** npm/yarn package manifest at the repo root for runtime code.

### 2.1 Repositories

| Name | Type | URL | Purpose |
|------|------|-----|---------|
| `drupal` | composer | `https://packages.drupal.org/8` | Drupal contrib modules/themes |
| `asset-packagist` | composer | `https://asset-packagist.org` | npm/bower assets as Composer packages |

### 2.2 Install paths (`extra.installer-paths`)

Packages land under `docroot/` by Composer installer type:

| Path | Type |
|------|------|
| `docroot/core` | `drupal-core` |
| `docroot/modules/contrib/{$name}` | `drupal-module` |
| `docroot/modules/custom/{$name}` | `drupal-custom-module` |
| `docroot/profiles/contrib/{$name}` | `drupal-profile` |
| `docroot/themes/contrib/{$name}` | `drupal-theme` |
| `docroot/libraries/{$name}` | `drupal-library`, `bower-asset`, `npm-asset` |
| `drush/Commands/{$name}` | `drupal-drush` |

Web root is `./docroot` (`extra.drupal-scaffold.locations.web-root`).

### 2.3 Allowed Composer plugins (`config.allow-plugins`)

All 10 set to `true`:

`composer/installers` · `cweagans/composer-patches` · `dealerdirect/phpcodesniffer-composer-installer` · `drupal/core-composer-scaffold` · `drupal/core-project-message` · `grasmash/drupal-security-warning` · `mglaman/composer-drupal-lenient` · `oomphinc/composer-installers-extender` · `php-http/discovery` · `phpstan/extension-installer`

---

## 3. Runtime Dependencies (`require`)

Grouped by function for readability. **Constraint** is the exact string from `composer.json`. Groupings are editorial; a module being installed does **not** mean it is enabled — enablement is per-site via `core.extension.yml` + Config Split (see [§9](#9-caveats--known-drift)).

### 3.1 Core, scaffold & Composer tooling

| Package | Constraint |
|---------|-----------|
| `drupal/core-recommended` | `^11.2` |
| `drupal/core-composer-scaffold` | `^11.2` |
| `drupal/core-project-message` | `^11.2` |
| `composer/installers` | `^2.2` |
| `cweagans/composer-patches` | `~1.0` |
| `mglaman/composer-drupal-lenient` | `^2.0` |
| `webflo/drupal-finder` | `^1.3.1` |
| `acquia/drupal-spec-tool` | `^6.1` |
| `drush/drush` | `^13` |

### 3.2 Hosting, cache & operations (Acquia)

| Package | Constraint | Role |
|---------|-----------|------|
| `acquia/memcache-settings` | `*` | Acquia memcache wiring |
| `drupal/acquia_connector` | `^4.0` | Acquia Cloud connector |
| `drupal/acquia_purge` | `^1.4` | Acquia Varnish/CDN purge |
| `drupal/memcache` | `^2.8` | Memcache backend |
| `drupal/stage_file_proxy` | `^3.1` | Pull missing files from upstream env |
| `drupal/ultimate_cron` | `^2.0@alpha` | Per-job cron scheduling |
| `drupal/filelog` | `^3.0` | Log channel to file |

### 3.3 Access, groups & permissions

| Package | Constraint |
|---------|-----------|
| `drupal/group` | `^3` |
| `drupal/access_policy` | `^2` |
| `drupal/menu_per_role` | `^1.3` |
| `drupal/allowed_languages` | `^2.0` |

### 3.4 Translation (TMGMT)

| Package | Constraint | Backend / role |
|---------|-----------|----------------|
| `drupal/tmgmt` | `^1.15` | Translation management core |
| `drupal/tmgmt_deepl` | `^2.2` | DeepL backend |
| `drupal/tmgmt_google` | `^1.1` | Google Translate backend |
| `drupal/tmgmt_microsoft` | `^1.2` | Microsoft Translator backend |
| `drupal/tmgmt_memsource` | `^1.27` | Phrase/Memsource backend |
| `drupal/languagefield` | `^1.14` | Language field type |

### 3.5 AI

| Package | Constraint | Role |
|---------|-----------|------|
| `drupal/ai` | `^1.4` | AI module framework |
| `drupal/ai_provider_openai` | `^1.2` | OpenAI provider |
| `drupal/ai_tmgmt` | `^1.0@beta` | AI ↔ TMGMT bridge |
| `drupal/ai_translate` | `^1.3` | AI-assisted translation |

### 3.6 API, REST & JSON:API

| Package | Constraint |
|---------|-----------|
| `drupal/jsonapi_extras` | `^3.28` |
| `drupal/jsonapi_page_limit` | `^1.1` |
| `drupal/restui` | `^1.20` |
| `drupal/consumers` | `^1.24` |
| `drupal/simple_oauth` | `^6.1` |
| `drupal/csv_serialization` | `^4.0` |
| `drupal/json_field` | `^1.5` |

### 3.7 Content sync, migration & feeds

| Package | Constraint |
|---------|-----------|
| `drupal/entity_share` | `^3.13` |
| `drupal/entity_share_cron` | `^3.0` |
| `drupal/feeds` | `^3.2` |
| `drupal/feeds_tamper` | `^2.0@RC` |
| `drupal/tamper` | `^1.0@beta` |
| `drupal/migrate_plus` | `^6.0` |
| `drupal/migrate_source_csv` | `^3.8` |
| `drupal/migrate_tools` | `^6.1` |
| `drupal/migrate_upgrade` | `^4.0` |
| `drupal/features` | `^3.8.0` |
| `drupal/structure_sync` | `^2.0` |
| `drupal/entity_update` | `^3.0` |

### 3.8 Configuration management

| Package | Constraint |
|---------|-----------|
| `drupal/config_ignore` | `^3.4` |
| `drupal/config_split` | `^2.0` |
| `drupal/config_update` | `^2.0@alpha` |

### 3.9 Media & images

| Package | Constraint |
|---------|-----------|
| `drupal/imageapi_optimize` | `^4.2` |
| `drupal/imageapi_optimize_avif_webp` | `^1.3` |
| `drupal/imageapi_optimize_binaries` | `^1.2@beta` |
| `drupal/imageapi_optimize_gd` | `^2.0` |
| `drupal/imageapi_optimize_webp` | `^2.1` |
| `drupal/imagemagick` | `*` |
| `drupal/image_style_quality` | `^1.7` |
| `drupal/webp` | `^1.0@RC` |
| `drupal/svg_image` | `^3.2` |
| `drupal/file_mdm` | `^3.1` |
| `drupal/lazy` | `^4.0` |
| `drupal/video_embed_field` | `^3.1` |
| `drupal/video_embed_media` | `^2.4` |

### 3.10 Content modeling, fields & editorial

| Package | Constraint |
|---------|-----------|
| `drupal/paragraphs` | `^1.20` |
| `drupal/entity` | `^1.6` |
| `drupal/entity_reference_revisions` | `*` |
| `drupal/entity_reference_validators` | `^2.0` |
| `drupal/inline_entity_form` | `^3.0@RC` |
| `drupal/auto_entitylabel` | `^3.4` |
| `drupal/autocomplete_id` | `^1.7` |
| `drupal/title_length` | `^2.1` |
| `drupal/linked_field` | `^1.7` |
| `drupal/color` | `^1.0` |
| `drupal/date_popup` | `^2.0` |
| `drupal/contextual_range_filter` | `^3.0` |
| `drupal/better_exposed_filters` | `^7.1` |
| `drupal/content_moderation_notifications` | `^3.7` |
| `drupal/contextual_range_filter` | `^3.0` |
| `drupal/rdf` | `^3.0@beta` |
| `drupal/quickedit` | `^2.0` |
| `drupal/layout_builder_styles` | `^2.1` |
| `drupal/layout_custom_section_classes` | `^1.0` |
| `drupal/layout_section_classes` | `^1.3` |

### 3.11 Views

| Package | Constraint |
|---------|-----------|
| `drupal/views_bulk_operations` | `^4.4` |
| `drupal/views_data_export` | `^1.10` |
| `drupal/views_field_view` | `^1.0` |
| `drupal/view_custom_table` | `2.0.8` (exact pin) |

### 3.12 SEO, routing & menus

| Package | Constraint |
|---------|-----------|
| `drupal/metatag` | `^2.2` |
| `drupal/pathauto` | `^1.15` |
| `drupal/redirect` | `^1.13` |
| `drupal/token` | `^1.17` |
| `drupal/lang_dropdown` | `^2.4` |
| `drupal/menu_link_attributes` | `^1.7` |
| `drupal/menu_export` | `^1.7` |
| `drupal/google_analytics` | `^4.0` |

### 3.13 Admin UI & developer tools

| Package | Constraint |
|---------|-----------|
| `drupal/gin` | `^5.0` |
| `drupal/gin_toolbar` | `^3.0` |
| `drupal/admin_toolbar` | `^3.6` |
| `drupal/toolbar_menu` | `^3.1` |
| `drupal/toolbar_menu_clean` | `^2.0` |
| `drupal/fpa` | `^4.0` |
| `drupal/devel` | `^5.4` |
| `drupal/drupal-extension` | `dev-main` (Behat; declared in `require`, not `require-dev`) |

### 3.14 Security & auth primitives

| Package | Constraint | Role |
|---------|-----------|------|
| `drupal/seckit` | `^2.0` | Security HTTP headers |
| `drupal/security_review` | `^3.1` | Security audit checklist |
| `drupal/shield` | `^1.2.0` | HTTP basic-auth shield |
| `drupal/key` | `^1.22` | Key/secret management |
| `drupal/email_tfa` | `^3.0` | Email-based two-factor authentication (MFA) — OTP sent to user's email after login |
| `enshrined/svg-sanitize` | `^0.22.0` | SVG sanitization (used by `file_sanitizer`) |
| `firebase/php-jwt` | `^7.0@stable` | JWT sign/verify (V2 API security) |
| `spomky-labs/cbor-php` | `^3.0` | CBOR decode (Apple App Attest) |

### 3.15 Mail & mobile

| Package | Constraint | Role |
|---------|-----------|------|
| `drupal/smtp` | `^1.0` | SMTP mail transport (Basic Auth). Superseded by Symfony Mailer O365 — the module is **uninstalled on all sites** (not in `core.extension.yml`); the Composer package remains declared |
| `drupal/symfony_mailer` | `^1.4` | Symfony Mailer framework (mail transport layer) |
| `drupal/symfony_mailer_office365` | `^1.0@alpha` | Office 365 OAuth (XOAUTH2) transport for Symfony Mailer |
| `drupal/mobile_app_links` | `^2.0` | `.well-known` deep-link files |

### 3.16 Database compatibility

| Package | Constraint | Role |
|---------|-----------|------|
| `drupal/mysql57` | `^1.0` | MySQL 5.7 backport driver for Acquia Dev (Drupal 11 requires MySQL 8.0+) |

### 3.17 Frontend assets

| Package | Constraint | Role |
|---------|-----------|------|
| `npm-asset/js-cookie` | `^3.0` | js-cookie library (installed locally, avoids CDN loading) |
| `oomphinc/composer-installers-extender` | `^2.0` | Allows Composer to install npm-asset packages to `docroot/libraries/` |

---

## 4. Verified Locked Versions (key packages)

Exact strings from `composer.lock`. These are the **installed** versions, not the `^`/`~` constraints above. (Full set lives in `composer.lock`.)

| Package | Locked version |
|---------|----------------|
| `drupal/core` / `drupal/core-recommended` | `11.3.13` |
| `drush/drush` | `13.7.3` |
| `drupal/group` | `3.3.5` |
| `drupal/access_policy` | `2.0.0-rc1` |
| `drupal/gin` | `5.0.15` |
| `drupal/entity_share` | `3.13.0` |
| `drupal/tmgmt` | `1.18.0` |
| `drupal/config_split` | `2.0.2` |
| `drupal/memcache` | `2.8.0` |
| `drupal/simple_oauth` | `6.1.1` |
| `drupal/jsonapi_extras` | `3.28.0` |
| `drupal/paragraphs` | `1.21.0` |
| `drupal/metatag` | `2.2.0` |
| `drupal/pathauto` | `1.15.0` |
| `drupal/views_data_export` | `1.10.0` |
| `drupal/feeds` | `3.2.0` |
| `drupal/migrate_plus` | `6.0.10` |
| `drupal/ai` | `1.4.3` |
| `drupal/webp` | `1.0.0-rc2` |
| `drupal/imagemagick` | `5.0.1` |
| `drupal/csv_serialization` | `4.0.1` |
| `drupal/google_analytics` | `4.0.3` |
| `firebase/php-jwt` | `v7.1.0` |
| `enshrined/svg-sanitize` | `0.22.0` |
| `spomky-labs/cbor-php` | `3.2.3` |

---

## 5. Dev Dependencies (`require-dev`)

The CI quality gate (`.github/workflows/pipelines.yml`) runs `composer validate`, PHPCS, `drupal-check`, and `phplint` — backed by these packages.

| Package | Constraint | Locked | Role |
|---------|-----------|--------|------|
| `drupal/coder` | `^8.3` | `8.3.31` | Drupal/DrupalPractice PHPCS sniffs |
| `mglaman/drupal-check` | `^1.5` | `1.5.0` | Deprecation / compatibility scan |
| `mglaman/phpstan-drupal` | `^1.1` | — | PHPStan Drupal extension |
| `phpstan/phpstan` | `^1.10` | `1.12.33` | Static analysis |
| `phpstan/phpstan-deprecation-rules` | `^1.1` | — | Deprecation rules for PHPStan |
| `phpstan/extension-installer` | `^1.3` | — | Auto-register PHPStan extensions |
| `overtrue/phplint` | `^9.0` | `9.7.2` | Parallel PHP lint |
| `phpunit/phpunit` | `^11.5` | `11.5.55` | Unit/kernel test runner |
| `phpspec/prophecy-phpunit` | `^2` | — | Prophecy mocking bridge |
| `symfony/phpunit-bridge` | `^6.4` | — | PHPUnit polyfills/bridge |
| `mikey179/vfsstream` | `^1.6` | — | Virtual filesystem for tests |

`autoload-dev` maps `Drupal\Tests\PHPUnit\` → `tests/phpunit/src/`.

---

## 6. Patches (`extra.patches`)

Applied by `cweagans/composer-patches`. `composer-exit-on-patch-failure: true` — a failed patch aborts install. `patchLevel` for `drupal/core` is `-p2`. **All 31 local patch files were confirmed present on disk**; 8 patches are fetched from drupal.org URLs.

| Target package | Fix | Source |
|----------------|-----|--------|
| `drupal/csv_serialization` | league/csv 9.27 deprecation fix (3562555) | local |
| `drupal/entity_share` | PHP 8.4 implicit-nullable (3553318) | local |
| `drupal/entity_share` | Remove dangling canonical link templates → `RouteNotFoundException` (3419256, MR!89) | local |
| `drupal/views_data_export` | Keep entity_type query metadata under Group access (3173296) | local |
| `drupal/core` | Content moderation translation fix | local |
| `drupal/core` | Translated field issue (3025039-94) | remote URL |
| `drupal/view_custom_table` | Defensive `views_data` checks | local |
| `drupal/video_embed_field` | Feeds mapping missing (3056385) | remote URL |
| `drupal/tmgmt` | Custom source-page filter (node id + country) | local |
| `drupal/tmgmt` | Entity author details | local |
| `drupal/tmgmt` | JobType grouped-filter `escapeLike` array fix | local |
| `drupal/tmgmt` | Contact only the selected provider when building the job form | local |
| `drupal/tmgmt_google` | No page-level error when the job form incidentally probes Google | local |
| `drupal/tmgmt_memsource` | PHP 8.4: explicit nullable param in `createFileTranslation` | local |
| `drupal/imagemagick` | Preserve URL-encoded filenames | local |
| `drupal/date_popup` | Include selected end date (+1 day, 2983680-7) | remote URL |
| `drupal/mobile_app_links` | `.well-known` controller for android/ios | local |
| `drupal/mobile_app_links` | PHP 8.4 implicit-nullable in `processOutbound` (MR!14) | local |
| `drupal/content_moderation_notifications` | Multilingual entity fix (2949891) | remote URL |
| `drupal/views_bulk_operations` | `isAllowed` member-call fix (3323324-3) | remote URL |
| `drupal/filelog` | Circular reference fix (3416342-12) | remote URL |
| `drupal/filelog` | Drupal 11 compatibility (3464223) | local |
| `drupal/lang_dropdown` | `InvalidArgumentException` on 404/403 (no route object) | remote URL |
| `webflo/drupal-finder` | Uncaught `TypeError` on start path | local |
| `drupal/webp` | Stampede prevention (3177103-32) | remote URL |
| `drupal/webp` | PHP 8.4 implicit-nullable + ctor param order (3561953 / MR!54) | local |
| `drupal/config_split` | Collection `complete_list` matching on export (lang overrides → wrong dir) | local |
| `drupal/inline_entity_form` | Allow override of translation restriction on IEF add-new | local |
| `drupal/autocomplete_id` | Filter unpublished entities from ID autocomplete | local |
| `drupal/contextual_range_filter` | PHP 8.4 implicit-nullable in `DateRange::init()` (3521312) | local |
| `drupal/menu_export` | Drush command calling protected `exportMenus` | local |
| `drupal/symfony_mailer_office365` | Pass event dispatcher to ESMTP transport factory + transport (SendAsDenied fix) | local |
| `drupal/ai` | Preserve HTML in streamed chat output — hold flush buffer until tags balance (3586558, MR!1734) | local |
| `drupal/ai_translate` | Stamp revision date, author and log message on AI-generated translations | local |
| `drupal/gin` | Contextual Edit toolbar tab always rendered highlighted (unqualified selector) | local |
| `drupal/migrate_source_csv` | league/csv 9.27 `createFromStream` deprecation fix | local |
| `drupal/structure_sync` | PHP 8.4 implicit-nullable params (3563762) | local |
| `drupal/ultimate_cron` | D11: legacy `#ajax` 'replace' method breaks scheduler/logger switch on job edit form (3535416, MR!71) | local |
| `drupal/warmer` | CDN warmer aborts cold pages at the 30s http_client default timeout | local |

---

## 7. Drupal Lenient (`extra.drupal-lenient`)

`mglaman/composer-drupal-lenient` relaxes the core-version constraint for modules not yet declaring D11 compatibility. Allowed list (6):

`drupal/filelog` · `drupal/entity_share_cron` · `drupal/imageapi_optimize_gd` · `drupal/imageapi_optimize_avif_webp` · `drupal/color` · `drupal/video_embed_media`

---

## 8. External Services

What the codebase reaches out to over the network, and which dependency drives it.

| Service | Driven by | Type | Notes |
|---------|-----------|------|-------|
| DeepL | `tmgmt_deepl` | Translation API | Backend for TMGMT |
| Google Translate | `tmgmt_google` | Translation API | Backend for TMGMT |
| Microsoft Translator | `tmgmt_microsoft` | Translation API | Backend for TMGMT |
| Phrase / Memsource | `tmgmt_memsource` | Translation API | Backend for TMGMT |
| OpenAI | `ai` + `ai_provider_openai` | AI API | AI-assisted translation/content |
| BigQuery | **custom** `pb_content_analytics` | HTTP analytics | No contrib package — custom HTTP sync (see `MODULES.md`) |
| Apple App Attest / Google Play Integrity | **custom** `bebbo_api_security` | Device attestation | Uses `firebase/php-jwt` + `spomky-labs/cbor-php` + OpenSSL; not a contrib integration package (see `API_SECURITY.md`) |
| Microsoft 365 (Office 365 OAuth mail) | `symfony_mailer` + `symfony_mailer_office365` | Mail | OAuth 2.0 (Authorization Code) via `smtp.office365.com:587`; sends as `admin@bebbo.app`. Supersedes the `smtp` module (Basic Auth blocked by M365 Security Defaults) — `smtp` is uninstalled on all sites though the `drupal/smtp ^1.0` Composer package remains declared. OAuth credential keys (`client_id`/`client_secret`/`tenant_id`) managed per-environment via key-level `config_ignore`. See [`CONFIGURATION.md`](CONFIGURATION.md) §1 and [`RUNBOOK.md`](RUNBOOK.md) §12 |
| Acquia Cloud (Varnish/CDN, memcache) | `acquia_connector` · `acquia_purge` · `memcache` · `acquia/memcache-settings` | Hosting/cache | Production platform |

---

## 9. Custom Module Dependencies

There are **13** custom modules under `docroot/modules/custom/`, all enabled (full inventory + functionality in [`MODULES.md`](MODULES.md)). The table below lists only the `dependencies:` each declares in its `.info.yml` — i.e. what each module pulls in beyond core. Modules with no `dependencies:` key (`bebbo_custom_general`, `custom_article`, `file_sanitizer`, `group_country_field`, `pb_content_analytics`, `pb_custom_field`, `pb_custom_form`) are omitted.

| Module | Declared `dependencies:` |
|--------|--------------------------|
| `bebbo_api_security` | `drupal:key` · `drupal:views` · `view_custom_table:view_custom_table` |
| `bebbo_serializer` | `drupal:rest` · `drupal:serialization` · `language_visibility_control:language_visibility_control` |
| `language_custom_field` | `field:field` · `field:field_ui` |
| `language_visibility_control` | `group:group` · `drupal:language` |
| `pb_custom_migrate` | `drupal:system (>=8.3)` · `drupal:migrate` · `drupal:migrate_drupal` · `drupal:migrate_plus` · `drupal:migrate_tools` · `drupal:migrate_source_csv` · `drupal:node` · `drupal:taxonomy` · `drupal:content_translation` |
| `pb_strings` | `drupal:taxonomy` · `drupal:content_translation` |

> **New in the V1/V2 API + device-security work:** `bebbo_api_security` introduced the hard `drupal:key` dependency (JWT signing key + Google service-account key) and `view_custom_table` (its 4 storage views); `bebbo_serializer` depends on `language_visibility_control` for API language filtering. The Composer-level libraries these use — `firebase/php-jwt`, `spomky-labs/cbor-php`, plus the `drupal/mobile_app_links` `.well-known` files — are listed in [§3.14](#314-security--auth-primitives)/[§3.15](#315-mail--mobile). `bebbo_serializer` supersedes the removed `pb_custom_rest_api` + `custom_serialization` + `pb_custom_standard_deviation` modules.

---