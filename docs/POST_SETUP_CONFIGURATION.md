# Post-Setup Configuration

Everything an operator has to configure **after** a site is standing up — that is, after the [README](../README.md) install steps and a successful `drush cim`. Each feature below ships with its structural configuration in Git, but stays inert until credentials, endpoints or enrolment settings are supplied per environment.

> **Scope.** This document covers configuration you perform. It does not repeat the architecture (see [ARCHITECTURE.md](ARCHITECTURE.md)), the config-split layout (see [CONFIGURATION.md](CONFIGURATION.md)), or the API contracts (see [API_REFERENCE.md](API_REFERENCE.md) and [API_SECURITY.md](API_SECURITY.md)).
>
> **No secrets belong in this repository.** Every value below is entered in an admin form or an environment variable. Where a value would otherwise be written into `config/sync` by `drush cex`, that is called out explicitly.

---

## 1. How configuration reaches a site

Three delivery mechanisms are in play, and knowing which one owns a value tells you where to change it.

| Mechanism | Where it lives | Travels with `cim` / `cex`? | Used for |
|---|---|---|---|
| **Exported config** | `config/sync/` plus the per-site split folders | Yes | Structure: channels, prompts, views, models, mail policy |
| **`config_ignore` entries** | Named in `config/sync/config_ignore.settings.yml` | No — read and written only on the live site | Credentials and per-environment endpoints |
| **Environment variables** | Acquia Cloud → *Configure → Environment variables*; `.ddev/config.yaml` locally | No | Key material read through the Key module's `env` provider |

The entities currently held back by `config_ignore`:

| Ignored | Granularity | Owner feature |
|---|---|---|
| `bebbo_api_security.settings` | whole entity | API security |
| `pb_content_analytics.settings` | whole entity | Content Analytics |
| `tmgmt.translator.*` (plus five explicit entries) | whole entity | Translation providers |
| `tmgmt_memsource.settings` | whole entity | Translation providers |
| `symfony_mailer_office365.config:client_id` / `:client_secret` / `:tenant_id` | key level | Outbound email |
| `key.key.entity_share_basic_auth:key_provider_settings.key_value` | key level | Entity Share |
| `mobile_app_links.android_packages`, `mobile_app_links.ios` | whole entity | App links |
| `admin_toolbar.settings`, `purge.logger_channels`, `views.view.entity_share_client_entity_import_status` | whole entity | Misc/environmental |

### Keys at a glance

Four Key entities exist; none carries a value in Git.

| Key entity | Provider | Source of the value | Consumed by |
|---|---|---|---|
| `openai_api_key` | `config` | Entered at `/admin/config/system/keys` | AI (all operations) |
| `entity_share_basic_auth` | `config` | Entered at `/admin/config/system/keys` | Entity Share client remotes |
| `bebbo_jwt_signing_key` | `env` | `BEBBO_JWT_PRIVATE_KEY`, base64-encoded | API security (JWT signing) |
| `bebbo_google_sa_key` | `env` | `BEBBO_GOOGLE_SA_KEY`, base64-encoded | API security (Play Integrity) |

> ⚠️ **`key.key.openai_api_key` is not in `config_ignore`.** Once you paste the OpenAI key into the UI, a subsequent `drush cex` will write it into `config/sync/key.key.openai_api_key.yml`. Never run a blanket export after entering it; export only the specific files your change touched, as the repository conventions require. `entity_share_basic_auth` does not have this problem — its `key_value` is ignored at key level.

---

## 2. AI

Powers AI translation, and is the provider layer other AI submodules build on. Modules installed on every site: `ai`, `ai_provider_openai`, `ai_translate`, `ai_tmgmt`.

### What is already configured (shipped in `config/sync`)

`ai.settings` names a default provider and model per operation type:

| Operation | Provider | Model |
|---|---|---|
| `chat`, `chat_with_complex_json` | openai | `gpt-4.1-mini` |
| `chat_with_image_vision`, `chat_with_structured_response`, `chat_with_tools` | openai | `gpt-5.2` |
| `embeddings` | openai | `text-embedding-3-small` |
| `moderation` | openai | `omni-moderation-latest` |
| `speech_to_text` | openai | `whisper-1` |
| `text_to_image` | openai | `gpt-image-1` |
| `text_to_speech` | openai | `tts-1-hd` |
| `translate_text` | `chat_translation` | `gpt-4.1-mini` |

Also shipped: `request_timeout: 60`, `prompt_logging: false`, the prompt type `ai_translate`, and the prompt `ai_translate__ai_translate_default`, which `ai_translate.settings` assigns to **all 28 languages**. `ai_translate.settings` further sets `use_ai_translate: true`, `entity_reference_depth: 1`, and `translation_status: create_draft` — AI translations land as drafts, never published directly.

### What you must configure

| Step | Where | Notes |
|---|---|---|
| 1. Generate an OpenAI API key | [platform.openai.com](https://platform.openai.com/api-keys) → **API keys** → *Create new secret key* | Produces an `sk-...` value, shown once. The account must have billing enabled or every request fails with a quota error. |
| 2. Add the key to the CMS | **Configuration → System → Keys** → `/admin/config/system/keys`, edit **OpenAI API Key** | `ai_provider_openai.settings` stores only the key entity name (`openai_api_key`), never the secret. Read the export warning in §1. |
| 3. Confirm the provider | **Configuration → AI → AI Infrastructure → AI Platform Providers → OpenAI** → `/admin/config/ai/providers/openai` | Moderation is on; `host` is empty, meaning the default OpenAI endpoint. Set a host only for a proxy or Azure-style gateway. |
| 4. Review default models | **Configuration → AI** → `/admin/config/ai` (the settings form is `ai.settings_form`, routed as `/admin/config/ai/settings/{nojs}`) | Only if you want to deviate from the table above. Model IDs must exist for your account. |
| 5. Review the translation prompt | **Configuration → AI → AI Infrastructure → Vector Database Configuration → Prompt Library** → `/admin/config/ai/prompts` → `ai_translate__ai_translate_default` | The AI module nests the Prompt Library link under Vector Database Configuration; the path is the reliable way in. Prompt variables available: `sourceLang`, `sourceLangName`, `destLang`, `destLangName` (required), `inputText`, `countryName`. Prompt types are the second tab, **AI Prompt Types** → `/admin/config/ai/prompts/prompt-types`. |
| 6. Review AI Translate behaviour | **Configuration → AI → AI Translate settings** → `/admin/config/ai/ai-translate` | Per-language model/prompt overrides, draft-vs-publish behaviour, reference depth. |
| 7. Verify | Open a node, use the **Translate** tab's AI translate action | A draft translation should be produced. |

### The TMGMT side

`ai_tmgmt` supplies a TMGMT translator plugin with the id **`ai`**. On this stage database it is configured as the translator **`open_ai`** ("Open AI") with settings `model_selection_type`, `chat_model`, `tokenizer_model`, `advanced`.

**Every `tmgmt.translator.*` entity is `config_ignore`d**, so no translator — AI or otherwise — arrives via `cim`. On a new site you must create them by hand:

1. Go to **Translation → Providers** → `/admin/tmgmt/translators`.
2. Add a provider, choose the plugin (AI, DeepL, Google, Microsoft, Memsource, File, Local), and enter that service's credentials.
3. For the AI provider, pick the chat model; it uses the AI provider and key configured above rather than its own key.

Providers present on the reference environment: `open_ai`, `deepl_free`, `deepl_pro`, `google`, `microsoft`, `memsource`, `file`, `local`.

### Required for it to work

An OpenAI key with billing enabled, outbound HTTPS from the environment, and — for TMGMT-driven jobs — at least one translator entity created on that site.

---

## 3. Email TFA (multi-factor authentication)

Module `email_tfa`. Sends a one-time code by email as a second factor after password login.

### Shipped defaults (`email_tfa.settings`, exported)

| Setting | Value | Meaning |
|---|---|---|
| `status` | `true` | Feature on |
| `tracks` | `globally_enabled` | Enforced for all users, rather than opt-in per user |
| `user_one` | `false` | Labelled *"Exclude user 1"* in the UI. Currently **off**, so **uid 1 is also challenged** — there is no built-in recovery account if mail breaks. |
| `security_code_length` | `6` | Digits in the code |
| `timeouts` | `300` | Code lifetime in seconds |
| `flood_threshold` / `flood_window` | `5` / `3600` | Five attempts per hour |
| `role_exclusion_type` / `ignore_role` | `disable_for` / `mfa_admin` | Holders of the **MFA Admin** role skip the OTP step; every other role is challenged |
| `dev_mode` | `false` | Must stay `false` outside local debugging |
| `log_events` | `false` | No verbose logging |
| `routes` | `email_tfa.verifiy`, `user.logout` | Routes reachable while a challenge is pending |

The subject line and body template are also exported, using the tokens `[user:name]`, `[user:email_tfa]` and `[site:name]`.

### What you configure

| Item | Where |
|---|---|
| All of the above | **Configuration → People → Email TFA Settings** → `/admin/config/people/email-tfa` (permission `administer email tfa`) |
| Login flow paths | `/tfa/login`, `/tfa/verify/{uid}/{hash}` — no configuration, listed so you can whitelist them if a WAF or cache sits in front |

### Required for it to work

**Working outbound email.** With `tracks: globally_enabled` and `user_one: false`, *every* account without the `mfa_admin` role — uid 1 included — needs the emailed code, so a mail outage locks everyone out of the UI. Configure §4 and verify a test email arrives **before** enabling TFA on a new environment, and confirm every account has a valid email address.

> Recovery paths: assign the **MFA Admin** role (`mfa_admin`) to an account — it is exempt from the OTP step and can administer both the mailer and the TFA settings. Alternatively tick **Exclude user 1** on the settings form, or keep `drush uli` available from the command line — that link bypasses the form login entirely.

---

## 4. Outbound email — Symfony Mailer + Office 365

Modules `symfony_mailer` and `symfony_mailer_office365`. Carries content-moderation notifications and TFA codes.

### Shipped configuration

- `symfony_mailer.settings` → `default_transport: office_365_oauth`.
- `symfony_mailer.mailer_transport.office_365_oauth` → plugin `office365_oauth`; `smtp_host` and `smtp_port` are set, while `client_id`, `client_secret`, `tenant_id` and `user` are **null**.
- `symfony_mailer_office365.config` → ships the literal placeholders `REPLACE_WITH_CLIENT_ID`, `REPLACE_WITH_CLIENT_SECRET`, `REPLACE_WITH_TENANT_ID` and an `admin@` sender address.
- A second transport, `sendmail`, exists as a fallback.
- `ultimate_cron.job.symfony_mailer_office365_cron` is enabled — it keeps the OAuth token refreshed.

### The flow is delegated, not application-level

The module requests these **delegated** scopes: `https://outlook.office365.com/IMAP.AccessAsUser.All`, `https://outlook.office365.com/SMTP.Send`, and `offline_access`. It does **not** use the Graph `Mail.Send` application permission. An administrator signs in once as the sending mailbox and the site stores the refresh token.

### What you configure

| Step | Where | Notes |
|---|---|---|
| 1. Register the app | [Microsoft Entra ID](https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade) | Capture Application (client) ID, Directory (tenant) ID, and a client secret **value** — the value, not its ID, and it is displayed only once |
| 2. Register the redirect URI | Entra → **Authentication → Add a platform → Web** | `https://<your-host>/office365/oauth/callback`. **One entry per environment and per domain** — Dev, Stage, Prod and each country domain each sign in against their own host. A mismatch fails with `AADSTS50011`. |
| 3. Grant delegated permissions | Entra → **API permissions** | The three scopes above, with admin consent if the tenant requires it. Confirm SMTP AUTH is enabled for the mailbox — Exchange Online disables it per-mailbox by default. |
| 4. Enter credentials | **Configuration → System → Mailer**, then the **Office365** tab → `/admin/config/system/mailer/office365` | Client ID, Client Secret, Tenant ID, sending mailbox. The page prints the exact Redirect URL for step 2. |
| 5. Sign in | Same page → **Login via Microsoft** | Until this is done the page reads *"Not functional. Login is needed"*. **Saving the form clears the stored token and forces a re-login.** |
| 6. Test | **Configuration → System → Mailer**, then the **Test** tab → `/admin/config/system/mailer/test` | Send a test message. The Mailer landing page itself (`/admin/config/system/mailer`) is the Policy list; Transport and Override are the other tabs. |

### Required for it to work

Steps 1–5 completed on **that** environment, cron running so the refresh token stays alive, and outbound network access to Microsoft's OAuth and SMTP endpoints. Locally, none of this is needed — DDEV captures mail in Mailpit (`ddev launch -m`).

---

## 5. Content Analytics

Custom module `pb_content_analytics`. Pulls per-content engagement figures from a BigQuery-backed HTTP endpoint and stores them against nodes, then reports on them.

### Shipped configuration

Views `content_analytics` and `content_analytics_sync_log`, four Feeds types (`content_analytics_import`, `analytics_import_activities`, `analytics_import_course`, `analytics_import_video_article`), the `pb_analytics_sync_log` database table (created by `hook_schema`), and `ultimate_cron.job.pb_content_analytics_cron`, enabled on the rule `0+@ 0 * * *` (once daily).

`pb_content_analytics.settings` is **`config_ignore`d in full** — the endpoint and its API key are per-environment and never exported.

### What you configure

**Configuration → Content Analytics Settings** → `/admin/config/content-analytics/settings` (permission `administer content analytics settings`):

| Field | Meaning |
|---|---|
| Enable Content Analytics | Master switch. Off = cron skips and no sync runs. |
| BigQuery API URL | Full endpoint URL for the analytics API |
| X-API-Key | Sent as the `X-API-Key` request header. Stored write-only in the form — leave blank to keep the current value. |
| Enable auto-sync | Lets cron trigger the sync |
| Sync frequency | `daily` (86400s) or `weekly` (604800s) |

### Pages and permissions

| Path | Purpose | Permission |
|---|---|---|
| `/admin/config/content-analytics/settings` | Settings above | `administer content analytics settings` |
| `/admin/config/content-analytics/sync` | Manual sync form and status | `manage content analytics sync` |
| `/admin/config/content-analytics/sync/now` | Trigger a sync immediately | `manage content analytics sync` |
| `/admin/config/content-analytics/report` | The report (also `/report/csv` for export) | `view content analytics report` |
| `/admin/config/content-analytics/logs` | Sync log view | — |

### Required for it to work

Enable the master switch, a reachable endpoint URL, a valid API key, and — for unattended operation — auto-sync enabled *and* cron running. Cron respects the frequency: it compares the last successful sync in `pb_analytics_sync_log` against the interval and skips if it is too soon, logging the reason to the `pb_content_analytics` channel. If the report looks stale, read that channel before suspecting the endpoint.

---

## 6. Entity Share

Modules `entity_share`, `entity_share_server`, `entity_share_client`, `entity_share_async`, `entity_share_diff`. Copies content between Bebbo sites — typically pulling the global English catalogue into a country site. Depends on `jsonapi`, `jsonapi_extras`, `serialization` and `basic_auth`, all installed.

### Shipped configuration

- **42 server channels** in `config/sync` (41 scoped to `en`, 1 to `und`), covering articles by category, basic pages, child development and growth, FAQs, games, health check-ups, milestones, vaccinations, daily home-screen messages, video articles, media and taxonomies. Each channel pins an entity type, bundle and langcode, a `channel_maxsize`, and the roles/users authorised to read it.
- **Two client remotes**: `prod` → `https://bebbo.app` and `stage` → `https://stage.bebbo.app`. Both authenticate with `basic_auth` and read their credentials from the Key entity **`entity_share_basic_auth`**.
- **One import config**: `config_production`, with processors for embedded entities, entity references, physical files, path aliases, revisions, changed-time comparison and `skip_imported`.

### What you configure

| Step | Where | Notes |
|---|---|---|
| 1. **Set the shared key** | **Configuration → System → Keys** → `/admin/config/system/keys`, edit **entity_share_basic_auth** | This is where the key goes. Its type is `entity_share_basic_auth`, holding the username and password of an account **on the remote site** that may read the channels. Its `key_value` is `config_ignore`d at key level, so it is never exported. |
| 2. Check the remotes | **Configuration → Web services → Entity Share → Remotes** → `/admin/config/services/entity_share/remote` | Confirm the URL is right for what this site should pull from. Add a remote here if you are pulling from somewhere else. |
| 3. Check the channels | **Configuration → Web services → Entity Share → Channels** → `/admin/config/services/entity_share/channel` | On the **source** site. A channel is only visible to a remote whose account holds an authorised role or is individually listed. |
| 4. Grant permissions | **People → Permissions** → `/admin/people/permissions` (filtered: `/admin/people/permissions/module/entity_share_client,entity_share_server`) | `entity_share_client_pull_content`, `entity_share_access_config_pages`, `entity_share_server_access_channels`, `entity_share_client_display_errors` — currently held by **global_admin**. |
| 5. Pull | **Content → Entity Share pull** → `/admin/content/entity_share/pull` | Choose remote, channel, then the entities to import. A CSV export of the pull list is available at `/admin/content/entity_share/pull/export/csv` (permission `entity_share_client_pull_content`). |

### Required for it to work

On the **source** site: the channels must exist and the reading account must be authorised on them. On the **target** site: the key must hold valid credentials for that account, the remote URL must be reachable, and the operator needs `entity_share_client_pull_content`. Both sides need JSON:API enabled — it is, on all sites.

---

## 7. API security (JWT + device attestation)

Custom module `bebbo_api_security`. Optional protection for `/v2/api/*`. Full model in [API_SECURITY.md](API_SECURITY.md); only the configuration is repeated here.

### Shipped defaults (`bebbo_api_security.settings`, `config_ignore`d in full)

| Setting | Default |
|---|---|
| `enforcement_mode` | `disabled` |
| `dev_bypass_enabled` / `dev_bypass_ips` | `false` / empty |
| `google_package_name`, `google_project_number` | empty |
| `google_verdict_freshness_seconds` | `600` |
| `google_allow_unrecognized_version` | `false` |
| `apple_team_id`, `apple_bundle_id` | empty |
| `apple_production_mode` | `true` |
| `jwt_expiry_seconds` | `3600` |
| `refresh_expiry_seconds` | `2592000` (30 days) |
| `refresh_rotation_enabled` | `true` |
| `register_rate_limit` / `refresh_rate_limit` | `10` / `30` |

Because the whole entity is ignored, these values are per environment and survive `cim`.

### What you configure

| Step | Where | Notes |
|---|---|---|
| 1. JWT signing key | **Acquia Cloud → Configure → Environment variables**, variable `BEBBO_JWT_PRIVATE_KEY` | RSA private key, **base64-encoded**. Read by the Key entity `bebbo_jwt_signing_key` (`env` provider, `base64_encoded: true`). Locally: `web_environment` in `.ddev/config.yaml`, then `ddev restart`. |
| 2. Android attestation key | Same place, variable `BEBBO_GOOGLE_SA_KEY` | Google service-account JSON with Play Integrity enabled, **base64-encoded**. Read by `bebbo_google_sa_key`. |
| 3. Android identifiers | `/admin/config/parent-buddy/api-security` | Package name, project number, verdict freshness |
| 4. iOS identifiers | Same form | Apple Team ID, bundle ID, the [Apple App Attestation Root CA](https://www.apple.com/certificateauthority/) PEM, production mode |
| 5. Enforcement | Same form | `disabled` → `grace_period` (monitor only) → `enforced` |

Generate the RSA signing key and encode it for step 1:

```bash
openssl genrsa -out bebbo-jwt.pem 2048
base64 -i bebbo-jwt.pem   # value for BEBBO_JWT_PRIVATE_KEY
```

Keep `bebbo-jwt.pem` out of the repository. For DDEV, uncomment and set `BEBBO_JWT_PRIVATE_KEY` under `web_environment` in `.ddev/config.yaml` (a commented example is present), then `ddev restart`. The Google service-account JSON for step 2 is encoded the same way (`base64 -i service-account.json`).

**Both environment variables are set in Acquia Cloud, per environment.** They are not inherited between Dev, Stage and Prod — add them wherever the secured flow runs, and redeploy or restart so PHP sees them. Nothing is committed and nothing is exported.

### Monitoring pages

| Path | Shows |
|---|---|
| `/admin/config/parent-buddy/api-security/devices` | Registered devices |
| `/admin/config/parent-buddy/api-security/tokens` | Refresh tokens |
| `/admin/config/parent-buddy/api-security/challenges` | Attestation challenges |
| `/admin/config/parent-buddy/api-security/security-log` | Security events |

`ultimate_cron.job.bebbo_api_security_cron` is enabled daily (`0+@ 0 * * *`) to expire stale records. Permission: `administer bebbo api security` (restricted).

### Required for it to work

For JWT issuance: `BEBBO_JWT_PRIVATE_KEY`. For Android attestation: `BEBBO_GOOGLE_SA_KEY` plus the package name and project number. For iOS attestation: Team ID, bundle ID and the root CA. Enforcement stays `disabled` until you deliberately change it — a fresh site serves `/v2/api/*` without any of this.

---

## 8. Cron — where it runs, and how to stop it

Several features above are inert without cron: the Office 365 token refresh (§4), the Content Analytics sync (§5), the API-security cleanup (§7), TMGMT polling and the purge queue.

### Nothing schedules itself inside Drupal

`automated_cron.settings` → `interval: 0`, so Drupal never runs cron at the end of a web request. Every cron run is triggered from outside: an **Acquia Cloud scheduled job** on Dev/Stage/Prod, or `drush cron` by hand. One Drush process bootstraps one site, so each of the 7 sites needs its own job.

| Environment | What triggers Drupal cron | Cadence |
|---|---|---|
| Dev | 7 Acquia jobs, `cron-wrapper.sh parentbuddy2.test https://<site>-dev.bebbo.app` | every 15 min |
| Stage | 6 Acquia jobs, `cron-wrapper.sh parentbuddy2.test https://<site>-stage.bebbo.app` | every 15 min |
| Prod | 1 Acquia job, `cron-wrapper.sh parentbuddy2.prod http://bebbo.app` — the default site only | daily 00:00 |
| Local (DDEV) | nothing; run `ddev drush @ddev.{site} cron` yourself | on demand |

A separate Acquia job on Dev and Stage runs the API cache warmer (`drush bebbo:warm-all`, every 2 h at :30) — that one is not Drupal cron, see §9. Full job list and commands: [ENVIRONMENTS.md](ENVIRONMENTS.md) §8.3; list them live with `acli api:environments:cron-job-list parentbuddy2.<env>`.

### What runs inside a cron run — `ultimate_cron`

The job registry lives in `config/sync` (17 `ultimate_cron.job.*` entities) and is managed at **Configuration → System → Cron** → `/admin/config/system/cron/jobs`; scheduler, launcher and logger defaults are at `/admin/config/system/cron/settings`.

| Job | Schedule rule | Runs |
|---|---|---|
| `tmgmt_cron` | `*/30+@ * * * *` | every 30 min |
| `purge_processor_cron_cron`, `symfony_mailer_office365_cron`, `tmgmt_memsource_cron` | no own rule | falls back to the site default, `*/15+@ * * * *` |
| `bebbo_api_security_cron`, `pb_content_analytics_cron`, `system_cron`, `dblog_cron`, `feeds_cron`, `field_cron`, `file_cron`, `filelog_cron`, `layout_builder_cron`, `locale_cron`, `ultimate_cron_cron`, `update_cron` | `0+@ 0 * * *` | once a day around midnight |
| `node_cron` | — | **disabled** (`status: false`) |

The `+@` in a rule is a per-job offset derived from the job name, so jobs sharing a rule do not all fire in the same minute. A job only runs if a cron run happens while its rule is due — with the daily prod cron, a `*/15` job still runs once a day. The launcher is `serial` with `max_threads: 1` and a 3600 s lock, so one long job blocks the rest of that run; clear a stuck lock at `/admin/config/system/cron/jobs/{job}/unlock`.

### Stopping cron

| Scope | How |
|---|---|
| One job, one site | `/admin/config/system/cron/jobs` → **Disable** on the row, or `drush cron:disable <job_id>` (`cron:enable` to restore, `cron:list` to see state). This is active config — export it only if you mean every site to stop running that job. |
| All jobs, one site | `drush cron:disable --all` |
| The whole site's cron | Disable or delete that site's scheduled job in **Acquia Cloud → Environment → Cron tasks**. Nothing else triggers cron (`automated_cron` interval is 0), so this stops all scheduled work for that site. |
| The API warmer | Disable the *DEV API Warmer* / *Stage API Warmer* Acquia job. It is Drush-only; disabling it does not affect Drupal cron. |
| Locally | Nothing to stop — cron runs only when you run it. |

> Stopping cron for more than a few hours stops the Office 365 refresh-token renewal. When that token lapses, mail stops, and with Email TFA globally enabled (§3) every account without the `mfa_admin` role is locked out. The purge queue keeps draining even without cron — the `lateruntime` processor drains a slice at the end of web requests — but analytics sync, TMGMT polling and security-token cleanup simply stop.

---

## 9. Caching

Nothing here needs configuring on a new site; it is listed so an operator knows which layer is serving a stale response.

### The layers, front to back

| # | Layer | What it does here |
|---|---|---|
| 1 | **Cloudflare** (6 public zones) | Proxy only. No cache rule is configured, so responses come back `cf-cache-status: DYNAMIC` and Cloudflare stores nothing. No `cloudflare`/`cloudflarepurger` module is enabled. |
| 2 | **Acquia Platform CDN (Fastly) + Varnish** | The real edge cache. Anonymous GETs are held for the full `max-age` and cleared by tag purges, not by TTL expiry. |
| 3 | **Drupal page cache** (`page_cache`) | Anonymous responses, `Cache-Control: max-age=2764800, public` (32 days) from `system.performance`. `dynamic_page_cache` + `big_pipe` cover authenticated pages. |
| 4 | **Views result cache** | `bebbo_v1_apis` and `bebbo_v2_apis` use the custom `bebbo_api_tag` plugin (`bebbo_serializer`); the other API views use core's tag plugin. |
| 5 | **Cache backends** | On Acquia, Memcache is the default bin plus `bootstrap`/`discovery`/`config`, with a per-site `key_prefix` so one site's `drush cr` does not flush another's; `depcalc` stays on the database. Locally everything is the database. |

### How invalidation works

Tag-driven, not TTL-driven. The API listings are tagged `bebbo_api_list:{bundle}:{langcode}` (plus `media_list`) instead of the site-wide `node_list`, so saving a node expires only the listings of its bundle in the languages whose values actually changed. `purge_queuer_coretags` queues every invalidated tag; the `acquia_purge` and `acquia_platform_cdn` purgers clear Varnish and the Platform CDN; the `cron` and `lateruntime` processors drain the queue. Tag details: [API_REFERENCE.md](API_REFERENCE.md) §12; purger/queuer/processor list: [CONFIGURATION.md](CONFIGURATION.md) *Purge / Cache invalidation*.

Two response-level helpers in `bebbo_serializer` sit alongside this: `ApiVaryResponseSubscriber` strips `Cookie` from `Vary` on `/api/*` and `/v{n}/api/*` so shared caches can store one copy per URL, and `EtagResponseSubscriber` answers `If-None-Match` with a 304 on five V2 displays (articles, video articles, activities, FAQs, basic pages) without rendering anything.

### Warming

Because a cold V1 listing is a slow Views render, `bebbo_custom_general` re-requests every V1 `/api/*` path × app-visible language (plus `/api/check-update/{gid}` per country group) after deploys and cache clears — `drush bebbo:warm-all` on an Acquia job every 2 h on Dev and Stage, or the **Warm this site** button at **Configuration → Development → API cache warmer** → `/admin/config/development/api-warmer`. Run log: `/admin/config/development/api-warmer/logs`. Prod has no warmer job. Nothing warms `/v2/api/*`. Settings and per-site language lists: [CONFIGURATION.md](CONFIGURATION.md) *API cache warmer*.

### Clearing caches by hand

`drush cr` per site rebuilds Drupal's own bins; it does not clear Varnish or the Platform CDN. `purge_drush` is not enabled, so there are no `p:*` Drush commands — inspect the queue, purgers and diagnostics at **Configuration → Development → Performance → Purge** → `/admin/config/development/performance/purge`, or let the tag purge that the content save already queued drain on its own. After a `drush cr` the V1 listings are cold; warm them rather than leaving the first app request to pay for the render.

---

## 10. New-site checklist

Order matters: email before TFA, keys before the features that read them.

| # | Task | Where | Blocking? |
|---|---|---|---|
| 1 | Import configuration, confirm the site loads | `drush cim -y` | Yes |
| 2 | Configure Office 365 mail and complete the Microsoft sign-in | §4 | Yes, if TFA is on |
| 3 | Send a test email and confirm delivery | `/admin/config/system/mailer` | Yes, if TFA is on |
| 4 | Review Email TFA settings | §3 | — |
| 5 | Add the OpenAI key | §2 | Only for AI features |
| 6 | Create the TMGMT providers this site uses | §2 | Only for TMGMT translation |
| 7 | Set the Entity Share basic-auth key and check the remote URL | §6 | Only if pulling content |
| 8 | Set the Content Analytics endpoint and API key, enable sync | §5 | Only for the analytics report |
| 9 | Set `BEBBO_JWT_PRIVATE_KEY` (and `BEBBO_GOOGLE_SA_KEY`) in Acquia | §7 | Only for secured API |
| 10 | Confirm cron is running — the site needs its own Acquia scheduled job (§8) | `/admin/reports/status`, `/admin/config/system/cron/jobs` | Yes — mail token refresh, analytics sync and security cleanup all depend on it |
| 11 | Re-check that no secret reached Git | `git status`, `git diff config/sync` | Yes |

### Post-configuration verification

| Feature | Check |
|---|---|
| Mail | Test email arrives; `/admin/config/system/mailer/office365` shows a token expiry date rather than "Login is needed" |
| TFA | Log in as a non-uid-1 account and confirm the code arrives |
| AI | Translate a node and confirm a draft translation is produced |
| TMGMT | The provider appears when creating a translation job |
| Entity Share | `/admin/content/entity_share/pull` lists channels from the remote instead of an auth error |
| Content Analytics | `/admin/config/content-analytics/logs` records a successful sync |
| API security | With enforcement `grace_period`, the security log records requests without rejecting them |

---

## 11. Related documentation

| Document | Covers |
|---|---|
| [README](../README.md) | Install, multisite setup, first-run feature setup |
| [CONFIGURATION.md](CONFIGURATION.md) | Config splits, per-site overrides, the full `config_ignore` list |
| [API_SECURITY.md](API_SECURITY.md) | Attestation flow, token lifetimes, rollout guidance |
| [API_REFERENCE.md](API_REFERENCE.md) | Endpoint contracts |
| [RUNBOOK.md](RUNBOOK.md) | Day-to-day operations and troubleshooting |
| [ENVIRONMENTS.md](ENVIRONMENTS.md) | What differs between Local, Dev, Stage and Prod |
