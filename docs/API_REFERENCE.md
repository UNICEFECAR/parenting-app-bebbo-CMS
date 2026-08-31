# Bebbo API Reference — REST (V1 + V2), Force-Update, JSON:API

> **Audience:** mobile-integration engineers, backend maintainers, support, QA.
> **Scope:** every HTTP API the CMS exposes to clients — the legacy **V1 REST** (`/api/*`), the current **V2 REST** (`/v2/api/*`), the **Force-Update / check-update endpoint** (`/api/check-update/{country}` + `/v2/api/check-update/{country}`), and **JSON:API** (`/jsonapi/*`). Field shapes, envelopes, query params, auth, and the V1→V2 changes.
> **Verified against codebase on 2026-07-03.** Endpoint paths come from the Views configs; envelopes/field transforms from the serializer source. **No GraphQL exists** (see `ARCHITECTURE.md`).
>
> **V1 implementation:** V1 `/api/*` is served by the **`bebbo_v1_serializer`** Views style (class `BebboV1Serializer`) on the **`bebbo_v1_apis`** view — it shares the `BebboSerializerHelpers` trait with V2's `bebbo_serializer`, dispatches via `match()` on the Views display id, and uses the same batched media resolution. It emits plain `json` (escaped slashes/unicode) to preserve the legacy V1 byte-for-byte JSON contract.

---

## 1. API Surfaces at a Glance

| Surface | Base | Served by | Status |
|---------|------|-----------|--------|
| **V1 REST** | `/api/*` | `bebbo_v1_serializer` Views style (`bebbo_v1_apis` view; `/api/strings` is served by `bebbo_serializer`) | Live — public (legacy JSON contract) |
| **V2 REST** | `/v2/api/*` | `bebbo_serializer` Views style (`bebbo_v2_apis` view) | Current — JWT-gated |
| **Force-Update** | `/api/check-update/{country}` + `/v2/api/check-update/{country}` | `bebbo_serializer` `CheckUpdateController` (routes `bebbo_serializer.v1_check_update` / `.v2_check_update`) | Live |
| **Device Security** | `/api/security/*` | `bebbo_api_security` (`SecurityController`) | Live — attestation + JWT issuance (see [§13.1](#131-device-security--attestation-api-apisecurity)) |
| **App-links** | `/.well-known/*` | `mobile_app_links` (`WellKnownController`) | Live — deep-link domain verification (see [§13.2](#132-app-link-well-known-endpoints)) |
| **JSON:API** | `/jsonapi/*` | core `jsonapi` + `jsonapi_extras` | Enabled, **read-only** |

The V1 and V2 content endpoints are **Drupal Views REST/page displays**, not REST plugins. Each display renders rows, then a custom Views *style plugin* serializes them into the Bebbo JSON envelope.

---

## 2. Common Conventions (V1 & V2 content endpoints)

### URL pattern
```
/api/{resource}/{langcode}            (V1)
/v2/api/{resource}/{langcode}         (V2)
```
The trailing path arg is the **langcode** (e.g. `en`, `bn`, `ur`). Taxonomy adds a vocabulary slug: `/v2/api/taxonomies/{langcode}/{vocab}`.

- **V1 langcode resolution:** `BebboV1Serializer::resolveLangcode()` takes the view arg if it is a valid language, else the current language; validated against enabled languages **and** per-group mobile language visibility (`language_visibility_control`) via `checkLanguageVisibility()`. Invalid → `{status:400,"message":"Request language is wrong"}`; not visible in any country group → `{status:403,"message":"Language not available"}`. Skipped for the country-groups display (`v1_country_listing_rest_export`).
- **V2 langcode resolution:** `BebboSerializer::resolveLangcode()` works identically; same visibility validation via `checkLanguageVisibility()` (skipped for country-groups).

### Standard response envelope
```json
{
  "status": 200,
  "total": 177,
  "langcode": "en",
  "datetime": "2026-06-15 12:00",
  "data": [ ... ]
}
```
- `datetime` is **server-generated** (not a request echo), formatted `Y-m-d H:i` in timezone **`Asia/Kolkata`** (both V1 `BebboV1Serializer::render()` and V2 `BebboSerializer::render()`, via the shared `BebboSerializerHelpers` trait).
- **Empty result:** `{status:204,"message":"No Records Found","datetime":"…"}` — no `data`/`total`/`langcode`. (Note: `204` is a JSON body field; the HTTP status is not necessarily 204.)
- Envelope variants per endpoint family are listed in their sections below.

### V2 JSON encoder (`BebboEncoder`, format `bebbo_json`)
```php
json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```
Only those two flags. **No pretty-print, and no `&nbsp;` substitution** — earlier documentation that claimed pretty-print/`&nbsp;` is incorrect. Empty/error envelopes are encoded as plain `json`, success envelopes as `bebbo_json` (`getOutputFormat()`).

### Authentication
- **V1 & V2 content endpoints:** the Views displays declare **no auth provider** (`auth: {}`) and gate only on the `access content` permission (held by anonymous). They are effectively **public reads**. Some displays set `disable_sql_rewrite: true` (in V2: `articles_rest_export`, `child_dev_boy_rest_export`, `child_dev_girl_rest_export`, `child_growth_rest_export` — 4 of 20 displays, not all).
- **Device/JWT protection:** the `bebbo_api_security` subscriber can require a Bearer JWT on `/v2/api/*` when enforcement is on. **V1 `/api/*` endpoints (content *and* `/api/check-update/`) are NOT in the default protected set.** Full detail: **`API_SECURITY.md`**.
- **`/api/check-update/{country}`:** public (V1, route `_access: TRUE`); the V2 path `/v2/api/check-update/{country}` is JWT-gated via the `/v2/api/` protected pattern (see [§9](#9-force-update--check-update-endpoint)).

---

## 3. V1 ↔ V2: Implementation & Endpoint Asymmetry

V1 (`bebbo_v1_serializer`) and V2 (`bebbo_serializer`) now share the **same architecture** — both dispatch via `transformRows()` `match()` on the Views display id, share the `BebboSerializerHelpers` trait (batched media/file/term loading, type-cast helpers, pre-computed `field_body_rendered`/`field_embedded_images`, `parseViewCoverImage()`), and produce the standard envelope. The differences are the **JSON contract** and the **set of endpoints**, not the internals:

| Aspect | V1 (`bebbo_v1_serializer`) | V2 (`bebbo_serializer`) |
|--------|-----------------------------|--------------------------|
| Output encoder | plain `json` (escaped slashes/unicode — byte-parity with the legacy V1 contract) | `bebbo_json` (`JSON_UNESCAPED_SLASHES` + `JSON_UNESCAPED_UNICODE`) |
| `total` | deduped `count($rows)` for de-duplicating displays, else `view->total_rows` | `view->total_rows` (falls back to `count($rows)`) |
| ETag / 304 | — | 5 displays (see §5.6) |
| Engagement counts | — | `read_count`, `love_count` on activities/articles/video-articles/course |
| V2-only content types | — | **Course** (`/v2/api/course`), **Quiz** (`/v2/api/quiz`), **Guide** (`/v2/api/guide`), **Weekly-overview** (`/v2/api/weekly-overview`) |
| V1-only endpoints | full **pinned-contents** set (`child_growth`, `faq`, `health_check_ups`, `vaccinations`) + `related-article-contents/*/milestone` + `updated-pinned-contents/*/faq` + `sponsors` | — |
| V2 URL aliases | — | `/v2/api/strings/%` and `/v2/api/check-update/{country}` — same view/controller as the V1 paths, identical responses |

**Endpoint asymmetry (verified):**
- **V2 adds** `course`, `guide`, `quiz`, `weekly-overview`; V2 has **only** the two `child_development/40` & `/41` pinned displays.
- **V1 has** the full pinned-contents set (`child_growth`, `faq`, `health_check_ups`, `vaccinations`), plus `related-article-contents/*/milestone`, `updated-pinned-contents/*/faq`, and `sponsors` — none of which exist in V2.

\* `/api/strings/%` is served by the **`bebbo_serializer`** style (not `bebbo_v1_serializer`), even though it sits under `/api/`. A V2 alias at `/v2/api/strings/%` exists as well (same `tax` view, `v2_string_rest_export` display).

> Both V1 and V2 remain live. The mobile app migrated to V2; V1 (`/api/*`) is retained, public, and backward-compatible.

---

## 4. V1 REST Endpoints (`/api/*`)

**Dispatch:** the `bebbo_v1_serializer` Views style plugin serves all `api/*` displays in `views.view.bebbo_v1_apis.yml` (plus the V1 displays on `views.view.country_listing.yml`, `views.view.tax.yml`, and `views.view.sponsors_list.yml`). `render()` resolves the langcode, then `transformRows()` `match()`es on the Views display id and runs the per-endpoint `transformX()` method (each row carries **short keys** — `id`, `type`, `title`, `body`, `cover_image`, …). `/api/strings/%` is the exception — it uses the `bebbo_serializer` style.

### 4.1 Endpoint inventory

The `bebbo_v1_apis` view has **22 displays**. The `tax`, `country_listing`, and `sponsors_list` views add the remaining V1 displays.

| Path | View / style | Notes |
|------|--------------|-------|
| `/api/articles/%` | bebbo_v1_apis / bebbo_v1_serializer | Pregnancy term preserved; child-age arrays filtered |
| `/api/video-articles/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/activities/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/milestones/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/faqs/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/basic-pages/%` | bebbo_v1_apis / bebbo_v1_serializer | adds `unique_name` (lowercased English title, spaces→`_`) |
| `/api/daily-homescreen-messages/%` | bebbo_v1_apis / bebbo_v1_serializer | minimal (id, type, title, dates) |
| `/api/vaccinations/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/child-development-data/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/child-growth-data/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/health-checkup-data/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/surveys/%` | bebbo_v1_apis / bebbo_v1_serializer | |
| `/api/archive/%` | bebbo_v1_apis / bebbo_v1_serializer | grouped `{Type:[ids]}` envelope |
| `/api/standard_deviation/%` | bebbo_v1_apis / bebbo_v1_serializer | nested SD envelope (see [§8](#8-standard-deviation)); SD logic folded into `BebboV1Serializer` (was `pb_custom_standard_deviation`, removed) |
| `/api/pinned-contents/%/child_development/40` (boy) | bebbo_v1_apis / bebbo_v1_serializer | type-specific media swap; dedup by id |
| `/api/pinned-contents/%/child_development/41` (girl) | bebbo_v1_apis / bebbo_v1_serializer | " |
| `/api/pinned-contents/%/child_growth` | bebbo_v1_apis / bebbo_v1_serializer | **V1-only** |
| `/api/pinned-contents/%/faq` | bebbo_v1_apis / bebbo_v1_serializer | **V1-only** |
| `/api/pinned-contents/%/health_check_ups` | bebbo_v1_apis / bebbo_v1_serializer | **V1-only** |
| `/api/pinned-contents/%/vaccinations` | bebbo_v1_apis / bebbo_v1_serializer | **V1-only** |
| `/api/updated-pinned-contents/%/faq` | bebbo_v1_apis / bebbo_v1_serializer | **V1-only** |
| `/api/related-article-contents/%/milestone` | bebbo_v1_apis / bebbo_v1_serializer | **V1-only**; dedup by id |
| `/api/country-groups/%` | country_listing / bebbo_v1_serializer | `%` is a country/app slug (e.g. `wawamor`), **not** a langcode; envelope `langcode` resolves to the active/site language (the slug arg is not a valid language, so `resolveLangcode()` falls back to the current language) |
| `/api/vocabularies/%` | tax / bebbo_v1_serializer | keyed map (see [§7](#7-taxonomy--vocabulary)) |
| `/api/taxonomies/%/%` | tax / bebbo_v1_serializer | keyed map; 2nd arg is a vocabulary machine name **or** the literal `all` |
| `/api/strings/%` | tax / **`bebbo_serializer`** | Also available at `/v2/api/strings/%` (same view, `v2_string_rest_export` display) |
| `/api/sponsors/%` | sponsors_list / bebbo_v1_serializer | **V1-only**; `%` is country-group id (`all` allowed), no `langcode` key in output |

### 4.2 Shared value transforms (`BebboV1Serializer` + `BebboSerializerHelpers` trait)

Applied per-row (via the same helpers V2 uses) to whichever short keys are present:

- **Text decode:** `title`, `question` → `decodeHtmlEntities` (`htmlspecialchars_decode ENT_QUOTES|ENT_HTML5`).
- **HTML body** (`body`, `summary`, `answer_part_1`, `answer_part_2`): read from the pre-computed `field_body_rendered`/related rendered fields (no request-time DOMDocument parsing); image URLs already WebP-converted at presave (see [§5.7](#57-body-image-processing-v2)).
- **`embedded_images`** (array of `<img>` src) sourced from the pre-computed `field_embedded_images` field.
- **Media objects** (`cover_image`, `country_flag`, `country_sponsor_logo`, `unicef_logo`, `country_national_partner`, `cover_video`) via `parseViewCoverImage()` / `resolveMediaIds()` (**batched**, shared trait): image → `{url,name,alt}` (WebP); remote_video/video → `{url,name,site}` (or thumbnail `{url,name,alt}` for `cover_image`); empty → `{url:'',name:'',alt:''}`.
- **Int arrays** (`child_age`, `keywords`, `related_articles`, `related_video_articles`, `related_activities`, `related_milestone`) via `toIntArray`.
- **Int casts** (`id`, `field_type_of_article`, `category`, `subcategory`, `child_gender`, `parent_gender`, `licensed`, `premature`, `mandatory`, `growth_type`, `standard_deviation`, `growth_period`, `activity_category`, `equipment`, `type_of_support`, `pinned_video_article`, `pinned_article`, `old_calendar`, `related_article`, `chatbot_subcategory`, …) via `castToInt`: empty → `0`.
- **Endpoint-specific:** pinned-contents — Article rows drop `cover_video`/`cover_video_image`, Video-Article rows move `cover_video_image`→`cover_image`, plus **dedup by `id`**; basic-pages — add `unique_name`; articles/taxonomies — Pregnancy term handling (see [§4.3](#43-v1-query-parameters)).

> The per-row field **set** for each endpoint is selected by the corresponding `views.view.bebbo_v1_apis.yml` (or `tax`/`country_listing`/`sponsors_list`) display. The serializer renders those fields under short keys and applies the transforms above. For the concrete shapes, the V1 endpoints mirror their V2 equivalents in [§5](#5-v2-rest-endpoints-v2api) — except the V1-only endpoints (sponsors, the extra pinned/updated/related variants) and the grouped `archive` / nested `standard_deviation` shapes. The V1 JSON is byte-compatible with the legacy V1 contract.

### 4.3 V1 query parameters

- **`pregnancy=true`** — honored on **two** endpoints (verified):
  - **articles** — `bebbo_serializer.module`'s `hook_views_query_alter()` injects the Pregnancy `child_age` TID into the `bebbo_v1_apis`/`v1_articles_rest_export` filter.
  - **taxonomies** — `BebboV1Serializer` keeps the otherwise-hidden Pregnancy `child_age` term in the taxonomy output.
  - It is **not** an exposed Views filter.
- **`datetime="YYYY-MM-DD HH:MM"`** and the other filter params ([§6](#6-query-parameters)) are Views **contextual-filter arguments** (`query_parameter` default-argument plugin) on the `bebbo_v1_apis` view, not exposed filters. The V1 view has **no exposed filters** at all — `nid` is an output field, and the `%` path segment is a contextual filter argument, not an exposed filter.

---

## 5. V2 REST Endpoints (`/v2/api/*`)

**Dispatch:** `transformRows($displayId)` `match()` → `transformX()`. Mapping (display id → method → path), verified in `BebboSerializer.php` + `views.view.bebbo_v2_apis.yml`:

| Path | Display id | Transform |
|------|-----------|-----------|
| `/v2/api/activities/%` | `activities_rest_export` | `transformActivities` |
| `/v2/api/articles/%` | `articles_rest_export` | `transformArticles` |
| `/v2/api/video-articles/%` | `video_article_rest_export` | `transformVideoArticles` |
| `/v2/api/faqs/%` | `faq_rest_export` | `transformFaq` |
| `/v2/api/basic-pages/%` | `basic_page_rest_export` | `transformBasicPages` |
| `/v2/api/daily-homescreen-messages/%` | `home_screen_rest_export` | `transformDailyHomeScreenMessages` |
| `/v2/api/vaccinations/%` | `vaccination_rest_export` | `transformVaccinations` |
| `/v2/api/milestones/%` | `milestones_rest_export` | `transformMilestones` |
| `/v2/api/pinned-contents/%/child_development/40` | `child_dev_boy_rest_export` | `transformChildDevPinned` |
| `/v2/api/pinned-contents/%/child_development/41` | `child_dev_girl_rest_export` | `transformChildDevPinned` |
| `/v2/api/child-development-data/%` | `child_development_rest_export` | `transformChildDevelopment` |
| `/v2/api/child-growth-data/%` | `child_growth_rest_export` | `transformChildGrowth` |
| `/v2/api/health-checkup-data/%` | `health_checkup_rest_export` | `transformHealthCheckUps` |
| `/v2/api/surveys/%` | `survey_rest_export` | `transformSurveys` |
| `/v2/api/weekly-overview/%` | `weekly_overview_export` | `transformPregnancyWeekly` |
| `/v2/api/guide/%` | `guide_rest_export` | `transformGuide` |
| `/v2/api/standard_deviation/%` | `standard_deviation_rest_export` | `transformStandardDeviation` |
| `/v2/api/archive/%` | `archive_rest_export` | `transformArchive` |
| `/v2/api/course/%` | `course_rest_export` | `transformCourse` |
| `/v2/api/quiz/%` | `quiz_rest_export` | `transformQuiz` |
| `/v2/api/country-groups/%` | `country_listing_export` (country_listing view) | `transformCountryGroups`; `%` is a country/app slug (e.g. `wawamor`), not a langcode; envelope `langcode` resolves to the active/site language (slug arg isn't a valid language, so `resolveLangcode()` falls back to current language) |
| `/v2/api/vocabularies/%` | `vocabulary_rest_export` (tax view) | `transformVocabularies` |
| `/v2/api/taxonomies/%/%` | `terms_rest_export` (tax view) | `transformTaxonomies`; 2nd arg is a vocabulary machine name **or** `all` |
| `/v2/api/strings/%` | `v2_string_rest_export` (tax view) | Same fields/filters as V1 `/api/strings/%` — V2 URL alias |
| `/v2/api/check-update/{country}` | `CheckUpdateController::checkUpdate` (route `bebbo_serializer.v2_check_update`) | Same as V1 `/api/check-update/{country}` — same controller method, V2 URL path |

### 5.1 Envelope variants
- **Standard:** `{status,total,langcode,datetime,data}`; `total = (int) view->total_rows`.
- **Taxonomy / Vocabulary:** `{status,langcode,datetime,data}` — **no `total`**.
- **Standard deviation:** `{status,langcode,data}` — **no `total`, no `datetime`**.
- **Archive:** `data` is grouped `{ "<ContentType>": [id,…] }`; `total = array_sum(map count)` (sum of IDs, not entity count).
- **Guide:** `related_articles` key is **removed when empty** (not `[]`).
- **Empty / language error:** `204`/`400`/`403` as in [§2](#2-common-conventions-v1--v2-content-endpoints).

### 5.2 Type-cast helpers
`castToInt` (→int, default 0) · `castToBool` (`FILTER_VALIDATE_BOOLEAN`) · `castToNumber` (natural int/float, non-numeric→0) · `toIntArray` (dedup int array) · `toStringArray` (trim, drop empty) · `decodeHtmlEntities` (`htmlspecialchars_decode ENT_QUOTES|ENT_HTML5`). Media object `{url,name,alt}` (webp, absolute); video object `{url,name,site}`.

### 5.3 Per-endpoint response fields

Every `data[]` row includes both **typed keys** (explicitly cast by the transform — marked with type annotations below) and **passthrough keys** (rendered by the View's `data_field` row plugin and forwarded as-is). Both appear in the JSON response. The cast helpers (`castToInt`, `toIntArray`, etc.) only operate on keys already present in the View output — they skip missing keys.

**Common passthrough keys** present on most content endpoints (omitted from the per-endpoint table):
- `type` (string) — content-type label. Present on: activities, articles, video-articles, faqs, basic-pages, daily-homescreen-messages, vaccinations, milestones, pinned-contents, child-development-data, child-growth-data, health-checkup-data, quiz. **Not** on: course, weekly-overview, guide, standard-deviation, archive, country-groups. Surveys have a `type` key but it comes from `field_type` (survey type), not the content-type label.
- `created_at` (string) — creation timestamp. Present on all content endpoints **except** standard-deviation.
- `updated_at` (string) — last-modified timestamp. Present on all content endpoints **except** standard-deviation.

> **Both date fields are rendered HTML, not plain text.** Views' default date formatter emits a complete `<time>` element with a trailing newline, and no serializer transform strips it — on V1 *and* V2:
>
> ```
> "created_at": "<time datetime=\"2026-07-16T09:13:01+02:00\" class=\"datetime\">Thu, 07/16/2026 - 09:13</time>\n"
> ```
>
> The human-readable part uses Drupal's configured *medium* date format; the machine-readable value is the `datetime` attribute, which clients must parse out. V1 additionally escapes the markup as `\u003Ctime …` because its empty/success envelopes are encoded with plain `json`, while V2's `bebbo_json` encoder passes `<` through unescaped — the payload is otherwise identical. The abbreviated `"Wed, 06/15/2026 - 10:00"` values used in the examples further down show only that inner text; the wire format carries the full element.

| Endpoint | All response keys |
|----------|-------------------|
| **activities** | `id`(int), `activity_category`(int), `equipment`(int), `type_of_support`(int), `mandatory`(int), `read_count`(int), `love_count`(int), `child_age`(int[]), `related_milestone`(int[]), `embedded_images`(str[]), `title`(decoded), `cover_image`{url,name,alt}, `body`(HTML), `summary`(HTML) |
| **articles** | `id`(int), `field_type_of_article`(int), `category`(int), `subcategory`(int), `child_gender`(int), `parent_gender`(int), `premature`(int), `read_count`(int), `love_count`(int), `child_age`(int[]), `keywords`(int[]), `related_articles`(int[]), `related_video_articles`(int[]), `target_audience`(int[]), `embedded_images`(str[]), `title`(decoded), `cover_image`{url,name,alt} (built from `cover_image_mid/_url/_name/_alt`, empty→`{'','',''}`), `body`(HTML), `summary`(HTML), `meta_keywords`, `do_not_feature` |
| **video-articles** | `id`(int), `category`(int), `child_gender`(int), `parent_gender`(int), `licensed`(int), `premature`(int), `mandatory`(int), `read_count`(int), `love_count`(int), `child_age`(int[]), `keywords`(int[]), `related_articles`(int[]), `related_video_articles`(int[]), `target_audience`(int[]), `embedded_images`(str[]), `title`(decoded), `cover_video`{url,name,site}, `cover_image`{url,name,alt}, `body`(HTML), `summary`(HTML) |
| **faqs** | `id`(int), `chatbot_subcategory`(int), `related_article`(int), `mandatory`(int), `child_age`(int[]), `question`(decoded), `answer_part_1`(HTML), `answer_part_2`(HTML) |
| **basic-pages** | `id`(int), `mandatory`(int), `embedded_images`(str[]), `title`(decoded), `unique_name` (lowercased English title, spaces→`_`), `body`(HTML) |
| **daily-homescreen-messages** | `id`(int), `title`(decoded) |
| **vaccinations** | `id`(int), `growth_period`(int), `pinned_video_article`(int), `old_calendar`(int), `pinned_article`(int), `related_articles`(int[]), `title`(decoded), `uuid` |
| **milestones** | `id`(int), `mandatory`(int), `child_age`(int[]), `related_activities`(int[]), `related_articles`(int[]), `related_video_articles`(int[]), `title`(decoded), `body`(HTML) |
| **pinned-contents .../40 & .../41** | `id`(int), `category`(int), `child_gender`(int), `parent_gender`(int), `licensed`(int), `premature`(int), `mandatory`(int), `child_age`(int[]), `keywords`(int[]), `related_articles`(int[]), `embedded_images`(str[], batch-loaded from `field_embedded_images` — not a view field on these displays), `title`(decoded); dedup by `id`; Video-Article rows: `cover_video`{url,name,site}, `cover_image`{url,name,alt}; passthrough: `body`(HTML), `summary`(HTML) |
| **child-development-data** | `id`(int), `boy_video_article`(int), `girl_video_article`(int), `mandatory`(int), `child_age`(int[]), `title`(decoded), `milestone` |
| **child-growth-data** | `id`(int), `growth_type`(int), `standard_deviation`(int), `mandatory`(int), `child_age`(int[]), `pinned_articles`(int[]), `title`, `body`(HTML) |
| **health-checkup-data** | `id`(int), `growth_period`(int), `pinned_article`(int), `pinned_video_article`(int), `title`(decoded); dedup by `id`. Note: the transform casts additional fields (`category`, `child_gender`, etc.) but the View display only provides the 8 fields listed here — the extra casts are no-ops. |
| **surveys** | `id`(int), `title`(decoded), `body`(HTML), `type` (survey type from `field_type`, not content-type label), `survey_feedback_link` |
| **weekly-overview** | `id`(int), `prental_age`(int), `licensed`(int), `average_height`(number), `average_weight`(number), `related_articles`(int[]), `featured_image_1`{url,name,alt}, `featured_image_2`{url,name,alt}, `title`, `fun_fact` |
| **guide** | `id`(int), `child_age`(int), `licensed`(int), `related_articles`(int[], dropped if empty), `related_games`(int[]), `title`, `message` |
| **archive** | grouped `{ "<Type>": [id,…] }` |
| **country-groups** | see [§5.5](#55-country-groups) |
| **course** | see [§5.4](#54-course--quiz) |
| **quiz** | see [§5.4](#54-course--quiz) |

> **Engagement counts:** the emitted JSON keys are **`read_count`** and **`love_count`** (present only on activities, articles, video-articles, course). Note the underlying Drupal field is `field_like_count` (see `pb_content_analytics`), but the V2 API key is `love_count`, not `like_count`. There is no `like_count` key in the API output.

#### JSON response examples

All content endpoints return the **standard envelope** wrapping a `data[]` array:

```json
{
  "status": 200,
  "total": 177,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [ ...items... ]
}
```

Envelope variants: **taxonomy/vocabulary** omit `total`; **standard-deviation** omits `total` and `datetime`; **archive** has a grouped `data` object (not array). See [§5.1](#51-envelope-variants).

The examples below show the **complete response** (envelope + a representative `data[]` item) for each endpoint. Timestamps (`created_at`, `updated_at`) are abbreviated to their inner text for readability — the wire format wraps them in a `<time>` element, as described in [§5.3](#53-per-endpoint-response-fields) above.

---

**Activities** (`/v2/api/activities/%`):
```json
{
  "status": 200,
  "total": 177,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 456,
      "type": "Activity",
      "title": "Sing and Clap Together",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Clap your hands while singing a favorite song...</p>",
      "summary": "A fun singing activity for toddlers",
      "activity_category": 3,
      "equipment": 0,
      "type_of_support": 2,
      "mandatory": 0,
      "child_age": [10, 20],
      "related_milestone": [100, 101],
      "embedded_images": ["https://example.com/sites/default/files/img1.webp"],
      "cover_image": {"url": "https://example.com/image.webp", "name": "activity-cover", "alt": "Children singing"},
      "love_count": 25,
      "read_count": 150
    }
  ]
}
```

---

**Articles** (`/v2/api/articles/%`):
```json
{
  "status": 200,
  "total": 350,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 123,
      "type": "Article",
      "title": "Breastfeeding Basics",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Breastfeeding provides essential nutrition...</p>",
      "summary": "A guide to breastfeeding for new mothers",
      "field_type_of_article": 1,
      "category": 5,
      "subcategory": 12,
      "child_gender": 0,
      "parent_gender": 0,
      "premature": 0,
      "child_age": [10, 20, 30],
      "keywords": [45, 67],
      "related_articles": [124, 125],
      "related_video_articles": [200],
      "target_audience": [1, 2],
      "embedded_images": ["https://example.com/sites/default/files/img1.webp"],
      "cover_image": {"url": "https://example.com/image.webp", "name": "cover", "alt": "Mother breastfeeding"},
      "love_count": 100,
      "read_count": 500,
      "meta_keywords": "breastfeeding, nutrition, baby",
      "do_not_feature": "0"
    }
  ]
}
```

> `cover_image` is built from intermediate View fields (`cover_image_mid`, `cover_image_url`, `cover_image_name`, `cover_image_alt`) which are removed from output. Image URL is converted to WebP. When `cover_image_mid` is 0/empty, returns `{"url":"","name":"","alt":""}`.

---

**Video Articles** (`/v2/api/video-articles/%`):
```json
{
  "status": 200,
  "total": 90,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 200,
      "type": "Video Article",
      "title": "How to Bathe Your Baby",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Step-by-step guide to bathing your newborn...</p>",
      "summary": "Safe bathing tips for new parents",
      "category": 3,
      "child_gender": 0,
      "parent_gender": 0,
      "licensed": 0,
      "premature": 0,
      "mandatory": 0,
      "child_age": [10, 20],
      "keywords": [45],
      "related_articles": [123],
      "related_video_articles": [201],
      "target_audience": [1],
      "embedded_images": ["https://example.com/sites/default/files/img.webp"],
      "cover_video": {"url": "https://www.youtube.com/watch?v=abc123", "name": "bath-video", "site": "youtube"},
      "cover_image": {"url": "https://example.com/thumb.webp", "name": "thumbnail", "alt": "Baby bath"},
      "love_count": 75,
      "read_count": 300
    }
  ]
}
```

> `cover_video` is parsed from an embedded view rendering a remote_video/video media entity. `site` is `"youtube"` or `"vimeo"`. `cover_image` is the video thumbnail parsed via `parseViewCoverImage`.

---

**FAQs** (`/v2/api/faqs/%`):
```json
{
  "status": 200,
  "total": 210,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 300,
      "type": "FAQ",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "question": "When should I start solid foods?",
      "answer_part_1": "<p>Around 6 months of age...</p>",
      "answer_part_2": "<p>Start with soft, mashed foods...</p>",
      "chatbot_subcategory": 5,
      "related_article": 123,
      "mandatory": 0,
      "child_age": [20, 30]
    }
  ]
}
```

> FAQs have no `title` key — the node title is aliased to `question`. No media fields, no engagement counts, no embedded images.

---

**Basic Pages** (`/v2/api/basic-pages/%`):
```json
{
  "status": 200,
  "total": 12,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 400,
      "type": "Basic page",
      "title": "About Bebbo",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Bebbo is a parenting app by UNICEF...</p>",
      "mandatory": 0,
      "embedded_images": ["https://example.com/sites/default/files/img.webp"],
      "unique_name": "about_bebbo"
    }
  ]
}
```

> `unique_name` is generated from the **English** node title (lowercased, spaces→`_`), regardless of the requested language. Empty string if no English title exists.

---

**Daily Homescreen Messages** (`/v2/api/daily-homescreen-messages/%`):
```json
{
  "status": 200,
  "total": 60,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 500,
      "type": "Daily Homescreen Message",
      "title": "Your baby loves hearing your voice!",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30"
    }
  ]
}
```

> Simplest content endpoint — only `id` and `title` are typed; `type`, `created_at`, `updated_at` pass through.

---

**Vaccinations** (`/v2/api/vaccinations/%`):
```json
{
  "status": 200,
  "total": 25,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 600,
      "type": "Vaccination",
      "title": "BCG Vaccine",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "growth_period": 1,
      "pinned_article": 123,
      "pinned_video_article": 200,
      "related_articles": [124, 125],
      "old_calendar": 0
    }
  ]
}
```

> Only vaccination endpoint exposes `uuid`. `related_articles` comes from `field_related_articles_vacci` (a separate field from the standard `field_related_articles`).

---

**Milestones** (`/v2/api/milestones/%`):
```json
{
  "status": 200,
  "total": 45,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 700,
      "type": "Milestone",
      "title": "First Steps",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Most children take their first steps between 9 and 12 months...</p>",
      "mandatory": 0,
      "child_age": [30, 40],
      "related_activities": [456],
      "related_articles": [123],
      "related_video_articles": [200]
    }
  ]
}
```

---

**Pinned Contents — Boy/Girl** (`/v2/api/pinned-contents/%/child_development/40` and `.../41`):

Response contains both Article-type and Video-Article-type rows in the same `data[]` array:
```json
{
  "status": 200,
  "total": 8,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 123,
      "type": "Article",
      "title": "Fine Motor Skills at 12 Months",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>At 12 months your child may...</p>",
      "summary": "Motor development milestones",
      "category": 5,
      "child_gender": 0,
      "parent_gender": 0,
      "licensed": 0,
      "premature": 0,
      "mandatory": 0,
      "child_age": [30],
      "keywords": [45],
      "related_articles": [124],
      "embedded_images": ["https://example.com/sites/default/files/img1.webp"]
    },
    {
      "id": 201,
      "type": "Video Article",
      "title": "Watch: 12-Month Motor Skills",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>...</p>",
      "summary": "...",
      "category": 5,
      "child_gender": 0,
      "parent_gender": 0,
      "licensed": 0,
      "premature": 0,
      "mandatory": 0,
      "child_age": [30],
      "keywords": [45],
      "related_articles": [123],
      "embedded_images": [],
      "cover_video": {"url": "https://www.youtube.com/watch?v=xyz", "name": "motor-skills", "site": "youtube"},
      "cover_image": {"url": "https://example.com/thumb.webp", "name": "thumbnail", "alt": "Motor skills video"}
    }
  ]
}
```

> Rows are **deduplicated by `id`**. Article-type rows do **not** get `cover_video`/`cover_image`; only Video Article rows do. The `boy_video_article`/`girl_video_article` keys listed in earlier doc versions are **not** present — those fields belong to the child-development-data endpoint.

---

**Child Development Data** (`/v2/api/child-development-data/%`):
```json
{
  "status": 200,
  "total": 18,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 800,
      "type": "Child development",
      "title": "12-18 Months Development",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "child_age": [30, 40],
      "boy_video_article": 200,
      "girl_video_article": 201,
      "mandatory": 0,
      "milestone": "First words, walking, grasping objects"
    }
  ]
}
```

> `milestone` is from `field_milestone_instructions` (passthrough). This endpoint returns the child_development nodes themselves — unlike pinned-contents which returns the referenced articles.

---

**Child Growth Data** (`/v2/api/child-growth-data/%`):
```json
{
  "status": 200,
  "total": 6,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 900,
      "type": "Child growth",
      "title": "Weight for Age",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Weight-for-age chart guidance...</p>",
      "growth_type": 1,
      "standard_deviation": 2,
      "child_age": [10, 20],
      "pinned_articles": [123],
      "mandatory": 0
    }
  ]
}
```

> `title` passes through as-is (not `decodeHtmlEntities`). `pinned_articles` is aliased from `field_related_articles`.

---

**Health Checkup Data** (`/v2/api/health-checkup-data/%`):
```json
{
  "status": 200,
  "total": 14,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 1000,
      "type": "Health checkup",
      "title": "6-Month Checkup",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "growth_period": 3,
      "pinned_article": 123,
      "pinned_video_article": 200
    }
  ]
}
```

The **V1** endpoint `/api/health-checkup-data/%` returns the same eight keys from the same source content, served by `bebbo_v1_serializer` instead. Verified live response:

```json
{
  "status": 200,
  "total": 12,
  "langcode": "en",
  "data": [
    {
      "id": 66891,
      "type": "Health Check-ups - Age Periods",
      "title": "Tests content 16july deployement sanity",
      "growth_period": 6476,
      "pinned_article": 65671,
      "pinned_video_article": 58511,
      "created_at": "<time datetime=\"2026-07-16T09:13:01+02:00\" class=\"datetime\">Thu, 07/16/2026 - 09:13</time>\n",
      "updated_at": "<time datetime=\"2026-07-16T09:20:28+02:00\" class=\"datetime\">Thu, 07/16/2026 - 09:20</time>\n"
    }
  ]
}
```

`type` is the content-type label **"Health Check-ups - Age Periods"** — note the hyphen in "Check-ups", which differs from the endpoint path segment `health-checkup-data` and from the `health_check_ups` machine name used in the V1 pinned-contents path. Rows are deduplicated by `id`. Do not confuse this endpoint with `/api/check-update/{country}`, the force-update endpoint documented in [§9](#9-force-update--check-update-endpoint).

> Rows are **deduplicated by `id`**. The View display only provides these 8 keys. The transform contains additional casts for `category`, `child_gender`, `parent_gender`, `licensed`, `premature`, `mandatory`, `child_age`, `related_articles`, `related_video_articles` and cover-image type-switching logic, but those are all no-ops since the View does not include those fields.

---

**Surveys** (`/v2/api/surveys/%`):
```json
{
  "status": 200,
  "total": 4,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 1100,
      "title": "Parenting Confidence Survey",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "body": "<p>Rate how confident you feel about...</p>",
      "type": "assessment",
      "survey_feedback_link": "https://example.com/survey"
    }
  ]
}
```

> `type` here is from `field_type` (the survey's own type field), **not** the content-type label — allowed values: `survey`, `special_survey`, `feedback`, `user_guide`, `donate`. This is the only endpoint where `type` does not mean the Drupal bundle label. No common `type` passthrough from `node_field_data.type`.

---

**Weekly Overview** (`/v2/api/weekly-overview/%`):
```json
{
  "status": 200,
  "total": 42,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 1200,
      "title": "Week 20",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "prental_age": 20,
      "licensed": 0,
      "average_height": 25.5,
      "average_weight": 0.3,
      "related_articles": [123, 124],
      "featured_image_1": {"url": "https://example.com/w20.webp", "name": "week20", "alt": "Week 20"},
      "featured_image_2": {"url": "https://example.com/w20b.webp", "name": "week20b", "alt": "Week 20 size"},
      "fun_fact": "Your baby is about the size of a banana!"
    }
  ]
}
```

> No `type` passthrough (View omits `node_field_data.type`). `average_height`/`average_weight` use `castToNumber` (natural int or float, non-numeric→0). `featured_image_1`/`featured_image_2` are parsed from embedded view renders.

---

**Guide** (`/v2/api/guide/%`):
```json
{
  "status": 200,
  "total": 6,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 1300,
      "title": "0-6 Months Guide",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "child_age": 10,
      "licensed": 0,
      "related_articles": [123, 124],
      "related_games": [456, 457],
      "message": "Welcome to the first 6 months!"
    }
  ]
}
```

> `related_articles` is **removed entirely** (not set to `[]`) when empty. No `type` passthrough.

---

**Standard Deviation** (`/v2/api/standard_deviation/%`):

Full response (non-standard envelope — no `total`, no `datetime`):
```json
{
  "status": 200,
  "langcode": "en",
  "data": {
    "weight_for_height": [
      {
        "child_age": [10, 20],
        "goodText": {
          "articleID": [123],
          "name": "Normal growth",
          "text": "<p>Your child's weight is within the normal range.</p>"
        },
        "warrningSmallHeightText": {
          "articleID": [124],
          "name": "Warning - low",
          "text": "<p>Your child may be underweight...</p>"
        },
        "emergencySmallHeightText": {
          "articleID": [],
          "name": "Emergency - low",
          "text": "<p>Seek medical attention...</p>"
        },
        "warrningBigHeightText": {
          "articleID": [125],
          "name": "Warning - high",
          "text": "<p>Your child may be overweight...</p>"
        },
        "emergencyBigHeightText": {
          "articleID": [],
          "name": "Emergency - high",
          "text": "<p>Seek medical attention...</p>"
        }
      }
    ],
    "height_for_age": [
      {
        "child_age": [10, 20],
        "goodText": {
          "articleID": [126],
          "name": "Normal height",
          "text": "<p>Your child's height is within the normal range.</p>"
        },
        "warrningSmallLengthText": {
          "articleID": [127],
          "name": "Warning - short",
          "text": "<p>Your child may be shorter than expected...</p>"
        },
        "emergencySmallLengthText": {
          "articleID": [],
          "name": "Emergency - short",
          "text": "<p>Seek medical attention...</p>"
        },
        "warrningBigLengthText": {
          "articleID": [128],
          "name": "Warning - tall",
          "text": "<p>Your child is taller than expected...</p>"
        }
      }
    ]
  }
}
```

> Each SD-label object has `{articleID(int[]), name(string), text(string)}`. `data` is grouped by growth type, then bucketed by child-age ranges. Internal growth type `height_for_weight` is renamed to `weight_for_height` in output.

---

**Archive** (`/v2/api/archive/%`):

Full response (standard envelope, but `data` is a **keyed object** not an array):
```json
{
  "status": 200,
  "total": 15,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": {
    "Article": [123, 456, 789],
    "Video Article": [200, 201],
    "FAQ": [300, 301],
    "Activity": [450, 451],
    "Basic page": [400]
  }
}
```

> `total` is the **sum of all IDs** across content types, not the number of content types. Keys are content-type labels; values are int arrays of deleted node IDs.

---

**Vocabularies** (`/v2/api/vocabularies/%`):

Full response (no `total` in envelope):
```json
{
  "status": 200,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": {
    "child_age": {"name": "Child's Age"},
    "category": {"name": "Category"},
    "growth_period": {"name": "Growth Period"},
    "activity_category": {"name": "Activity Category"},
    "growth_type": {"name": "Growth Type"},
    "child_gender": {"name": "Child Gender"},
    "parent_gender": {"name": "Parent Gender"}
  }
}
```

> Returns a **keyed object** (not array). Each key is a vocabulary machine name; value is `{"name": "Translated Label"}`. The `keywords` vocabulary is always skipped. Labels prefer the requested language's translation; fall back to the default-language label.

---

**Taxonomies** (`/v2/api/taxonomies/%/%`):

Full response (no `total` in envelope, keyed by vocabulary):
```json
{
  "status": 200,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": {
    "growth_period": [
      {"id": 1, "name": "0-2 months", "vaccination_opens": 0, "short_name": "0-2m", "unique_name": "0_2_months"}
    ],
    "child_age": [
      {"id": 10, "name": "0-1 month", "days_from": 0, "days_to": 30, "buffers_days": 5, "age_bracket": [1, 2]}
    ],
    "growth_introductory": [
      {"id": 50, "name": "Newborn", "body": "<p>Your newborn...</p>", "days_from": 0, "days_to": 30}
    ],
    "chatbot_subcategory": [
      {"id": 60, "name": "Feeding", "parent_category_id": 5, "unique_name": "feeding"}
    ],
    "category": [
      {"id": 5, "name": "Nutrition", "unique_name": "nutrition", "field_type_of_article": "article_for_birth_to_6_years"}
    ],
    "activity_category": [
      {"id": 3, "name": "Music & Songs", "unique_name": "music_songs"}
    ],
    "type_of_article": [
      {"id": 1, "name": "General"}
    ]
  }
}
```

> **Specialty vocabs** (`growth_period`, `child_age`, `growth_introductory`, `chatbot_subcategory`, `category`): each has a unique term shape (see keys above). **Unique-name vocabs** (`growth_type`, `activity_category`, `child_gender`, `parent_gender`, `relationship_to_parent`, `chatbot_category`, `subcategory`, `target_audience`, `course_category`): `{id, name, unique_name}`. **Basic vocabs** (everything else): `{id, name}`. The `keywords` vocabulary is always skipped.

---

**Country-groups** (`/v2/api/country-groups/%`):

Full response (standard envelope; `langcode` resolves to the active/site language — `en` shown here as the default-site example):
```json
{
  "status": 200,
  "total": 6,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "CountryID": "45",
      "name": "Bangladesh",
      "country_email": "admin@babuni.app",
      "app_name": "Babuni",
      "content_toggle": "1, 2",
      "country_national_partner": {"url": "https://example.com/partner.webp", "name": "partner", "alt": "National Partner"},
      "country_sponsor_logo": {"url": "https://example.com/sponsor.webp", "name": "sponsor", "alt": "Sponsor"},
      "unicef_logo": {"url": "https://example.com/unicef.webp", "name": "unicef", "alt": "UNICEF"},
      "all_logos": {"url": "https://example.com/branding.webp", "name": "branding", "alt": "Branding"},
      "country_flag": {"url": "https://example.com/flag.webp", "name": "flag", "alt": "Country Flag"},
      "languages": [
        {
          "name": "Bangladesh",
          "displayName": "বাংলা",
          "languageCode": "bn",
          "locale": "bn_BD",
          "luxonLocale": "bn",
          "pluralShow": "1",
          "content_toggle": "1"
        }
      ]
    },
    {
      "CountryID": "126",
      "name": "Rest of the world",
      "displayName": "Rest of the world",
      "country_email": "",
      "app_name": "Bebbo",
      "content_toggle": "",
      "country_national_partner": {"url": "", "name": "", "alt": ""},
      "country_sponsor_logo": {"url": "", "name": "", "alt": ""},
      "unicef_logo": {"url": "", "name": "", "alt": ""},
      "all_logos": {"url": "", "name": "", "alt": ""},
      "country_flag": {"url": "", "name": "", "alt": ""},
      "languages": [
        {"name": "Rest of the world", "displayName": "English", "languageCode": "en", "locale": "en_US", "luxonLocale": "en", "pluralShow": "1"},
        {"name": "Rest of the world", "displayName": "Русский", "languageCode": "ru", "locale": "ru_RU", "luxonLocale": "ru", "pluralShow": "1"}
      ]
    }
  ]
}
```

> `CountryID 131` is filtered out. `CountryID 126` gets `displayName` (absent on other groups), hardcoded `en`+`ru` languages (without per-language `content_toggle`), and is always moved to the end of the array. See [§5.5](#55-country-groups) for full key details.

### 5.4 Course & Quiz

#### `/v2/api/course/%`

Full response shape (standard envelope + all data keys):

```json
{
  "status": 200,
  "total": 5,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 123,
      "title": "Parenting Skills",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "course_description": "<p>Course overview HTML</p>",
      "course_category": [7, 8],
      "child_age": [10, 20, 30],
      "cover_image": {"url": "https://example.com/image.webp", "name": "cover", "alt": "Cover"},
      "course_duration": 30,
      "course_expiry": "2027-06-15",
      "target_audience": [5, 6],
      "course_link": "https://example.com/course",
      "module_numbering": "1.1",
      "number_of_modules": 3,
      "module_locked": false,
      "certificate": {"url": "https://example.com/cert.webp", "name": "cert", "alt": "Certificate"},
      "final_assessment": 456,
      "feedback_required": true,
      "feedback_question": ["How was the course?", "Would you recommend it?"],
      "love_count": 50,
      "read_count": 100,
      "licensed": 1,
      "module": [
        {
          "module_title": "Introduction to Responsive Caregiving",
          "content_numbering": "1.1",
          "optional_module": false,
          "course_content": [101, 102, 103],
          "resource_file": {"url": "https://example.com/handout", "name": "Handout"},
          "resource_file_internal": {"url": "https://example.com/guide.pdf", "name": "Facilitator Guide"}
        }
      ]
    }
  ]
}
```

**Course top-level keys:**

| Key | Type | Source / Notes |
|-----|------|---------------|
| `id` | int | Node ID (`castToInt`) |
| `title` | string | Node title (`decodeHtmlEntities`) |
| `created_at` | string | Formatted creation timestamp (passthrough) |
| `updated_at` | string | Formatted last-modified timestamp (passthrough) |
| `course_description` | string | HTML body from `field_description` (passthrough) |
| `course_category` | int[] | Taxonomy term IDs (`toIntArray`) |
| `child_age` | int[] | Child-age term IDs (`toIntArray`) |
| `cover_image` | {url,name,alt} | WebP image via `parseViewCoverImage` |
| `course_duration` | int | Duration in minutes (`castToInt`) |
| `course_expiry` | string | Expiry date (passthrough) |
| `target_audience` | int[] | Audience term IDs (`toIntArray`) |
| `course_link` | string | External course URL (passthrough) |
| `module_numbering` | string | Numbering style from `field_module_numbering_style` (passthrough) |
| `number_of_modules` | int | Module count (`castToInt`) |
| `module_locked` | bool | Must complete modules in order (`castToBool`) |
| `certificate` | {url,name,alt} | Certificate image via `parseViewCoverImage` |
| `final_assessment` | int | Assessment quiz node ID (`castToInt`, 0 if none) |
| `feedback_required` | bool | Whether post-course feedback is required (`castToBool`) |
| `feedback_question` | string[] | Feedback question prompts (`toStringArray`, raw output) |
| `love_count` | int | Like/love count (`castToInt`) |
| `read_count` | int | Read count (`castToInt`) |
| `licensed` | int | Licensed content flag (`castToInt`) |
| `module` | array | Course modules — see below |

> Note: course does **not** have the common `type` passthrough key (the View display omits `node_field_data.type`).

**Module object** (each element in `module[]`, sourced from `courses_module` nodes via `field_course_modules`, language-aware, delta-ordered):

| Key | Type | Description |
|-----|------|-------------|
| `module_title` | string | Module title (`field_module_title`) |
| `content_numbering` | string | Numbering/labeling style (`field_numbering_style`) |
| `optional_module` | bool | Whether module is optional (`field_optional_module`) |
| `course_content` | int[] | Referenced content node IDs (`field_course_content`) |
| `resource_file` | {url,name} \| null | External link resource (`field_resource_file_external`); `null` if not set |
| `resource_file_internal` | {url,name} \| null | Internal document media (`field_resource_file_internal`); `null` if not set |

---

#### `/v2/api/quiz/%`

Full response shape:

```json
{
  "status": 200,
  "total": 3,
  "langcode": "en",
  "datetime": "2026-06-17 12:00",
  "data": [
    {
      "id": 789,
      "type": "Quiz",
      "title": "Module 1 Assessment",
      "created_at": "Wed, 06/15/2026 - 10:00",
      "updated_at": "Mon, 06/16/2026 - 14:30",
      "instructions": "<p>Answer all questions to complete the assessment.</p>",
      "passing_score": 70,
      "number_of_questions": 5,
      "quiz_type": "assessment",
      "licensed": 1,
      "questions": [
        {
          "type": "multiple_choice",
          "question": "What is responsive caregiving?",
          "image": {"url": "https://example.com/q1.webp", "name": "q1", "alt": "Question image"},
          "answers": [
            {"answer": "Reacting to a child's cues", "correct_answer": true},
            {"answer": "Ignoring tantrums", "correct_answer": false}
          ],
          "explanation": "Responsive caregiving means noticing and responding to a child's signals."
        }
      ]
    }
  ]
}
```

**Quiz top-level keys:**

| Key | Type | Source / Notes |
|-----|------|---------------|
| `id` | int | Node ID (`castToInt`) |
| `type` | string | Content-type label (passthrough from `node_field_data.type`) |
| `title` | string | Node title (`decodeHtmlEntities`) |
| `created_at` | string | Formatted creation timestamp (passthrough) |
| `updated_at` | string | Formatted last-modified timestamp (passthrough) |
| `instructions` | string | HTML instructions from `field_instructions` (passthrough) |
| `passing_score` | int | Minimum passing score (`castToInt`) |
| `number_of_questions` | int | Total question count (`castToInt`) |
| `quiz_type` | string | Quiz type from `field_quiz_type` (passthrough) |
| `licensed` | int | Licensed content flag (`castToInt`) |
| `questions` | array | Quiz questions — see below |

**Question object** (each element in `questions[]`, sourced from `quiz_questions` nodes via `field_quiz_questions`, language-aware, delta-ordered):

| Key | Type | Description |
|-----|------|-------------|
| `type` | string | Question type (`field_question_type`, e.g. `multiple_choice`) |
| `question` | string | Question text (`field_question`) |
| `image` | {url,name,alt} | Question image; `{"url":"","name":"","alt":""}` if not set |
| `answers` | array | Answer options from `field_answers` (empty answers filtered out) |
| `explanation` | string | Explanation text (`field_explanation`) |

**Answer object** (each element in `answers[]`):

| Key | Type | Description |
|-----|------|-------------|
| `answer` | string | Answer text |
| `correct_answer` | bool | Whether this is the correct answer |

### 5.5 Country-groups

`transformCountryGroups` filters out `CountryID 131`, dedups by CountryID, parses media, builds language arrays, and removes raw `langcode`. The path `%` argument is a **country/app slug** (e.g. `wawamor`, `babuni`), not a langcode; because the slug is not a valid language, `resolveLangcode()` falls back to the active/site language, so the envelope `langcode` is the current site language (not hardcoded `en`). `CountryID 126` ("Rest of the world") gets hardcoded `en`+`ru` languages and is moved to the end.

**Country-group object keys:**

| Key | Type | Notes |
|-----|------|-------|
| `CountryID` | string | Group entity ID |
| `name` | string | Country/group name |
| `country_email` | string | Contact email (passthrough) |
| `app_name` | string | App display name (passthrough) |
| `content_toggle` | string | Default-language content toggle (overridden by transform from Group entity) |
| `country_national_partner` | {url,name,alt} | National partner logo (parsed from view embed) |
| `country_sponsor_logo` | {url,name,alt} | Sponsor logo (parsed from view embed) |
| `unicef_logo` | {url,name,alt} | UNICEF logo (parsed from view embed) |
| `all_logos` | {url,name,alt} | 2.0 branding image (parsed from view embed, WebP) |
| `country_flag` | {url,name,alt} | Country flag image (parsed from view embed, WebP) |
| `languages` | array | Language objects — see below |
| `displayName` | string | **Only on CountryID 126** ("Rest of the world"); absent on other groups |

**Language object** (each element in `languages[]`, weight-sorted, weight removed):

| Key | Type | Notes |
|-----|------|-------|
| `name` | string | Country name |
| `displayName` | string | Language display name (from `custom_language_data.custom_language_name_local`) |
| `languageCode` | string | BCP-47 code (`en`, `bn`, etc.) |
| `locale` | string | POSIX locale (`en_US`, `bn_BD`, etc.) |
| `luxonLocale` | string | Luxon locale string |
| `pluralShow` | string | Plural display flag |
| `content_toggle` | string | Per-language content toggle (present on regular groups only, absent on CountryID 126) |

### 5.6 ETag / conditional requests

`BebboSerializer::checkEtag()` enables conditional requests on **5 specific V2 displays only**: `articles_rest_export`, `video_article_rest_export`, `activities_rest_export`, `faq_rest_export`, `basic_page_rest_export`. All other V2 displays skip ETag processing.

1. Computes an ETag from a **lightweight SQL fingerprint**: `MD5(bundle + MAX(changed) + COUNT(*) + queryString)` queried against `node_field_data` — **not** a hash of the response body.
2. Stores the ETag on the request attributes (`bebbo_etag`).
3. If `If-None-Match` request header matches → sets `bebbo_etag_match = true`.
4. `EtagResponseSubscriber` (priority 0, `KernelEvents::RESPONSE`) reads those attributes: on match, replaces the response with **304 Not Modified** (empty body); otherwise sets the `ETag` response header.

The mobile app should send `If-None-Match: "<etag>"` to skip re-downloading unchanged content.

### 5.7 Body image processing (V2)

`BodyImageProcessor` (`bebbo_serializer.body_image_processor`) populates embedded images at **presave time** (not request time):

1. Renders the body HTML through Drupal's text format pipeline (resolves `<drupal-media>` → `<img>`).
2. Extracts all image URLs from the rendered body.
3. Converts internal image URLs to WebP via image style with `itok` security tokens.
4. Returns a `string[]` of extracted URLs; the calling code stores this into `field_embedded_images`.

V1 did equivalent work at request time via DOMDocument parsing per row (N+1 entity loads). V2 front-loads this to content save, so the API serializer reads a pre-computed field.

---

## 6. Query Parameters

Configured as **Views contextual filters** (arguments) using the `query_parameter` default-argument plugin with `query_param` keys (verified in the view configs) — **not** exposed filters. They apply to the article-family endpoints (V1 and V2):

| Param | Type | Meaning | V1 displays | V2 displays |
|-------|------|---------|-------------|-------------|
| `datetime` | ISO timestamp | Return rows changed after this time (`changed` filter) | 7 | 5 |
| `childAge` | int (term id) | Filter by child-age term | 1 | 1 |
| `childGender` | int (term id) | Filter by child gender | 1 | 1 |
| `parentGender` | int (term id) | Filter by parent gender | 1 | 1 |
| `category` | int (term id) | Filter by content category | 1 | 1 |
| `typeArticle` | int (term id) | Filter by type-of-article | 1 | 1 |
| `pre_populated` | `0`/`1` | Filter pre-populated content | 3 | 3 |
| `pregnancy` | `true` | Include Pregnancy child-age term (serializer/`hook_views_query_alter`, not an exposed filter) | articles/taxonomies | `articles_rest_export` + child_age taxonomy |

> `pregnancy` is **not** a Views exposed filter — V2 injects the Pregnancy term id into the `field_child_age` IN-condition via `bebbo_serializer`'s `hook_views_query_alter()` (only on `bebbo_v2_apis` / `articles_rest_export`), and `queryChildAgeTerms()` excludes the Pregnancy term unless `pregnancy=true`.

---

## 7. Taxonomy & Vocabulary

Both versions return **keyed objects**, not flat arrays.

- **Vocabularies** (`/api/vocabularies/%`, `/v2/api/vocabularies/%`): `{ "<machine_name>": {"name": "Label"} }`. V2 prefers the translated label and **skips `keywords`**.
- **Taxonomies** (`/api/taxonomies/{lang}/{vocab}`, `/v2/api/taxonomies/{lang}/{vocab}`): `{ "<vocab>": [ term, … ] }`. The `{vocab}` argument is a **vocabulary machine name** (e.g. `child_age`) **or** the literal **`all`** — `all` returns terms for every vocabulary in one response (e.g. `/api/taxonomies/en/all` is valid). V2 per-vocab term shapes (`transformTaxonomies`):
  - `growth_period`: `{id,name,vaccination_opens(int),short_name,unique_name}`
  - `child_age`: `{id,name,days_from,days_to,buffers_days,age_bracket(int[])}`
  - `growth_introductory`: `{id,name,body,days_from,days_to}`
  - `chatbot_subcategory`: `{id,name,parent_category_id(int),unique_name}`
  - `category`: `{id,name,unique_name,field_type_of_article}` — `field_type_of_article` is a machine name derived from the referenced term's English label, so it does not vary by `{lang}`.
  - unique-name vocabs: `{id,name,unique_name}`
  - basic vocabs: `{id,name}`
  - `keywords` is always skipped.

V1 (`bebbo_v1_serializer`) produces an equivalent keyed map (byte-identical to V2); both envelopes omit `total`.

### 7.1 Strings (`/api/strings/%`, `/v2/api/strings/%`)

UI label strings for the mobile app, stored as terms in the **`strings`** vocabulary. Both paths are displays on the `tax` view (`string_rest_export` for V1, `v2_string_rest_export` for V2) and both use the **`bebbo_serializer`** style, even though one sits under `/api/`. The two displays carry identical fields, filters and arguments, so the responses are byte-identical.

| Aspect | Value |
|--------|-------|
| Row plugin | `data_field` — **no `transformX()` method runs**, so field values are emitted exactly as Views renders them |
| Fields | `name`, `field_unique_name`, `status`, `changed` |
| Filters | `status = 1` (published only), `vid = strings` |
| Arguments | `langcode` (path arg 1), `changed` (optional — see below) |
| Envelope | Standard `{status,total,langcode,datetime,data}` |

**Response fields**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | The translated string value for the requested langcode |
| `field_unique_name` | string | Stable machine key the app looks the string up by — this, not the term ID, is the contract |
| `status` | string | Published flag rendered by Views as the **string** `"1"`, not an integer or boolean |
| `changed` | string | **Rendered HTML**, not a timestamp — Views' default date formatter emits a full `<time datetime="…" class="datetime">…</time>` element, including a trailing newline. Clients that need a machine-readable value must parse the `datetime` attribute. |

Example — `GET /api/strings/en`:

```json
{
  "status": 200,
  "total": 1,
  "langcode": "en",
  "datetime": "2026-08-05 18:00",
  "data": [
    {
      "name": "term 1",
      "field_unique_name": "testing_term1",
      "status": "1",
      "changed": "<time datetime=\"2026-07-16T09:43:25+02:00\" class=\"datetime\">Thu, 07/16/2026 - 09:43</time>\n"
    }
  ]
}
```

`GET /v2/api/strings/en` returns exactly the same body.

> The `status` and `changed` shapes are a consequence of the `data_field` row plugin: unlike every other endpoint, no serializer transform normalises them. Treat them as raw Views output.

The vocabulary is editable at `/admin/structure/taxonomy/manage/strings/overview`, with per-string translation at `/admin/structure/taxonomy/manage/strings/strings-list` (provided by the `pb_strings` module). Neither page has an editorial-menu entry.

---

## 8. Standard Deviation

Two distinct paths — **do not conflate**:

| Path | View / style | Output |
|------|--------------|--------|
| `/api/standard_deviation/%` (V1) | `views.view.bebbo_v1_apis.yml` / **`bebbo_v1_serializer`** | `{status,langcode,data}` (SD logic folded into `BebboV1Serializer`; the old `pb_custom_standard_deviation` module is removed) |
| `/v2/api/standard_deviation/%` (V2) | `views.view.bebbo_v2_apis.yml` / `bebbo_serializer` | `{status,langcode,data}` |

Both build nested growth-type structures keyed `height_for_age` and `weight_for_height` (the latter renamed from the internal `height_for_weight`), bucketed by child-age ranges, with SD-label keys such as `goodText`, `warrningSmallLengthText`, `emergencySmallLengthText`, `warrningBigLengthText` (height_for_age) and `goodText`, `warrningSmallHeightText`, `emergencySmallHeightText`, `warrningBigHeightText`, `emergencyBigHeightText` (weight_for_height). Each SD entry carries `child_age` (int[]) and per-label objects `{articleID:int[], name, text}`. Empty results → `{status:204,"message":"No Records Found"}`; bad langcode → `{status:400}`.

> Not part of the mobile API: the admin `standard-deviation` page and the `taxonomy-*-export-standard-deviation/%` CSV exports are separate views.

---

## 9. Force-Update / Check-Update Endpoint

**`GET /api/check-update/{country}`** (V1) and **`GET /v2/api/check-update/{country}`** (V2) — both served by `CheckUpdateController::checkUpdate` in `bebbo_serializer` (routes `bebbo_serializer.v1_check_update` / `.v2_check_update`, defined in `bebbo_serializer.routing.yml`). Both routes call the **same** controller method, so the two responses are identical.

| Property | Value |
|----------|-------|
| Method | `GET` only |
| Format | `json` |
| **Authentication (V1 `/api/check-update/`)** | **Public** — route `_access: 'TRUE'`; not under `/v2/api/`, so the `bebbo_api_security` JWT subscriber never matches it |
| **Authentication (V2 `/v2/api/check-update/`)** | **JWT-gated** when enforcement is on — matches the `/v2/api/` protected pattern; the V2 route also sets `no_cache: TRUE` to prevent a cached 200 being replayed to unauthenticated callers |
| `{country}` | country **group entity ID** (matched against `countries_id`) |
| Source | latest row per `(countries_id, update_type)` in `forcefull_check_update_api` |

Response (both record types present):
```json
{
  "status": 200,
  "flag": 1,
  "updated_at": "1609459200",
  "content_update": { "flag": 1, "updated_at": "1609459200" },
  "app_update": {
    "flag": 0,
    "updated_at": "1712345678",
    "google_play_url": "https://play.google.com/store/apps/details?id=...",
    "app_store_url": "https://apps.apple.com/app/..."
  }
}
```
- Top-level `flag`/`updated_at` mirror `content_update` for **backward compatibility**; both are `null` if no content-update record.
- `app_update` adds `google_play_url`/`app_store_url` (empty string coerced to `null`).
- When **both** record types are missing: body `{status:204,"message":"No Records Found"}` (HTTP status remains 200 — `204` is a body field, not the HTTP code).

### 9.1 `forcefull_check_update_api` table schema

| Column | Type | Description |
|--------|------|-------------|
| `id` | serial PK | Auto-increment |
| `uuid` | varchar(255) | UUID of admin user who triggered the update |
| `created_at` | varchar(255) | Timestamp of creation |
| `countries_id` | varchar(255) | Country group entity ID |
| `flag_status` | varchar(255) | `0` or `1` |
| `update_type` | varchar(50) | `content_update` or `app_update` |
| `google_play_url` | varchar(512), nullable | Play Store URL (app_update only) |
| `app_store_url` | varchar(512), nullable | App Store URL (app_update only) |

Populated via admin form at `/admin/config/parent-buddy/forcefull-update-check` (`pb_custom_form` module, `CustomForm`). Each form submission inserts a new row.

> Only the **V2** path (`/v2/api/check-update/`) is JWT-protected, via the `/v2/api/` pattern in the `bebbo_api_security` default protected set. The **V1** path (`/api/check-update/`) is public and is **not** in the protected set.

### 9.2 Group / App names per site (country IDs)

The `{country}` argument for check-update is a **group entity ID** (`countries_id`), and country-groups dedups/keys on the same group IDs (`CountryID`). Group IDs are **local to each site's database** — the same numeric ID means different things on different sites. DB-verified 2026-06-29:

**Default (Bebbo) site — 18 groups:**

| Group ID | Group | `app_name` |
|----------|-------|------------|
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

> There is **no** group `156` / "Türkiye" on the default site — Türkiye is the separate `turkey` site (group `1`, app `merhababebek`).

**Other sites (each group ID is local to its own site):**

| Site | Group ID | Group | `app_name` |
|------|----------|-------|------------|
| Bangladesh | 1 | Bangladesh | Babuni |
| Turkey | 1 | Türkiye | merhababebek |
| Ecuador | 1 | Ecuador | Wawamor |
| PK | 1 | Pakistan | pakistan |
| Pacific Islands (`somoa`) | 1 | Samoa | BebboPacific |
| Pacific Islands (`somoa`) | 6 | Fiji | BebboPacific |
| Zimbabwe | 1 | Zimbabwe | reraiumntwana |

> In `country-groups`, `CountryID 131` (Global - Russian) is filtered out and `CountryID 126` (Global - English, "Rest of the world") is moved to the end with hardcoded `en`+`ru` languages.

---

## 10. JSON:API

Core `jsonapi` + `jsonapi_extras`, both enabled.

| Setting | Value | Source |
|---------|-------|--------|
| Path prefix | `jsonapi` (→ `/jsonapi/*`) | `jsonapi_extras.settings.yml` |
| **Read-only** | **`true`** | `jsonapi.settings.yml` (`read_only: true`) |
| `include_count` | `true` | `jsonapi_extras.settings.yml` |
| `default_disabled` | `false` (resources exposed by default) | `jsonapi_extras.settings.yml` |
| `validate_configuration_integrity` | `false` | `jsonapi_extras.settings.yml` |
| Resource overrides | **none** (`0` `jsonapi_resource_config.*` files) | repo scan |

JSON:API exposes Drupal entities at `/jsonapi/{entity_type}/{bundle}` for **reads only** (write methods are disabled globally). No per-resource overrides are configured, so behavior is core defaults plus the global settings above.

---

## 11. Event Subscriber Pipeline (request/response lifecycle)

API requests pass through these event subscribers in priority order:

### Request phase (`KernelEvents::REQUEST`)

| Priority | Subscriber | Module | Effect |
|----------|-----------|--------|--------|
| **1000** | `RequestFormatSubscriber` | `bebbo_serializer` | Registers `bebbo_json` format → `application/json` MIME type. Prevents 406 errors on V2 routes. |
| **300** | `ApiSecuritySubscriber` | `bebbo_api_security` | JWT enforcement on protected paths (`/v2/api/*`, which covers `/v2/api/check-update/`). Mode: disabled/grace_period/enforced. |

### Response phase (`KernelEvents::RESPONSE`)

| Priority | Subscriber | Module | Effect |
|----------|-----------|--------|--------|
| **0** | `EtagResponseSubscriber` | `bebbo_serializer` | Sets ETag header; converts to 304 on matching `If-None-Match`. |
| **-10** | `ApiVaryResponseSubscriber` | `bebbo_serializer` | Runs after core's `FinishResponseSubscriber` (priority 0) and removes `Cookie` from `Vary` on `/api/*` and `/v{n}/api/*`. The API bodies do not depend on any cookie, so without this a request carrying any cookie would miss the shared-cache copy stored for a cookie-less one. `Accept-Encoding` and anything an edge added stay; HTML responses keep `Vary: Cookie`. |

> Both serializers call `filterLanguageDataForApi()` inline inside `transformCountryGroups()`; there is no response-phase language filter (no `language_visibility_control` `ApiResponseSubscriber`).

---

## 12. Caching Layers

### V1 (`BebboV1Serializer`) and V2 (`BebboSerializer`) — shared `BebboSerializerHelpers` trait

Both serializers use the same caching/batching mechanisms (verified in `BebboSerializerHelpers.php` + `bebbo_serializer.module`):

| Layer | Scope | Details |
|-------|-------|---------|
| **ETag / 304** | Per-endpoint | V2 only — 5 displays (see [§5.6](#56-etag--conditional-requests)). V1 has no ETag. |
| **Batched media resolution** | Per request | `resolveMediaIds()` / `parseViewCoverImage()` resolve all media IDs in a row set together (WebP image styles) — one query per row set, no per-row N+1 entity loads. |
| **Batched taxonomy/title lookups** | Per request | `queryBasicTermsBatch()` / `queryUniqueNameTermsBatch()` (taxonomy transforms); `getEnglishNodeTitles()` (shared) load in one query per request. |
| **Persistent cache** | Across requests | Pregnancy term TID: permanent, cid `bebbo_serializer:pregnancy_tid`, tag `taxonomy_term_list` (`bebbo_serializer.module`). |
| **Render / page cache** | Whole response | Anonymous GETs are stored by core's page cache and Acquia (Varnish + Platform CDN) with `max-age` `2764800` s (`system.performance`). `Vary` carries no `Cookie` on API paths (see §11). |
| **Listing cache tags** | Per bundle, per language | The articles displays (`v1_articles_rest_export`, `articles_rest_export`) render rows in an isolated render context and tag the response `bebbo_api_list:{bundle}:{langcode}` for each bundle the display filters on, plus `media_list` — instead of core's site-wide `node_list`, which any node save would expire. Isolating the row render also keeps hundreds of `node:ID` tags out of the response, which `acquia_purge` would otherwise emit as a `Surrogate-Key` header and flood the purge queue with. `hook_node_update` / `insert` / `predelete` in `bebbo_serializer.module` fire the matching tags — per language when only translated values changed, all languages when an untranslatable field changed. Other displays keep core's `node_list` tagging. |
| **Invalidation** | Origin → edge | `purge_queuer_coretags` queues every invalidated tag; `acquia_purge` + `acquia_platform_cdn` purgers clear Varnish and the Platform CDN; processed by cron (`purge_processor_cron`) and late-runtime. See [`CONFIGURATION.md`](CONFIGURATION.md) *Purge / Cache invalidation*. |
| **Warming** | V1 only | `bebbo_custom_general` warmer (`bebbo:warm-all`, Acquia scheduled job every 2 h on dev and stage; manual *Warm this site* button at `/admin/config/development/api-warmer`) requests every V1 listing × app-visible language plus `/api/check-update/{gid}` so the first app request after a cache clear or deploy hits a warm copy. Nothing warms `/v2/api/*`. See [`MODULES.md`](MODULES.md) §3. |

---

## 13. Other Public Endpoints & Routes

### 13.1 Device security / attestation API (`/api/security/*`)

Served by `bebbo_api_security` (`SecurityController`). All are **public** (`_access: TRUE`), **`POST`**, JSON in / JSON out, and `no_cache: TRUE`. They issue and rotate the JWTs that gate the content APIs — see **`API_SECURITY.md`** for the full attestation flow, token lifetimes, and enforcement.

| Path | Required body fields | Success response | Purpose |
|------|----------------------|------------------|---------|
| `/api/security/register` | `platform` (`android`\|`ios`), `device_id`; Android: `integrity_token` (+ optional `nonce`); iOS: `key_id`, `attestation_object`, `client_data_hash` | `{status:"verified", access_token, token_type:"Bearer", expires_in, refresh_token}` | Attest a store-installed device via Play Integrity (Android) or App Attest (iOS), then issue tokens |
| `/api/security/device/register` | `device_id`, `public_key` | `{status:"challenge_issued", challenge, expires_in}` | Sideloaded step 1 — submit EC public key, receive a challenge |
| `/api/security/device/verify` | `device_id`, `challenge`, `signature` | `{status:"verified", access_token, token_type:"Bearer", expires_in, refresh_token}` | Sideloaded step 2 — present signed challenge, issue tokens |
| `/api/security/refresh` | `refresh_token` | `{status:"refreshed", access_token, token_type:"Bearer", expires_in, refresh_token}` | Exchange a refresh token for a rotated access/refresh pair |
| `/api/security/revoke` | _none_ — requires `Authorization: Bearer <jwt>` header | `{status:"revoked"}` | Revoke all refresh tokens for the calling device (logout/uninstall) |

Common errors: `400 {error:"missing_field"\|"invalid_platform"\|"invalid_key", message}`, `401 {error:"missing_token"\|"invalid_token"}` / `{status:"invalid"}` (refresh), `403 {status:"rejected", reason:"device_integrity_failed"\|"verification_failed"\|"signature_invalid", message}`, `429` when the per-device/per-IP rate limit (config `bebbo_api_security.settings`) is exceeded.

### 13.2 App-link `.well-known` endpoints

Served by `mobile_app_links` (`WellKnownController`). All are **public** (`_access: TRUE`), bypass the route normalizer (`_disable_route_normalizer: TRUE`), and set **no `methods` requirement** — so they respond to any HTTP method, not GET-only. Used by the OS to verify deep-link / universal-link domain ownership.

| Path | Method | Purpose |
|------|--------|---------|
| `/.well-known/assetlinks.json` | any (no method restriction) | Android App Links — Digital Asset Links statement |
| `/.well-known/apple-app-site-association` | any (no method restriction) | iOS Universal Links — app-site association |
| `/.well-known/apple-developer-domain-association.txt` | any (no method restriction) | Apple developer domain association |
| `/.well-known/apple-developer-merchantid-domain-association.txt` | any (no method restriction) | Apple Pay merchant-id domain association |

### 13.3 Miscellaneous public routes

These are not APIs but are publicly accessible routes served by custom modules:

| Path | Module | Purpose |
|------|--------|---------|
| `/downloadapp.html` | `bebbo_custom_general` | App store redirect page (`_access: TRUE`) |
| `/share/{param1}/{param2}/{param3}` | `bebbo_custom_general` | Mobile app deep-link share controller (permission: `manage mobile javascript`) |
| `/foleja/share/{param1}/{param2}/{param3}` | `bebbo_custom_general` | Kosovo (`foleja`) variant of the deep-link share controller (permission: `manage mobile javascript`) |

---

## 14. Request Examples

All content endpoints are **GET**, public (anonymous `access content` permission), and return `Content-Type: application/json`. When JWT enforcement is enabled, V2 endpoints require `Authorization: Bearer <JWT>` (see `API_SECURITY.md`).

### 14.1 V2 content endpoint — articles

```bash
curl -s 'https://bebbo.example.com/v2/api/articles/en'
```

With query parameters (changed-since + child-age filter):
```bash
curl -s 'https://bebbo.example.com/v2/api/articles/en?datetime=2026-06-01T00:00:00&childAge=10'
```

With JWT (when enforcement is enabled):
```bash
curl -s -H 'Authorization: Bearer eyJhbGciOi...' \
  'https://bebbo.example.com/v2/api/articles/en'
```

### 14.2 V2 content endpoint — ETag conditional request

ETag is supported on 5 V2 displays only: articles, video-articles, activities, faqs, basic-pages.

First request (get the ETag):
```bash
curl -si 'https://bebbo.example.com/v2/api/articles/en'
# Response includes: ETag: "a1b2c3d4..."
```

Subsequent request (skip unchanged data):
```bash
curl -s -H 'If-None-Match: "a1b2c3d4..."' \
  'https://bebbo.example.com/v2/api/articles/en'
# Returns HTTP 304 with empty body if data unchanged
```

### 14.3 V2 taxonomy and vocabulary

```bash
# All vocabularies
curl -s 'https://bebbo.example.com/v2/api/vocabularies/en'

# Terms for a specific vocabulary
curl -s 'https://bebbo.example.com/v2/api/taxonomies/en/child_age'

# Include Pregnancy child-age term
curl -s 'https://bebbo.example.com/v2/api/taxonomies/en/child_age?pregnancy=true'
```

### 14.4 V2 country-groups

The `%` argument is a country/app slug (e.g. `wawamor`), **not** a langcode; the envelope `langcode` resolves to the active/site language (the slug arg is not a valid language, so `resolveLangcode()` falls back to the current language):
```bash
curl -s 'https://bebbo.example.com/v2/api/country-groups/wawamor'
```

### 14.5 Force-update / check-update

```bash
# V1 — public
curl -s 'https://bebbo.example.com/api/check-update/45'

# V2 — JWT-gated when enforcement is on (same response when authorized)
curl -s -H 'Authorization: Bearer eyJhbGciOi...' \
  'https://bebbo.example.com/v2/api/check-update/45'
```

### 14.6 V1 content endpoint (legacy)

```bash
curl -s 'https://bebbo.example.com/api/articles/en'
```

### 14.7 Device registration and token flow

See `API_SECURITY.md` §4 for the security endpoint request/response examples.

```bash
# Android attestation → get tokens
curl -s -X POST 'https://bebbo.example.com/api/security/register' \
  -H 'Content-Type: application/json' \
  -d '{"platform":"android","device_id":"abc123","integrity_token":"eyJ..."}'

# Refresh tokens
curl -s -X POST 'https://bebbo.example.com/api/security/refresh' \
  -H 'Content-Type: application/json' \
  -d '{"refresh_token":"a1b2c3d4e5f6..."}'

# Revoke (logout)
curl -s -X POST 'https://bebbo.example.com/api/security/revoke' \
  -H 'Authorization: Bearer eyJhbGciOi...'
```

---

## 15. Error & Status Code Reference

### 15.1 Content API errors (V1 & V2 serializer)

> **Important:** Content API error codes appear in the **JSON body `status` field only**. The actual HTTP status code is always **200**. This is a design characteristic of the Bebbo serializer — do not rely on HTTP status for content API error detection.

| Body `status` | Body `message` | Condition | Applies to | Source |
|---------------|----------------|-----------|------------|--------|
| `400` | `"Request language is wrong"` | Langcode URL argument is not an enabled Drupal language | All V1 & V2 content endpoints **except country-groups** | `BebboSerializer::checkLanguageVisibility()`, `BebboV1Serializer::checkLanguageVisibility()` |
| `403` | `"Language not available"` | Langcode is valid but not visible in any country group (per `language_visibility_control`) | All V1 & V2 content endpoints **except country-groups** | `BebboSerializer::checkLanguageVisibility()`, `BebboV1Serializer::checkLanguageVisibility()` |
| `204` | `"No Records Found"` | View query returns zero rows | All V1 & V2 content endpoints, force-update | `BebboSerializer::render()`, `BebboV1Serializer::render()`, `CheckUpdateController` (check-update) |
| `200` | _(none)_ | Success — `data` array/object populated | All endpoints | — |

Error envelope shape (`400`/`403`/`204` cases):
```json
{"status": 400, "message": "Request language is wrong", "datetime": "2026-06-18 12:00"}
```
No `data`, `total`, or `langcode` keys on error responses.

### 15.2 ETag / conditional response

| HTTP Status | Body | Condition | Endpoints |
|-------------|------|-----------|-----------|
| **304** Not Modified | Empty | `If-None-Match` header matches computed ETag | **5 V2 displays only:** articles, video-articles, activities, faqs, basic-pages |

This is a real HTTP 304 (set by `EtagResponseSubscriber`), not a body field. The ETag is an MD5 fingerprint of `bundle + MAX(changed) + COUNT(*) + queryString` — not a body hash.

### 15.3 JWT enforcement errors (on protected content endpoints)

When `enforcement_mode` is `enforced`, the `ApiSecuritySubscriber` returns real HTTP 401 responses on protected paths (`/v2/api/*`, which covers `/v2/api/check-update/`). These use the **RFC 6750** response shape (with `error_description`, not `message`).

| HTTP Status | Body | Headers | Condition |
|-------------|------|---------|-----------|
| **401** | `{"error": "missing_token", "error_description": "A valid JWT token is required to access this resource."}` | `WWW-Authenticate: Bearer realm="Bebbo API"` | No `Authorization: Bearer` header |
| **401** | `{"error": "invalid_token", "error_description": "The provided JWT token is invalid or expired."}` | `WWW-Authenticate: Bearer realm="Bebbo API"` | JWT validation fails (expired, bad signature, malformed) |

> **Note:** There is no separate error code for expired vs. invalid tokens — both return `invalid_token`. In `grace_period` mode, invalid tokens are logged as warnings but the request is allowed through. In `disabled` mode, no JWT checking occurs.

### 15.4 Security endpoint errors

The security endpoints (`/api/security/*`) return **real HTTP status codes** via `JsonResponse`. Response shape varies by endpoint — see the table below.

| HTTP Status | Error key/field | Response body | Condition | Endpoint(s) |
|-------------|-----------------|---------------|-----------|-------------|
| **400** | `error: "invalid_json"` | `{"error": "invalid_json", "message": "Request body must be valid JSON."}` | Request body not valid JSON | All security POST endpoints |
| **400** | `error: "missing_field"` | `{"error": "missing_field", "message": "Field '{field}' is required."}` | Required field missing or empty | All security POST endpoints |
| **400** | `error: "invalid_platform"` | `{"error": "invalid_platform", "message": "Platform must be android or ios."}` | `platform` not `android` or `ios` | `/api/security/register` |
| **400** | `error: "invalid_key"` | `{"error": "invalid_key", "message": "<detail>"}` | Public key not valid EC P-256 PEM | `/api/security/device/register` |
| **401** | `error: "missing_token"` | `{"error": "missing_token", "message": "Authorization header required."}` | No Bearer token in header | `/api/security/revoke` |
| **401** | `error: "invalid_token"` | `{"error": "invalid_token", "message": "Invalid or expired JWT."}` | JWT validation fails | `/api/security/revoke` |
| **401** | `status: "invalid"` | `{"status": "invalid", "message": "Refresh token expired or revoked. Re-attestation required."}` | Refresh token not found, expired, revoked, or replay detected | `/api/security/refresh` |
| **403** | `status: "rejected"` | `{"status": "rejected", "reason": "device_integrity_failed", "message": "<detail>"}` | Play Integrity or App Attest verification fails | `/api/security/register` |
| **403** | `status: "rejected"` | `{"status": "rejected", "reason": "verification_failed", "message": "<detail>"}` | Sideloaded challenge lookup fails (expired, used, not found) | `/api/security/device/verify` |
| **403** | `status: "rejected"` | `{"status": "rejected", "reason": "signature_invalid", "message": "Challenge signature verification failed."}` | ECDSA signature verification fails | `/api/security/device/verify` |
| **429** | `error: "rate_limited"` | `{"error": "rate_limited", "message": "Too many requests. Try again later."}` | Flood threshold exceeded | `/api/security/register`, `/device/register`, `/device/verify`, `/refresh` |

> **Replay detection:** When a revoked refresh token is reused, the server revokes the **entire token family** (all tokens in the same rotation chain) and logs `"Refresh token replay detected"`. However, the client receives the same **401** `{status: "invalid"}` response — there is no distinct replay error code. The replay is only distinguishable server-side via the security log.

---

## 16. Field Glossary — Business Meanings

The per-endpoint tables in §5.3 document datatypes and cast rules. This section explains what the fields **mean** in the Bebbo product context — information a new developer needs to interpret the API values correctly.

### 16.1 Content-level flags

| Field | Appears on | Business meaning |
|-------|-----------|------------------|
| `licensed` | video-articles, pinned-contents, weekly-overview, guide, course, quiz | Content is licensed from a third party (copyrighted material requiring usage rights). `1` = licensed, `0` = original/UNICEF content. |
| `mandatory` | activities, video-articles, faqs, basic-pages, milestones, pinned-contents, child-development-data, child-growth-data | Content is part of the mandatory adaptation set. Used for prioritization when a new country adapts Bebbo — **not used in the app itself**. `1` = mandatory for adaptation, `0` = optional. |
| `premature` | articles, video-articles, pinned-contents | Content is specifically relevant to premature/preterm children. `1` = premature-specific, `0` = general. |
| `do_not_feature` | articles | Controls home-screen visibility. `"1"` = article should **not** appear in featured/promoted slots on the app home screen. `"0"` = eligible for featuring. |
| `equipment` | activities | Whether any physical materials or toys are needed to perform the activity. `1` = equipment required, `0` = no equipment needed. |
| `old_calendar` | vaccinations | Whether the vaccination entry uses a legacy/old immunization calendar schedule. `1` = old calendar, `0` = current calendar. |

### 16.2 Taxonomy reference fields

These fields store **taxonomy term IDs** (integers). The IDs are **subsite-specific** — the same concept (e.g. "Boy") has a different numeric ID on each country subsite. Use `unique_name` from the taxonomy endpoint to match across subsites.

| Field | Appears on | Business meaning | Taxonomy source |
|-------|-----------|------------------|-----------------|
| `category` | articles, video-articles | Primary content category (e.g. Nutrition, Health, Safety). Groups content for navigation and filtering. | `/v2/api/taxonomies/{lang}/category` |
| `subcategory` | articles | Secondary content category, nested under `category`. Further classifies articles for drill-down navigation. | `/v2/api/taxonomies/{lang}/subcategory` |
| `child_gender` | articles, video-articles | Gender relevance of the content. `0` = not gender-specific. Non-zero values reference gender terms. | `/v2/api/taxonomies/{lang}/child_gender` |
| `parent_gender` | articles, video-articles | Gender of the parent/caregiver the content is aimed at. Used to organize articles and video articles by caregiver gender. Typically includes a "both" value (by `unique_name`) for content targeting all caregivers — verify via taxonomy API. | `/v2/api/taxonomies/{lang}/parent_gender` |
| `activity_category` | activities | Category of the activity (e.g. Music & Songs, Games, Reading). Groups activities by type of play/interaction. | `/v2/api/taxonomies/{lang}/activity_category` |
| `type_of_support` | activities | Type of caregiver support the activity requires (e.g. independent play vs. guided). Defines the level of adult involvement needed. | `/v2/api/taxonomies/{lang}/type_of_support` |
| `growth_type` | child-growth-data | Which physical growth measurement type this record covers. Two types: height-for-age and weight-for-height, used to define deviation from WHO growth standards. | `/v2/api/taxonomies/{lang}/growth_type` |
| `growth_period` | vaccinations, health-checkup-data | Age period bracket used for vaccination schedules and health checkups. Different from `child_age` — these are medical milestone periods. | `/v2/api/taxonomies/{lang}/growth_period` |
| `field_type_of_article` | articles | Distinguishes article sub-types (e.g. General, Pregnancy-specific). Used for filtering and display logic. | `/v2/api/taxonomies/{lang}/type_of_article` |
| `child_age` | articles, video-articles, activities, faqs, milestones, child-development-data, child-growth-data, course, guide | Array of child age-group IDs the content applies to. Each term defines a `days_from`/`days_to` range from birthdate. | `/v2/api/taxonomies/{lang}/child_age` |
| `chatbot_subcategory` | faqs | Subcategory for organizing FAQ questions in the chatbot flow. | `/v2/api/taxonomies/{lang}/chatbot_subcategory` |
| `target_audience` | articles, video-articles, course | Target audience for the content (e.g. parents, caregivers, health workers). Comma-separated in some contexts. | `/v2/api/taxonomies/{lang}/target_audience` |
| `standard_deviation` | child-growth-data | Reference to the standard deviation category defining the level of deviation from growth standards. | `/v2/api/taxonomies/{lang}/standard_deviation_category` |
| `keywords` | articles, video-articles | Pre-defined keyword term IDs for search and categorization. The `keywords` vocabulary is **skipped** in the taxonomy API — these IDs are only usable for matching, not for display. | _(not exposed in taxonomy API)_ |

### 16.3 Country-group fields

| Field | Business meaning |
|-------|------------------|
| `CountryID` | ID of the country-group entity in the CMS subsite. Each Bebbo deployment (Bangladesh, Kosovo, etc.) has its own group. |
| `name` | Display name of the country. |
| `country_email` | Email address used for the in-app contact/support link. |
| `app_name` | Branded app name for this country (e.g. "Babuni" for Bangladesh, "Bebbo" for global). |
| `country_national_partner` | Logo image of the national implementing partner organization. |
| `country_sponsor_logo` | Logo image of the funding sponsor for this country deployment. |
| `unicef_logo` | UNICEF logo variant used in this country. |
| `all_logos` | Branding image for the 2.0 app version (WebP). |
| `country_flag` | Flag image for this country (WebP). |
| `content_toggle` | Comma-separated list of feature IDs to **hide** in the app for this country. Controls which app sections (e.g. growth tracking, vaccinations) are visible. |
| `languages[].displayName` | Language name as it should appear in the app — usually includes both the local script name and English name. |
| `languages[].languageCode` | BCP-47 language code used to segregate content in the CMS and APIs. |
| `languages[].locale` | POSIX locale code used in app settings (e.g. `bn_BD`, `en_US`). |
| `languages[].luxonLocale` | Locale code used by the Luxon date library in the app. |
| `languages[].pluralShow` | Indicates if the language uses plural forms for terminology like days, months, years. `"1"` = uses plurals. |
| `languages[].content_toggle` | Per-language feature toggle overriding the country-level `content_toggle`. Only on regular country groups, absent on CountryID 126. |

### 16.4 Taxonomy vocabularies — business context

| Vocabulary | Business meaning |
|-----------|------------------|
| `growth_introductory` | Informational text about physical growth during specific day-ranges. Functions as both content and taxonomy — each term has a `body`, `days_from`, and `days_to`. |
| `growth_period` | Age-period brackets used specifically for vaccination and health-checkup scheduling. Different from `child_age` which is used for general content targeting. Each term has `vaccination_opens` (days from birthdate when the period starts). |
| `growth_type` | Two types of physical growth monitoring: height-for-age and weight-for-height. Used to define which WHO growth standard deviation charts to apply. |
| `parent_gender` | Gender of the parent/caregiver. Used to organize articles and video articles. Typically includes a "both" value (by `unique_name`) for content targeting all caregivers — verify via taxonomy API. |
| `relationship_to_parent` | Defines the user type/role (e.g. mother, father, grandparent, other caregiver). |
| `standard_deviation_category` | Levels of deviation from WHO growth standards (e.g. normal, warning-low, emergency-low, warning-high, emergency-high). |
| `subcategory` | Subcategories for articles and video articles, nested under `category`. |
| `target_audience` | Target audience segments for articles, courses, and video articles. |
| `type_of_support` | Types of adult support/involvement needed for activities. |

### 16.5 Engagement and analytics fields

| Field | Appears on | Business meaning |
|-------|-----------|------------------|
| `read_count` | articles, video-articles, activities, course | Count of how many times the content has been read/viewed in the app. |
| `love_count` | articles, video-articles, activities, course | Count of how many times the content has been "loved" (liked/favorited) in the app. The underlying Drupal field is `field_like_count`, but the API key is `love_count`. |

### 16.6 Content relationship fields

| Field | Appears on | Business meaning |
|-------|-----------|------------------|
| `related_articles` | articles, video-articles, vaccinations, milestones, weekly-overview, guide, pinned-contents | Array of article IDs related to this content. Used for "Related reading" sections in the app. |
| `related_video_articles` | articles, video-articles, milestones | Array of video article IDs related to this content. |
| `related_activities` | milestones | Array of activity IDs related to this milestone. Links milestones to suggested activities. |
| `related_milestone` | activities | Milestones that this activity helps develop. Links activities to developmental milestones. |
| `related_article` | faqs | Single article ID related to this FAQ. |
| `pinned_article` | vaccinations, health-checkup-data | Specific article pinned to this vaccination/checkup for direct reference. |
| `pinned_articles` | child-growth-data | Array of article IDs pinned to this growth data record (aliased from `field_related_articles`). |
| `pinned_video_article` | vaccinations, health-checkup-data | Specific video article pinned to this vaccination/checkup. |
| `boy_video_article` / `girl_video_article` | child-development-data | Gender-specific video articles pinned to this developmental stage. |

---

## 17. Enum & Taxonomy Value Mappings

### 17.1 How to resolve enum values

Most integer fields in the API reference **taxonomy term IDs**. These IDs are **subsite-specific** — the same concept has a different numeric ID on each Bebbo country subsite. To interpret values:

1. **Fetch the taxonomy:** `GET /v2/api/taxonomies/{langcode}/{vocabulary_name}`
2. **Match by `id`** for the current subsite
3. **Match by `unique_name`** to correlate across subsites (the `unique_name` is consistent across all subsites while `id` differs)

### 17.2 Known value conventions

**`child_gender`** — source: `/v2/api/taxonomies/{lang}/child_gender`

The pinned-contents endpoints encode gender in the URL path, revealing the well-known IDs on the default (bebbo) subsite:

| ID (bebbo subsite) | `unique_name` | Meaning |
|--------------------|---------------|---------|
| `40` | `boy` | Boy |
| `41` | `girl` | Girl |
| `0` | _(empty/unset)_ | Not gender-specific / both |

> On other subsites these numeric IDs will differ. Always resolve via the taxonomy API or use `unique_name`.

**`parent_gender`** — source: `/v2/api/taxonomies/{lang}/parent_gender`

Typically includes a "both" value (by `unique_name`) for content targeting all caregivers. Verify exact terms via the taxonomy API — `unique_name` values are not exported in config.

> `0` in the API response means not set / applies to all.

**`growth_type`** — source: `/v2/api/taxonomies/{lang}/growth_type`

| `unique_name` | Meaning |
|---------------|---------|
| `height_for_age` | Height-for-age WHO growth standard |
| `weight_for_height` | Weight-for-height WHO growth standard (internally stored as `height_for_weight`, renamed in API output) |

**`activity_category`** — source: `/v2/api/taxonomies/{lang}/activity_category`

Values vary per subsite. Common `unique_name` examples: `music_songs`, `reading`, `games`. Fetch the full list from the taxonomy endpoint.

**`category`** — source: `/v2/api/taxonomies/{lang}/category`

Article/video-article categories. Each term also carries `unique_name` and `field_type_of_article`, the latter a machine name derived from the referenced `type_of_article` term's English label (e.g. `article_for_birth_to_6_years`) — unlike `name`, it is identical across languages. Values vary per subsite — fetch from taxonomy endpoint.

**`subcategory`** — source: `/v2/api/taxonomies/{lang}/subcategory`

Nested under `category`. Each term has `{id, name, unique_name}`. Values vary per subsite.

**`type_of_support`** — source: `/v2/api/taxonomies/{lang}/type_of_support`

Defines the type of caregiver involvement needed for activities. Each term has `{id, name}`. Values vary per subsite.

**`target_audience`** — source: `/v2/api/taxonomies/{lang}/target_audience`

Each term has `{id, name, unique_name}`. Values vary per subsite.

### 17.3 Fields that are NOT taxonomy references

These fields look like enums but are simple integers or booleans, not taxonomy term IDs:

| Field | Type | Values |
|-------|------|--------|
| `licensed` | int (boolean) | `0` = original content, `1` = licensed/third-party |
| `mandatory` | int (boolean) | `0` = optional, `1` = mandatory for adaptation |
| `premature` | int (boolean) | `0` = general, `1` = premature-child-specific |
| `equipment` | int (boolean) | `0` = no equipment needed, `1` = equipment required |
| `old_calendar` | int (boolean) | `0` = current calendar, `1` = old/legacy calendar |
| `do_not_feature` | string (boolean) | `"0"` = eligible for featuring, `"1"` = hide from home screen |

---

## 18. Related Documentation

| Topic | Document |
|-------|----------|
| Device attestation / JWT enforcement on the API | `API_SECURITY.md` |
| System architecture & where APIs sit | `ARCHITECTURE.md` |
| Serializer modules & custom-module detail | `MODULES.md` |
| Engagement counts (`field_like_count`/`field_read_count`) sync | `MODULES.md` (pb_content_analytics) |
