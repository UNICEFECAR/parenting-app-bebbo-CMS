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
| 3. Confirm the provider | `/admin/config/ai/providers/openai` | Moderation is on; `host` is empty, meaning the default OpenAI endpoint. Set a host only for a proxy or Azure-style gateway. |
| 4. Review default models | **Configuration → AI** → `/admin/config/ai` (the settings form is `ai.settings_form`, routed as `/admin/config/ai/settings/{nojs}`) | Only if you want to deviate from the table above. Model IDs must exist for your account. |
| 5. Review the translation prompt | `/admin/config/ai/prompts` → `ai_translate__ai_translate_default` | Prompt variables available: `sourceLang`, `sourceLangName`, `destLang`, `destLangName` (required), `inputText`, `countryName`. Prompt types are at `/admin/config/ai/prompts/prompt-types`. |
| 6. Review AI Translate behaviour | `/admin/config/ai/ai-translate` | Per-language model/prompt overrides, draft-vs-publish behaviour, reference depth. |
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
| `role_exclusion_type` / `ignore_role` | `disable_for` / empty | No role is currently exempt |
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

**Working outbound email.** With `tracks: globally_enabled` and `user_one: false`, *every* account — uid 1 included — needs the emailed code, so a mail outage locks everyone out of the UI. Configure §4 and verify a test email arrives **before** enabling TFA on a new environment, and confirm every account has a valid email address.

> If you want a recovery path, tick **Exclude user 1** on the settings form, or keep `drush uli` available from the command line — that link bypasses the form login entirely.

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
| 4. Enter credentials | `/admin/config/system/mailer/office365` | Client ID, Client Secret, Tenant ID, sending mailbox. The page prints the exact Redirect URL for step 2. |
| 5. Sign in | Same page → **Login via Microsoft** | Until this is done the page reads *"Not functional. Login is needed"*. **Saving the form clears the stored token and forces a re-login.** |
| 6. Test | `/admin/config/system/mailer` | Send a test message |

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
| 3. Check the channels | `/admin/config/services/entity_share/channel` | On the **source** site. A channel is only visible to a remote whose account holds an authorised role or is individually listed. |
| 4. Grant permissions | `/admin/people/permissions` | `entity_share_client_pull_content`, `entity_share_access_config_pages`, `entity_share_server_access_channels`, `entity_share_client_display_errors` — currently held by **global_admin**. |
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

## 8. New-site checklist

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
| 10 | Confirm cron is running | `/admin/reports/status` | Yes — mail token refresh, analytics sync and security cleanup all depend on it |
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

## 9. Related documentation

| Document | Covers |
|---|---|
| [README](../README.md) | Install, multisite setup, first-run feature setup |
| [CONFIGURATION.md](CONFIGURATION.md) | Config splits, per-site overrides, the full `config_ignore` list |
| [API_SECURITY.md](API_SECURITY.md) | Attestation flow, token lifetimes, rollout guidance |
| [API_REFERENCE.md](API_REFERENCE.md) | Endpoint contracts |
| [RUNBOOK.md](RUNBOOK.md) | Day-to-day operations and troubleshooting |
| [ENVIRONMENTS.md](ENVIRONMENTS.md) | What differs between Local, Dev, Stage and Prod |
