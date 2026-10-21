# PHP2SHELL vulnerability response — 21 October 2026

This record documents the response to CVE-2026-109618 and CVE-2026-109619 in the MediCare demonstration application. It records a confirmed outdated PHP runtime and precautionary containment. It does **not** establish that the application was exploited or that data was compromised.

**Status: response in progress.** The patched source-built base and application images are complete and locally verified. The public dev and prod backend entry points remain restricted; deployment, cloud execution checks and restoration are still pending. Times are UTC; Taiwan time is UTC+08:00.

## Outcome at this checkpoint

- Confirmed PHP **8.5.11** in the local backend runtime and identified PHP-FPM as the web execution model. Offline inspection of the exact deployed application artifact also confirmed PHP 8.5.11 in both its CLI and FPM binaries.
- Inventoried the deployed dev and prod backend services and their migration jobs. All four references still pointed to the same pre-response application image digest.
- Restricted both Cloud Run backend services to `internal` ingress and verified HTTP `404` from their public API entry points. This intentionally interrupted public login and API use while leaving the frontend deployment unchanged.
- Isolated the security change from unreleased reporting features by branching from `origin/main`.
- Prepared PHP **8.5.12** runtime and CI pins, plus explicit base-image pulls in the normal application build path.
- Identified a release propagation gap: upstream PHP source and Docker image metadata had been updated, while the required Docker Hub image tags were still unavailable.
- Completed an emergency local `linux/amd64` build using the official PHP Dockerfile at an exact upstream commit. Source checksum/signature validation passed; both the finished base and application image report PHP CLI and FPM 8.5.12. Existing backend tests passed (40 tests, 688 assertions), isolated runtime checks passed (41 checks), and the real application returned the expected 200/401/200 health/authentication/documentation responses.
- On resuming the response, verified that the official FPM and CLI patch tags had become available. The ordinary pinned CI path can now use them.

## Application and deployment context

The backend is Laravel 13 running through Nginx and PHP-FPM inside a custom application container, with Supervisor starting those processes. The frontend is deployed separately. The cloud environments are demonstration sites named dev and prod; the label prod does not imply that this response involved real patient production records.

The application has JWT-protected Employee, HR and Helpdesk APIs. Those application controls address authentication and authorization. A defect in the PHP runtime has a different remediation boundary: replacing Laravel code, adding an authorization check, or updating Composer packages would not by itself replace the PHP interpreter embedded in a deployed image.

The response covered these runtime consumers:

| Component | Finding before remediation | Required follow-through |
| --- | --- | --- |
| Local Compose backend | PHP 8.5.11 directly observed; PHP-FPM configuration identified | Rebuild and recreate the backend with a verified patched image without resetting the database |
| Dev Cloud Run backend | Exact deployed artifact confirmed to contain PHP 8.5.11; public ingress subsequently restricted | Deploy patched image, verify the new revision, then restore intended ingress |
| Prod Cloud Run backend | Same verified PHP 8.5.11 application artifact; public ingress subsequently restricted | Repeat the verified deployment and restoration sequence |
| Dev migration job | Referenced the existing application image | Update its image even though no database migration is required by this patch |
| Prod migration job | Referenced the existing application image | Update its image and retain the ordinary migration command |
| Frontend services | Do not execute PHP | No PHP runtime rebuild required |

The old application image digest was `sha256:21fa367f1a426276c95d90d1ef894fb972f243035938d84ad3ede2d0b9636000`. Both services resolved to that exact digest. At **2026-10-21 15:36:29Z**, offline inspection of that artifact confirmed `linux/amd64`, CLI PHP 8.5.11 and PHP-FPM 8.5.11 (`fpm-fcgi`). The checks used `docker run --rm --network none` with explicit PHP entry points, no environment-file injection and no volume mounts. This confirms the deployed artifact's vulnerable version without claiming that a request was executed inside a live cloud worker. Post-patch cloud execution verification remains a separate evidence item.

## Validating the advisory and the actual risk

The initial alert came from a news article describing PHP2SHELL as unpatched. The response checked upstream PHP security advisories and the PHP release announcement rather than treating the article's publication state as current. PHP 8.5.12 had since become available as a security release. The related supported-branch fixes were 8.4.27, 8.3.36 and 8.2.35. The project remained on its existing 8.5 branch and targeted its fixed patch release. [PHP 8.5.12 announcement](https://www.php.net/releases/8_5_12.php)

The OOM advisory describes invalid request state after an interrupted allocation, with possible code execution when shutdown consumes it. It distinguishes a demonstrated mod_php exploit from FPM susceptibility established by source analysis; it did not demonstrate a complete FPM exploit for that advisory. [PHP OOM advisory](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)

The stale-state advisory describes request-owned memory surviving through worker globals into another request. Its validated FPM path depends on a suitable application operation and a later early consumer. Accordingly, the response treated this FPM deployment as requiring the update without claiming a working exploit against MediCare. Upgrading is the complete mitigation; individual configuration changes are partial. [PHP stale global state advisory](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)

Google's notice confirmed the operational responsibility for this deployment: a customer-built PHP container on Cloud Run must be rebuilt and redeployed by its owner. Managed base-image automatic updates, where enabled, do not replace PHP inside an independently built custom image. That supported the chosen image rebuild and deployment response. [Google Cloud advisory](https://cloud.google.com/blog/products/identity-security/responding-to-core-php-vulnerabilities-cve-2026-109618-cve-2026-109619)

### Runtime evidence and its limits

The local version check returned PHP 8.5.11. CLI inspection also returned `session.upload_progress.enabled=1` and `fatal_error_backtraces=1`. These were CLI observations, not a claim that every effective FPM setting had been read through a live web request.

The tracked FPM configuration set `memory_limit=128M`, `post_max_size=65M`, `upload_max_filesize=23M`, and `max_file_uploads=40`. Nginx allowed `client_max_body_size 70M`. This distinction mattered because CLI defaults reported different request limits. The investigation did not combine values from the two SAPIs and present them as one measured web configuration.

A read-only application review found no obvious application-level shell/eval chain, arbitrary external include, unsafe deserialization chain or file upload/write endpoint in the reviewed application paths. Fixed aggregate SQL expressions in the reporting service were not attacker-supplied SQL. This was useful scope information, but it did not certify the whole application or prove the runtime unexploitable.

The Nginx rules returning `404` for direct `.php` paths were also not accepted as a runtime fix: ordinary application requests still reach the front controller and PHP-FPM. Likewise, JWT middleware runs within the application and cannot be treated as a general protection for PHP request initialization or worker-lifecycle defects.

No exploit payload or public proof of concept was sent to the application. Version checks, configuration inspection, control-plane queries and normal HTTP requests were used instead.

## Response timeline

Exact timestamps are included only where an observed record supports them. Other events are ordered phases; their clock times were not captured precisely enough to reconstruct them.

| Time or phase | Action and observed result |
| --- | --- |
| Initial triage, exact time not recorded | Read the supplied alert and upstream PHP advisories; checked the local PHP version, web execution model and relevant configuration. |
| Inventory, exact time not recorded | Recorded service image references, migration-job image references and intended ingress. Separated the runtime vulnerability from application-code findings. |
| Change isolation, exact time not recorded | Fetched `main` and created `security/php-8.5.12` from `origin/main`; did not merge the unreleased reporting branch. |
| Patch preparation, exact time not recorded | Pinned the backend runtime and CI PHP images to 8.5.12; added normal build `--pull` and a README deployment note. YAML parsing and `git diff --check` passed. |
| Registry check, exact time not recorded | Exact 8.5.12 FPM and CLI tags were unavailable. The floating 8.5 FPM tag still identified 8.5.11. A pull of the floating tag would therefore not supply the needed fix at that point. |
| **2026-10-21 15:26:41.940910Z** (23:26:41.940910 Taiwan) | Cloud Audit Logs recorded the prod service replacement associated with the containment change. |
| **2026-10-21 15:26:42.883139Z** (23:26:42.883139 Taiwan) | Cloud Audit Logs recorded the dev service replacement associated with the containment change. |
| Containment verification, exact time not recorded | Both services showed `internal` ingress. Normal public `/api/me` requests returned `404`. |
| Source-build fallback, exact time not recorded | Downloaded the official PHP Dockerfile and helper scripts at a fixed upstream commit. The official build verified the PHP source archive checksum and signature. |
| First source-build attempt, exact time not recorded | Build stopped because downloaded helper scripts lacked executable permissions. Restored executable permissions on those upstream helper files and restarted the build; no application behavior was changed for this correction. |
| **2026-10-21 15:33:19Z** | Response documentation checkpoint; the response remained in progress. |
| **2026-10-21 15:34:29–30Z** | Rechecked both backend services: ingress remained `internal`, with the old ready revisions still selected. This was containment verification, not patched deployment verification. |
| Registry recheck at approximately 15:34Z | Required official registry tags were still unavailable; this observation does not predict when they would be published. |
| **2026-10-21 15:36:29Z** | Offline inspection of the old deployed image confirmed `linux/amd64`, CLI PHP 8.5.11 and FPM PHP 8.5.11. The artifact was run without network, environment-file injection or volume mounts. |
| **2026-10-21 15:42:20Z** | Finished source-built base verified: CLI and FPM 8.5.12, linux/amd64. Application build subsequently completed. Interactive execution was then interrupted by an assistant usage limit before deployment; containment remained in place. |
| **2026-10-21 18:06:49Z** (22 October 02:06:49 Taiwan) | User resumed the response. Rechecked cloud ingress at 18:07:16–17Z: both backends remained internal with their previous revisions. |
| **2026-10-21 18:07:15Z** | Existing tests passed on patched PHP (40 tests, 688 assertions). Official FPM/CLI tags now resolved; the earlier registry blockage had ended. |
| **2026-10-21 18:07:52Z** | Real application smoke check passed: /up 200, /api/me 401 without authentication, /api/doc 200; generated test keys and in-memory SQLite only. |
| **2026-10-21 18:08:16Z** | Existing isolated runtime smoke script passed all 41 checks. |

The audit-log replacement timestamps identify control-plane events; they are not a measured start and end of user-visible downtime. No exact incident duration, recovery duration or exposure window is claimed.

## Decisions and operational tradeoffs

### Restrict ingress while the replacement image is unavailable

The intended original backend ingress was `internal-and-cloud-load-balancing`. The response changed both backends to `internal`, verified that state through the Cloud Run control plane, and checked the public entry points. The observed `404` responses showed that the normal public path no longer reached the backend application during those checks. Internal access was not eliminated.

The tradeoff was deliberate downtime for the demonstration API. The frontend could still load, but login and protected operations depended on the unavailable backend. This response therefore makes no zero-downtime claim. Restoring the original public path is gated on patched-image and revision verification.

### Use a security branch based on the deployed feature baseline

The ongoing `backend/features` branch contained three reporting changes not merged into `main`. Combining them with an emergency runtime patch would have increased the change surface and made a security rollback harder to reason about.

The security branch was created from `origin/main` at `109f926f743b13adf8a7e2734f18f31783b4ace0`. The feature branch remained separate. The prepared patch changed the runtime base, the two CI PHP image references, normal backend base-image pull behavior, and the README security note. No schema change or business feature was required.

The initial checkpoint contained an uncommitted diff. Commit, merge and CI results are recorded separately as they occur; local validation is not represented as a completed remote pipeline.

### Treat registry publication as a separate dependency

A version announcement, an upstream Dockerfile change, a registry tag and a running application container are four different artifacts. In this response they did not become available simultaneously.

The normal Dockerfile now names `php:8.5.12-fpm-trixie`, and CI names `php:8.5.12-cli`. The normal application build now requests `--pull`. These prevent silently relying on a previously cached floating base during ordinary builds, but they do not create a tag that the registry has not published. Until the exact tags resolve, a normal CI build using those pins may fail at image retrieval.

The emergency path used a locally built official-source base, followed by an application build that intentionally does **not** request `--pull` for that missing registry tag. That exception belongs to this emergency build only; it is not evidence that the public tag exists or that the resulting image came from Docker Hub.

### Preserve upstream build provenance

The fallback used the Docker Library PHP repository's official FPM build definition instead of an unreviewed third-party binary image or a vulnerability demonstration script.

| Provenance item | Recorded value |
| --- | --- |
| Upstream repository | `docker-library/php` |
| Exact upstream commit | `f6048b5f6e3c2202ee082665a4ccc0d90de291a3` |
| Definition path | `8.5/trixie/fpm/Dockerfile` |
| Downloaded Dockerfile SHA-256 | `d60acda840d1d30c33276ee58841a8424c1e997131a30a70404d83ee6a81af5b` |
| PHP release archive | `https://www.php.net/distributions/php-8.5.12.tar.xz` |
| Archive SHA-256 declared in the official definition | `08bc2eba2a588654ade1357174aa646b761556c862dda0678345ee29ed481209` |
| Archive validation observed | Checksum validation and GPG good-signature result completed in the build before compilation |
| Build target architecture | `linux/amd64`, matching the deployment target |
| Intended emergency local tag | `php:8.5.12-fpm-trixie`; locally built, not a claim of registry origin |
| Source-built base local image ID | `sha256:573d9c6602f30ce3d4b9445f36229b79533c7c9d2b21e8c991276bb97c3d7efe` |
| Source-built application local manifest index | `sha256:e37cc6afbf816b8ae094ee24083578a17b1f97e1fd247b2cee0ace48b5aa67f4` |
| Official published FPM tag, observed at 18:07Z | `sha256:8b4b4a764f7985137b13e4cb49dd59e967d9daa7946a731f9d06e31e748e39bb` |
| Official published CLI tag, observed at 18:07Z | `sha256:ce5d49625b9c50020dc1ce3c9cf4cdfa31cba31b6651e72daa3e6f5a010d234a` |

The helper scripts were downloaded from the same exact commit. HTTPS certificate verification remained enabled. A local Python certificate-trust problem was handled by using the system `curl` trust path, not by disabling TLS checks. The source build ran locally; no new paid Cloud Build or other cloud resource was provisioned for this fallback.

The first build failure was an executable-bit problem in the downloaded build context, not a PHP compiler failure. Raw file downloads do not preserve Git executable mode. Restoring mode `755` on the upstream helper scripts allowed the next attempt to continue.

### Do not confuse mitigation or a restart with the completed fix

Configuration-only changes such as disabling upload progress, changing error-display behavior, or changing memory and request limits were not accepted as the completed remediation. The response chose the patched runtime, with temporary network containment while it was being prepared.

Restarting an existing application container would start the same embedded interpreter. Updating a Dockerfile would affect a future build, not an existing revision. Updating a service image would also leave a migration job stale unless its image reference was updated separately. These were explicit deployment checks in the response plan.

No database reset, `migrate:fresh`, demo-data replacement, broad cache purge or unrelated feature merge was needed. The intended image-verification job command prints and asserts a PHP version; it must not accidentally run the default migration-and-seed script during that check.

## Sanitized command examples

These examples explain the response and are **not a transcript of every command**. Replace placeholders with the intended environment. Do not paste credentials, environment files, JWTs or full service specifications into a public record.

Inspect the running local binary and web-server configuration separately:

```sh
docker compose --env-file .env.docker exec -T backend php -v
docker compose --env-file .env.docker exec -T backend php-fpm -v
docker compose --env-file .env.docker exec -T backend php --ini
```

Inspect the exact remote base tag before relying on a new build:

```sh
docker buildx imagetools inspect php:8.5.12-fpm-trixie
docker buildx imagetools inspect php:8.5.12-cli
```

Apply and verify temporary ingress restriction for each intended backend:

```sh
gcloud run services update BACKEND_SERVICE \
  --project=PROJECT_ID --region=REGION --ingress=internal --quiet

gcloud run services describe BACKEND_SERVICE \
  --project=PROJECT_ID --region=REGION \
  --format='value(metadata.annotations."run.googleapis.com/ingress")'

curl --silent --output /dev/null --write-out '%{http_code}\n' \
  https://BACKEND_HOST/api/me
```

Verify the finished image's binary rather than trusting its tag. The following assertion applies to this project's PHP 8.5 branch, not every PHP version family:

```sh
docker run --rm --platform linux/amd64 --entrypoint php PATCHED_APP_IMAGE \
  -r 'if (PHP_VERSION_ID < 80512 || PHP_VERSION_ID >= 80600) { exit(1); } echo PHP_VERSION.PHP_EOL;'

docker run --rm --platform linux/amd64 --entrypoint php-fpm PATCHED_APP_IMAGE -v
```

Deploy by an immutable application image digest, then update the migration job's image without executing its ordinary command:

```sh
gcloud run deploy BACKEND_SERVICE \
  --project=PROJECT_ID --region=REGION \
  --image=PATCHED_APP_IMAGE_AT_DIGEST --quiet

gcloud run jobs update MIGRATION_JOB \
  --project=PROJECT_ID --region=REGION \
  --image=PATCHED_APP_IMAGE_AT_DIGEST --quiet
```

Keep ingress restricted during deployment and verification. After the new revision and actual runtime are verified, restore the previously intended ingress and repeat normal HTTP checks:

```sh
gcloud run services update BACKEND_SERVICE \
  --project=PROJECT_ID --region=REGION \
  --ingress=internal-and-cloud-load-balancing --quiet
```

## Evidence ledger

| Evidence | Status at this checkpoint | What it supports; what it does not support |
| --- | --- | --- |
| PHP release announcement and primary advisories | Read and cross-checked | Supports the selected fix; does not prove a deployment changed |
| Local `php -v` | PHP 8.5.11 observed | Confirms that local runtime needed an update; does not measure every cloud worker |
| Offline checks of exact deployed artifact | PHP 8.5.11 CLI and FPM observed at the recorded digest | Confirms affected version in the artifact resolved by both cloud services; not a post-patch check inside a cloud execution |
| FPM and Nginx configuration review | Completed | Establishes the web execution model and configured limits; does not demonstrate exploitation |
| Read-only application-code review | Completed within reviewed paths | Identifies no obvious separate code-execution chain; not a comprehensive security certification |
| Pre-response cloud image references | Shared old digest recorded | Identifies affected deployment consumers; binary verification is separate |
| Containment control-plane changes and public requests | `internal` ingress and public `404` observed for both backends | Confirms the tested public path was blocked at that time; not proof of zero access from every network |
| Repository diff and YAML parse | Prepared diff, clean whitespace check and parsed YAML | Supports reviewability and syntax; not a successful image build or CI result |
| Exact official Dockerfile, source checksum and signature | Recorded and verified before compilation | Supports build-input provenance; finished-binary verification remains pending |
| Patched base and application image versions | CLI and FPM 8.5.12 verified in both images | Confirms built binaries; deployment and remote digest are separate checks |
| Existing backend regression tests | 40 passed, 688 assertions on PHP 8.5.12 | Network disabled, SQLite in memory; no MySQL access |
| Existing isolated runtime smoke | 41 passed, 0 failed | Checks routing, FPM settings, extensions, Supervisor, logs and shutdown using fixtures |
| Local real-application production-container smoke | /up 200; /api/me 401; /api/doc 200 | Generated test keys, no project env file or external database attached |
| Dev patched revision and actual PHP version | **Pending** | Required before dev ingress restoration |
| Prod patched revision and actual PHP version | **Pending** | Required before prod ingress restoration |
| Migration job image updates and version-only execution | **Pending** | Must distinguish image update from execution of schema/data commands |
| Restored public access and normal HTTP responses | **Pending** | Required before claiming recovery |
| Local backend recreate and patched version | **Pending** | Required before claiming all inventoried runtimes updated |
| Security commit, merge, pipeline and final deployed digest | **Pending** | Record actual identifiers and results; do not infer them from a planned workflow |
| Compromise investigation | No full forensic investigation recorded | No evidence in this response establishes compromise; this is not a finding that compromise was impossible or absent |

Raw environment files, credentials, application tokens, full service descriptions, private account details and unredacted cloud logs are excluded from this record. Temporary local build logs are diagnostic evidence, not a durable public archive. Preserve only sanitized excerpts needed to support the final claims.

## Recovery and rollback conditions

Recovery must establish the following for each environment: the new image is identifiable by digest; its PHP binary is patched; the intended Cloud Run revision is ready and receives the intended traffic; associated migration jobs use a patched image; public ingress is restored only after those checks; and normal health, authentication failure and documentation requests behave as expected.

If a patched rollout fails, the first safe operational fallback is to keep or restore restricted ingress while diagnosing it. An old working revision is not an acceptable public rollback merely because it previously served requests: it may contain the vulnerable runtime. Any externally reachable rollback target must itself have a verified patched PHP version. The pre-response digest is retained for traceability, not endorsed as a recovery target.

This response does not require schema changes. Rebuilding the image and updating job references must preserve the existing database and demo records. Avoid broad rollback or cleanup commands that would change unrelated data.

## Interview material grounded in the evidence

At the current checkpoint, an accurate short explanation is:

> A critical PHP runtime advisory affected the version used by my Laravel demonstration project. I checked the official PHP and Google Cloud guidance, verified the local runtime and deployment architecture, isolated a security branch from ongoing feature work, and temporarily restricted both public backends. When the fixed Docker tags were not yet available, the response moved to an official-source build with recorded checksum, signature and upstream commit. Build and deployment verification were still in progress at this checkpoint.

After recovery is actually verified, replace the last sentence with the recorded build, deployment and recovery results. Do not claim completion from this draft.

The engineering skills evidenced by the completed portions are:

- Validating current upstream advice and distinguishing it from an earlier news report.
- Separating runtime exposure from application authentication and code review findings.
- Inventorying every consumer of a vulnerable image, including operational jobs.
- Containing exposure while making the availability cost explicit.
- Keeping an urgent patch separate from unreleased business features.
- Diagnosing a registry publication delay instead of assuming a floating tag contains a fix.
- Preserving source provenance while adapting the build path to available artifacts.
- Recording evidence strength, incomplete work and uncertainty without inflating the outcome.

The pending deployment steps may add evidence of artifact promotion, cloud runtime verification and service recovery. They should appear in the interview account only after they occur. No claim is made here that an attack was stopped, that there was zero downtime, that every vulnerability was eliminated, or that performance improved.

## Follow-up after recovery

1. Replace this in-progress status with the verified final result, image digests, revisions, commit and pipeline outcome.
2. Record actual recovery checks and timestamps from observed results; report a downtime duration only if supported by start and end evidence.
3. Return the normal build path to published official patch tags once available and verify that CI can retrieve both FPM and CLI images.
4. Keep a lightweight runtime-version inventory and a repeatable emergency image-update checklist, including Cloud Run jobs.
5. Retain request-size and memory controls as routine hardening, without presenting them as substitutes for the runtime update.
6. Decide separately whether further log review or forensic investigation is needed. A lack of observed exploit evidence during this patch response is not a forensic conclusion.

## Completion addendum — intentionally unfilled

| Final item | Verified result |
| --- | --- |
| Patched PHP base build and binary version | Completed; CLI and FPM 8.5.12, linux/amd64 |
| Application build, architecture and digest | Pending |
| Local smoke-check result | 40 backend tests / 688 assertions; 41 runtime checks; real application 200/401/200 |
| Dev revision, version check and restoration | Pending |
| Prod revision, version check and restoration | Pending |
| Dev/prod migration job images and version-only checks | Pending |
| Local Compose backend version after recreation | Pending |
| Security commit / merge request / pipeline | Pending |
| Final service recovery observation time | Pending |
| Remaining limitations or follow-up owner | Pending |
