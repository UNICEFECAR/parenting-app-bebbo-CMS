# Bebbo CMS: security, bug and dependency review

**Review date:** 16 November 2026
**Repository:** UNICEFECAR/parenting-app-bebbo-CMS
**Baseline:** `main` at `5060246f34ec7caafb9adf4e00245c2a1a46957c`
**Review branch:** `codex/cms-security-review-2026-11-16`

## Outcome and boundaries

This branch contains verified code fixes, targeted dependency upgrades and regression tests. It is a proposal for review, **not a deployment**. Main, Acquia environments, live databases, content, accounts, API credentials and external services were not changed. The existing deployment workflow only deploys pushes to develop/stage; the new review workflow has no deployment steps or environment secrets.

The original lock file returned **44 security advisories across 22 packages**. The revised lock file returns **no known vulnerability advisories** from Composer's configured Drupal and Packagist feeds. **27 locked package versions** changed. This does not establish that a deployed site is running these versions or that every possible vulnerability has been eliminated.

**Release status: draft, with rollout gates.** In particular, provision a secret hash salt where the hosting platform does not supply one, and resolve the attestation replay/identity protocol findings below before treating enforced device authentication as production ready. Browser, external provider, production database and load tests remain outstanding.

## Review coverage

The assessment combined manual review of security-sensitive custom code with repository-wide syntax checks, Drupal static analysis, coding standards, dependency advisory checks and unit/kernel tests. Areas examined included API authentication and caching; token issuance and refresh; Apple/Google verification; country/group and language access; editorial actions; string translations; file naming and SVG dependencies; exports; migrations/import integration points; analytics synchronisation; multisite settings; and build/deployment configuration.

The shared code serves the default site and the repository's `zimbabwe`, `ecuador`, `turkey`, `somoa`, `pakistan` and `bangladesh` site directories. Country configuration splits and translation configuration were parsed but not imported into any live or staging database. This is code/configuration assessment and isolated testing, not an interactive test of every country, language or admin screen.

## Findings fixed on this branch

### 1. Known vulnerabilities in core, modules and PHP libraries

- **Issue:** The baseline lock file contains advisories covering remote code execution, access bypass, cross-site scripting, XML/file handling, HTTP handling and denial of service. Applicability depends on enabled modules, permissions and configuration; the audit alone does not prove exploitation.
- **Change:** Resolve targeted upgrades with Composer and its dependency solver, retaining Drupal 11.4 and AI 1.4 rather than moving to new feature/minor or prerelease lines. The SVG library must cross a major version to receive its available security fixes; SVG Image is upgraded with it. Keep the lock file reproducible and preserve active project patches where still needed.
- **Files:** `composer.json`, `composer.lock`; the AI translation patch listed in item 2.
- **Verification:** Clean Composer install, platform requirements, package audit, Drupal static analysis and the application test suite. No advisory suppression or security-ignore list was added.

### 2. Build failures and patch compatibility

- **Issue:** A clean baseline install fails on the old Views Bulk Operations `3323324-3.patch`. The AI streaming patch conflicts with the upgraded module, and the AI translation revision patch conflicts with security-related constructor changes upstream.
- **Change:** Remove the VBO workaround from active Composer configuration and correct the custom actions' access contract instead. Remove the active AI streaming patch because the upgraded AI source contains the HTML buffering fix. Rebase the revision metadata patch onto AI Translate 1.4.4, preserving its new access-related dependencies and adding the project's time/current-user dependencies to the new constructor. Autowiring remains in use.
- **Files:** `composer.json`, `composer.lock`, `patches/ai_translate/stamp-revision-metadata-on-ai-translation.patch`; custom action files in item 3.
- **Verification:** A fresh install in a separate worktree applies all configured patches successfully. The upstream AI HTML/Markdown streaming tests pass (13 tests, 161 assertions). A new test verifies revision date, editor, log message and changed time on the patched translation orchestrator.

### 3. Bulk editorial actions skip their intended access checks

- **Issue:** Eight custom actions compare `$object->getEntityType()` with the string `node`. Drupal returns an entity-type object, so that condition never succeeds; the method falls through to unconditional `TRUE`, including when VBO requests an AccessResult object. This skips the intended update/status permissions and can also cause VBO's `isAllowed()` call to fail.
- **Change:** Check `NodeInterface`, return a proper forbidden result for unsupported entities, honour node update and status-field access, and always respect the boolean/object return contract. For state-changing actions, require an applicable moderated entity and a transition allowed by Drupal's workflow service to the actual target state. Country/language customisations in execution logic are retained.
- **Files:** `docroot/modules/custom/pb_custom_field/src/Plugin/Action/AssigncontentAction.php`, `ChangeToSMEAction.php`, `ChangedToArchiveAction.php`, `ChangedToPublishedAction.php`, `ChangedToSeniorEditorAction.php`, `MovefrompublishtodraftAction.php`, `MovefrompublishtosenioreditorAction.php`, `MovefrompublishtosmeAction.php` (all in that directory).
- **Verification:** Tests cover granted and denied node access, permitted and unavailable workflow transitions, unsupported objects and both return formats across all eight actions. Country role/workflow combinations still need acceptance testing against actual site configuration.

### 4. Device registration inserts an incomplete row

- **Issue:** `DeviceRegistryService::registerDevice()` calls `insertFields()` with only `created`. This replaces the merge query's insert field list, omitting required device identity/platform fields. The isolated database tests reproduce a NOT NULL failure when registering a new device.
- **Change:** Supply the full insert field set while retaining the separate update field set, so re-registration preserves the original creation time.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Service/DeviceRegistryService.php`.
- **Verification:** Existing real-database registration, re-registration and cleanup kernel tests now pass.

### 5. Sideloaded registration can replace another registered key

- **Issue:** The public registration endpoint upserts the public key and status solely by a caller-supplied device ID. Knowledge of an existing ID is sufficient to replace its key, change its platform and reset its status to pending.
- **Change:** Pin the first public key. Permit repeat challenges for the same sideloaded identity/key, preserving creation time and active status; reject replacement keys, platform changes and revoked identities. Verification rejects revoked/non-sideloaded records. Challenge consumption uses an atomic `used = 0` / unexpired update so a challenge cannot succeed twice after parallel reads.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Service/SideloadedVerificationService.php`.
- **Verification:** Original challenge/signature/replay/expiry tests plus replacement-key, same-key re-registration and revoked-device regressions. Real simultaneous multi-worker stress testing was not performed.
- **Compatibility:** A client that loses its private key must create a new device identity, or use a separately authorised reset procedure. It can no longer silently replace the key for an existing identity.

### 6. Refresh rotation is non-atomic and can strand a session

- **Issue:** Two refresh requests can both read an unrevoked token before either updates it. A signing failure after rotation can consume the old token without returning a usable replacement. Device status is not checked before refresh.
- **Change:** Require an active device, sign before consuming refresh state, and perform conditional token claiming plus replacement insertion in a transaction. Reject a losing claim and revoke the session family, following existing replay policy. Treat expiry at the current second as expired. Preserve the existing configuration that deliberately disables rotation.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Service/JwtService.php`.
- **Verification:** Real-database refresh/rotation/replay tests and regressions for revoked devices and signing failure. The conditional update is checked in code and exercised through normal lifecycle tests; contention under MySQL production load remains untested.

### 7. JWT signature validation accepts incomplete claims

- **Issue:** A correctly signed JWT is accepted without requiring the expected issuer or a valid subject/expiry/issued-at shape. Callers subsequently assume `sub` is a string.
- **Change:** Require issuer `bebbo-cms`, a non-empty string subject within storage length, integer expiry/issued-at claims and a sensible expiry interval. Signature algorithm remains pinned to RS256.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Service/JwtService.php`.
- **Verification:** Signed invalid-issuer, missing/malformed-claim, empty-subject, expired, tampered and wrong-key cases.

### 8. Malformed request fields can cause TypeErrors/storage failures

- **Issue:** JSON validation checks only presence. Arrays, objects, integers and overlong values can reach string-typed cryptographic/database methods and trigger server errors.
- **Change:** Cap authentication request bodies at 64 KiB, validate known input fields as bounded non-empty strings, and validate 64-character hexadecimal challenge/client-data hashes before decoding them.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Controller/SecurityController.php`.
- **Verification:** Endpoint-level kernel tests for nested/integer/overlong fields, invalid platform/JSON, oversize requests and successful existing device flows.

### 9. Language-prefixed or aliased paths can bypass path protection

- **Issue:** The JWT subscriber runs before Drupal resolves incoming language prefixes and aliases, and matches raw paths. A routed API can therefore have a raw URL that does not match `/v2/api/`.
- **Change:** Run after routing and match Drupal's resolved current path. Apply the same resolved-path logic when setting response cache policy.
- **Files:** `docroot/modules/custom/bebbo_api_security/src/EventSubscriber/ApiSecuritySubscriber.php`, `bebbo_api_security.services.yml`.
- **Verification:** A language-prefixed request resolving to a protected API is rejected without a token; disabled/grace/enforced modes and development bypass cases remain covered.

### 10. Simple OAuth can mistake device JWTs for Drupal user tokens

- **Issue:** Both systems receive `Authorization: Bearer`. Simple OAuth runs before the custom device subscriber and attempts to validate device tokens using its separate user-token system, potentially rejecting otherwise valid device requests and revocations.
- **Change:** Add route options supported by the installed Simple OAuth provider so configured device API routes and `/api/security/` opt out of user OAuth. Other routes, including OAuth and JSON:API, keep their existing user authentication. Request a route rebuild when the protected/excluded patterns are saved.
- **Files:** `docroot/modules/custom/bebbo_api_security/src/Routing/DeviceTokenRouteSubscriber.php`, `bebbo_api_security.services.yml`, `src/Form/ApiSecuritySettingsForm.php`.
- **Verification:** Route selection tests exercise the installed Simple OAuth provider's real `applies()` method and verify that the opt-out is scoped to device routes.

### 11. Sensitive API responses can be shared through caches

- **Issue:** Token responses have no explicit no-store policy. The serializer removes Cookie variation broadly from API responses, including cases that can depend on authentication. Enforced APIs must not be served from an unauthenticated shared cache without a separate edge authentication design.
- **Change:** Mark security endpoints and enforced protected APIs `private, no-store`; set Drupal cache max-age to zero for cacheable response types. Only remove Cookie variation on successful, explicitly public, safe, anonymous content requests without authentication/cookie inputs or response cookies. Preserve other Vary headers.
- **Files:** `docroot/modules/custom/bebbo_api_security/src/EventSubscriber/ApiSecuritySubscriber.php`, `docroot/modules/custom/bebbo_serializer/src/EventSubscriber/ApiVaryResponseSubscriber.php`.
- **Verification:** Tests cover token and enforced-content no-store policy, public content, Cookie/Authorization inputs, private/error responses and subrequests.
- **Rollout:** Purge previously stored Drupal/CDN API responses before testing enforcement. No remote cache was purged during this review. Enforced-mode cache misses increase origin load; test this before rollout.

### 12. Apple attestation does not bind the stored key to its certificate

- **Issue:** Certificate signatures and credential ID are checked, but the COSE key stored from authenticator data is not compared with the certificate public key, and the certificate-key hash is not checked against the key ID. Certificate validity windows are not enforced. Some malformed/truncated structures are insufficiently validated.
- **Change:** Require matching EC P-256 certificate/COSE keys, compare the SHA-256 hash of the certificate's uncompressed X9.62 point with the key ID, check certificate validity periods, validate client hash/credential lengths and certificate-chain value types.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Service/AppleAppAttestService.php`.
- **Verification:** Real OpenSSL-generated key/certificate binding regression and existing CBOR/attestation/assertion unit tests. No Apple-signed real-device attestation was obtained; the protocol-level challenge finding below remains open.

### 13. Google Standard Integrity requests are rejected

- **Issue:** Verification reads only classic `nonce`, while Standard Integrity returns `requestHash`. The existing Standard Integrity tests fail against the baseline service.
- **Change:** Support classic nonce binding and standard SHA-256/base64url request-hash binding. Keep package, freshness, device and app recognition checks. Do not enable the development-only unrecognised-app allowance.
- **File:** `docroot/modules/custom/bebbo_api_security/src/Service/GooglePlayIntegrityService.php`.
- **Verification:** Classic and standard verdict tests, package/freshness/integrity rejection tests and existing development-override checks. Google token exchange and mobile cloud-project/package configuration require external integration testing.

### 14. String translation editor ignores assigned languages and source identity

- **Issue:** The inline translation form offers every language despite its permission description promising assigned-language access. It saves without rechecking that assignment. Selecting a source language can overwrite a source label, and `empty()` treats the legitimate translation `0` as deletion.
- **Change:** Restrict selectable/accepted languages through Allowed Languages; retain explicit administrator/all-language permission overrides. Choose an allowed fallback for stale session filters and handle accounts with no assigned languages. Render source-language fields disabled, reject source/other-vocabulary writes during submission, and compare empty strings explicitly.
- **Files:** `docroot/modules/custom/pb_strings/src/Form/StringsFilterForm.php`, `StringsTranslateForm.php`.
- **Verification:** Assigned-language filtering, forbidden submission and source-label preservation tests. The language permission itself is unchanged.

### 15. Analytics sync can be triggered without CSRF protection

- **Issue:** A privileged GET route starts a state-changing analytics sync, with permission checking but no CSRF token requirement.
- **Change:** Add Drupal's route CSRF requirement. The existing form builds its redirect with `Url::fromRoute`, allowing Drupal's outbound route processing to supply the token.
- **File:** `docroot/modules/custom/pb_content_analytics/pb_content_analytics.routing.yml`.
- **Verification:** YAML parsing and review of route/form integration. Authenticated browser confirmation, including tokenless rejection, remains a rollout test.

### 16. Analytics data can reset counters and rewrite revision history

- **Issue:** Incomplete/malformed data is cast into counters, sometimes zero; a malformed row can crash the batch. Revision-table writes update every revision for an entity, rewriting historical counts. Multi-field writes are not transactional.
- **Change:** Validate node IDs, record shape, type/timestamp strings and non-negative integer counters before writes; skip/log invalid records. Write current entity fields and only the current default revision's counter rows, excluding deleted rows, within a transaction.
- **File:** `docroot/modules/custom/pb_content_analytics/src/Service/AnalyticsSyncService.php`.
- **Verification:** A real Drupal node/field/revision kernel test verifies current counter updates, preservation of an older revision and rejection of invalid records. Existing historical database values are not repaired by this branch.

### 17. Large CSV export can repeat pages indefinitely

- **Issue:** Each batch operation already represents one fixed page, but sets `finished = 0`, asking Drupal to repeat that same operation. The final redirect passes a route name where Batch API expects a URL. Fetch exceptions are swallowed, risking a partial-success result.
- **Change:** Finish each page after processing it, resolve the download route to a URL and propagate logged fetch errors so the batch can fail visibly.
- **File:** `docroot/modules/custom/bebbo_custom_general/src/Controller/CsvExportController.php`.
- **Verification:** Regression test confirms a fixed-page operation completes after one remote-manager call. Large real Entity Share exports still need integration testing.

### 18. CSV output can execute spreadsheet formulas

- **Issue:** CSV quote escaping alone does not prevent spreadsheet applications interpreting labels beginning with formula/control characters.
- **Change:** Apply one shared cell encoder to both export paths, prefix dangerous cells with a literal-text apostrophe and retain correct CSV quote escaping.
- **File:** `docroot/modules/custom/bebbo_custom_general/src/Controller/CsvExportController.php`.
- **Verification:** Formula-prefix, whitespace/control-prefix and ordinary quoted-label cases. The apostrophe is intentional in exported dangerous cells.

### 19. Predictable hash salt and unlimited request memory

- **Issue:** Shared post-settings overwrite the platform hash salt with a hash of public installation paths. They also disable PHP's memory limit for every request.
- **Change:** Preserve a platform-provided secret salt, or use `BEBBO_HASH_SALT` to derive a site-specific HMAC salt. Fail clearly when neither source is available; never fall back to public paths. Honour the PHP/hosting memory limit; CLI jobs can set their own limit explicitly.
- **File:** `docroot/sites/common_settings/post.settings.php`.
- **Verification:** Tests for preserving platform salt, deriving the configured salt and failing when no secret exists. No hosted configuration was read or changed.
- **Rollout:** Provision a stable, strong random secret separately for each environment where required; back it up securely. Changing the effective salt invalidates existing HMAC-based links/tokens, so plan and test the transition. Do not deploy before this is prepared.

### 20. Review checks and build supply chain

- **Issue:** The deployment-oriented workflow does not test pull requests to main or run the custom PHPUnit suite. Checkout uses an old moving major tag, and the workflow has no explicit token-permission restriction.
- **Change:** Add a review-only workflow for this branch pattern/main PRs: Composer validation/install/audit, custom PHP lint, PHPCS, Drupal Check and SQLite-backed custom tests. Add root PHPUnit configuration. Retain the new core scaffold ignore files so generated runtime/recipe files are not accidentally committed. Pin checkout v5 and PHP setup to verified commit SHAs, and restrict workflow token permissions to contents read. The review checkout does not retain credentials. Existing deploy targets/triggers are unchanged.
- **Files:** `.github/workflows/cms-review.yml`, `.github/workflows/pipelines.yml`, `phpunit.xml.dist`; regression test files in the inventory below.
- **Verification:** Equivalent local checks pass. GitHub-hosted workflow status must be checked after publishing the branch; local results do not substitute for a completed remote run.

## Dependency versions changed

| Package | Baseline | Review branch |
|---|---|---|
| `drupal/ai` | 1.4.7 | 1.4.11 |
| `drupal/ai_translate` | 1.4.0 | 1.4.4 |
| `drupal/allowed_languages` | 2.0.1 | 2.0.4 |
| `drupal/core` | 11.4.5 | 11.4.12 |
| `drupal/core-composer-scaffold` | 11.4.5 | 11.4.12 |
| `drupal/core-project-message` | 11.4.5 | 11.4.12 |
| `drupal/core-recommended` | 11.4.5 | 11.4.12 |
| `drupal/gin` | 5.0.15 | 5.0.16 |
| `drupal/imageapi_optimize_webp` | 2.1.0 | 2.1.2 |
| `drupal/linked_field` | 1.7.0 | 1.8.0 |
| `drupal/menu_link_attributes` | 1.7.0 | 1.10.0 |
| `drupal/shield` | 1.8.0 | 1.11.0 |
| `drupal/simple_oauth` | 6.1.1 | 6.1.4 |
| `drupal/stage_file_proxy` | 3.1.6 | 3.1.7 |
| `drupal/svg_image` | 3.2.4 | 3.2.5 |
| `enshrined/svg-sanitize` | 0.22.0 | 1.1.1 |
| `masterminds/html5` | 2.10.1 | 2.12.0 |
| `symfony/config` | v7.4.17 | v7.4.23 |
| `symfony/filesystem` | v7.4.18 | v7.4.23 |
| `symfony/http-client` | v7.4.18 | v7.4.23 |
| `symfony/http-foundation` | v7.4.18 | v7.4.23 |
| `symfony/http-kernel` | v7.4.18 | v7.4.23 |
| `symfony/mime` | v7.4.18 | v7.4.22 |
| `symfony/routing` | v7.4.18 | v7.4.23 |
| `symfony/serializer` | v7.4.18 | v7.4.23 |
| `symfony/validator` | v7.4.18 | v7.4.23 |
| `symfony/yaml` | v7.4.18 | v7.4.23 |

## Further upgrades assessed

Composer's direct-dependency outdated check was also run. Availability of a newer version does not by itself establish a security or functional reason to upgrade it in this patch. The full current candidate list is recorded below; versions are observations from 16 November, not ongoing guarantees.

- **Plan a separate development-tool upgrade:** PHPStan remains on the project's 1.x line and reports that it is old. Moving to 2.x requires coordinated updates to Drupal's PHPStan integration and deprecation rules; it is not an application security fix. Current static analysis passes.
- **Replace abandoned packages through a tested build/hosting change:** Composer reports `acquia/memcache-settings`, `oomphinc/composer-installers-extender` and transitive `doctrine/common` as abandoned. These are not hidden or ignored. Their roles in Acquia cache settings, asset installation and test tooling need replacing deliberately. No replacement package was guessed or forced into production configuration.
- **Maintain the patched module set deliberately:** TMGMT, Entity Share, image processing, migration and cron modules carry project patches or prerelease constraints. Batch upgrade them only with representative import/translation/cron/image tests and patch review. Security fixes identified by the audit are already included above.
- **Keep Drupal 12 / Drupal 11.5 prereleases and AI 1.5 out of this security patch:** They are not required to clear the known advisories on the supported lines chosen here. The SVG library major upgrade is the exception because the secure version requires it.

| Direct dependency | Locked | Available within the inspected major line |
|---|---|---|
| `drupal/ai` | 1.4.11 | 1.5.2 |
| `drupal/better_exposed_filters` | 7.1.3 | 7.2.0 |
| `drupal/email_tfa` | 3.0.0 | 3.0.2 |
| `drupal/entity_reference_revisions` | 1.14.0 | 1.15.0 |
| `drupal/file_mdm` | 3.2.0 | 3.2.1 |
| `drupal/image_style_quality` | 1.7.0 | 1.8.0 |
| `drupal/imagemagick` | 5.0.1 | 5.0.2 |
| `drupal/key` | 1.22.0 | 1.23.0 |
| `drupal/languagefield` | 1.14.0 | 1.15.0 |
| `drupal/memcache` | 2.8.0 | 2.10.0 |
| `drupal/menu_export` | 1.7.0 | 1.9.0 |
| `drupal/metatag` | 2.2.0 | 2.2.7 |
| `drupal/migrate_plus` | 6.0.10 | 6.0.11 |
| `drupal/migrate_source_csv` | 3.8.0 | 3.10.0 |
| `drupal/migrate_tools` | 6.1.4 | 6.1.5 |
| `drupal/pathauto` | 1.15.0 | 1.16.0 |
| `drupal/tmgmt_deepl` | 2.4.0 | 2.4.5 |
| `drupal/tmgmt_memsource` | 1.30.0 | 1.34.0 |
| `drupal/token` | 1.17.0 | 1.18.0 |
| `drupal/ultimate_cron` | 2.0.0-beta1 | 2.0.0-beta2 |
| `drupal/video_embed_field` | 3.1.0 | 3.1.1 |
| `drupal/view_custom_table` | 2.0.8 | 2.0.9 |
| `drupal/views_bulk_operations` | 4.4.7 | 4.4.9 |
| `drush/drush` | 13.7.6 | 13.8.1 |
| `firebase/php-jwt` | v7.1.0 | v7.2.1 |
| `overtrue/phplint` | 9.7.2 | 9.8.2 |
| `phpunit/phpunit` | 11.5.56 | 11.5.58 |
| `spomky-labs/cbor-php` | 3.3.2 | 3.4.2 |
| `symfony/phpunit-bridge` | v6.4.45 | v6.4.49 |

## Verified checks

| Check | Result |
|---|---|
| Clean install from the updated lock file in a separate worktree, with no existing vendor/core/contrib | Passed; configured patches apply |
| Composer manifest/lock validation | Passed with existing warnings about three wildcard constraints and the intentionally pinned custom-table version |
| Composer platform requirements | Passed on PHP 8.4.27 with required extensions |
| Composer locked security audit | 44 advisories / 22 affected packages before; zero known advisories after; three abandoned packages reported separately |
| Custom unit and SQLite kernel suite | 155 tests, 502 assertions, no errors or failures |
| Upstream AI HTML/Markdown streaming regressions | 13 tests, 161 assertions, no errors or failures |
| Drupal Check over all custom modules and their tests | No errors |
| PHPCS over configured custom modules, themes and tests | No errors or warnings |
| Repository source syntax | 173 PHP-family files, 14 JavaScript files and four shell scripts checked; no syntax failures |
| YAML configuration/workflow parsing | 3,176 YAML files checked; no parse failures |
| Whitespace / patch integrity | `git diff --check` passed |

The PHPUnit run reports 710 metadata deprecations, largely from existing annotation-based tests and dependency test infrastructure, plus an error-handler-change notice. These are not test failures and have not been described as a warning-free run. PHPStan also emits tooling-age/bootstrap deprecation notices. This branch does not rewrite the test ecosystem merely to suppress these messages.

Commands used (with normal PHP/Composer on a configured development machine):

```sh
composer validate --no-check-publish
composer install --prefer-dist --no-interaction --no-progress --no-scripts
composer check-platform-reqs
composer audit --locked --format=json
vendor/bin/phpcs
vendor/bin/drupal-check --no-progress --memory-limit=1G docroot/modules/custom
SIMPLETEST_DB=sqlite://localhost//tmp/bebbo-cms-test.sqlite BROWSERTEST_OUTPUT_DIRECTORY=/tmp/bebbo-cms-test-output vendor/bin/phpunit
```

Kernel tests use a disposable local SQLite database and generated test keys, not CMS data or production credentials. Syntax checks additionally cover `.module`, `.install`, `.inc` and `.theme` files. No live penetration test, provider token call, database upgrade, configuration import, content migration or remote backup/restore was run.

## Open findings and release gates

| Priority | Finding / remaining validation | Required follow-up |
|---|---|---|
| High | Attestation is not consistently bound to a server-issued, one-use challenge. iOS accepts a caller-supplied client-data hash; Android retains a caller-supplied/deterministic binding fallback. Certificate and nonce/hash checks do not by themselves prevent replay or prove that a caller-supplied device ID belongs to the attested installation. | Design and test a coordinated app/CMS challenge/assertion flow for all supported installation channels. Bind identity/platform/key and consume challenges atomically. Retain current disabled/grace configuration until real-device verification and rollout planning are complete. This was not silently changed because it changes the mobile protocol. |
| High | The sideloaded flow proves possession of a caller-generated key; it does not independently attest that the client is an authorised app build. Key pinning closes identity replacement, but does not turn that flow into store attestation. | Confirm the permitted sideloaded threat model and distribution policy; add a provisioning/authorisation mechanism if genuine-app proof is required. Device tokens must not substitute for Drupal user permissions. |
| High | Salt correction requires environment preparation. | Preserve/provision a strong stable secret before deploying; derive distinct site salts from the configured environment secret. Test login/CSRF/one-use links and retain the secret securely for restores. No environment secret was set during this task. |
| High | Old cached API responses can predate the new no-store policy. | Purge Drupal/CDN API entries before validating enforcement; verify unauthenticated requests are rejected even after an authenticated cache warm. Test Fastly/Cloudflare behaviour and origin capacity. No purge was performed. |
| High | Simple OAuth 6.1.4 changes consumer access rules and Client Credentials scope enforcement. | Run normal database updates in an isolated test environment, review the new consumer-access permission, test each actual consumer/scope and review/revoke existing questionable tokens as required by the upstream advisory. No tokens or consumer accounts were changed here. |
| Medium | Existing access JWTs remain valid until their expiry after refresh-token revocation; immediate access-token revocation is not implemented by this stateless scheme. | Confirm whether the current short expiry is acceptable, or add a tested revocation/version check with a performance budget. Revoked devices are prevented from refreshing on this branch. |
| Medium | Store registration has a per-device rate limit; arbitrary new caller-supplied IDs can multiply its budget. | Confirm a gateway/IP/global attestation-request budget that also accommodates shared networks. Test with actual registration volumes; no live rate limits were changed. |
| Medium | Actual deployed host trust, proxy headers, CORS, TLS, WAF, secret storage, provider permissions and backups cannot be established from the repository alone. | Review Acquia/CDN/environment configuration and perform an isolated restore exercise with authorised infrastructure access. Tracked JSON:API is read-only and tracked error display is hidden, but deployed overrides were not inspected. |
| Medium | Country roles, language assignments, editorial transitions and user/group management vary by site and active database. | Run a role/language/country acceptance matrix including denied direct URLs and bulk actions, translated revisions, Arabic/Urdu editing and administrator tasks. Do not loosen access checks merely to make a workflow appear to work. |
| Medium | Real imports/exports, file derivatives, analytics endpoints, email/2FA and AI translation providers depend on external services or representative data. | Test interrupted/retried/large Entity Share exports and imports, CSV restore semantics, media/SVG/WebP, BigQuery sync, email delivery, AI formatting/revisions and no-network/error behaviour in an isolated environment. No live data was used. |
| Medium | Production MySQL and real parallel requests differ from SQLite tests. | Run refresh/challenge contention and analytics failure/rollback tests on the supported MySQL stack, plus load/memory checks after enabling no-store responses. |
| Maintenance | Abandoned packages and outdated test-tool line remain. | Replace through dedicated compatible build/tooling changes, as described above. |

These findings are intentionally not presented as completed. The source changes do not authorise or perform deployment, migration, credential rotation or environment modifications.

## Primary references

- [Drupal 11.4.12 release notes](https://www.drupal.org/project/drupal/releases/11.4.12): core and upstream Symfony/HTML parser changes.
- [AI 1.4.11 release](https://www.drupal.org/project/ai/releases/1.4.11): security fixes on the retained AI line.
- [Simple OAuth advisory SA-CONTRIB-2026-296](https://www.drupal.org/sa-contrib-2026-296): affected/fixed versions and required consumer/scope review.
- [Allowed Languages advisory SA-CONTRIB-2026-308](https://www.drupal.org/sa-contrib-2026-308).
- [SVG Image releases](https://www.drupal.org/project/svg_image/releases) and [SVG Sanitizer releases](https://github.com/darylldoyle/svg-sanitizer/releases).
- [Apple: validating apps that connect to your server](https://developer.apple.com/documentation/devicecheck/validating-apps-that-connect-to-your-server): certificate/key checks and one-use server challenges.
- [VBO issue 3323324](https://www.drupal.org/project/views_bulk_operations/issues/3323324): the legacy workaround and the requirement for action access implementations to honour the object return contract.

The package/upgrade assessment additionally uses the actual Composer lock data and machine-readable advisory responses captured during this review; no claim of exploitation is inferred from package presence alone.

## File inventory

The following files form the proposed change. Tests are included so the fixes remain reproducible. No country content/configuration export was edited.

- `.github/workflows/cms-review.yml`
- `.github/workflows/pipelines.yml`
- `composer.json`
- `composer.lock`
- `docroot/.gitignore`
- `docroot/modules/custom/bebbo_api_security/bebbo_api_security.services.yml`
- `docroot/modules/custom/bebbo_api_security/src/Controller/SecurityController.php`
- `docroot/modules/custom/bebbo_api_security/src/EventSubscriber/ApiSecuritySubscriber.php`
- `docroot/modules/custom/bebbo_api_security/src/Form/ApiSecuritySettingsForm.php`
- `docroot/modules/custom/bebbo_api_security/src/Routing/DeviceTokenRouteSubscriber.php`
- `docroot/modules/custom/bebbo_api_security/src/Service/AppleAppAttestService.php`
- `docroot/modules/custom/bebbo_api_security/src/Service/DeviceRegistryService.php`
- `docroot/modules/custom/bebbo_api_security/src/Service/GooglePlayIntegrityService.php`
- `docroot/modules/custom/bebbo_api_security/src/Service/JwtService.php`
- `docroot/modules/custom/bebbo_api_security/src/Service/SideloadedVerificationService.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Kernel/ApiSecuritySubscriberIntegrationTest.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Kernel/JwtServiceTest.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Kernel/SecurityControllerTest.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Kernel/SideloadedVerificationServiceTest.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Unit/ApiSecuritySubscriberTest.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Unit/AppleAppAttestServiceTest.php`
- `docroot/modules/custom/bebbo_api_security/tests/src/Unit/DeviceTokenRouteTest.php`
- `docroot/modules/custom/bebbo_custom_general/src/Controller/CsvExportController.php`
- `docroot/modules/custom/bebbo_custom_general/tests/src/Unit/AiTranslationRevisionTest.php`
- `docroot/modules/custom/bebbo_custom_general/tests/src/Unit/CsvExportRegressionTest.php`
- `docroot/modules/custom/bebbo_custom_general/tests/src/Unit/HostingSettingsTest.php`
- `docroot/modules/custom/bebbo_serializer/src/EventSubscriber/ApiVaryResponseSubscriber.php`
- `docroot/modules/custom/bebbo_serializer/tests/src/Unit/ApiVaryResponseSubscriberTest.php`
- `docroot/modules/custom/pb_content_analytics/pb_content_analytics.routing.yml`
- `docroot/modules/custom/pb_content_analytics/src/Service/AnalyticsSyncService.php`
- `docroot/modules/custom/pb_content_analytics/tests/src/Kernel/AnalyticsSyncRegressionTest.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/AssigncontentAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/ChangeToSMEAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/ChangedToArchiveAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/ChangedToPublishedAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/ChangedToSeniorEditorAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/MovefrompublishtodraftAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/MovefrompublishtosenioreditorAction.php`
- `docroot/modules/custom/pb_custom_field/src/Plugin/Action/MovefrompublishtosmeAction.php`
- `docroot/modules/custom/pb_custom_field/tests/src/Unit/BulkActionAccessTest.php`
- `docroot/modules/custom/pb_strings/src/Form/StringsFilterForm.php`
- `docroot/modules/custom/pb_strings/src/Form/StringsTranslateForm.php`
- `docroot/modules/custom/pb_strings/tests/src/Unit/StringTranslationAccessTest.php`
- `docroot/sites/common_settings/post.settings.php`
- `docs/reviews/CMS_SECURITY_REVIEW_2026-11-16.md`
- `patches/ai_translate/stamp-revision-metadata-on-ai-translation.patch`
- `phpunit.xml.dist`
- `recipes/.gitignore`
