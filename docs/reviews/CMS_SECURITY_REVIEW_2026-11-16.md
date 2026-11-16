# Bebbo CMS: explained review of bugs, security fixes and upgrades

**Review and explanation updated:** 16 November 2026

**Repository:** UNICEFECAR/parenting-app-bebbo-CMS

**Original version compared:** `main` at `5060246f34ec7caafb9adf4e00245c2a1a46957c`

**Branch containing the fixes:** `codex/cms-security-review-2026-11-16`

**Implementation described:** commit `f1c3494560976b02b789f52a2991ef1e16c02823`. This expanded explanation changes the documentation, not the application code.

## What this report means for Bebbo

The review found weaknesses in several parts of the CMS: controlling who can change content, recognising app installations, keeping app access working, translating interface text, downloading reports and updating analytics. It also found that several third-party components needed security updates.

The fixes are saved on a separate GitHub branch and proposed in [draft PR #370](https://github.com/UNICEFECAR/parenting-app-bebbo-CMS/pull/370). **They have not been merged into main or deployed to a live environment.** Caregivers and CMS staff therefore continue to use the currently deployed version until a separately planned release takes place.

The results from the code review are:

- **20 areas of fixes and safeguards**, explained individually below. Some areas contain several related changes.
- **27 package version changes.** Packages are third-party software components used by the CMS.
- The original dependency scan reported **44 published security warnings affecting 22 installed packages**. The revised dependency file reports **zero known warnings from those advisory feeds**. This is a check against published vulnerabilities, rather than proof that all possible security weaknesses have been removed.
- **155 automated tests, with 502 checks, passed.** The GitHub review checks also passed for the implementation commit. A further 13 tests checked that the upgraded AI component still handles streamed text correctly.
- **Some important work remains before deployment.** This includes coordinating the mobile app and CMS device-verification process, preparing a server secret, checking actual country permissions and testing external services. These outstanding items are explained in a separate section.

## Where the main changes affect daily work

| Area | Practical change being proposed | Detailed items |
|---|---|---|
| Editorial work | Check the right permissions before publishing, archiving or changing several content items together. | 3 |
| App connections | Save complete device records, validate access passes more carefully and avoid conflicts between the two token systems. | 4–10 |
| Secure content delivery | Apply appropriate rules to saved API responses and strengthen Apple/Google verification handling. | 11–13 |
| Interface translations | Keep translation within assigned languages and protect original strings. | 14 |
| Analytics | Protect the sync action and update valid current counts without rewriting older revisions. | 15–16 |
| Report downloads | Let large CSV exports finish, report failures and prevent text being interpreted as spreadsheet instructions. | 17–18 |
| Hosting and releases | Use a real server secret, retain memory limits, install corrected components and run repeatable checks. | 1–2, 19–20 |

## How to read the findings

For each finding, the report explains what the relevant CMS function does, what was wrong, an example of the possible effect, exactly what changed, and what has or has not been tested. File names are included at the end of each item for the developer.

**The examples illustrate how a bug could affect Bebbo. They are not claims that the example happened on the live system.** Where a failure was reproduced in an isolated test, that is stated explicitly.

“Fixed on the review branch” means that the proposed source code has changed. It does not mean that the fix is already protecting the live service, or that every real-device and country-specific scenario has been tested.

| What is being discussed | What it means here |
|---|---|
| CMS | The Drupal system where staff manage Bebbo content, translations, media and settings. |
| API | The connection through which the mobile app or another system asks the CMS for information. |
| Device authentication | Checking an app installation before allowing it to use selected API connections. It is separate from a staff member's CMS login. |
| Token | A digital access pass. An access token is presented when requesting information; a refresh token is used to obtain a replacement access pass later. |
| Cache | A saved copy of a response, used to serve repeated requests faster. Its security rules must match the information it stores. |
| Verified | A specific code check or test passed. The report states the limits of that evidence. |
| Outstanding | A code change, configuration task, decision or real-system test that is still needed. |

## Scope and limits of the review

The review covered shared custom code, third-party dependencies, configuration files and the build/checking process. It examined authentication, content access, country/language restrictions, editorial actions, translations, exports, import integration points, media-related code, analytics updates and hosting settings.

The shared code serves the default site and the repository's `zimbabwe`, `ecuador`, `turkey`, `somoa`, `pakistan` and `bangladesh` site directories. These are the repository's existing directory names. Country configuration and translation files were checked for syntax, but were not imported into a live or staging database.

Testing used isolated local databases, generated test keys and simulated service responses. The review did not change Acquia environments, live databases, content, accounts, credentials or external services. It did not include an interactive check of every country, language and CMS screen.

## Detailed findings and changes

**Status of items 1–20:** the described code changes are implemented on the review branch. Each item identifies any remaining testing or deployment requirement.

### 1. Updating software components with published security weaknesses

**What this part does.** Bebbo's CMS uses Drupal and many additional modules and PHP libraries. These provide functions such as content management, permissions, image processing and communication between systems. A weakness in one of these components can affect Bebbo even when Bebbo's own custom code is correct.

**What was wrong.** The versions recorded in the original dependency file matched 44 published security warnings across 22 packages. The warnings covered several kinds of problem: allowing an action without the right permission, processing unsafe content, reading or handling files incorrectly, overloading the server and, in some cases, running unintended code on the server. Whether a particular weakness can be used depends on which features are enabled and how they are configured.

**Example of the possible effect.** An unsafe content-processing library could mishandle specially prepared text or an image. A permission-related weakness could allow a user to do more than their role should permit. The package scan identifies exposure to known weaknesses; it does not establish that anyone exploited them on Bebbo.

**What changed.** The affected components and the supporting components they require were upgraded together using Composer, the CMS's package manager. Drupal remains on the 11.4 release line and the AI module remains on 1.4, with their relevant fixes. The SVG image-cleaning library needed a move to a newer major version to obtain its available security fixes, so its related Drupal module was upgraded with it. The exact versions are listed later in this report.

**What should improve.** After a tested deployment, the CMS will use the corrected component versions instead of the versions flagged by the scan.

**Evidence and remaining limits.** A fresh installation of the updated package set succeeded, and the revised dependency scan returned no known vulnerability warnings. Automated code tests also passed. The live site's installed versions were not inspected or changed, and the remaining integration tests still apply.

**Files changed:** `composer.json`, `composer.lock`; the AI translation patch listed in item 2.

### 2. Making the updated CMS install successfully without losing existing fixes

**What this part does.** Bebbo includes small modifications to some third-party components. These modifications are called patches. When the CMS is installed, the build process first obtains a component and then applies the relevant Bebbo patch.

**What was wrong.** A fresh installation of the original version failed when it tried to apply an old bulk-operations patch. Some existing AI patches also conflicted with the upgraded components. This matters because a developer may have a working installation on their computer while a fresh deployment fails to build from the same repository.

**Example of the possible effect.** A release could stop during installation, before the updated CMS is ready to run. Simply ignoring every failed patch would also be unsafe, because a patch might contain a Bebbo feature or fix that is still required.

**What changed.** Three cases were handled separately:

- The obsolete bulk-operations workaround was removed from the active build configuration. Its underlying problem in Bebbo's custom actions was corrected directly, as explained in item 3.
- The separate patch for preserving HTML during AI streaming was removed from the active configuration because the upgraded AI component already contains that fix.
- The patch that records the editor and time of an AI translation was adjusted to work with AI Translate 1.4.4. The upgraded component's new access-related requirements were retained.

**What should improve.** A new installation can be built reproducibly, while preserving the required AI formatting and translation-history behaviour.

**Evidence and remaining limits.** A clean installation in a separate working copy applied the configured patches successfully. Thirteen AI streaming tests, with 161 checks, passed. A new test also confirmed that an AI translation records its editor, date and revision message. Real provider output still needs integration testing.

**Files changed:** `composer.json`, `composer.lock`, `patches/ai_translate/stamp-revision-metadata-on-ai-translation.patch`; custom action files in item 3.

### 3. Checking permission before publishing, archiving or changing many articles at once

**What this part does.** Bulk editorial actions let an editor select several pieces of content and apply an operation, such as publishing them or moving them to senior-editor review, in one step.

**What was wrong.** Eight custom actions contained a check intended to recognise content items before checking permission. The check compared two different kinds of value, so it never succeeded. The code then returned “allowed” without performing its intended checks. This affected the custom access check for people able to reach and use those bulk operations. It could also cause an error because Drupal sometimes expects a detailed permission result, while the code returned only yes/no.

**Example of the possible effect.** An editor selects 20 articles and chooses “Publish”. The intended rule is that the operation must respect permission to edit the articles and to make that particular workflow change. The faulty custom check could skip those safeguards. The review did not demonstrate that an unauthenticated visitor could publish content.

**What changed.** The actions now correctly recognise content items, check editing and status-field permission, and return the kind of permission result Drupal requests. The actions that change workflow state also check whether the content uses moderation and whether the user's role permits the requested transition. Unsupported items are refused. The existing country/language execution logic remains in place.

**What should improve.** Selecting many articles should not provide a way around the access rules that should govern an editorial change.

**Evidence and remaining limits.** Tests cover allowed and denied editing, allowed and unavailable workflow transitions, unsupported items and both kinds of permission response across all eight actions. The actual roles and workflows in each country's database still need acceptance testing.

**Developer detail.** The faulty comparison was `$object->getEntityType() === 'node'`. The fix uses `NodeInterface` and Drupal's normal access/workflow services.

**Files changed:** `docroot/modules/custom/pb_custom_field/src/Plugin/Action/AssigncontentAction.php`, `ChangeToSMEAction.php`, `ChangedToArchiveAction.php`, `ChangedToPublishedAction.php`, `ChangedToSeniorEditorAction.php`, `MovefrompublishtodraftAction.php`, `MovefrompublishtosenioreditorAction.php`, `MovefrompublishtosmeAction.php` (all in that directory).

### 4. Saving a complete record when a store-distributed app registers its device

**What this part does.** In the device-authentication flow, the CMS records an app installation's identifier, platform and verification method. This record is used by later token and verification operations. The store-build registration path uses the device-registry service discussed here.

**What was wrong.** The service prepared a full device record, but a later database instruction replaced the insert field list with only the creation date. Required information, including the device identifier, was therefore missing from a new record. An isolated database test reproduced the resulting failure.

**Example of the possible effect.** An Android or iOS installation completes the required verification step, but the CMS fails while saving its registration. If the requested API requires device authentication, the app may be unable to complete that access flow.

**What changed.** A new device is now inserted with all required registration fields. Updates to an existing device use a separate field list, which preserves the original creation date rather than treating every re-registration as a new installation.

**What should improve.** A valid new registration can be saved successfully, and repeat registrations retain a consistent record of when the device was first created.

**Evidence and remaining limits.** Real-database automated tests for registration, re-registration and cleanup pass. These use an isolated test database; they do not demonstrate a complete Google- or Apple-backed registration from an actual phone.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Service/DeviceRegistryService.php`.

### 5. Preventing an existing sideloaded device identity from being replaced

**What this part does.** A sideloaded app is installed outside the normal app-store route. In this verification flow, the app has a pair of cryptographic keys: a private key it keeps and a public key the CMS can use to check its signed replies. The public key is not a password and does not need to be secret.

**What was wrong.** The public registration endpoint could overwrite the stored key and status using only a supplied device identifier. A caller who knew an existing identifier could therefore attempt to replace its registered identity. The old flow could also reset a previously revoked device to a pending state.

**Example of the possible effect.** The CMS has already associated device A with its original key. A later registration claims to be device A but supplies a different key. Previously, that new key could replace the original record instead of being refused.

**What changed.** The CMS now keeps the first registered key associated with that identity. It permits another verification attempt with the same key, but refuses replacement keys, changes to another platform and revoked identities. A one-use verification challenge is also marked as consumed through a conditional database update, so two requests cannot both successfully consume it after reading the same initial state.

**What should improve.** Knowing an existing identifier should no longer be enough to replace that sideloaded identity through this registration path.

**Evidence and remaining limits.** Tests cover changed keys, same-key re-registration, revoked devices, incorrect signatures, reused challenges and expiry. A real multi-server concurrency test remains outstanding. Also, possession of a self-generated key does not prove that the caller is an authorised Bebbo build; outstanding item A2 explains this separate limitation.

**Compatibility point.** An installation that loses its private key must use a new identity or a separately authorised reset process. It can no longer silently replace the old key.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Service/SideloadedVerificationService.php`.

### 6. Avoiding broken or duplicated access-pass renewal when requests overlap

**What this part does.** An access token is a short-lived digital pass used for API requests. A refresh token allows the app to obtain a new pass later. When refresh-token rotation is enabled, the CMS retires the old refresh token and gives the app a replacement.

**What was wrong.** Two renewal requests arriving almost together could both see the same old token as usable. Separately, the code could retire the old refresh token and then fail to create the new access token, leaving the app without a usable replacement. The renewal code also did not check whether the device was still active.

**Example of the possible effect.** An app retries a renewal request after a slow connection. Both requests reach the CMS. Without an indivisible check-and-update, both can try to renew the same token. Alternatively, a server signing-key problem could leave the app holding an old token that has already been retired.

**What changed.** The CMS now requires an active device and creates the signed access token before changing the refresh record. It claims the old refresh token through a database update that succeeds only while the record is still valid and unused. Retiring the old token and storing its replacement are grouped in a database transaction. A conflicting/replayed use follows the existing security policy of invalidating that related series of refresh tokens. Expiry at the current second is treated as expired.

**What should improve.** Renewal failures should be less likely to strand a legitimate session or allow one refresh token to produce multiple valid replacements. Conflicting retries can still require the app to verify itself again; this change does not promise that every retry will be invisible to users. The deliberate setting that disables rotation is retained.

**Evidence and remaining limits.** Database tests cover normal rotation, reuse detection, revoked devices and signing failure. Production MySQL behaviour under simultaneous requests still needs testing.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Service/JwtService.php`.

### 7. Checking the contents of a signed device access pass, as well as its signature

**What this part does.** A JWT is a signed token containing information such as who issued it, which device it identifies and when it expires. The signature helps establish that the token came from a holder of the correct signing key.

**What was wrong.** The code checked the signature, but did not explicitly require all the information that Bebbo's token-handling code relies on. A correctly signed token could have the wrong issuer, a missing or incorrectly typed device identifier, or incomplete time information. The existing signature check was therefore not enough to establish that the token was a valid Bebbo device pass in the expected format.

**Example of the possible effect.** A server-side integration mistakenly issues a signed token without a device identifier, or places a list where a single identifier should be. Later CMS code assumes that information exists and is usable.

**What changed.** The token must identify `bebbo-cms` as its issuer, contain a non-empty device identifier within the allowed length, and contain integer issue/expiry times with expiry later than issue time. The existing RS256 signature-algorithm restriction is retained.

**What should improve.** Signed but incomplete or inappropriate tokens are refused before later code relies on their contents. This finding does not mean that arbitrary unsigned tokens were previously accepted.

**Evidence and remaining limits.** Automated tests cover incorrect issuer, missing or malformed fields, empty identifier, expired tokens, altered tokens and tokens signed with another key. Immediate revocation of an already issued access token is a separate outstanding design question, explained in A6.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Service/JwtService.php`.

### 8. Rejecting badly formed authentication requests cleanly

**What this part does.** Registration and token endpoints receive JSON, a structured message format used by the app. Each field must have the expected kind of value and fit within the size accepted by the verification service and database.

**What was wrong.** The validation mainly checked whether a field was present. A request could provide a list, object or number where the code expects text, or send text too long for storage. These values could reach later code and produce a server error instead of a clear rejection.

**Example of the possible effect.** A faulty app request sends a nested list as the device identifier. The endpoint passes it to a function that requires a text identifier. The request then fails with a PHP type error, which the app may experience as a failed connection or failed registration.

**What changed.** Authentication requests are limited to 64 KiB. Known fields must be non-empty text with appropriate maximum lengths. Challenge values and client-data hashes must have the expected 64-character hexadecimal format before they are decoded or used by cryptographic functions.

**What should improve.** Malformed requests should receive a deliberate client-error response, such as “invalid field” or “request too large”, before causing a type or storage failure.

**Evidence and remaining limits.** Endpoint-level tests cover nested values, integers, overlong fields, invalid JSON, unsupported platforms and oversized requests. Successful existing device flows are also tested. These checks concern the CMS request handling; no mobile-device crash was reproduced by this test.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Controller/SecurityController.php`.

### 9. Applying API protection consistently when a URL includes a language prefix

**What this part does.** Drupal can recognise a language prefix or an alternative address and translate it into the internal route it should serve. For example, a request may include an Arabic language prefix before an API address.

**What was wrong.** The device-token check ran before Drupal finished resolving those prefixes and aliases. It checked the raw address for the protected `/v2/api/` pattern. An address that eventually reached a protected API could therefore fail to match that raw-text pattern.

**Example of the possible effect.** A request uses `/ar/v2/api/articles/ar`. The relevant API is still the articles API, but the raw address starts with `/ar/`, rather than `/v2/`. A check based only on the latter starting pattern could miss it.

**What changed.** The token check now runs after routing and uses Drupal's resolved internal path. The response-cache rule uses the same resolved-path approach. This makes the protection follow the API actually being accessed.

**What should improve.** A language prefix or alternative route name should not provide a way around the device-token check when protection is enabled.

**Evidence and remaining limits.** A test verifies rejection of a tokenless, language-prefixed request that resolves to a protected API. Tests also cover disabled mode, grace-period mode, enforced mode and the development bypass. Every deployed country's URL configuration has not been exercised through a browser or real phone.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/EventSubscriber/ApiSecuritySubscriber.php`, `bebbo_api_security.services.yml`.

### 10. Keeping staff/integration login tokens separate from app-device tokens

**What this part does.** The CMS has two different token systems. Simple OAuth handles Drupal user/integration access. Bebbo's custom system checks app installations for selected device APIs. Both can send their pass in the same HTTP field, labelled `Authorization: Bearer`.

**What was wrong.** Simple OAuth could receive a device token first and try to validate it as a Drupal user token. Because the two token types belong to different systems, a legitimate device request could be rejected before Bebbo's own validator had a chance to check it.

**Example of the possible effect.** The mobile app sends a valid device pass to the content API, but the CMS's user-token system treats it as an invalid user login. The content request fails even though the device-token validator would have accepted it.

**What changed.** Routes belonging to the configured device APIs and the device-security endpoints now tell Simple OAuth to leave authentication to the appropriate custom mechanism. User OAuth routes and other interfaces, including JSON:API, retain their existing user authentication. Saving the protected/excluded path settings also requests a route rebuild so the separation follows those settings.

**What should improve.** The correct validator should handle each type of token. A device token does not gain permission to administer Drupal or edit content through this change.

**Evidence and remaining limits.** Tests check which routes opt out and exercise the installed Simple OAuth provider's actual decision method. Real deployed integrations and their permitted operations still need testing, especially after the OAuth upgrade described in A5.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Routing/DeviceTokenRouteSubscriber.php`, `bebbo_api_security.services.yml`, `src/Form/ApiSecuritySettingsForm.php`.

### 11. Ensuring saved response copies do not bypass access checks or expose tokens

**What this part does.** The CMS and delivery networks can keep saved copies of API responses to make repeat requests faster. A response that is public can often be shared. A response containing a token, or one that requires a successful access check, needs stricter rules.

**What was wrong.** Security endpoints had no explicit instruction forbidding storage of their responses. Another custom rule broadly removed the instruction telling caches that responses might differ according to browser cookies. Together, these behaviours were too broad for authenticated, personal or security-related responses. A saved response must not allow a later request to bypass an access check.

**Example of the possible effect.** A permitted request causes a response to be saved. A later request without the required pass should not receive that saved copy simply because it asks for the same address. Similarly, an individual device's token response should never become a shared cached response.

**What changed.** Device-security responses and protected API responses in enforced mode are marked `private, no-store`, meaning they should not be stored and reused by shared caches. Drupal's own cache lifetime is set to zero where applicable. Cookie-based response variation is removed only for successful, explicitly public, safe requests without authentication/cookie inputs or response cookies. Other variation rules remain.

**What should improve.** Public content can retain appropriate caching, while token responses and enforced access checks receive the stricter handling they require.

**Evidence and remaining limits.** Tests cover public content, token responses, enforced content, authentication/cookie inputs, private/error responses and subrequests. Previously saved copies on the real CMS/CDNs must still be cleared and tested before rollout. Less caching in enforced mode can increase server load; no load benchmark was performed.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/EventSubscriber/ApiSecuritySubscriber.php`, `docroot/modules/custom/bebbo_serializer/src/EventSubscriber/ApiVaryResponseSubscriber.php`.

### 12. Checking that an Apple-verified key is the same key the CMS stores

**What this part does.** Apple App Attest supplies evidence about an app installation. That evidence contains a certificate and a cryptographic public key. The CMS must verify that these pieces of evidence refer to the same identity before trusting the key for later checks.

**What was wrong.** The original code checked certificate signatures and a supplied key identifier, but did not fully connect the public key it stored with the public key in the signed certificate. It also did not enforce certificate validity dates, and some malformed/truncated structures were not sufficiently checked.

**Example of the possible effect.** The server checks one piece of cryptographic evidence but then stores another key without confirming that they match. Its later confidence in that stored key is therefore stronger than the checks actually justify.

**What changed.** The certificate and supplied COSE key must represent the same EC P-256 key. COSE is the compact key format used in the attestation message. The certificate key's calculated identifier must match the supplied key ID. Certificate dates, client-data hash format, credential lengths and the types of certificate-chain values are also checked.

**What should improve.** The CMS should trust only a key that is properly linked to the verified certificate evidence, rather than accepting loosely related pieces of information.

**Evidence and remaining limits.** Tests use real keys and certificates generated with OpenSSL to check the key-binding rule, together with existing message-decoding and assertion tests. These are not Apple-signed samples from an actual phone. Validity checks can also reject old or expired evidence that a previous flow reused. The one-use server-challenge process still requires coordinated app/CMS work, as explained in A1.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Service/AppleAppAttestService.php`.

### 13. Accepting both supported Google integrity-message formats

**What this part does.** Google Play Integrity provides verification information about an Android app request. The Classic and Standard versions of that process use different fields to link the returned evidence to the request being checked.

**What was wrong.** The original service checked only the Classic field, called a nonce. The Standard format supplies a request hash instead. Existing automated tests for Standard-format verification failed against the original service.

**Example of the possible effect.** An Android app uses Google's Standard Integrity flow and receives valid evidence. The CMS looks for the Classic field, does not find the expected value and rejects the request.

**What changed.** The service now handles Classic nonce binding and Standard request-hash binding. For the Standard path, it calculates and compares the expected SHA-256/base64url value. Package-name, time/freshness, device-integrity and app-recognition checks remain in place. The development option that accepts an unrecognised app version was not enabled.

**What should improve.** A legitimate Android request should not be rejected simply because it uses the Standard message format instead of Classic.

**Evidence and remaining limits.** Automated tests cover both formats and rejection of inappropriate package, age and integrity results. These tests do not perform a real Google token exchange or confirm each country's configured Android package/cloud-project settings. The request's resistance to reuse is also part of the outstanding protocol work in A1.

**Files changed:** `docroot/modules/custom/bebbo_api_security/src/Service/GooglePlayIntegrityService.php`.

### 14. Keeping interface-text translation within the translator's assigned languages

**What this part does.** The strings screen lets staff translate short interface text, such as labels and app messages. These strings have an original/source value and separate translated values.

**What was wrong.** The screen offered every language, even though its permission description referred to assigned languages. Saving did not recheck the assignment. It could also treat the selected source language as an editable translation and overwrite its original label. Finally, the code treated the legitimate text `0` as empty, which could remove a translation instead of saving it.

**Example of the possible effect.** A translator assigned to Arabic sees another country's language as an editing option. In another case, choosing the original language could change the shared source text rather than adding a translation. This could affect the content other translators use as their starting point.

**What changed.** The available languages are filtered through the Allowed Languages service, and the submitted language is checked again before saving. Explicit administrator/all-language permissions remain respected. Stale screen filters fall back to a permitted language, and accounts without assigned languages receive an appropriate message. Source-language fields are disabled; attempted source or other-vocabulary writes are skipped. Only an actual empty string is treated as empty.

**What should improve.** Translators should work within the language scope assigned to them, without accidentally changing the original string. Legitimate zero text is retained.

**Evidence and remaining limits.** Tests cover assigned-language filtering, refusal of an unassigned language and preservation of a source label. Actual country role/language assignments and the visual editing experience still need acceptance testing.

**Files changed:** `docroot/modules/custom/pb_strings/src/Form/StringsFilterForm.php`, `StringsTranslateForm.php`.

### 15. Protecting the analytics-sync button against a forged browser request

**What this part does.** A privileged CMS user can start a synchronisation that updates content analytics from the reporting source. The CMS therefore needs to check both the user's permission and whether the request genuinely belongs to that user's intended action.

**What was wrong.** A GET address could start this data-changing operation. The route checked permission, but lacked Drupal's CSRF protection. CSRF means tricking an already logged-in person's browser into making an action request they did not intend.

**Example of the possible effect.** A logged-in user with sync permission follows an unrelated link that causes their browser to request the sync address. The fact that the browser is logged in should not, by itself, make that an authorised click on “Sync”.

**What changed.** The route now requires Drupal's CSRF token, a value used to validate the action request. The existing form already creates its destination through Drupal's route/URL system, which can supply that token.

**What should improve.** Permission alone should no longer be enough to start the action through an unvalidated link. This finding does not mean that an anonymous visitor had permission to run the sync.

**Evidence and remaining limits.** The route and form integration were reviewed and the configuration parses correctly. An authenticated browser test is still needed to confirm that a normal button click works and a tokenless request is refused.

**Files changed:** `docroot/modules/custom/pb_content_analytics/pb_content_analytics.routing.yml`.

### 16. Updating current analytics without clearing good counts or changing old revisions

**What this part does.** The analytics sync updates values such as content likes and reads. Drupal also keeps older revisions of content, allowing staff to see earlier versions.

**What was wrong.** Incomplete source records could be converted into values such as zero instead of being rejected. An unexpected data type could stop processing. Separately, revision-table updates affected every revision belonging to an item, rather than only its current revision. The related field writes were not grouped into a single database transaction.

**Example of the possible effect.** A record arrives without a valid likes count. Treating the missing value as zero could erase a good count. Or a new analytics update could change the stored value in an old revision as well as the current one, making the revision history less reliable.

**What changed.** The service now validates the item identifier, record structure, content type, timestamp and non-negative integer counts before writing. Invalid records are skipped and logged. It updates current entity values and only the current default revision's relevant rows, while excluding deleted rows. The related writes are grouped in a transaction so they can be rolled back together if an error occurs.

**What should improve.** Bad incoming data should not silently reset valid counters, and updating today's analytics should preserve older revision values.

**Evidence and remaining limits.** A test using real Drupal content fields and two revisions confirms current-count updates, preservation of the earlier revision and rejection of malformed records. The test does not prove rollback under every production MySQL failure. Any historical values already overwritten on a live database are not repaired by these source changes.

**Files changed:** `docroot/modules/custom/pb_content_analytics/src/Service/AnalyticsSyncService.php`.

### 17. Allowing a large CSV export to finish instead of repeatedly processing one page

**What this part does.** A large export is split into smaller pages so the CMS can process it in batches. Each fixed-page operation should fetch its page once, append its rows and tell Drupal that the operation is complete.

**What was wrong.** The operation fetched a specific page, then reported that it was unfinished. Drupal could call the same operation again rather than move to the next page. The final download redirect also used an internal route name where a usable URL was expected. Some fetch errors were logged but hidden from the overall batch result.

**Example of the possible effect.** An export needs several pages. Page 1 is fetched and appended, but the batch says page 1 is unfinished. The process can append that page again and again, increasing the file size without completing the export. A failed remote page could also lead to an incomplete export being treated as successful.

**What changed.** Each fixed-page operation now reports completion after processing. The final destination is converted into a proper download URL. A fetch failure is logged and allowed to fail the batch visibly, rather than silently continuing as though the export succeeded.

**What should improve.** The batch can progress through its pages and reach the download step, while reporting remote failures more honestly.

**Evidence and remaining limits.** A focused test confirms that a fixed-page operation completes after a single remote-manager call. It does not recreate a full large production export or every remote-service interruption. Large, interrupted and retried exports still need integration testing.

**Files changed:** `docroot/modules/custom/bebbo_custom_general/src/Controller/CsvExportController.php`.

### 18. Keeping exported text from being interpreted as an Excel formula

**What this part does.** A CSV export is a text file that staff may open in Excel or another spreadsheet program. Quoting a cell correctly protects the CSV structure, but does not always tell the spreadsheet to treat the cell as literal text.

**What was wrong.** A title or label starting with a formula character could be interpreted as a spreadsheet instruction when opened, even though the CSV quotes were escaped correctly.

**Example of the possible effect.** A label containing `=1+1` is intended as text, but a spreadsheet may calculate it and show `2`. More harmful spreadsheet instructions are possible with unsafe input. The review did not find evidence that a malicious export had been opened by staff.

**What changed.** Both export paths now use the same cell-encoding function. It checks for dangerous formula prefixes, including relevant leading whitespace/control characters, and adds a literal-text apostrophe when needed. Ordinary CSV quote escaping is retained.

**What should improve.** Labels and titles should remain data when a report is opened in a spreadsheet, rather than becoming executable spreadsheet instructions.

**Evidence and remaining limits.** Tests cover formula prefixes, whitespace/control prefixes and ordinary text containing quotes. The apostrophe is an intentional change in dangerous cells. If such a CSV is later re-imported as content rather than opened for reporting, that treatment should be tested as part of the import workflow.

**Files changed:** `docroot/modules/custom/bebbo_custom_general/src/Controller/CsvExportController.php`.

### 19. Using a real server secret and retaining a sensible memory limit

**What this part does.** This finding contains two hosting settings. A hash salt is a secret used when generating certain security-sensitive values, such as signed links/tokens. A PHP memory limit puts a ceiling on the memory an individual server process can consume.

**What was wrong.** Shared post-settings replaced the platform's salt with a value calculated from installation paths. Paths are predictable and are not an appropriate secret. The same file also removed PHP's memory limit for every request, reducing protection against a very large or faulty operation exhausting server memory.

**Example of the possible effect.** A security value may depend on a predictable input where a private secret should have been used. Separately, a problematic request could consume excessive memory and affect other requests served by the same infrastructure. The review did not demonstrate a live compromise or memory outage.

**What changed.** A secret supplied by the hosting platform is preserved. Alternatively, the hosting team can configure `BEBBO_HASH_SALT`; the CMS derives a separate site salt from that environment secret. If no secret is available, the code stops with an explicit configuration error instead of using a predictable fallback. The CMS also honours the host's PHP memory limit; a deliberate command-line job can request a different limit when appropriate.

**What should improve.** The security calculation uses an actual private secret, and ordinary requests retain the host's resource protection.

**Evidence and remaining limits.** Tests cover preserving a platform salt, deriving a site salt and refusing to proceed without one. No hosted secret or PHP configuration was changed.

**Important deployment consequence.** The hosting team must prepare this before release. Deploying the branch on a host without the required secret could prevent the CMS from starting. Changing the effective salt can invalidate existing signed links/tokens. The secret must remain stable and be backed up securely for future restores.

**Files changed:** `docroot/sites/common_settings/post.settings.php`.

### 20. Checking proposed CMS changes automatically and making build tools more predictable

**What this part does.** GitHub workflows run automated checks when developers push changes or open a pull request. A pull request is a proposal to merge one branch into another. These checks help reveal problems before a release.

**What was wrong.** The existing deployment-focused workflow did not cover pull requests to main or run Bebbo's custom PHPUnit tests. It also referred to an older moving checkout-action tag and did not explicitly limit the workflow's GitHub token permissions.

**Example of the possible effect.** A proposed change could look ready for main without automatically running the regression tests that would reveal a broken device flow or permission check. A fresh build could also depend on a moving tool reference instead of a specifically reviewed copy.

**What changed.** A review-only workflow now covers the CMS review-branch pattern and pull requests to main. It validates and installs dependencies, scans published vulnerabilities, checks custom PHP syntax and coding standards, runs Drupal static analysis, and runs the custom tests against a disposable SQLite database. The checkout and PHP-setup tools are pinned to specific commit IDs. Workflow token permissions are limited to reading repository contents; the review checkout does not retain credentials. PHPUnit configuration and generated-file ignore rules are included.

**What should improve.** Future changes receive repeatable checks closer to the point where they are proposed. The existing deployment destinations and deployment triggers remain unchanged; the review workflow has no deployment steps or environment secrets.

**Evidence and remaining limits.** The local checks and GitHub push/pull-request checks passed for the implementation commit. These tests assess code behaviour and buildability; they do not replace real phones, country-specific workflows or external-provider tests.

**Files changed:** `.github/workflows/cms-review.yml`, `.github/workflows/pipelines.yml`, `phpunit.xml.dist`; regression test files in the inventory below.

## Exact package upgrades and why they matter

A package is a software component obtained from another project. Composer is the tool that selects compatible package versions and records them in `composer.lock`. The lock file lets another developer install the same set of versions instead of receiving a different combination on each build.

Some rows below are parts of the same product. For example, the four Drupal core-related packages together support one Drupal installation. The total of 27 is the number of changed package versions, rather than 27 separate Bebbo features.

| Component group | What it does for Bebbo | Reason for this change |
|---|---|---|
| Drupal core and its core support packages | Provide the CMS framework, permissions, content handling and standard files used in a Drupal installation. | Bring the framework onto the corrected 11.4.12 package set, including relevant upstream security fixes. |
| AI and AI Translate | Connect Drupal with AI services and support translation workflows. | Address the published vulnerabilities while keeping the established AI 1.4 line. Preserve the project's formatting/revision-history fixes. |
| Allowed Languages, Simple OAuth and Shield | Help control language editing, token-based user/integration access and protected-environment access. | Address known access-control warnings in the installed versions. Actual country permissions and OAuth consumers still need testing. |
| Gin, Linked Field and Menu Link Attributes | Support the administration interface and how links/attributes are handled. | Bring the flagged components to corrected versions. |
| ImageAPI Optimize WebP, SVG Image and SVG Sanitizer | Process or safely display relevant image formats. | Address the image-related warnings, including the need for a newer SVG-sanitising library. |
| Stage File Proxy | Supports access to remote files in development/staging workflows. | Address the published warning affecting the locked version. Its deployed configuration was not inspected. |
| HTML5 parser and Symfony libraries | Handle low-level tasks such as parsing content, web requests, paths, files, messages and structured data. | Use the compatible corrected library versions needed by the revised package set. These libraries support many CMS functions indirectly. |

The table below preserves the precise before/after versions for the developer.


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

## Other available upgrades: why they were not all applied

The review also checked which directly used components have newer releases. A newer release is not automatically a necessary change: it may add features, alter behaviour or conflict with a Bebbo patch without addressing a weakness reported in this review. The security-related package changes identified above were applied; the other candidates below were recorded for planned maintenance. Their availability was checked on 16 November 2026 and may change later.

- **Code-checking tools need their own planned update:** PHPStan remains on the project's 1.x line and reports that it is old. Moving to 2.x requires coordinated updates to Drupal's PHPStan integration and deprecation rules; it is not an application security fix. Current static analysis passes.
- **Some supporting components are no longer maintained by their original projects:** Composer reports `acquia/memcache-settings`, `oomphinc/composer-installers-extender` and transitive `doctrine/common` as abandoned. “Abandoned” is Composer's label for maintenance status; it does not by itself prove an exploitable vulnerability. Their roles in Acquia cache settings, asset installation and test tooling need replacing deliberately. No replacement package was guessed or forced into production configuration.
- **Update components with Bebbo-specific patches in tested groups:** TMGMT, Entity Share, image processing, migration and cron modules carry project patches or prerelease constraints. Batch upgrade them only with representative import/translation/cron/image tests and patch review. Security fixes identified by the audit are already included above.
- **Leave broader Drupal/AI release changes for a separate, tested upgrade:** They are not required to clear the known advisories on the supported lines chosen here. The SVG library major upgrade is the exception because the secure version requires it.

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

## What the tests actually establish

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

A passing test means the behaviour checked by that test worked under the stated conditions. It does not establish that every country, phone, permission combination or external service has been tested.

The PHPUnit run also reports **710 metadata deprecation notices**. These largely concern older ways of declaring test information and supporting test infrastructure; they do not mean that 710 application checks failed. There is also an error-handler-change notice. PHPStan reports notices about its older tooling/bootstrap configuration. These messages remain visible and should be handled through planned test-tool maintenance. The reported test result is therefore “no errors or failures”, while still acknowledging those notices.

The GitHub push/pull-request workflows passed for implementation commit `f1c3494560976b02b789f52a2991ef1e16c02823`, including installation, advisory checks, coding standards, static analysis and the custom test suite. This expanded-documentation update does not change that application code.

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

## Work still needed before the changes can be treated as ready for release

The source fixes above do not complete every part of a secure rollout. There are three different kinds of outstanding work: **further app/CMS development**, **server configuration**, and **testing with actual country systems and external services**. These should not be confused with one another.

| Item | Type of work | Practical reason it remains open |
|---|---|---|
| A1. One-use device verification | Mobile app and CMS development, then real-device testing | The CMS and app need to agree on how a fresh verification request is issued, answered and consumed. |
| A2. Sideloaded-app trust | Security/product decision, possibly further development | Owning a self-generated key proves control of that key, but does not establish that the caller is an authorised Bebbo app. |
| A3. Hosting secret | Server configuration and release preparation | The corrected CMS must have a real secret available before it starts. |
| A4. Previously cached responses | Deployment preparation and access/load testing | Old saved responses may still exist outside the newly corrected application code. |
| A5. Existing OAuth integrations | Permission/configuration review and integration testing | The upgraded component changes which access existing integrations may receive. |
| A6. Immediate cancellation of access | Security/performance decision, possibly further development | Cancelling renewal does not immediately cancel every access pass already issued. |
| A7. Registration request limits | Traffic-policy decision and server/gateway configuration | A limit based only on a claimed device ID does not sufficiently control total registration volume. |
| A8. Hosting security and recovery | Infrastructure review and restore testing | Repository files cannot show all active hosting/CDN settings or prove that backups can be restored. |
| A9. Country roles and languages | Country-specific acceptance testing | The real permissions and workflow settings are held partly in each site's database. |
| A10. External services and large operations | Integration and interruption testing | Simulated responses cannot establish that the actual providers, imports and email services work together. |
| A11. Production database and load | MySQL/concurrency/performance testing | SQLite tests do not recreate multiple production requests running at the same time. |
| A12. Older maintenance components | Planned build/tooling maintenance | Some supporting packages are abandoned or use an older tooling line. |

### A1. High priority: make device verification specific to one fresh request

**What is still missing.** “Attestation” means obtaining evidence that an app/device is genuine. A “challenge” is a fresh random value the CMS issues for a particular verification attempt. The app should answer that challenge, and the CMS should accept it once. This helps prevent somebody reusing a captured old verification message, which is called a replay.

The current flow does not consistently require that full process. The iOS path accepts a client-supplied hash, while Android retains a fallback based on information supplied by the client or a deterministic value. A certificate or matching hash can be valid without proving that this is a fresh response to this particular CMS request, or that the claimed device identity is the correct one.

**Why the completed fixes do not resolve this.** Items 5, 12 and 13 strengthen particular key/message checks. They do not establish the entire one-use exchange between every mobile flavour and the server.

**What completion requires.** Agree and implement the app/CMS message flow; connect the challenge to the intended device, platform and key; reject expired or already used answers; and test it on real Android, iOS and supported sideloaded installations. Test older supported app versions as part of the rollout plan. The report does not recommend enabling strict enforcement until this work and real-device verification are complete. Existing live enforcement settings were not read or changed by this review.

### A2. High priority: decide what proof a sideloaded installation must provide

**What is still missing.** The sideloaded flow demonstrates that a caller can sign a message using the private key corresponding to its registered public key. That is useful for recognising the same caller again. However, another program can generate its own key pair too. This process alone does not establish that the program is an authorised Bebbo release.

**Why the completed fix does not resolve this.** Item 5 prevents a caller from replacing an existing registered sideloaded key. It strengthens continuity of an identity, rather than providing independent proof of where the app came from.

**What completion requires.** Confirm the intended distribution and security policy. If genuine-app proof is required for these installations, introduce and test an appropriate authorised provisioning or verification mechanism. Preserve the intended ability to distribute Bebbo outside app stores where that is needed. Device recognition must also remain separate from staff permission to edit CMS content.

### A3. High priority: prepare the hosting secret before deploying

**What is still missing.** The hosting environment must supply a strong, stable private salt, either through its existing secure setting or the new `BEBBO_HASH_SALT` configuration described in item 19.

**Why this matters.** The branch now deliberately refuses to fall back to a predictable salt. If the secret is absent on a particular host, deploying the branch could prevent that CMS from starting. A new effective secret can also invalidate previously generated signed links/tokens.

**What completion requires.** Check each environment's current secret-setting mechanism, provision a strong random secret where needed, keep different environments appropriately separated, and store it securely for future restores. Test login, action validation and one-use links after the change. Do not generate a new secret automatically on every request or every deployment. This task did not create, read or change a real hosting secret.

### A4. High priority: remove old saved API responses and test enforced access

**What is still missing.** A CMS or delivery network may already hold saved responses created before item 11's new cache rules exist. Updating the code does not necessarily erase those copies.

**Example of the required test.** Make a permitted request first, then make the same request without the required pass. The second request must not receive content through an old saved response. Repeat the test through the actual delivery networks, rather than testing only a direct connection to Drupal.

**What completion requires.** Clear the relevant Drupal/CDN API caches during the planned rollout, confirm that token responses are not stored, and verify access rejection after cache warming. Also measure the extra demand on the CMS when enforced responses are no longer served from shared caches. No live cache purge or infrastructure load test was performed.

### A5. High priority: review the access of existing OAuth integrations

**What is still missing.** In OAuth terminology, a “consumer” is a registered application/integration and its “scopes” describe the operations it may perform. The Simple OAuth security upgrade changes consumer-access rules and makes Client Credentials scope restrictions stricter. Client Credentials is a method an integration can use to act with its own application credentials.

**Why this matters.** Installing corrected code does not prove that every existing integration has appropriate permissions. A previously over-permitted integration may lose access it relied on, while a questionable existing token may need to be reviewed and revoked.

**What completion requires.** Run the normal database updates in an isolated environment, review the new consumer-access permission, check which applications/roles should receive it, and test each real integration's intended operations. Review existing consumers and tokens in line with the upstream advisory. Item 10 separates device-token handling from user OAuth, but does not perform this administrative review. No live consumers or tokens were changed.

### A6. Medium priority: decide whether cancelling access must take effect immediately

**What is still missing.** The system currently cancels refresh tokens so the device cannot obtain another access token. An access token already issued can remain usable until its own expiry.

**Example.** If a current access pass still has 20 minutes remaining when renewal is cancelled, that pass may continue to work for those 20 minutes. This is an illustration, rather than a measurement of any active device's configured expiry.

**Why this matters.** Some systems accept this short window to avoid a database check on every request. Others need immediate cancellation and therefore require an additional revocation or version check. Preventing revoked devices from renewing, as implemented in item 6, does not by itself provide immediate cancellation of all existing access passes.

**What completion requires.** Confirm the required security behaviour and acceptable expiry window. If immediate cancellation is required, implement it with tests and a clear performance budget. The report does not label that feature as implemented.

### A7. Medium priority: limit total registration traffic without blocking shared networks

**What is still missing.** The store-registration endpoint limits requests by device identifier. Because the identifier is supplied by the caller, changing the claimed identifier can multiply the available request budget. A per-device limit is therefore only one part of protection against excessive traffic.

**Why this matters.** Verification work consumes server/provider resources. Stronger limits can reduce abuse and service costs, but a simple limit on network address can also affect many legitimate caregivers sharing a clinic, school or mobile-network connection.

**What completion requires.** Choose appropriate combined limits at device, network and total-service level, using realistic registration volumes and allowing for shared networks. Configure/test those limits on the actual gateway or service. Existing live rate limits were not inspected or changed.

### A8. Medium priority: check the real hosting setup and demonstrate a restore

**What is still missing.** Source files cannot establish all the settings actually active in Acquia, Cloudflare, Fastly or a government-hosted deployment. They cannot prove that a backup is recent, complete or usable either.

**What needs checking.** Confirm accepted website/domain names; which upstream servers are trusted to supply request information; cross-origin browser access rules; HTTPS/TLS configuration; firewall/WAF rules; where secrets are stored; and the permissions granted to external-service credentials. In the tracked configuration, JSON:API is read-only and detailed error display is hidden. An environment can have overrides, so these observations cannot be assumed to describe every deployed site.

**What completion requires.** Review the authorised hosting settings and restore a backup into an isolated environment. Confirm that the restored content, media, country configuration and necessary secrets work together. No live configuration review or remote backup/restore was performed during the code task.

### A9. Medium priority: test actual country permissions and editorial work

**What is still missing.** Country roles, group membership, assigned languages and permitted editorial transitions depend on real site/database configuration as well as the shared source code. Automated tests use controlled examples and cannot establish that every live configuration is correct.

**Useful acceptance scenarios.** Check an assigned-language translator and an unassigned language; a country-restricted user attempting another country's content; a user selecting an unavailable publish/review transition; and an administrator performing an operation they should be allowed to complete. Test direct URLs and bulk operations, not just whether buttons are hidden. Also check translated revisions and Arabic/Urdu editing visually.

**What completion requires.** Record the intended access rules for each role and site, then test allowed and denied cases against those rules. A newly refused operation may expose an incorrect role configuration rather than a reason to remove the permission check. This review did not alter country content/configuration exports or perform the full acceptance matrix.

### A10. Medium priority: test actual services, large data and interruptions

**What is still missing.** Some functions depend on services or data that were not connected during isolated testing. A simulated successful response cannot establish that the actual account, credentials, network path and configuration will work.

**What needs testing.** Use representative data to test large and interrupted Entity Share imports/exports, CSV re-import behaviour, images/SVG/WebP generation, the actual BigQuery analytics endpoint, email delivery and two-factor authentication, and real AI translation output/revision records. Include unavailable services, slow responses, retries, invalid responses and interrupted transfers.

**What completion requires.** Perform these tests in an isolated environment using authorised service access. Check both the visible result and whether incomplete work is reported or recovered correctly. The code review did not send real provider requests, import live content or check live email delivery.

### A11. Medium priority: repeat database and load tests on the production-style stack

**What is still missing.** The kernel tests use SQLite, a lightweight test database. Production uses a MySQL-based stack, and several workers may process requests simultaneously. Locking, timing and failure behaviour can therefore differ.

**What needs testing.** Send overlapping refresh requests and challenge answers, verify that only the permitted use succeeds, and confirm recovery when a database write fails. Test analytics rollback, large batches, memory limits and response times under expected load. Include the additional server work caused by the stricter cache rules in enforced mode.

**What completion requires.** Run controlled tests on the supported MySQL configuration and realistic server setup before release. The branch adds conditional updates and transactions, but the review does not claim that production contention and every rollback scenario have been reproduced.

### A12. Maintenance: replace older supporting components through a planned change

**What is still missing.** Composer reports `acquia/memcache-settings`, `oomphinc/composer-installers-extender` and the indirect dependency `doctrine/common` as abandoned. PHPStan, the code-analysis tool, is also on an older major line.

**Why these were not simply removed.** These components participate in hosting cache settings, asset installation or test tooling. Removing or replacing them without checking those roles could break a build or hosting behaviour. “Abandoned” describes maintenance status; it is not itself evidence that the component has been exploited.

**What completion requires.** Identify compatible replacements or removal paths, update the related build/tooling configuration, and verify a fresh installation and the relevant hosting/test functions. The zero-advisory result describes known vulnerability warnings in the revised package set, not completion of this maintenance work.

## Practical order for the remaining work

1. **Complete the device-verification design and compatibility work.** Address A1/A2 and decide the required cancellation/rate-limit behaviour in A6/A7.
2. **Prepare a representative isolated environment.** Provide the secure salt, actual country configuration, supported MySQL stack and authorised test-service connections.
3. **Run the release acceptance checks.** Cover OAuth integrations, country permissions, phones, imports/exports, analytics, email, AI, cache behaviour, memory and load.
4. **Plan deployment and recovery only after those results are reviewed.** Include database updates, secret stability, clearing old saved responses, verification after release and a recovery/restore plan.

This is the remaining work list, rather than a statement that deployment has been authorised or performed. The CMS review PR remains a draft.

## Short glossary for the remaining technical terms

| Term | Meaning in this report |
|---|---|
| Authentication | Checking which user/app/device a request claims to come from. |
| Authorisation / permission | Checking what that recognised user/app/device is allowed to do. |
| Attestation | Evidence from a platform such as Apple or Google about an app installation/device. |
| Challenge / nonce | A value used to connect verification evidence to a particular request; a server-issued, one-use value provides stronger freshness/reuse protection. |
| Replay | Reusing a previously captured valid message when a fresh verification response should be required. |
| Public/private key | A related cryptographic pair: the private part signs/proves possession; the public part lets the CMS check the result. |
| Hash | A calculated fingerprint of data, used here as part of comparisons and verification. A hash of public/predictable data does not make that data a private secret. |
| JWT | A signed token carrying fields such as issuer, device identifier and expiry. |
| OAuth consumer / scope | A registered integration/application and the limits on what its access token permits. |
| CSRF | A forged action request made through the browser of a person who is already logged in. |
| Transaction | A group of database changes intended to succeed together or be rolled back together. |
| Atomic / conditional update | A database operation that checks and changes the relevant state together, avoiding a gap between a separate check and write. |
| Revision | A saved historical version of content. |
| Dependency / package | Software obtained from another project and used by the CMS. |
| Lock file | The record of exact dependency versions used to reproduce an installation. |
| Patch | A specific modification applied to third-party software during installation. |
| Static analysis | Checking code for certain errors without running all the real user/service flows. |
| Unit test | An automated test of a focused piece of behaviour, often with simulated dependencies. |
| Kernel test | A Drupal test that loads framework services and, where relevant, an isolated real database. |
| Regression test | A test intended to catch a previously identified bug if it returns later. |
| CI / GitHub workflow | Automated checks run when repository changes are pushed or proposed. |
| CORS | Browser rules governing requests from a different website/origin. |
| TLS | The security protocol used by HTTPS connections. |
| WAF | A web application firewall that filters certain unwanted traffic. |
| CDN | A delivery network such as Fastly or Cloudflare that can serve and cache responses closer to users. |
| Rollout / release gate | A required preparation or verification step before deploying the proposed change. |

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
