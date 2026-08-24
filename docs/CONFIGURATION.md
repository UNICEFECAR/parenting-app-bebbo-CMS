# Configuration Reference

Complete reference of the configuration stored in this Drupal 11 multisite, sourced **directly from the exported YAML in `config/sync/`** and the per-site config-split folders. Every value below was read from the actual config files — nothing is inferred from defaults or assumed.

- **Shared base config:** `config/sync/` — 1,586 files
- **Per-site overrides:** `config/{bebbo,bangla,ecuador,pakistan,somoa,turkey,zimbabwe}/`
- **Sites:** 7 (Default/Bebbo + Bangladesh, Ecuador, PK, Pacific Islands [Bebbo Pacific, dir `somoa`], Turkey, Zimbabwe)

---

## Table of Contents

1. [System Settings](#1-system-settings)
2. [Security & Access](#2-security--access)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Content Types & Fields](#4-content-types--fields)
5. [Taxonomy Vocabularies](#5-taxonomy-vocabularies)
6. [Media & Files](#6-media--files)
7. [Content Moderation Workflow](#7-content-moderation-workflow)
8. [Groups (Group 3.x)](#8-groups-group-3x)
9. [Languages & Translation](#9-languages--translation)
10. [TMGMT (Translation Management)](#10-tmgmt-translation-management)
11. [AI / OpenAI](#11-ai--openai)
12. [REST & JSON:API](#12-rest--jsonapi)
13. [Entity Share (Content Syndication)](#13-entity-share-content-syndication)
14. [Feeds & Migrations](#14-feeds--migrations)
15. [Views](#15-views)
16. [Config Split Architecture](#16-config-split-architecture)

---

## 1. System Settings

`system.site.yml` is **not** in `config/sync/` — each site provides its own via its split folder.

### Performance — `system.performance.yml`
| Setting | Value |
|---|---|
| Page cache max-age | `2764800` s (32 days) |
| CSS preprocess / gzip | `true` / `true` |
| JS preprocess / gzip | `true` / `true` |
| Fast 404 | Enabled for static files (txt, png, gif, jpg, css, js, ico, swf, flv, cgi, bat, pl, dll, exe, asp) |
| Fast 404 exclude paths | matching `/styles/` or `/imagecache/` |

### Date & Timezone — `system.date.yml`

| Setting | Value |
|---|---|
| Base country / timezone (`config/sync/`) | `CH` / `Europe/Zurich` |
| First day of week | `0` (Sunday) |
| User-configurable timezone | `true` (default `0`, no warning) |

Per-site config-split patches (`config/{folder}/config_split.patch.system.date.yml`) override country + timezone locally:

| Site | Country | Timezone |
|---|---|---|
| Bangladesh | BD | Asia/Dhaka |
| Turkey | TR | Europe/Istanbul |
| Ecuador | EC | America/Guayaquil |
| PK | PK | Asia/Karachi |
| Pacific Islands | FJ | Pacific/Fiji |
| Zimbabwe | ZW | Africa/Harare |

The timezone affects admin-UI date display only — stored values are Unix timestamps, and the API `datetime` envelope field is hardcoded to `Asia/Kolkata` (see [`API_REFERENCE.md`](API_REFERENCE.md) §2).

### Logging & Cron
| File | Setting | Value |
|---|---|---|
| `system.logging` | Error display | `hide` (no errors shown to users) |
| `system.cron` | Warning / error thresholds | `172800` s (2 d) / `1209600` s (14 d); logging `true` |
| `automated_cron.settings` | Interval | `86400` s (24 h) |
| `dblog.settings` | Row limit | `1000` |
| `syslog.settings` | Identity / Facility | `bebbo` / `128` (LOG_LOCAL0) |
| `syslog.settings` | Format | `!base_url\|!timestamp\|!type\|!ip\|!request_uri\|!referer\|!uid\|!link\|!message` |

### Mail — `system.mail.yml` + Symfony Mailer

| Setting | Value |
|---|---|
| Default interface | `php_mail` (Symfony Mailer takes over at the transport layer) |
| Mailer DSN | scheme `sendmail`, host `default` |
| Default transport | `office_365_oauth` (`symfony_mailer.settings.yml`) |
| Transport host | `smtp.office365.com:587` (TLS) |
| Transport user | `admin@bebbo.app` |
| Transport auth plugin | `office365_oauth` (`symfony_mailer_office365`) |
| OAuth credentials | Placeholders in config — real values entered per-environment via `/admin/config/system/mailer/office365`; the credential **keys** (`client_id`, `client_secret`, `tenant_id`) are protected by key-level `config_ignore` entries while the rest of the transport config stays in Git (see §16) |
| Sender override | `MailerSenderOverride` event subscriber forces From / Sender / Envelope to `admin@bebbo.app` (read from `symfony_mailer_office365.config` → `mail`); original From preserved as Reply-To. Required because O365 rejects `SendAs` for per-site addresses (e.g. `info@babuni.app`) unless explicit SendAs permissions are configured in Exchange admin. |
| Mailer policy | URL-to-absolute, inline CSS, wrap-and-convert (plain: false, swiftmailer: false), theme: `_active_fallback` |
| Test email policy | Subject `"Test email from [site:name]"`, format `email_html` |

**Background:** the `smtp` module (Basic Auth via `SMTPMailSystem`) was uninstalled on all sites because Microsoft 365 Security Defaults block Authenticated SMTP at the tenant level; the `drupal/smtp` Composer package is still declared in `composer.json` but the module is not enabled anywhere. Replaced by `drupal/symfony_mailer` + `drupal/symfony_mailer_office365` using OAuth 2.0 Authorization Code flow (delegated `SMTP.Send` permission). OAuth tokens are auto-refreshed via Drupal cron. See [`RUNBOOK.md`](RUNBOOK.md) §12 for post-deployment setup steps.

**Patch — event dispatcher injection:** The upstream `symfony_mailer_office365` module creates its SMTP transport without passing the Symfony event dispatcher, which means `MessageEvent` subscribers (including `MailerSenderOverride`) never fire. A local patch (`patches/symfony_mailer_office365-pass-event-dispatcher.patch`) injects `@event_dispatcher` into the factory service and passes it through to `EsmtpTransport`. Without this patch, all outgoing emails use the per-site `system.site.mail` address as From, which triggers O365 `SendAsDenied` errors on sites whose address differs from the authenticated mailbox (`admin@bebbo.app`). See [`DEPENDENCIES.md`](DEPENDENCIES.md) §6 for the patch entry.

### Email TFA (Multi-Factor Authentication) — `email_tfa.settings.yml`

| Setting | Value |
|---|---|
| Status | `enabled` (`status: true`) |
| Scope | `globally_enabled` (all users) |
| OTP code length | `6` digits |
| OTP timeout | `300` s (5 min) |
| Flood protection | `5` attempts per `3600` s (1 h) |
| Dev mode | `disabled` |
| Role exclusion | none (`ignore_role: {}`) |
| Email subject | "One Time Password" |
| Email body tokens | `[user:email_tfa]`, `[user:name]`, `[site:name]` |
| Log events | `disabled` |
| Routes intercepted | `email_tfa.verifiy`, `user.logout` |
| Admin settings | `/admin/config/people/email-tfa` (permission: `administer email tfa`) |
| Verification route | `/tfa/verify/{uid}/{hash}` |

After standard login, users are redirected to the OTP verification page. On successful verification, users are redirected to `/dashboard` (via custom submit handler in `pb_custom_field`). Bebbo split carries language translation overrides for `email_tfa.settings` in `ro`, `ru`, `sk`, `sq`, `sr`, `uk`.

### Admin UI
| File | Setting | Value |
|---|---|---|
| `admin_toolbar.settings` | Menu depth | `4`; hover-intent & toggle shortcut disabled |
| `admin_toolbar_tools.settings` | Max bundle number | `20`; show local tasks `false` |
| `gin.settings` (admin theme) | Accent `light_blue`, focus `gin`, toolbar `classic`, dark mode off, secondary toolbar on frontend `true`, custom favicon `favicon/icon.png` |
| `claro.settings` | Favicon | `public://bebbo_favicon_0.png` |

### Search — `search.settings.yml`
| Setting | Value |
|---|---|
| Default page | `node_search` |
| Cron limit | `100` items/run |
| Minimum word size | `3` |
| CJK overlap | `true`; logging `false` |
| Tag weights | h1:25, h2:18, h3:15, h4:14, h5:9, h6:6, a:10, u/b/i/strong/em:3 |

3 search pages: Content (node), Users, Help.

### Purge / Cache invalidation
- **Purgers:** Acquia Purge (`acquia_purge`) + Acquia Platform CDN (`acquia_platform_cdn`)
- **Processors:** drush_purge_queue_work, drush_purge_invalidate, cron, lateruntime, purge_ui_block_processor
- **Queuers:** drush_purge_queue_add, coretags, purge_ui_block_queuer
- **Tag blacklist:** `4xx-response`, `config:core.extension`, `extensions`, `config:purge`, `theme_registry`, `config:field.storage`, `route_match`, `routes`

### Text formats — `filter.format.*`
| ID | Name | Status |
|---|---|---|
| `admin_full_html` | Admin Full HTML | Active |
| `full_html` | Full HTML | Active (weight -10) |
| `plain_text` | Plain text | Active (weight -8) |
| `email_html` | Email HTML | Active (weight 10) — used by Symfony Mailer test email templates; no filters configured |
| `basic_html` | Basic HTML | **Disabled** |
| `restricted_html` | Restricted HTML | **Disabled** |

---

## 2. Security & Access

### SecKit — `seckit.settings.yml`
| Protection | Value |
|---|---|
| CSP | Enabled; `frame-ancestors 'self'`; report-uri `/report-csp-violation`; report-only `false` |
| HSTS | Enabled; max-age `63072000` (2 y); include subdomains `true`; preload `true` |
| Clickjacking | X-Frame-Options **SAMEORIGIN** (`x_frame: '1'`) |
| X-XSS-Protection | `1; mode=block` |
| CSRF origin / Expect-CT / Feature-Policy / Referrer-Policy | Disabled |

### Bebbo API Security — `bebbo_api_security.settings.yml`
| Setting | Value |
|---|---|
| Enforcement mode | **`disabled`** |
| Dev bypass | `false` |
| JWT expiry | `3600` s (1 h) |
| Refresh token expiry | `2592000` s (30 d) |
| Refresh rotation | enabled |
| Register rate limit | `10` |
| Refresh rate limit | `30` |
| Apple production mode | `true` |
| Google verdict freshness | `600` s; allow unrecognized version `false` |
| Google/Apple attestation credentials | empty (not configured) |

> `bebbo_api_security.settings` is in `config_ignore` → managed per-environment, not committed.

### Users — `user.settings.yml`
| Setting | Value |
|---|---|
| Registration | `admin_only` |
| Verify mail | `true` |
| Cancel method | `user_cancel_block` |
| Password reset timeout | `86400` s (24 h) |
| Password strength meter | `true` |

**User email notifications** — restricted to security-related email only:

| Notification | Enabled | Reason |
|---|---|---|
| `password_reset` | **true** | Security — user-initiated password recovery |
| `register_admin_created` | false | Disabled — admins set the password on new accounts and deliver it out-of-band |
| `cancel_confirm` | false | Non-essential |
| `status_activated` | false | Non-essential |
| `status_blocked` | false | Non-essential |
| `status_canceled` | false | Non-essential |
| `register_no_approval_required` | false | Not applicable (`admin_only` registration) |
| `register_pending_approval` | false | Not applicable (`admin_only` registration) |

Combined with Email TFA (§1 above) and disabled content moderation notifications (§7), the only emails users receive are: **MFA OTP codes** and **password reset links**.

Because registration is `admin_only` and no account-created mail is sent, creating a user is a two-part operation: create the account with a password set on the form, then pass the credentials to the user through a channel outside Drupal. The user changes the password after first login.

### Keys — `key.key.*`
| Key ID | Label | Provider | Source |
|---|---|---|---|
| `bebbo_google_sa_key` | Google Service Account Key (Play Integrity) | `env` | env var `BEBBO_GOOGLE_SA_KEY` (base64) |
| `bebbo_jwt_signing_key` | Bebbo JWT Signing Key (RSA) | `env` | env var `BEBBO_JWT_PRIVATE_KEY` (base64) |
| `openai_api_key` | OpenAI API Key | `config` | stored in config (value empty) |

---

## 3. User Roles & Permissions

9 global roles (`user.role.*.yml`). Permission counts are exact from the YAML.

| Role ID | Label | is_admin | # Permissions |
|---|---|---|---|
| `administrator` | Administrator | `true` | full (bypass; 0 explicit) |
| `anonymous` | Anonymous user | `false` | 4 |
| `authenticated` | Authenticated user | `false` | 25 |
| `editor` | Editor | `null` | 131 |
| `global_admin` | Global Admin | `null` | 387 |
| `reviewer` | Country Admin | `null` | 14 |
| `se` | Senior Editor | `null` | 177 |
| `sme` | SME | `null` | 70 |
| `translator` | Translator | `null` | 64 |

> ⚠️ **Naming mismatch:** role machine name `reviewer` carries the label **"Country Admin"** — legacy mismatch, not a typo to fix blindly.

**Role highlights (from actual permission lists):**
- **Anonymous** — `access content`, `manage mobile javascript`, REST GET, `view media`
- **Authenticated** — content/media access, toolbar, `full_html`, view unpublished, admin theme
- **Editor** — full content-type CRUD, media, `translate any entity`, layout builder, workflow transitions (draft→SME, draft→review_after_translation)
- **Senior Editor (`se`)** — Editor + publish/archive/reject transitions, revision revert all types, content translations
- **SME** — edit any content (no create), transitions SME→reject / SME→senior_editor / SME→SME, view unpublished
- **Country Admin (`reviewer`)** — `access content`, `access group overview`, `administer allowed languages`, entity_share client/server, user actions (add_role/block/unblock), redirect settings
- **Global Admin** — full admin: feeds (all 43 feed types), entity_share, REST, content types, nodes, all workflow transitions, layout builder, redirect, toolbar menu
- **Translator** — translate nodes/taxonomy/media, translation job mgmt, `translate interface`, transitions review_after_translation→draft/SME

---

## 4. Content Types & Fields

**18 node types** (`node.type.*`) — all with `new_revision: true`. "Submitted info" shown for all **except** Basic page.

| Machine name | Label | Preview | Submitted info |
|---|---|---|---|
| `activities` | Games | disabled | yes |
| `article` | Article | disabled | yes |
| `child_development` | Child Development - Age Periods | disabled | yes |
| `child_growth` | Child Growth - Age Periods | disabled | yes |
| `course` | Course | optional | yes |
| `courses_module` | Courses Module | optional | yes |
| `daily_homescreen_messages` | Daily Homescreen Messages | disabled | yes |
| `faq` | FAQ | disabled | yes |
| `guide` | Guide | optional | yes |
| `health_check_ups` | Health Check-ups - Age Periods | disabled | yes |
| `milestone` | Milestone | disabled | yes |
| `page` | Basic page | disabled | **no** |
| `pregnancy_weekly_overview` | Pregnancy Weekly Overview | optional | yes |
| `quiz` | Quiz | optional | yes |
| `quiz_questions` | Quiz Questions | disabled | yes |
| `survey` | Linked Pages | disabled | yes |
| `vaccinations` | Vaccinations - Age Periods | disabled | yes |
| `video_article` | Video Article | disabled | yes |

> `courses_module` and `quiz_questions` are **not** under the moderation workflow (see §7).

### Fields per content type

Cardinality `1` = single, `-1` = unlimited. Reference target shown for entity_reference fields.

#### Activities (Games)
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| feeds_item | feeds_item | -1 | No | — |
| field_activity_category | entity_reference | 1 | Yes | taxonomy_term |
| field_analytics_updated | timestamp | 1 | No | — |
| field_body_rendered | text_long | 1 | No | — |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_cover_image | entity_reference | 1 | Yes | media |
| field_embedded_images | string | -1 | No | — |
| field_equipment | boolean | 1 | No | — |
| field_licensed_content | boolean | 1 | No | — |
| field_like_count | integer | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_pre_populated | boolean | 1 | No | — |
| field_read_count | integer | 1 | No | — |
| field_related_articles | entity_reference | -1 | No | node |
| field_type_of_support | entity_reference | 1 | No | taxonomy_term |

#### Article
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| feeds_item | feeds_item | -1 | No | — |
| field_analytics_updated | timestamp | 1 | No | — |
| field_australian_article | boolean | 1 | No | — |
| field_body_rendered | text_long | 1 | No | — |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_child_gender | entity_reference | 1 | Yes | taxonomy_term |
| field_content_category | entity_reference | 1 | Yes | taxonomy_term |
| field_cover_image | entity_reference | 1 | Yes | media |
| field_do_not_feature | boolean | 1 | No | — |
| field_embedded_images | string | -1 | No | — |
| field_generic_content | boolean | 1 | No | — |
| field_keywords | entity_reference | -1 | No | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_like_count | integer | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_meta_keywords | string_long | 1 | No | — |
| field_parent_gender | entity_reference | 1 | Yes | taxonomy_term |
| field_pre_populated | boolean | 1 | No | — |
| field_premature_content | boolean | 1 | No | — |
| field_read_count | integer | 1 | No | — |
| field_references_and_comments | text_long | 1 | No | — |
| field_related_articles | entity_reference | -1 | No | node |
| field_related_video_articles | entity_reference | -1 | No | node |
| field_subcategory | entity_reference | 1 | No | taxonomy_term |
| field_target_audience | entity_reference | -1 | No | taxonomy_term |
| field_type_of_article | entity_reference | 1 | No | taxonomy_term |

#### Child Development - Age Periods
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| field_activity_child_age | entity_reference | 1 | Yes | taxonomy_term |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_home_message_after_end | decimal | 1 | No | — |
| field_home_message_after_start | decimal | 1 | No | — |
| field_home_message_before_end | decimal | 1 | No | — |
| field_home_message_before_start | decimal | 1 | No | — |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_milestone_instructions | text_long | 1 | No | — |
| field_pinned_article_for_boy | entity_reference | 1 | Yes | node |
| field_pinned_article_for_girl | entity_reference | 1 | Yes | node |
| field_pre_populated | boolean | 1 | No | — |

#### Child Growth - Age Periods
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_growth_type | entity_reference | 1 | Yes | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_pre_populated | boolean | 1 | No | — |
| field_related_articles | entity_reference | -1 | Yes | node |
| field_related_video_articles | entity_reference | -1 | No | node |
| field_standard_deviation | entity_reference | 1 | Yes | taxonomy_term |

#### Course
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| feeds_item | feeds_item | -1 | No | — |
| field_analytics_updated | timestamp | 1 | No | — |
| field_certificate | entity_reference | 1 | No | media |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_commentsand_references | text_long | 1 | No | — |
| field_course_category | entity_reference | 1 | No | taxonomy_term |
| field_course_duration | integer | 1 | No | — |
| field_course_expiry | datetime | 1 | No | — |
| field_course_link | string | 1 | No | — |
| field_course_modules | entity_reference | -1 | No | node |
| field_cover_image | entity_reference | 1 | Yes | media |
| field_description | text_long | 1 | No | — |
| field_feedback_question | string | -1 | No | — |
| field_feedback_required | boolean | 1 | No | — |
| field_final_assessment | entity_reference | 1 | No | node |
| field_licensed_content | boolean | 1 | No | — |
| field_like_count | integer | 1 | No | — |
| field_module_numbering_style | list_string | 1 | No | — |
| field_modules_unlock | boolean | 1 | No | — |
| field_number_of_modules | integer | 1 | No | — |
| field_read_count | integer | 1 | No | — |
| field_target_audience | entity_reference | -1 | No | taxonomy_term |

#### Courses Module
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| field_course_content | entity_reference | -1 | No | node |
| field_module_title | string | 1 | No | — |
| field_numbering_style | list_string | 1 | No | — |
| field_optional_module | boolean | 1 | No | — |
| field_resource_file_external | link | 1 | No | — |
| field_resource_file_internal | entity_reference | 1 | No | media |

#### Daily Homescreen Messages
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| feeds_item | feeds_item | -1 | No | — |
| field_daily_message_category | entity_reference | 1 | No | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_pre_populated | boolean | 1 | No | — |

#### FAQ
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | Yes | — |
| field_answer_part_2 | text_with_summary | 1 | No | — |
| field_chatbot_subcategory | entity_reference | 1 | Yes | taxonomy_term |
| field_child_s_age | entity_reference | -1 | No | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_pinned_article | entity_reference | 1 | No | node |
| field_pre_populated | boolean | 1 | No | — |

#### Guide
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| field_child_age_guide | entity_reference | 1 | No | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_message | text_long | 1 | No | — |
| field_related_articles | entity_reference | -1 | No | node |
| field_related_games | entity_reference | -1 | No | node |

#### Health Check-ups - Age Periods
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| feeds_item | feeds_item | -1 | No | — |
| field_growth_period | entity_reference | 1 | Yes | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_notification_from | integer | 1 | No | — |
| field_notification_to | integer | 1 | No | — |
| field_pinned_article | entity_reference | 1 | No | node |
| field_pinned_video_article | entity_reference | 1 | No | node |
| field_pre_populated | boolean | 1 | No | — |

#### Milestone
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_pre_populated | boolean | 1 | No | — |
| field_related_activities | entity_reference | 1 | No | node |
| field_related_articles | entity_reference | -1 | No | node |
| field_related_video_articles | entity_reference | -1 | No | node |

#### Basic Page
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| field_body_rendered | text_long | 1 | No | — |
| field_embedded_images | string | -1 | No | — |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_meta_tags | metatag | 1 | No | — |
| field_pre_populated | boolean | 1 | No | — |
| layout_builder__layout | layout_section | 1 | No | — |

#### Pregnancy Weekly Overview
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| field_average_height | decimal | 1 | No | — |
| field_average_weight | decimal | 1 | No | — |
| field_featured_image_1 | entity_reference | 1 | Yes | media |
| field_featured_image_2 | entity_reference | 1 | No | media |
| field_fruit | string | 1 | Yes | — |
| field_fun_fact | string | 1 | No | — |
| field_licensed_content | boolean | 1 | No | — |
| field_parental_week | list_integer | 1 | No | — |
| field_related_articles | entity_reference | -1 | No | node |

#### Quiz
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| field_instructions | string_long | 1 | No | — |
| field_licensed_content | boolean | 1 | No | — |
| field_number_of_questions | integer | 1 | No | — |
| field_passing_score | integer | 1 | No | — |
| field_quiz_questions | entity_reference | -1 | No | node |
| field_quiz_type | list_string | 1 | No | — |

#### Quiz Questions
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| field_answers | quiz_answer | -1 | No | — |
| field_explanation | string_long | 1 | No | — |
| field_question | string_long | 1 | No | — |
| field_question_image | entity_reference | 1 | No | media |
| field_question_type | list_string | 1 | No | — |

#### Survey (Linked Pages)
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| field_licensed_content | boolean | 1 | No | — |
| field_mandatory_content | boolean | 1 | Yes | — |
| field_pre_populated | boolean | 1 | No | — |
| field_survey_link | link | 1 | Yes | — |
| field_type | list_string | 1 | Yes | — |

Allowed values for `field_type` (`field.storage.node.field_type`): `survey` (Survey), `special_survey` (Special survey), `feedback` (Feedback), `user_guide` (User Guide), `donate` (Donate).

#### Vaccinations - Age Periods
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| feeds_item | feeds_item | -1 | No | — |
| field_growth_period | entity_reference | 1 | Yes | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_notification_from | integer | 1 | No | — |
| field_notification_to | integer | 1 | No | — |
| field_old_calendar | boolean | 1 | No | — |
| field_pinned_article | entity_reference | 1 | No | node |
| field_pinned_video_article | entity_reference | 1 | No | node |
| field_pre_populated | boolean | 1 | No | — |
| field_related_articles_vacci | entity_reference | -1 | No | node |
| field_vaccination_closes | integer | 1 | No | — |
| field_vaccination_opens | integer | 1 | No | — |

#### Video Article
| Field | Type | Card. | Req | Target |
|---|---|---|---|---|
| body | text_with_summary | 1 | No | — |
| feeds_item | feeds_item | -1 | No | — |
| field_analytics_updated | timestamp | 1 | No | — |
| field_australian_article | boolean | 1 | No | — |
| field_body_rendered | text_long | 1 | No | — |
| field_child_age | entity_reference | -1 | Yes | taxonomy_term |
| field_child_gender | entity_reference | 1 | Yes | taxonomy_term |
| field_content_category | entity_reference | 1 | Yes | taxonomy_term |
| field_cover_video | entity_reference | 1 | Yes | media |
| field_embedded_images | string | -1 | No | — |
| field_generic_content | boolean | 1 | No | — |
| field_keywords | entity_reference | -1 | No | taxonomy_term |
| field_licensed_content | boolean | 1 | No | — |
| field_like_count | integer | 1 | No | — |
| field_mandatory_content | boolean | 1 | No | — |
| field_meta_keywords | string_long | 1 | No | — |
| field_parent_gender | entity_reference | 1 | Yes | taxonomy_term |
| field_pre_populated | boolean | 1 | No | — |
| field_premature_content | boolean | 1 | No | — |
| field_read_count | integer | 1 | No | — |
| field_references_and_comments | text_long | 1 | No | — |
| field_related_articles | entity_reference | -1 | No | node |
| field_related_video_articles | entity_reference | -1 | No | node |
| field_seasons | entity_reference | 1 | No | taxonomy_term |
| field_target_audience | entity_reference | -1 | No | taxonomy_term |

---

## 5. Taxonomy Vocabularies

22 vocabularies (`taxonomy.vocabulary.*`).

| VID | Label | Description |
|---|---|---|
| `activity_category` | Domain of Activity | 4 child-development domains linked to Activities |
| `category` | Category | Thematic categories for all articles |
| `chatbot_category` | Chatbot Category | Used in FAQ for chatbot |
| `chatbot_child_age` | Chatbot Child Age | — |
| `chatbot_subcategory` | Chatbot Subcategory | Used in FAQ for chatbot |
| `child_age` | Child's Age | Per defined age periods |
| `child_gender` | Child's Gender | Male, Female |
| `course_category` | Course Category | Thematic categories for courses |
| `daily_home_screen_message_catego` | Daily Home Screen Message Category | Daily messages for parents |
| `growth_introductory` | Growth Introductory | Intro message about typical growth |
| `growth_period` | Age periods for vaccinations and health check-ups | — |
| `growth_type` | Growth Type | Weight for height/length; Weight for age |
| `keywords` | Keywords | Characteristic words for article content |
| `parent_gender` | Parent's Gender | Caregiver gender, tagging category |
| `relationship_to_parent` | Relationship to Child | — |
| `standard_deviation` | Standard Deviation | WHO Growth-chart values |
| `standard_deviation_category` | Standard Deviation Category | WHO Growth-chart categories |
| `strings` | Strings | Translations of v2 app strings |
| `subcategory` | Subcategory | Thematic subcategories for articles |
| `target_audience` | Target Audience | User types and contexts |
| `type_of_article` | Type of Article | Defines where article is displayed |
| `type_of_support` | Type of Support | Support type for Activities |

### Taxonomy term fields
| Vocabulary | Field | Type | Card. | Req |
|---|---|---|---|---|
| activity_category | field_unique_name | string | 1 | No |
| category | field_type_of_article | entity_reference | 1 | No |
| category | field_unique_name | string | 1 | No |
| chatbot_category | field_unique_name | string | 1 | No |
| chatbot_child_age | field_days_from / field_days_to | integer | 1 | No |
| chatbot_child_age | field_period | string | 1 | No |
| chatbot_subcategory | field_chatbot_category | entity_reference → taxonomy_term | 1 | Yes |
| chatbot_subcategory | field_unique_name | string | 1 | No |
| child_age | field_age_bracket | entity_reference | -1 | No |
| child_age | field_buffers_days | integer | 1 | No |
| child_age | field_days_from / field_days_to | integer | 1 | No |
| child_age | field_weeks_from / field_weeks_to | integer | 1 | No |
| child_age | field_unique_name | string | 1 | Yes |
| child_gender | field_unique_name | string | 1 | No |
| course_category | field_unique_name | string | 1 | No |
| growth_introductory | field_days_from / field_days_to | integer | 1 | Yes |
| growth_period | field_days_from / field_days_to | integer | 1 | No |
| growth_period | field_short_name / field_unique_name | string | 1 | No |
| growth_period | field_vaccination_opens | integer | 1 | Yes |
| growth_type | field_unique_name | string | 1 | No |
| parent_gender | field_unique_name | string | 1 | No |
| relationship_to_parent | field_unique_name | string | 1 | No |
| standard_deviation | field_child_gender / field_growth_type | entity_reference | 1 | Yes |
| standard_deviation | field_sd0, sd1, sd1neg, sd2, sd2neg, sd3, sd3neg, sd4, sd4neg | decimal | 1 | Yes |
| strings | field_unique_name | string | 1 | Yes |
| subcategory | field_category | entity_reference → taxonomy_term | 1 | Yes |
| subcategory | field_child_age | entity_reference → taxonomy_term | -1 | Yes |
| subcategory | field_unique_name | string | 1 | No |
| target_audience | field_unique_name | string | 1 | No |

> Most vocabularies also carry a `feeds_item` field (except course_category, subcategory, target_audience, type_of_article). `type_of_article` has **no configured fields** at all.

---

## 6. Media & Files

### Media types (`media.type.*`)
| Type | Label | Source | New revision |
|---|---|---|---|
| `document` | Document | file | false |
| `image` | Image | image | true |
| `remote_video` | Remote video | oembed:video | true |
| `video` | Video | video_file | true |

| Media type | Field | Type | Card. | Req |
|---|---|---|---|---|
| document | field_media_file | file | 1 | No |
| image | field_media_image | image | 1 | Yes |
| image | feeds_item | feeds_item | -1 | No |
| remote_video | field_media_oembed_video | string | 1 | Yes |
| remote_video | field_video_embed_url | video_embed_field | 1 | Yes |
| remote_video | feeds_item | feeds_item | -1 | No |
| video | field_media_video_file | file | 1 | Yes |

> `feeds_item` present only on image + remote_video (not document/video).

### File handling — `file.settings.yml`
| Setting | Value |
|---|---|
| Make unused files temporary | `false` |
| Filename sanitization | all disabled (transliterate, replace whitespace, replace non-alphanumeric, deduplicate separators, lowercase = `false`) |
| Replacement character | `-` |
| `file_mdm` cache | enabled, expiration `172800` s (2 d), missing-file log level `3` (warning) |

### Image styles (`image.style.*`)
| Style | Effect | Dimensions | Pipeline |
|---|---|---|---|
| `content_1200xh_` | image_scale | 1200×auto | `webp_compression` |
| `large` | image_scale | 480×480 | — |
| `media_library` | image_scale | 220×220 | — |
| `medium` | image_scale | 220×220 | — |
| `thumbnail` | image_scale | 100×100 | — |

No style has `upscale: true`.

### Image optimization (`imageapi_optimize.pipeline.*`)
- **`local_binaries`** — advdef → advpng → jfifremove → jpegoptim → jpegtran → optipng (level 5) → pngcrush → pngout → pngquant (quality 90–99, speed 3)
- **`webp_compression`** — webp (quality 80) → jpegtran (progressive) → pngcrush

### Other media/image settings
| File | Settings |
|---|---|
| `imagemagick.settings` | quality 85, binaries `imagemagick`, v6, sRGB; enabled PNG/JPEG/GIF/WEBP; disabled SVG/AVIF/TIFF/PDF/HEIC/BMP/PSD/WBMP/XBM/ICO |
| `webp.settings` | quality 85 |
| `sophron.settings` | map option 0, empty map class/commands |

### Block types
| ID | Label | Revision | Description |
|---|---|---|---|
| `basic` | Basic block | false | Title + body |

### Layout Builder styles (`layout_builder_styles.style.*`)
| ID | Label | CSS class | Type |
|---|---|---|---|
| `container` | container | `container` | section |
| `homepage1`–`homepage7` | Homepage1–7 | `homepageblock1`–`homepageblock7` | component |

### Menus (`system.menu.*`)
`account`, `admin`, `editorial-menu`, `footer`, `main`, `quick-links`, `tools`.

### Metatag defaults (`metatag.metatag_defaults.*`)
9 defaults: `403`, `404`, `front`, `global`, `node`, `node__article`, `node__page`, `taxonomy_term`, `user`. Global description/keywords describe the UNICEF parenting app; node title pattern `[node:title] | [site:name]`.

### Pathauto — `pathauto.settings.yml`
| Setting | Value |
|---|---|
| Separator | `-` |
| Max length / component length | `100` / `100` |
| Transliterate | `true` |
| Reduce ASCII | `false` |
| Case | `true` (lowercase) |
| Update action | `2` (create new, keep old) |

**Pattern:** `basic_pages_alias` → `/[node:title]` for `page` (weight -5, **disabled**).

---

## 7. Content Moderation Workflow

One workflow: **`group_workflow`** (`workflows.workflow.group_workflow.yml`), type `content_moderation`.

### 7 states
| State | Label | Published | Default revision |
|---|---|---|---|
| `draft` | Draft | No | No |
| `sme_review` | SME Review | No | No |
| `senior_editor_review` | Senior Editor Review | No | No |
| `reject` | Require Modification | No | No |
| `published` | Published | **Yes** | **Yes** |
| `archive` | Archived | No | No |
| `review_after_translation` | Review_after_translation | No | No |

Default moderation state: **draft**.

### 29 transitions
| Label | From → To |
|---|---|
| Draft to Draft | draft → draft |
| Draft to SME Review | draft → sme_review |
| Draft to Senior Editor Review | draft → senior_editor_review |
| Draft to Published | draft → published |
| Draft to Review after translation | draft → review_after_translation |
| SME Review to SME Review | sme_review → sme_review |
| SME Review to Senior Editor Review | sme_review → senior_editor_review |
| SME Review to Require Modification | sme_review → reject |
| SME Review to Review_after_Translation | sme_review → review_after_translation |
| Senior Editor Review to Published | senior_editor_review → published |
| Senior Editor Review to Require Modification | senior_editor_review → reject |
| Senior Editor Review to Draft | senior_editor_review → draft |
| Senior Editor Review to SME Review | senior_editor_review → sme_review |
| Senior Editor Review to Review After Translation | senior_editor_review → review_after_translation |
| Require Modification to draft | reject → draft |
| Require Modification to Senior Editor Review | reject → senior_editor_review |
| Require Modification to SME Review | reject → sme_review |
| Require Modification to Published | reject → published |
| Require Modification to Review_after_translation | reject → review_after_translation |
| Publish to Draft | published → draft |
| Published to Archive | published → archive |
| Published to Published | published → published |
| Published to Review_after_translation | published → review_after_translation |
| Archive to Draft | archive → draft |
| Archive to Review_after_translation | archive → review_after_translation |
| Review_after_translation to Draft | review_after_translation → draft |
| Review_after_translation to Senior Editor Review | review_after_translation → senior_editor_review |
| Review_after_translation to SME Review | review_after_translation → sme_review |
| Review_after_translation to Review_after_translation | review_after_translation → review_after_translation |

**Applies to 16 node types:** activities, article, child_development, child_growth, course, daily_homescreen_messages, faq, guide, health_check_ups, milestone, page, pregnancy_weekly_overview, quiz, survey, vaccinations, video_article.

### Moderation notifications (`content_moderation_notifications.*` — 7)
| ID | Trigger | Notifies | Status |
|---|---|---|---|
| `draft_to_sme` | Draft → SME Review | SME | **Disabled** |
| `draft_to_sr_editor` | Draft → Sr. Editor Review | Senior Editor | **Disabled** |
| `sme_review_to_sr_editor_review_` | SME Review → Sr. Editor Review | Senior Editor | **Disabled** |
| `sme_to_require_modification` | SME Review → Require Modification | Editor | **Disabled** |
| `sr_editor_review_to_require_modifications_` | Sr. Editor Review → Require Modifications | Editor | **Disabled** |
| `master_any_state_to_review_after_translation` | Any → Review after translation | Editor, SE | **Disabled** |
| `master_senior_editor_publish` | Sr. Editor / Draft → Published | Editor, SE | **Disabled** |

All notify the role only (author + site_mail notifications off).

---

## 8. Groups (Group 3.x)

### Group type: `country` (`group.type.country`)
| Setting | Value |
|---|---|
| New revision | `false` |
| Creator membership | `false` |
| Creator wizard | `false` |
| Creator roles | `country-admin` |

`group.settings.yml`: `use_admin_theme: true`.

### Group relationship types
| ID | Group type | Plugin | Group card. | Entity card. |
|---|---|---|---|---|
| `country-group_membership` | country | group_membership | 0 (unlimited) | 1 |

### Group fields (country)
| Field | Type | Card. | Req |
|---|---|---|---|
| field_2_0_branding | entity_reference (media) | 1 | No |
| field_app_name | list_string | 1 | No |
| field_content_toggle | list_string | -1 | No |
| field_country_email | email | 1 | Yes |
| field_country_flag | entity_reference (media) | 1 | No |
| field_country_national_partner | entity_reference (media) | 1 | No |
| field_country_sponsor_logo | entity_reference (media) | 1 | No |
| field_language | list_string | -1 | Yes |
| field_language_name_in_local | string | 1 | No |
| field_language_visibility_in_app | string | -1 | No |
| field_make_available_for_mobile | boolean | 1 | No |
| field_master_language | language_field | -1 | No |
| field_unicef_logo | entity_reference (media) | 1 | No |

### Group roles (8)
| ID | Label | Scope | Admin | Global role | Weight |
|---|---|---|---|---|---|
| `country-administrator` | Site Administrator | outsider | **true** | administrator | -1 |
| `country-anonymous` | Anonymous | outsider | false | anonymous | -102 |
| `country-outsider` | Outsider | outsider | false | authenticated | -101 |
| `country-member` | Member | insider | false | authenticated | -100 |
| `country-admin` | Country Admin | individual | false | — | 100 |
| `country-editor` | Editor | individual | false | — | 101 |
| `country-sme` | SME | individual | false | — | 102 |
| `country-senior_editor` | Senior Editor | individual | false | — | 104 |

**Permissions by role (from YAML):**
- **Site Administrator** — `admin: true` (full bypass, maps to global `administrator`)
- **Anonymous** — `view group`
- **Outsider** — `view group` + view all 12 published group_node types
- **Member** — `access group_node overview`, `view group_membership content` ⚠️, `view latest version`
- **Country Admin** — `access content overview`, `access group_node overview`, `administer members`, `update own group_membership relationship`, `view group_membership relationship`, view unpublished (12 types)
- **Editor** — create+entity for 10 types, update any/own for 12 types, editorial + group_workflow transitions, `view latest version`, view unpublished
- **SME** — update any for 12 types, transitions SME→require-modification / SME→senior-editor / SME→reviewer, `view latest version`, view unpublished
- **Senior Editor** — create+entity for 10 types, update any/own for 12 types, approval/publish/archive/reject/review-routing transitions, view unpublished

> ⚠️ **Legacy permission string:** `country-member` carries `view group_membership content` — a Group 1.x-era permission name (the Group 3.x equivalent is `view group_membership relationship`). This is the string committed in the YAML; do not "correct" it without re-verifying the member role's access.

### Groups & app names per site (DB-verified 2026-06-29)

Each site holds one or more `country` group entities. The **group id is the "country ID"** consumed by the API: it is the `{country}` argument in `/api/check-update/{country}` (and the V2 variant) and the country whose content the `country-groups` / `country_listing` view serves. The **app name** comes from the group's `field_app_name` field. Group ids are local to each site's database (they restart at 1 on the country sites).

**Default (Bebbo) — 18 groups** (there is **no** group `156`/Türkiye on the default site; Türkiye is the separate `turkey` site):

| Group ID | Group | app_name |
|---|---|---|
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

**Country sites** (each group id is local to its own site's DB):

| Site | Group ID | Group | app_name |
|---|---|---|---|
| Bangladesh | 1 | Bangladesh | Babuni |
| Turkey | 1 | Türkiye | merhababebek |
| Ecuador | 1 | Ecuador | Wawamor |
| PK | 1 | Pakistan | pakistan |
| Pacific Islands (somoa) | 1 | Samoa | BebboPacific |
| Pacific Islands (somoa) | 6 | Fiji | BebboPacific |
| Zimbabwe | 1 | Zimbabwe | reraiumntwana |

---

## 9. Languages & Translation

### Base languages (`config/sync/`)
| ID | Label | Direction | Weight | Locked |
|---|---|---|---|---|
| `en` | English | LTR | -15 | false |
| `und` | Not specified | LTR | 30 | true |
| `zxx` | Not applicable | LTR | 31 | true |

### Per-site languages (defined in each split folder)
| Site | Language codes |
|---|---|
| Bebbo (default) | al-sq, bg-bg, by-be, by-ru, gr-el, kg-ky, kg-ru, md-ro, me-cnr, mk-mk, mk-sq, ro, ro-ro, rs-en, rs-sr, ru, sk, sq, sr, tj-ru, tj-tg, uk, uz-kaa, uz-ru, uz-uz, xk-rs, xk-sq (27) |
| Bangladesh | bn |
| Ecuador | ec-es, es |
| PK | ur |
| Pacific Islands (Bebbo Pacific) | fj-en, fj-fj, ws-en, ws-sm |
| Turkey | tr |
| Zimbabwe | zw-en, zw-nd, zw-sn |

### Per-site enabled languages (DB-verified 2026-06-29 — total 46)

This table reflects the languages **actually enabled in each site's live database** (includes the shared base `en`), and is the authoritative per-site count. It can differ from the config-folder list above, which may carry staged or inactive language entities.

| Site | Count | Languages (code — label) |
|---|---|---|
| Default (Bebbo) | 28 | en English · ru Russian · sq Albanian · al-sq Albania-Albanian · by-be Belarus-Belarusian · by-ru Belarus-Russian · bg-bg Bulgaria-Bulgarian · gr-el Greek · xk-sq Kosovo-Albanian · xk-rs Kosovo-Serbian · kg-ky Kyrgyzstan-Kyrgyz · kg-ru Kyrgyzstan-Russian · md-ro Moldova-Romanian · me-cnr Montenegro-Montenegrin · mk-mk North Macedonia-Macedonian · mk-sq North Macedonia-Albanian · ro Romanian · ro-ro Romania-Romanian · sr Serbian · rs-sr Serbia-Serbian · rs-en Serbia-English · sk Slovak · tj-tg Tajikistan-Tajik · tj-ru Tajikistan-Russian · uk Ukrainian · uz-uz Uzbekistan-Uzbek · uz-ru Uzbekistan-Russian · uz-kaa Uzbekistan-Karakalpak |
| Bangladesh | 2 | en English · bn Bengali |
| Turkey | 2 | en English · tr Turkish |
| Ecuador | 3 | en English · es Spanish · ec-es Ecuador-Spanish |
| PK | 2 | en English · ur Urdu |
| Pacific Islands (somoa) | 5 | en Global English · fj-fj Fijian · fj-en Fiji-English · ws-sm Samoan · ws-en Samoa-English |
| Zimbabwe | 4 | en Global English · zw-en Zimbabwe-English · zw-sn Zimbabwe-Shona · zw-nd Zimbabwe-Ndebele |

> Zimbabwe has **4** enabled languages including `zw-sn` Zimbabwe-Shona.

### Negotiation & locale
| File | Setting | Value |
|---|---|---|
| `language.negotiation` | URL source | `path_prefix`; session param `language`; selected langcode `site_default` |
| `language.types` | Configurable | `language_interface`; URL = `language-url` + fallback |
| `locale.settings` | Cache strings | `true`; translate English `false`; source `remote_and_local`; overwrite customized `false`, not-customized `true`; update interval `0` (disabled); import enabled `true` |

---

## 10. TMGMT (Translation Management)

### `tmgmt.settings.yml`
| Setting | Value |
|---|---|
| Quick checkout | `false` |
| Anonymous access | `true` |
| Purge finished | `_never` |
| Respect text format | `true` |
| Word count exclude tags | `true` |
| Source list limit | `50` |
| Submit job item on cron | `false` |
| Job items cron limit | `50` |

| File | Settings |
|---|---|
| `tmgmt_content.settings` | embedded_fields empty; default_moderation_states `group_workflow: ''` |
| `tmgmt_local.settings` | use_admin_theme `true`; allow_all `false` |
| `tmgmt_memsource.settings` | debug `true`; token is empty in config, and `tmgmt_memsource.settings:memsource_token` is a key-level `config_ignore` entry so each environment keeps its own |

### Translators
Eight translator entities live in shared `config/sync/`: `open_ai` (weight −10), `deepl_pro` (−9), `google` (−8), `microsoft` (−7), `memsource` (−6), `file` (−5), `deepl_free` (−4), `local` (−3). That weight order is the provider order every site shows.

`remote_languages_mappings` is **empty in the shared base** and supplied per site by config-split partial patches (`config/{folder}/config_split.patch.tmgmt.translator.{id}.yml`), because each site serves a different language set. Patch counts: bebbo 6, bangla 4, turkey 6, ecuador 6, pakistan 2, somoa 4, zimbabwe 5 — one per translator that has mappings on that site. `file` and `local` hold no mappings anywhere. Each patch maps exactly the languages that site has installed and nothing else: bebbo its 28, bangla `en`/`bn`, turkey `en`/`tr`, ecuador `en`/`es`/`ec-es`, pakistan `en`/`ur`, somoa `en`/`fj-en`/`fj-fj`/`ws-en`/`ws-sm`, zimbabwe `en`/`zw-en`/`zw-nd`/`zw-sn`. A language the provider does not support carries an empty value rather than being omitted.

Credential fields (`api_key`, `auth_key`, `memsource_user_name`, `memsource_password`) are empty in every committed file and protected by key-level `config_ignore` entries, so an import cannot overwrite a live key. Ecuador additionally pins `open_ai` `settings.tokenizer_model` to `openai__gpt-5.4-nano-2026-03-17` through its patch; the shared base is `openai__gpt-4.1-mini`.

> Before the first `cim` in any environment, run `scripts/tmgmt-translator-uuid-align.php` once per site. A uuid mismatch makes Drupal delete and recreate the entity, and the delete cascades through that site's TMGMT jobs.

---

## 11. AI / OpenAI

`ai.settings`, `ai_provider_openai.settings`, `ai_translate.settings` and the prompt definitions all live in **shared `config/sync/`**. `ai_translate.settings` is *additionally* overridden per-site in the 6 non-default split folders (bangla, ecuador, pakistan, somoa, turkey, zimbabwe); the other two are shared-only.

### `ai.settings.yml` — provider/model per capability
| Capability | Provider | Model |
|---|---|---|
| Chat | OpenAI | gpt-4.1-mini |
| Chat (complex JSON) | OpenAI | gpt-4.1-mini |
| Chat (image vision) | OpenAI | gpt-5.2 |
| Chat (structured response) | OpenAI | gpt-5.2 |
| Chat (tools) | OpenAI | gpt-5.2 |
| Embeddings | OpenAI | text-embedding-3-small |
| Moderation | OpenAI | omni-moderation-latest |
| Speech to text | OpenAI | whisper-1 |
| Text to image | OpenAI | gpt-image-1 |
| Text to speech | OpenAI | tts-1-hd |
| Translate text | Chat Translation | gpt-4.1-mini |

Request timeout 60 s; prompt logging disabled.

### `ai_translate.settings.yml`
`use_ai_translate: true`, default prompt `ai_translate__ai_translate_default`, `entity_reference_depth: 1`, `translation_status: create_draft`, `redirect_after_create: list`, **28** language codes in `language_settings` (all default prompt, no per-language overrides).

### Prompts (in sync)
- `ai.ai_prompt_type.ai_translate` — variables: sourceLang, sourceLangName, destLang, destLangName, inputText
- `ai.ai_prompt.ai_translate__ai_translate_default` — translate while preserving HTML, translate alt/title/placeholder attributes, handle LTR↔RTL, anti-prompt-injection guard
- `ai.external_moderation` — present (default)

---

## 12. REST & JSON:API

### JSON:API
`jsonapi.settings.yml` holds **only**: `read_only: true`, `maintenance_header_retry_seconds: {min: 5, max: 10}`.

The path/count/disable settings actually live in **`jsonapi_extras.settings.yml`**:
| Setting | Value | File |
|---|---|---|
| Read-only | `true` | jsonapi.settings |
| Path prefix | `jsonapi` | jsonapi_extras.settings |
| Include count | `true` | jsonapi_extras.settings |
| Default disabled | `false` | jsonapi_extras.settings |
| Validate config integrity | `false` | jsonapi_extras.settings |

### Force-update / check-update routes

The force-update check is served by `CheckUpdateController` in `bebbo_serializer`, defined in `bebbo_serializer.routing.yml` (not REST resource config). The earlier `rest.resource.custom_rest_resource` / `rest.resource.v2_custom_rest_resource` configs and the `pb_custom_rest_api` module were removed.

**`bebbo_serializer.v1_check_update`** — V1 force-update check

| Setting | Value |
|---|---|
| Controller | `CheckUpdateController::checkUpdate` |
| Path | `/api/check-update/{country}` |
| Methods | GET |
| Access | `_access: 'TRUE'` (public) |

**`bebbo_serializer.v2_check_update`** — V2 force-update check

| Setting | Value |
|---|---|
| Controller | `CheckUpdateController::checkUpdate` (same method as V1) |
| Path | `/v2/api/check-update/{country}` |
| Methods | GET |
| Access | `_access: 'TRUE'`; JWT-gated by `bebbo_api_security` via the `/v2/api/` protected pattern; `no_cache: TRUE` |

Both routes call the same controller method — identical response, different URL path (the V2 path adds JWT enforcement).

---

## 13. Entity Share (Content Syndication)

Entity Share configuration is **standardized across all 7 sites** and lives entirely in shared `config/sync/` — no split folder carries any channel or remote YAML.

**42 server channels** (`entity_share_server.channel.*`) in shared sync, by `channel_entity_type`: **20 node, 3 media, 19 taxonomy_term**. Pattern `{type}_{entity}_{language}`; all use `access_by_permission: true`, authorized role `administrator` + 3 authorized user UUIDs, `maxsize: 50`.

### Node channels (20)
content_article_mandatory_en, content_article_nonmandatory_en_, content_article_nonm_health_en, content_article_nonm_nutrition_en, content_article_nonm_parenting_corner_en, content_article_nonm_play_en_, content_article_nonm_responsive_parenting_en, content_article_nonm_safety_en, content_article_pregnancy_en, content_basic_page_en, content_child_development_age_periods, content_child_growth_en, content_daily_homescreen_messages_en, content_faq_en, content_games_en, content_health_check_ups, content_linked_pages_en, content_milestone_en, content_vaccinations_age_en, content_video_article_en.

### Media channels (3)
media_image_en, media_remote_video_en, media_video_en.

### Taxonomy channels (19)
taxonomy_age_periods_for_vacc_en, taxonomy_category_en, taxonomy_chatbot_category_en, taxonomy_chatbot_child_age_en, taxonomy_chatbot_child_age_tr, taxonomy_chatbot_subcategory_en_, taxonomy_child_s_age_en, taxonomy_child_s_gender_en, taxonomy_domain_of_activity_en, taxonomy_growth_introductory_en, taxonomy_growth_type_en, taxonomy_keywords_en, taxonomy_parent_s_gender_en, taxonomy_relationship_to_child_en, taxonomy_standard_deviation_category_en, taxonomy_standard_deviation_en, taxonomy_subcategory_en, taxonomy_type_of_article_en, taxonomy_type_of_support_en_.

### Client remotes (2)
`entity_share_client.remote.prod` and `entity_share_client.remote.stage` are committed in `config/sync/` (shared by all sites). The Basic-Auth credential is not committed: `key.key.entity_share_basic_auth` has its `key_value` protected by a key-level `config_ignore` entry (see §16).

`entity_share_diff.settings`: lines_leading `100`, lines_trailing `100`.

---

## 14. Feeds & Migrations

### Feeds — 43 feed types (`feeds.feed_type.*`)
All use `parser: csv`, `fetcher: upload`.

- **Content (7):** analytics_import_activities, analytics_import_course, analytics_import_video_article, content_analytics_import, content_health_check_up_language, content_homescreen_messages, content_vaccination_languages → processor `entity:node`
- **Media (2):** media_image_import, media_remote_video → processor `entity:media`
- **User (1):** user_import → processor `entity:user`
- **Taxonomy (33):** each vocabulary has a base + language-variant feed (age_period, categories, chatbot_category, chatbot_child_age, chatbot_subcategory, child_age, child_gender, domain, growth_introductory, growth_period, growth_type, keywords, parents_gender, standard_deviation, strings, type_of_support, relationship_to_child)

### Migrations — 207 (`migrate_plus.migration.*`)
All `source: csv` (sources in `modules/custom/pb_custom_migrate/sources/`). Organized by content type × country/language.

Content types migrated: activity, article, basic_page, child_development, child_growth, homescreen, milestone, vaccinations, video_article (English base + country variants).

Country coverage (approx counts): Albania 45, Serbia 36, Greece 27 (3 languages: Greek/Arabic/Persian), Belarus 27, Kosovo/Kyrgyzstan/Uzbekistan/Tajikistan/Bulgaria 18 each, North Macedonia 17, Montenegro 9.

---

## 15. Views

**68 views** (`views.view.*`). Machine name → label → base table:

| Machine name | Label | Base table |
|---|---|---|
| archive | Archive | node_field_data |
| articlescontentlist | ArticlesContentList | node_field_data |
| bebbo_api_challenges | Challenges | bebbo_api_challenges |
| bebbo_api_devices | Registered Devices | bebbo_api_devices |
| bebbo_api_refresh_tokens | Refresh Tokens | bebbo_api_refresh_tokens |
| bebbo_api_security_log | Security Log | bebbo_api_security_log |
| bebbo_v1_apis | Bebbo v1 APIs | node_field_data |
| bebbo_v2_apis | Bebbo v2 APIs | node_field_data |
| block_content | Content blocks | block_content_field_data |
| child_growth_reports | Child Growth Reports | node_field_data |
| content | Content | node_field_data |
| content_analytics | Content Analytics | node_field_data |
| content_analytics_sync_log | Content Analytics Sync Log | pb_analytics_sync_log |
| content_export_csv | Content Export CSV | node_field_data |
| content_listing | Content Listing | node_field_data |
| content_recent | Recent content | node_field_data |
| country_content_listing | Country Content Listing | node_field_data |
| country_listing | Country listing | groups_field_data |
| country_reports | Country Reports | node_field_data |
| dashboard_recent_content | dashboard_recent_content | node_field_data |
| duplicate_of_moderated_group_relationship | Moderated group content | node_field_revision |
| editor_review_pending | Editor Review Pending | node_field_data |
| entity_share_client_entity_import_status | (import status) | entity_import_status |
| feeds_feed | Feeds | feeds_feed |
| files | Files | file_managed |
| filter_by_published_content | Filter by Published Content | node_field_data |
| force_update_check | force-update-check | forcefull_check_update_api |
| frontpage | Frontpage | node_field_data |
| global_content_listing | Global Content Listing | node_field_data |
| global_content_listing_country_users | Global Content Listing Country Users | node_field_data |
| global_recent_logged_in_users | Global Recent Logged in Users | users_field_data |
| global_reports | Global Reports | node_field_data |
| glossary | Glossary | node_field_data |
| group_members | Group members | group_relationship_field_data |
| keyword_term_count | Keyword term count | taxonomy_term_field_data |
| media | Media | media_field_data |
| media_details | Media Details | media_field_data |
| media_library | Media library | media_field_data |
| moderated_content | Moderated content | node_field_revision |
| my_language_content | my_language_content | node_field_data |
| recent_country_content | recent_country_content | node_field_data |
| recent_global_content | recent_global_content | node_field_data |
| recent_logged_in_users | recent_logged_in_users | users_field_data |
| recent_users | Recent Users | users_field_data |
| review_pending | Review Pending | node_field_data |
| senior_editor_review_pending | Senior Editor Review Pending | node_field_data |
| sme_review_pending | SME Review Pending | node_field_data |
| sponsors_list | Sponsors List | groups_field_data |
| standard_deviation_page | Standard Deviation | taxonomy_term_field_data |
| tax | Taxonomy, Vocabulary & Strings APIs | taxonomy_term_field_data |
| taxonomy_export | Taxonomy Export | taxonomy_term_field_data |
| taxonomy_export_standard_deviation | Taxonomy Export - Standard Deviation | taxonomy_term_field_data |
| taxonomy_term | Taxonomy term | node_field_data |
| tmgmt_job_items | Translation Job Items | tmgmt_job_item |
| tmgmt_job_messages | Translation Job messages | tmgmt_message |
| tmgmt_job_overview | Job overview | tmgmt_job |
| tmgmt_local_manage_translate_task | Manage Tasks | tmgmt_local_task |
| tmgmt_local_task_items | Translation Task Items | tmgmt_local_task_item |
| tmgmt_local_task_overview | Translation Local Task Overview | tmgmt_local_task |
| tmgmt_translation_all_job_items | Translation All Job Items | tmgmt_job_item |
| top_5_contents | Top 5 Contents | node_field_data |
| translation_review_pending | translation_review_pending | tmgmt_job |
| user_admin_people | People | users_field_data |
| users_list | Users List | users_field_data |
| users_reports | Users Reports | users_field_data |
| watchdog | Watchdog | watchdog |
| who_s_new | Who's new | users_field_data |
| who_s_online | Who's online block | users_field_data |

**Custom base tables** (from custom modules): `bebbo_api_challenges`, `bebbo_api_devices`, `bebbo_api_refresh_tokens`, `bebbo_api_security_log`, `pb_analytics_sync_log`, `forcefull_check_update_api`, `entity_import_status`.

> The `tax` view (machine name `tax`) is labelled "Taxonomy, Vocabulary & Strings APIs"; it serves the V1 `api/taxonomies/%/%`, `api/strings/%`, `api/vocabularies/%` and V2 `v2/api/...` displays.

> The user listings `user_admin_people` (display `page_2`, path `users`) and `users_list` (display `page_2`, path `users/country`) show group membership through the `pb_custom_field` Views plugins `pb_user_groups`, `pb_user_group_label` and `pb_user_group_id` rather than a `group_relationship` relationship, so each user occupies exactly one row regardless of how many groups — or group translations — they have. `users_reports` still uses the relationship and lists a user once per group.

---

## 16. Config Split Architecture

7 splits (`config_split.config_split.*`). All `status: false`, `weight: 0`, `stackable: false`, `no_patching: false`, `storage: folder`. Activated at runtime by `site.splits.php`.

| Split ID | Label | Folder | Override files |
|---|---|---|---|
| `bebbo_site` | Bebbo Site | `../config/bebbo` | 35 |
| `bangladesh_site` | Bangladesh Site | `../config/bangla` | 144 |
| `ecuador_site` | Ecuador Site | `../config/ecuador` | 93 |
| `pakistan_site` | Pakistan Site | `../config/pakistan` | 70 |
| `somoa_site` | Somoa Site | `../config/somoa` | 75 |
| `turkey_site` | Turkey Site | `../config/turkey` | 129 |
| `zimbabwe_site` | Zimbabwe Site | `../config/zimbabwe` | 53 |

> **No split declares any extra `module:` or `theme:`** (verified: every split's `module:` and `theme:` field is `{ }`). All per-site customization is currently done via complete_list / partial_list config overrides.

> **Module-installation rule (non-negotiable):** `core.extension.yml` lives **exclusively** in `config/sync/` and is the single source of truth for all 7 sites. **Never** add `core.extension` to a split's `complete_list` and **never** create `core.extension.yml` in a split folder. If a site needs a module the others don't, declare it in the `module:` field of that site's `config_split.config_split.{site}_site.yml`, then place that module's config YAML under the site's folder (`config/{folder}/`) and run `drush cim -y` on that site only.

**Override categories per site:**
- **All sites:** block, language entities, `mobile_app_links.android_packages` + `.ios`, `bebbo_custom_general.landing_pages` / `.language_redirects`, system.site
- **ai_translate:** all 6 non-default sites
- **Field overrides:** bangla, pakistan, somoa, turkey
- **workflows.workflow.group_workflow:** somoa, turkey, zimbabwe (complete_list)
- **`config_split.patch.system.date.yml`:** the 6 country sites override the base timezone/country (see §1)
- **`views.view.bebbo_v1_apis`:** per-site split patches override the "Pregnancy" `child_age` TID used by the V1 articles/taxonomies pregnancy filter, since the TID differs per site
- **Entity Share:** no split carries channel or remote YAML — all Entity Share config is shared in `config/sync/` (see §13)

> Operator-facing guidance for the values these ignores protect — AI keys, mailer credentials, the Entity Share key, analytics endpoint, API-security environment variables — is in [POST_SETUP_CONFIGURATION.md](POST_SETUP_CONFIGURATION.md).

### `config_ignore.settings.yml` — never imported/exported

Whole-entity ignores: `admin_toolbar.settings`, `bebbo_api_security.settings`, `mobile_app_links.android_packages`, `mobile_app_links.ios`, `pb_content_analytics.settings`, `purge.logger_channels`, `views.view.entity_share_client_entity_import_status`.

Key-level ignores (only the named key is environment-managed; the rest of the entity stays in Git):
- `key.key.entity_share_basic_auth:key_provider_settings.key_value`
- `symfony_mailer.mailer_transport.office_365_oauth:configuration.client_id` / `:configuration.client_secret` / `:configuration.tenant_id`
- `symfony_mailer_office365.config:client_id` / `:client_secret` / `:tenant_id`
- `tmgmt.translator.deepl_free:settings.auth_key` / `tmgmt.translator.deepl_pro:settings.auth_key`
- `tmgmt.translator.google:settings.api_key` / `tmgmt.translator.microsoft:settings.api_key`
- `tmgmt.translator.memsource:settings.memsource_user_name` / `:settings.memsource_password`
- `tmgmt_memsource.settings:memsource_token`

### Custom-module configs in sync
- `bebbo_custom_general.adminsettings` — Master language `en,sr,ru,sq`
- `bebbo_custom_general.app_store_redirect` — App store / Google Play URLs both empty
- `bebbo_custom_general.editorial_menu` — canonical editorial menu, 39 links keyed by UUID with title, URI, parent, weight, enabled state and `menu_per_role` show/hide roles. Shared by all 7 sites and applied to each site's menu links by `drush bebbo:menu-sync` (menu links are content entities, so `cim` alone does not apply them)

---

*Generated from `config/sync/` and per-site split folders. All values read from actual YAML — no defaults assumed. Counts: 1,586 shared config files, 18 node types, 22 vocabularies, 4 media types, 68 views, 42 entity-share channels, 43 feed types, 207 migrations, 7 config splits.*

*Verified 2026-07-03: config-split entity→folder mappings and empty `module:`/`theme:` fields confirmed against `config/sync/config_split.config_split.*.yml`; split file counts, Entity Share channel/remote layout, and `config_ignore` entries re-read from the YAML. Per-site enabled-language counts (total 46) and group/app-name tables DB-verified 2026-06-29.*
