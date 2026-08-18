# Bebbo Custom Modules Reference

> **Audience:** backend maintainers, code reviewers, onboarding developers.
> **Scope:** all 13 enabled custom modules in `docroot/modules/custom/`, their purpose, hooks, services, routes, database tables, and interdependencies.
> **Verified:** every hook, class, service, and route below was confirmed in source. **Verified 2026-07-03** against `config/sync/core.extension.yml` and the module directories on disk.

---

## Module Index

| # | Module | Package | Purpose | Complexity |
|---|--------|---------|---------|------------|
| 1 | [`bebbo_api_security`](#1-bebbo_api_security) | Security | Device attestation, JWT auth for V2 API | High |
| 2 | [`bebbo_serializer`](#2-bebbo_serializer) | Serialization | V1 + V2 REST API serialization, response envelopes, ETag, check-update | High |
| 3 | [`bebbo_custom_general`](#3-bebbo_custom_general) | Utilities | Catch-all utilities extracted from `pb_custom_form`: CSV export, TMGMT UX, mobile share, redirects, app-store QR | High |
| 4 | [`pb_custom_field`](#4-pb_custom_field) | Editorial | Field access, form alterations, editorial workflow, group membership | High |
| 5 | [`pb_custom_form`](#5-pb_custom_form) | Admin | Force-update API table + admin config container | Medium |
| 6 | [`pb_content_analytics`](#6-pb_content_analytics) | Analytics | BigQuery sync for read/like counts, analytics reports | Medium |
| 7 | [`group_country_field`](#7-group_country_field) | Groups | Views query alteration for group-scoped content, TMGMT form control | Medium |
| 8 | [`language_visibility_control`](#8-language_visibility_control) | Language | Per-group mobile language visibility, API response filtering | Medium |
| 9 | [`language_custom_field`](#9-language_custom_field) | Language | Custom fields on language entities (locale, luxon, plural) | Low |
| 10 | [`custom_article`](#10-custom_article) | Content | Batch keyword lowercasing, cross-translation field copy, Drush utilities | Low |
| 11 | [`file_sanitizer`](#11-file_sanitizer) | Media | Filename sanitization on upload, MIME mismatch scanning | Low |
| 12 | [`pb_custom_migrate`](#12-pb_custom_migrate) | Migration | CSV-based content migration configs | Low |
| 13 | [`pb_strings`](#13-pb_strings) | Content | Strings taxonomy management, unique name enforcement, bulk translate UI | Low |

> **Removed modules** (no longer on disk): [`pb_custom_rest_api`](#removed-modules), [`custom_serialization`](#removed-modules), [`pb_custom_standard_deviation`](#removed-modules) — see the [Removed modules](#removed-modules) section. Their logic moved into `bebbo_serializer` and `bebbo_custom_general`.

---

## 1. `bebbo_api_security`

**Name:** Bebbo API Security
**Core:** `^10 || ^11`
**Dependencies:** `drupal:key`, `drupal:views`, `view_custom_table:view_custom_table`
**Full documentation:** [`API_SECURITY.md`](API_SECURITY.md)

### Purpose

Device authentication for the V2 content API. Apps prove authenticity via platform attestation (Apple App Attest / Google Play Integrity) or sideloaded challenge-response, then receive JWT access tokens for protected endpoints.

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `bebbo_api_security.device_registry` | `DeviceRegistryService` | DB CRUD for devices, tokens, challenges; cron purge |
| `bebbo_api_security.jwt_service` | `JwtService` | RS256 JWT create/validate; refresh token rotation with replay detection |
| `bebbo_api_security.google_play_integrity` | `GooglePlayIntegrityService` | Android Play Integrity verdict verification |
| `bebbo_api_security.apple_app_attest` | `AppleAppAttestService` | iOS App Attest offline verification (CBOR + OpenSSL) |
| `bebbo_api_security.sideloaded_verification` | `SideloadedVerificationService` | EC P-256 challenge-response for sideloaded builds |
| `bebbo_api_security.request_subscriber` | `ApiSecuritySubscriber` | `KernelEvents::REQUEST` (priority 300) — JWT enforcement on protected paths |

### Routes

| Path | Method | Access | Handler |
|------|--------|--------|---------|
| `/api/security/register` | POST | public | `SecurityController::register` |
| `/api/security/device/register` | POST | public | `SecurityController::deviceRegister` |
| `/api/security/device/verify` | POST | public | `SecurityController::deviceVerify` |
| `/api/security/refresh` | POST | public | `SecurityController::refresh` |
| `/api/security/revoke` | POST | public (Bearer required in handler) | `SecurityController::revoke` |
| `/admin/config/parent-buddy/api-security` | GET/POST | `administer bebbo api security` | `ApiSecuritySettingsForm` |

The five `/api/security/*` endpoints are PUBLIC because unauthenticated devices bootstrap auth here — they are how a client OBTAINS the JWT used for `/v2/api/*`. JWT-only path protection is `/v2/api/` (the `/api/check-update/` pattern is not in the protected set — the V1 check-update path stays public).

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_cron` | Purges expired challenges, revoked/expired refresh tokens, trims security log |

### Database Tables

`bebbo_api_devices`, `bebbo_api_refresh_tokens`, `bebbo_api_challenges`, `bebbo_api_security_log` — schema details in [`API_SECURITY.md` §8](API_SECURITY.md#8-data-model). Backed by 4 admin Views (`bebbo_api_devices`, `bebbo_api_challenges`, `bebbo_api_refresh_tokens`, `bebbo_api_security_log`). Keys via the Key module: `bebbo_jwt_signing_key` (JWT) and `bebbo_google_sa_key` (Play Integrity / Google SA).

### Permissions

- `administer bebbo api security` (restrict access: true)

### Update Hooks

- `10001`: Installs admin Views (devices, tokens, challenges, security log)

### Tests

3 Unit + 5 Kernel test classes in `tests/src/`.

---

## 2. `bebbo_serializer`

**Name:** Bebbo Serializer
**Core:** `^10 || ^11`
**Dependencies:** `drupal:rest`, `drupal:serialization`, `language_visibility_control:language_visibility_control`

### Purpose

Both V1 (`/api/*`, public) and V2 (`/v2/api/*`, JWT-protected) REST API serialization. Provides the `bebbo_serializer` (V2) and `bebbo_v1_serializer` (V1) Views style plugins that transform Views REST export rows into the Bebbo JSON envelope (`{status, total, langcode, datetime, data}`), the `bebbo_json` encoder, ETag support (V2), and presave logic for computed fields. V1 emits plain escaped `json` for byte parity with the legacy app; V2 emits `bebbo_json` (unescaped slashes/unicode) with ETag/304.

Also hosts two endpoints exposed under both V1 and V2 paths: the **Strings API** (`/api/strings/%` and `/v2/api/strings/%`, served by the `string_rest_export` / `v2_string_rest_export` displays of the `tax` view using this module's style plugin) and the **Force-Update / check-update** endpoint (`/api/check-update/{country}` and `/v2/api/check-update/{country}`, served by `CheckUpdateController` via `bebbo_serializer.routing.yml`). Both V1/V2 pairs return identical responses. The V2 check-update route is marked `no_cache: TRUE` to prevent a cached authenticated 200 being replayed to anonymous clients by the page cache.

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `bebbo_serializer.encoder.bebbo_json` | `BebboEncoder` | JSON encoder with `UNESCAPED_SLASHES \| UNESCAPED_UNICODE` |
| `bebbo_serializer.request_format_subscriber` | `RequestFormatSubscriber` | `KernelEvents::REQUEST` (priority 1000) — registers `bebbo_json` MIME type |
| `bebbo_serializer.etag_response_subscriber` | `EtagResponseSubscriber` | `KernelEvents::RESPONSE` (priority 0) — ETag/304 support |
| `bebbo_serializer.body_image_processor` | `BodyImageProcessor` | Renders body HTML, extracts image URLs, converts to WebP |

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_node_presave` | Populates `field_number_of_modules` (course), `field_number_of_questions` (quiz), truncates height/weight decimals (pregnancy_weekly_overview), auto-populates `field_embedded_images` for published nodes and `field_body_rendered` for nodes in any moderation state |
| `hook_node_predelete` | Deletes orphaned quiz_questions nodes when quiz node deleted |
| `hook_views_query_alter` | Adds Pregnancy term to child_age filter when `?pregnancy=true` on the V1 + V2 articles endpoints |
| `hook_form_alter` | Makes module/question count fields readonly on course/quiz forms; validates course expiry and passing score; rejects saving a Quiz as `single_question_quiz` while it holds more than one question (validation error — extra questions are never auto-removed) |
| `hook_inline_entity_form_translation_restrict_alter` | Allows adding new course modules on translation forms |
| `hook_inline_entity_form_entity_form_alter` | Renames IEF "Add another item" → "Add another answer" on quiz forms |

### Key Classes

| Class | Type | Role |
|-------|------|------|
| `BebboSerializer` | Views style plugin | 20+ `transformX()` methods for per-endpoint row transformation (V2 + Strings) |
| `BebboV1Serializer` | Views style plugin | V1 counterpart style plugin (`bebbo_v1_serializer`) for the V1 displays |
| `BebboSerializerHelpers` | Trait | Shared pure row-transform helpers (type casts, media/HTML parsing, language resolution, taxonomy) used by both style plugins |
| `CheckUpdateController` | Controller | Force-update / check-update endpoint, shared by the V1 and V2 routes |
| `BebboEncoder` | Encoder | `bebbo_json` format encoding |
| `BodyImageProcessor` | Service | Presave body HTML → image URL extraction |
| `QuizAnswerItem` | Field type | Custom compound field (value + is_correct) |
| `QuizAnswerFormatter` | Field formatter | Displays quiz answers with correct indicator |
| `QuizAnswerWidget` | Field widget | Form widget for quiz answers |

### Drush Commands

| Command class | Purpose |
|---------------|---------|
| `EmbeddedImagesCommands` | Batch populate `field_embedded_images` from body |
| `BodyRenderedCommands` | Batch populate `field_body_rendered` |
| `FilePathsFixCommands` | Fix file path issues |
| `RemoveTrTranslationsCommands` | Remove Turkish translations |

### Update Hooks

- `10001`: Deletes orphaned paragraph and consumer entities
- `10002`: Migrates `field_content_toggle` value `ai_chatbot` → `chatbot`
- `10003`: Migrates `field_content_toggle` data values (`chatbot`/`Chatbot` → `ai_chatbot`, etc.) to machine names and sets the canonical field storage `allowed_values` (this reverses the intermediate `ai_chatbot` → `chatbot` of `10002`; the canonical machine value is `ai_chatbot`)

> **Supersedes** the removed `pb_custom_rest_api` (check-update), `custom_serialization` (V1 serialization), and `pb_custom_standard_deviation` (V1 standard-deviation transform) — see [Removed modules](#removed-modules).

---

## 3. `bebbo_custom_general`

**Name:** Bebbo Custom General
**Core:** `^10 || ^11`
**Dependencies:** `drupal:views`, `group`, `menu_per_role`

### Purpose

Catch-all utilities module created by decomposing the `pb_custom_form` grab-bag (P1 slices + `.module` hook slices 1–5b). Houses self-contained admin features and editorial-form helpers that have no dedicated module: Entity Share CSV export, TMGMT overview/cart UX, mobile-JS share landing pages, language/landing-page redirect management, app-store QR redirect, master-language settings, article category AJAX cascade, group-country form handling, node archive validation, node-action batch helpers, and the canonical editorial menu (export, per-site sync, and the country-users redirect).

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `bebbo_custom_general.mailer_sender_override` | `MailerSenderOverride` | Event subscriber — overrides mail sender from config |
| `bebbo_custom_general.internal_content_node_redirect` | `InternalContentNodeRedirect` | `KernelEvents::REQUEST` — redirects anonymous internal node URLs by language / site context |
| `bebbo_custom_general.editorial_menu_manager` | `EditorialMenuManager` | Exports the editorial menu to config and applies that canon to a site |

### Routes

| Path | Handler | Permission |
|------|---------|------------|
| `/admin/config/parent-buddy/mobile-javascript` | `MobileAppShareLinkForm` | `administer site configuration` |
| `/share/{param1}/{param2}/{param3}` | `PbMobile::render` | `manage mobile javascript` |
| `/foleja/share/{param1}/{param2}/{param3}` | `PbMobile::kosovorender` | `manage mobile javascript` |
| `/admin/config/parent-buddy/redirect-management` | `RedirectManagementForm` | `manage redirect settings` |
| `/admin/config/parent-buddy/admin-parent-buddy` | `SettingsForm` (master language) | `administer site configuration` |
| `/admin/config/parent-buddy/app-store-redirect` | `AppStoreRedirectForm` | `administer site configuration` |
| `/downloadapp.html` | `AppStoreRedirectController::render` | public (`_access: TRUE`) |
| `/admin/config/parent-buddy/apply-trans-related-articles-video` | `ApplyTransRelatedArticlesVideo` | `administer site configuration` |
| `/admin/content/entity_share/pull/export/csv` | `CsvExportController::download` | `entity_share_client_pull_content` |
| `/admin/content/entity_share/pull/export/csv/batch` | `CsvExportController::downloadBatch` | `entity_share_client_pull_content` |
| `/admin/content/entity_share/pull/export/csv/download` | `CsvExportController::downloadCompleted` | `entity_share_client_pull_content` |
| `/country-users` | `CountryUsersController::redirectToCountry` | `_user_is_logged_in` |

> The admin forms hang off the `pb_custom_form.admin_config_parent_buddy` menu container, and their permissions (`manage mobile javascript`, `manage redirect settings`) are still declared by `pb_custom_form`.

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_theme` | Defines `pb-mobile` and `kosovo-mobile` share-page templates |
| `hook_form_alter` | Article category→subcategory AJAX cascade; Entity Share pull CSV-download button; group-country edit form (make-available-for-mobile default + library + submit handler) |
| `hook_form_tmgmt_overview_form_alter` | TMGMT overview Node ID column + filter + sortable header |
| `hook_query_TAG_alter` (`tmgmt_entity_get_translatable_entities`) | Node ID filter/sort on the translatable-entities query |
| `hook_menu_local_tasks_alter` | Adds TMGMT Job Items/Jobs/Sources/Cart/Providers/Settings local tasks |
| `hook_menu_local_actions_alter` | Renames the "Add group" action on `/admin/group` to "Add Country" (all sites use the single `country` group type) |
| `hook_preprocess_page` | TMGMT route cache context |
| `hook_preprocess_node_add_list` | Removes hidden bundles (see helper below) from the `/node/add` content-type selection page |
| `hook_menu_links_discovered_alter` | Removes `node.add` links for hidden bundles from every Add-content menu, including the `admin_toolbar_tools` shortcuts |
| `hook_form_views_exposed_form_alter` | Removes hidden bundles from the exposed "Type" dropdown on the `content`, `global_content_listing`, and `country_content_listing` views |
| `hook_views_data_alter` (`bebbo_custom_general.views.inc`) | Adds a `title_natural` sort ("Title (language-neutral sort)") to `node_field_data` and `node_field_revision`, backed by the `bebbo_natural_title` sort plugin |
| `hook_views_query_alter` | Rewrites the Title column-header click sort on the `duplicate_of_moderated_group_relationship` view so it uses the same ordering as that sort plugin |
| `bebbo_custom_general_node_validate` (form `#validate` handler) | Attached to node edit/add forms via `hook_form_alter`; requires a revision log when a non-admin sets a node to the archive moderation state |

> **Hidden-bundle visibility:** `_bebbo_custom_general_hidden_node_types()` returns the node bundles that must never appear as standalone, selectable content in the admin UI (currently `quiz_questions`, which is authored only inline via the Quiz content type's `field_quiz_questions` inline entity form). The three hooks above consume it. This is visibility-only — no permission or access change — so inline entity form authoring is unaffected. To reveal a bundle, remove it from that helper.

> **Language-neutral title sort:** node titles are stored under `utf8mb4_general_ci`, which only folds case for ASCII — accented letters sort after Z, leading whitespace and punctuation skew the order, and digits sort ahead of letters. `NaturalTitleSort` first strips leading non-word characters with `REGEXP_REPLACE(title, '^\\W+', '')`, so a title opening with a quote (`'Pula kërcimtare'!`, `“Vendosja e alarmit”`) files under its real letter instead of bunching at the top; `\\W` is Unicode-aware here, so accented Latin and Cyrillic letters survive. It then orders that value under `utf8mb4_unicode_520_ci`, so `Ç` sorts with `C` and `Á` with `A`, and adds a leading `LEFT(…, 1) BETWEEN '0' AND '9'` key so numeric titles group at the end of an ascending list and the start of a descending one. Scripts still sort in Unicode block order (Latin, then Cyrillic, then others) — this is collation, not transliteration, so a Cyrillic title does not interleave with its Latin equivalent. The digit test avoids `REGEXP '^[0-9]'` on purpose: Drupal reads square brackets in a query string as identifier quoting, so that pattern reaches the database as `^"0-9"` and never matches. The sort is selectable in any view over nodes; `duplicate_of_moderated_group_relationship` (`/group/{id}/moderated`) uses it via its Title column, which is also that display's default sort — the table lands on Title A-Z and any column header click replaces it for that request. Note that adding it as an *exposed* sort would disable every column-header sort in a view, because core's `ExposedFormPluginBase::query()` clears the query's ORDER BY whenever the exposed form carries a `sort_by` value.

> **Dead-code note:** `bebbo_custom_general_pb_custom_field_preprocess_views_view_field()` was moved verbatim from `pb_custom_form` (slice 4/5). Its name does not match the `{module}_preprocess_{hook}` pattern, so the theme registry never registers it — it is a no-op.

### Key Classes

| Class | Type | Role |
|-------|------|------|
| `CsvExportController` | Controller | Batch CSV export from Entity Share remote pull data |
| `PbMobile` | Controller | Mobile share page + Kosovo (`foleja`) variant |
| `AppStoreRedirectController` | Controller | QR-code `/downloadapp.html` landing page for app download |
| `ChangeNodeStatus` | Batch processor | Archives node translations during country offload |
| `ApplyNodeTranslations` | Batch processor | Copies related articles/videos across node translations |
| `InternalContentNodeRedirect` | Event subscriber | Anonymous internal node → language redirect |
| `MailerSenderOverride` | Event subscriber | Mail sender override from config |
| `NaturalTitleSort` | Views sort plugin (`bebbo_natural_title`) | Language-neutral alphabetical ordering of a text column, numeric titles grouped apart |
| Form classes | Forms | `MobileAppShareLinkForm`, `RedirectManagementForm`, `SettingsForm`, `AppStoreRedirectForm`, `ApplyTransRelatedArticlesVideo` |

### Config

`bebbo_custom_general.adminsettings`, `bebbo_custom_general.app_store_redirect`, and `bebbo_custom_general.mobile_app_share_link_form` live in shared `config/sync/` (the mobile-share form is additionally overridden in the Ecuador and Turkey splits). `bebbo_custom_general.landing_pages` and `bebbo_custom_general.language_redirects` are **per-site**: each of the 7 split folders carries its own copy; neither exists in `config/sync/`. `bebbo_custom_general.editorial_menu` is shared and holds the canonical editorial menu: every link keyed by UUID with its title, URI, parent, weight, enabled state and `menu_per_role` show/hide roles. Schema in `config/schema/bebbo_custom_general.schema.yml`. None of these are in `config_ignore`.

### Drush commands

| Command | Direction | Use |
|---------|-----------|-----|
| `bebbo:menu-export` (`bme`) | database → config | Run on bebbo after editing the editorial menu, then commit the exported config file. Keeps every value of multi-value fields, unlike `menu_export`. |
| `bebbo:menu-sync` (`bms`) | config → database | Applies the canon to one site. Idempotent, `--dry-run` shows the diff. Runs per site on every deploy from `hooks/common/code-deploy.sh`. |

Menu links are content entities, so config import alone never applies a menu change. `bebbo:menu-sync` upserts by UUID, sets the `menu_per_role` roles to exactly the canonical values, and deletes editorial-menu links absent from the canon — scoped to that menu only.

### Post-Update Hooks

- `remove_defunct_modules`: uninstalls/removes modules no longer in use before config import.
- `remove_stale_rest_configs`: removes stale REST resource config entities.
- `clean_orphan_language_content`: per-site, runs on `drush updb` — discovers content in non-configured languages (Entity Share import leftovers) and triages each (remove orphan translation / delete entirely-foreign entity / re-langcode to site default). Idempotent.

---

## 4. `pb_custom_field`

**Name:** Custom Field
**Core:** `^9.2 || ^10 || ^11`

### Purpose

The largest editorial module. Controls field access, form alterations, editorial menu visibility, group membership workflows, content assignment actions, and admin route marking. Central to the multi-country editorial workflow.

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `pb_custom_field.admin_route_subscriber` | `AdminRouteSubscriber` | Marks 40+ Views/editorial routes as admin routes (for Gin theme) |
| `pb_custom_field.group_create_form_route_subscriber` | `GroupCreateFormRouteSubscriber` | Swaps group membership create controller for wizard |

### Hooks (23+)

| Hook | Behavior |
|------|----------|
| `hook_user_update` | Clears user caches on update |
| `hook_form_alter` | Language filters, field access control, validation on node/media forms |
| `hook_link_alter` | Alters editorial menu links for country users |
| `hook_preprocess_menu` | Filters editorial menu by user roles |
| `hook_preprocess_html` | Loads homepage styling, Kosovo-specific assets |
| `hook_preprocess_page` | Attaches mylib and admin_rtl libraries |
| `hook_block_access` | Restricts block visibility for non-admin users |
| `hook_views_pre_view` | View header customization, language filtering |
| `hook_views_pre_render` | Transforms force_update_check view data |
| `hook_views_pre_execute` | Column sorting conversions |
| `hook_views_query_alter` | Cross-country access, language filtering, TMGMT query mods |
| `hook_menu_local_tasks_alter` | Removes/displays tabs based on roles |
| `hook_menu_local_actions_alter` | Renames "Add member" → "Add existing member" |
| `hook_entity_operation_alter` | Controls edit/delete/translate by moderation state |
| `hook_entity_field_access` | Lets group admins set user status via group UI |
| `hook_user_login_form_submit` | Dashboard redirect after login — early-returns if user is anonymous (guards against redirect before TFA verification) |
| `hook_form_email_tfa_email_tfa_verify_login_alter` | Adds dashboard redirect submit handler to TFA OTP verification form, so user lands on `/dashboard` after successful MFA |
| `hook_form_user_login_form_alter` | Adds password reset link |
| `hook_form_user_form_alter` | Customizes user profile form |
| `hook_form_user_register_form_alter` | Group membership creation wizard, restricts role selection |
| `hook_form_user_admin_permissions_alter` | Attaches FPA Gin styling |
| `hook_content_moderation_notification_mail_data_alter` | Filters moderation emails by user language/opt-in |
| `hook_toolbar` | Adds admin UI language switcher dropdown |
| `hook_group_relation_type_alter` | Sets `entity_access=TRUE` on `group_membership` plugin |
| `hook_allowed_languages_form_media_library_*_alter` (×2) | Filters media library language dropdowns (oEmbed + upload forms) |

### Key Classes

| Class | Type | Role |
|-------|------|------|
| `GroupMembershipCreateController` | Controller | Extends `GroupRelationshipController`, uses "register" form operation (Group 3.x manage-users) |
| `BebboMembershipAccessControl` | Group relation handler | Guards uid:1 and own-account access (patch #2949408) |
| `AssigncontentStatus` | Batch helper | Batch assigns content to country languages as drafts |
| `AdminRouteSubscriber` | Route subscriber | Marks 40+ editorial routes as admin |

**VBO Action Plugins** (`src/Plugin/Action/`):

| Class | Role |
|-------|------|
| `AssigncontentAction` | Assigns content to country languages |
| `ChangedToArchiveAction` | Changes moderation state to Archive |
| `ChangedToPublishedAction` | Changes moderation state to Published |
| `ChangedToSeniorEditorAction` | Changes moderation state to Senior Editor review |
| `ChangeToSMEAction` | Changes moderation state to SME review |
| `MovefrompublishtodraftAction` | Moves from Published to Draft |
| `MovefrompublishtosenioreditorAction` | Moves from Published to Senior Editor |
| `MovefrompublishtosmeAction` | Moves from Published to SME |

**Batch Handler Classes** (`src/`):

`ChangeActionStatus`, `ChangeintoArchiveActionStatus`, `ChangeintoPublishActionStatus`, `ChangeintoSeniorEditorActionStatus`, `ChangeintoSMEActionStatus` — batch processing callbacks for the corresponding VBO actions.

### Helper Functions

- `_pb_custom_field_get_target_roles()` → `['editor', 'se', 'sme', 'reviewer']`
- `_pb_custom_field_get_trusted_roles()` → `['editor', 'se', 'sme']`
- `_pb_custom_field_get_user_groups_cached()` — Group memberships with 5min TTL cache
- `_pb_custom_field_is_multi_country_user()` — Checks multiple group memberships
- `_pb_custom_field_get_user_primary_group()` — First user's primary group
- `_pb_custom_field_get_user_group_languages()` — Language codes from primary group
- `_pb_custom_field_get_user_allowed_languages()` — From profile or groups

### Service Provider

`PbCustomFieldServiceProvider` — Decorates Group's membership access control with `BebboMembershipAccessControl`.

### Update Hooks

- `10003`: Migrates `field_content_toggle` keys from title-case to machine names

### Libraries

`mylib` (admin CSS), `admin_rtl` (RTL CSS), `toolbar_language_switcher` (JS/CSS), `homepage` (public landing pages), `homepage_grid` (Bootstrap grid), `fpa_gin_style` (Gin theme customizations)

### Tests

`MembershipEntityAccessTest` — Kernel test for group membership access control.

---

## 5. `pb_custom_form`

**Name:** Custom Form
**Core:** `^9.2 || ^10 || ^11`

### Purpose

Now a small module after most of its grab-bag was decomposed into [`bebbo_custom_general`](#3-bebbo_custom_general). Retains the **Force-Update** admin config (`CustomForm`, `ForceUpdateCheckForm`), the `forcefull_check_update_api` database table, the `/admin/config/parent_buddy` admin-menu container that the other parent-buddy admin pages hang off, and the shared permissions consumed by the moved features.

### Permissions

| Permission | Description |
|-----------|-------------|
| `manage mobile javascript` | Access mobile app share link JavaScript (used by `bebbo_custom_general` share routes) |
| `forcefull update check` | Access force update check API config |
| `manage redirect settings` | Access redirect management configuration (used by `bebbo_custom_general` redirect form) |

### Routes

| Path | Handler | Permission |
|------|---------|------------|
| `/admin/config/parent_buddy` | `SystemController::systemAdminMenuBlockPage` (menu container) | `administer site configuration` |
| `/admin/config/parent-buddy/forcefull-update-check` | `CustomForm` | `forcefull update check` |
| `/forcefull-update-check` | `ForceUpdateCheckForm` | `forcefull update check` |

### Hooks

The module-level hooks were moved to `bebbo_custom_general` / `bebbo_serializer`. The only remaining `.module` code is the helper `pb_custom_form_my_goto()` (a redirect utility used by the force-update forms).

> **Dead-code note:** `pb_custom.links.action.yml` declares an action link referencing routes `pb_custom_redirects.add` / `pb_custom_redirects.list`, which exist in no module — these action links are orphaned and non-functional.

### Database Table

**`forcefull_check_update_api`** — stores force-update flags per country per update type. Read by `bebbo_serializer`'s `CheckUpdateController`. Schema details in [`API_REFERENCE.md` §9.1](API_REFERENCE.md#91-forcefull_check_update_api-table-schema).

### Key Classes

| Class | Type | Role |
|-------|------|------|
| `CustomForm` | Form | Force-update settings admin form (writes `forcefull_check_update_api`) |
| `ForceUpdateCheckForm` | Form | Front-end force-update check form |

### Update Hooks

`70010` through `70018` — Mostly column migrations for the `forcefull_check_update_api` table (google_play_url, app_store_url, etc.). Exception: `70017` installs the `config_split` module.

---

## 6. `pb_content_analytics`

**Name:** Content Analytics
**Core:** `^9.2 || ^10 || ^11`
**Dependencies:** none declared in `.info.yml`

### Purpose

Syncs content engagement data (read counts, like counts) from Google BigQuery into Drupal node fields (`field_like_count`, `field_read_count`, `field_analytics_updated`). Provides admin reports and manual sync UI.

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `pb_content_analytics.sync_service` | `AnalyticsSyncService` | BigQuery API client, batch node field updates |
| `pb_content_analytics.feeds_import_subscriber` | `FeedsImportSubscriber` | Event subscriber for Feeds import events |

### Routes

| Path | Handler | Permission |
|------|---------|------------|
| `/admin/config/content-analytics/settings` | `ContentAnalyticsSettingsForm` | `administer content analytics settings` |
| `/admin/config/content-analytics/sync` | `ContentAnalyticsSyncForm` | `manage content analytics sync` |
| `/admin/config/content-analytics/sync/now` | `AnalyticsSyncController::syncNow` | `manage content analytics sync` |

### Permissions

| Permission | Description |
|-----------|-------------|
| `administer content analytics settings` | Configure analytics settings |
| `manage content analytics sync` | Trigger manual syncs |
| `view content analytics report` | View analytics reports |

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_cron` | Auto-syncs analytics on schedule (configurable: daily/weekly) |
| `hook_form_alter` | Disables analytics fields on node forms (read-only); adds CSV export buttons to analytics views |
| `hook_views_pre_render` | Hides body fields in reports based on filter |
| `hook_node_view` | Displays analytics counts in node view |

### Database Table

**`pb_analytics_sync_log`** — tracks sync operations (timestamp, status, count, errors).

### Config

6 install configs including Views for analytics reports and Feeds import definitions.

### Update Hooks

- `9001`, `9002`: Schema/data migrations for analytics tables

> **Note:** The V2 API emits `read_count` and `love_count` — the storage field is `field_like_count` but the API key is `love_count`.

---

## 7. `group_country_field`

**Name:** Group Country Field
**Core:** `^9.2 || ^10 || ^11`

### Purpose

Group-based Views query alteration and TMGMT form control. Ensures Views filter by group language configuration, shows latest revisions for moderated content, and restricts TMGMT moderation options by role.

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_form_alter` | Restricts TMGMT job item moderation state options by role (global_admin, editor, translator) |
| `hook_views_query_alter` | Filters views by group language (`field_language`); ensures latest revision per node/language for moderated group content; filters "recent logged in users" by group membership |
| `hook_views_pre_render` | Sets dynamic view title from group label |
| `hook_form_views_exposed_form_alter` | Populates "Updated by" filter with group members; removes locked system languages from language filters; renames date filter labels |

No services, routes, or database tables.

---

## 8. `language_visibility_control`

**Name:** Language Visibility Control
**Core:** `^10 || ^11`
**Dependencies:** `group:group`, `drupal:language`

### Purpose

Controls which languages appear in the mobile app API per country group. Adds a "Language Visibility in Mobile App" fieldset to country group forms and filters API responses accordingly.

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `language_visibility_control.service` | `LanguageVisibilityService` | Manages per-group language visibility; called inline by both serializers via `filterLanguageDataForApi()` |

> The former `ApiResponseSubscriber` (RESPONSE event, V1 country-groups post-filtering) was **removed**: `BebboV1Serializer` / `BebboSerializer` now call `filterLanguageDataForApi()` inline, so the subscriber would only double-filter.

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_form_alter` | Adds language visibility checkboxes to country group add/edit forms |
| `hook_help` | Module help text |
| Custom validate/submit | `language_visibility_control_group_form_validate`, `language_visibility_control_group_form_submit` |

### Install

Creates `field_language_visibility_in_app` (string field, unlimited cardinality, max_length 32) on the `country` group bundle.

### Key Methods (`LanguageVisibilityService`)

- `getAllGroupLanguages(Group)` — All langcodes in `field_language`
- `getVisibleLanguages(Group)` — Langcodes in `field_language_visibility_in_app`
- `filterLanguageDataForApi(array, Group)` — Filters to visible languages (falls back to all group languages if no visibility config)
- `getLanguageVisibilityStats()` — Stats across all country groups

---

## 9. `language_custom_field`

**Name:** Language Custom Field
**Core:** `^10 || ^11`
**Dependencies:** `field:field`, `field:field_ui`

### Purpose

Adds custom fields to Drupal language entities: local name, locale code, Luxon locale, and plural configuration. Stored in a custom database table.

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_form_alter` | Adds custom fields (custom_language_name_local, custom_locale, custom_luxon, custom_plural) to `language_admin_edit_form` |
| `hook_preprocess_node` | Adds `body_summary` to node template variables |
| `hook_node_view` | Adds `body_summary` to node view build |

### Database Table

**`custom_language_data`**

| Column | Type |
|--------|------|
| `id` | serial PK |
| `langcode` | varchar(12) |
| `custom_locale` | varchar(255) |
| `custom_luxon` | varchar(255) |
| `custom_plural` | varchar(255) |
| `custom_language_name_local` | varchar(255) |
| `created_date` | varchar(255), not null |

### Update Hooks

- `9001`: Adds `custom_language_name_local` column

---

## 10. `custom_article`

**Name:** Custom Article Update
**Core:** `^10 || ^11`

### Purpose

Utility module for batch content operations: lowercase keyword taxonomy terms, copy field values between translations, and Drush commands for content management.

### Routes

| Path | Handler | Permission |
|------|---------|------------|
| `/admin/content/copy-translated-field` | `CopyTranslatedField` | `administer site configuration` |
| `/admin/content/lowercase-keywords` | `LowercaseKeywordsForm` | `administer taxonomy` |

### Key Classes

| Class | Type | Role |
|-------|------|------|
| `LowercaseKeywordsForm` | Form | Batch lowercases keyword taxonomy terms |
| `CopyTranslatedField` | Form | Copies field values between node translations |
| `CustomArticleUpdate` | Drush commands | Article update operations |
| `CopyKeyword` | Drush commands | Keyword copy operations |
| `DeleteTaxonomyTermsCommands` | Drush commands | Taxonomy term deletion |

---

## 11. `file_sanitizer`

**Name:** File Sanitizer
**Core:** `^10 || ^11`

### Purpose

Sanitizes filenames on upload (transliteration, unsafe character removal, lowercasing) and provides Drush commands for scanning existing files for issues.

### Services

| Service ID | Class | Role |
|-----------|-------|------|
| `file_sanitizer.filename_sanitizer` | `FilenameSanitizer` | Filename validation and sanitization (transliteration → ASCII) |

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_file_presave` | Sanitizes filenames for NEW image uploads only (`temporary://` scheme). Skips system-generated files (`public://`, `private://`) and non-image MIME types. |

### `FilenameSanitizer` Pipeline

1. URL-decode
2. Split extension
3. Lowercase
4. Transliterate UTF-8 → ASCII
5. Replace dots with dashes
6. Replace unsafe characters with dashes
7. Collapse consecutive dashes
8. Clean extension

### Drush Commands

| Command class | Purpose |
|---------------|---------|
| `FileSanitizerCommands` | Scan cover images, detect MIME mismatches, scan body-embedded media — generates CSV reports |
| `NodeTouchCommands` | Re-save nodes to update `changed` timestamp (all translations, chunks of 50) |

---

## 12. `pb_custom_migrate`

**Name:** PB Custom Migrate (Multilingual)
**Core:** `^9.2 || ^10 || ^11`
**Dependencies:** `drupal:system`, `migrate`, `migrate_drupal`, `migrate_plus`, `migrate_tools`, `migrate_source_csv`, `node`, `taxonomy`, `content_translation`

### Purpose

CSV-based content migration definitions covering per-country, per-language content imports for activities, articles, FAQs, and other content types.

### Structure

- `config/install/migrate_plus.migration.*.yml` — migration configs
- `sources/` — Migration source CSV files and mappings
- `.module` — Empty (documentation comment only)

---

## 13. `pb_strings`

**Name:** PB Strings
**Core:** `^10 || ^11`
**Dependencies:** `drupal:taxonomy`, `drupal:content_translation`

### Purpose

Manages the `strings` taxonomy vocabulary with UI for bulk translation and enforces unique `field_unique_name` values on string terms.

### Routes

| Path | Handler | Access |
|------|---------|--------|
| `/admin/structure/taxonomy/manage/{taxonomy_vocabulary}/strings-list` | `StringsListController::listPage` | `_custom_access: StringsListController::access` |

> The route uses `_custom_access` (not `_permission`); the `access()` method enforces the `administer strings` / `translate strings` permissions internally.

### Permissions

| Permission | Description |
|-----------|-------------|
| `administer strings` | Manage string terms |
| `translate strings` | Translate string terms |

### Hooks

| Hook | Behavior |
|------|----------|
| `hook_taxonomy_term_presave` | Enforces unique `field_unique_name` across all `strings` terms |
| `hook_form_taxonomy_term_strings_form_alter` | Adds validation callback to strings term form |

### Key Classes

| Class | Type | Role |
|-------|------|------|
| `StringsListController` | Controller | List page with access check |
| `StringsFilterForm` | Form | Filter form for strings listing |
| `StringsTranslateForm` | Form | Bulk translation form |

---

## Removed modules

These modules were uninstalled and removed from the codebase; their logic lives in `bebbo_serializer` and `bebbo_custom_general`.

| Module | Replaced by |
|--------|-------------|
| `pb_custom_rest_api` | `bebbo_serializer` — force-update / check-update now served by `CheckUpdateController` at `/api/check-update/{country}` (public) and `/v2/api/check-update/{country}` (JWT-gated, `no_cache`) via `bebbo_serializer.routing.yml`. The `forcefull_check_update_api` table stays in `pb_custom_form`. |
| `custom_serialization` | `bebbo_serializer` — V1 REST serialization is now the `bebbo_v1_serializer` Views style plugin (`BebboV1Serializer`); the old `CustomSerializer` style plugin and `CustomSerializerHelper` service are gone. |
| `pb_custom_standard_deviation` | `bebbo_serializer` — the `/api/standard_deviation/%` (and V2) transform is now `transformStandardDeviation()` in the V1/V2 style plugins. |

Both V1 and V2 of the new `/api/*` and `/v2/api/*` infrastructure were validated at byte-parity with the legacy endpoints before the old modules were removed.

---

## Cross-Module Dependencies

```
bebbo_serializer ──→ language_visibility_control ──→ group (contrib)
                                                  ──→ drupal:language
                 ──→ drupal:rest, drupal:serialization
                 ──→ pb_custom_form (CheckUpdateController reads its forcefull_check_update_api table)

bebbo_custom_general ──→ pb_custom_form (admin-menu container parent + shared permissions)

bebbo_api_security ──→ drupal:key
                   ──→ drupal:views
                   ──→ view_custom_table (contrib)

pb_custom_migrate ──→ migrate, migrate_plus, migrate_tools, migrate_source_csv

pb_strings ──→ drupal:taxonomy, drupal:content_translation
language_custom_field ──→ field:field, field:field_ui
```

---

## Database Tables (custom)

| Table | Owner module | Purpose |
|-------|-------------|---------|
| `forcefull_check_update_api` | `pb_custom_form` | Force-update flags per country |
| `custom_language_data` | `language_custom_field` | Custom fields on language entities |
| `bebbo_api_devices` | `bebbo_api_security` | Registered devices |
| `bebbo_api_refresh_tokens` | `bebbo_api_security` | Hashed refresh tokens with family-based rotation |
| `bebbo_api_challenges` | `bebbo_api_security` | Single-use nonces for sideloaded verification |
| `bebbo_api_security_log` | `bebbo_api_security` | Security audit trail |
| `pb_analytics_sync_log` | `pb_content_analytics` | Analytics sync tracking |

---

## Cron Implementations

| Module | Behavior |
|--------|----------|
| `bebbo_api_security` | Purge expired challenges, revoked/expired tokens, trim security log |
| `pb_content_analytics` | Auto-sync analytics from BigQuery (configurable schedule) |

---

## Event Subscribers

| Service ID | Event | Priority | Module | Effect |
|-----------|-------|----------|--------|--------|
| `bebbo_serializer.request_format_subscriber` | `REQUEST` | 1000 | `bebbo_serializer` | Registers `bebbo_json` MIME type |
| `bebbo_api_security.request_subscriber` | `REQUEST` | 300 | `bebbo_api_security` | JWT enforcement |
| `bebbo_custom_general.internal_content_node_redirect` | `REQUEST` | — | `bebbo_custom_general` | Anonymous internal node URL redirect |
| `bebbo_custom_general.mailer_sender_override` | mail events | — | `bebbo_custom_general` | Mail sender override from config |
| `bebbo_serializer.etag_response_subscriber` | `RESPONSE` | 0 | `bebbo_serializer` | ETag/304 |
| `pb_content_analytics.feeds_import_subscriber` | Feeds events | — | `pb_content_analytics` | Feeds import integration |

---

## Related Documentation

| Topic | Document |
|-------|----------|
| REST API endpoints & response shapes | [`API_REFERENCE.md`](API_REFERENCE.md) |
| Device attestation & JWT security | [`API_SECURITY.md`](API_SECURITY.md) |
| System architecture | [`ARCHITECTURE.md`](ARCHITECTURE.md) |
