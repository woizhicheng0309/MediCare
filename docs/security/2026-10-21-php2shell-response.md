# PHP2SHELL vulnerability response — 21–22 October 2026 (Taiwan)

This record documents the response to CVE-2026-109618 and CVE-2026-109619 in the MediCare demonstration application. It records a confirmed outdated PHP runtime and precautionary containment. It does **not** establish that the application was exploited or that data was compromised.

**Status: patched and verified.** Local development, dev/prod backend services and both cloud migration jobs use PHP 8.5.12. After the verified emergency source-built rollout, the normal main pipeline passed all 11 jobs and promoted the same official-registry-based application artifact to dev and prod. Final cloud revision/digest, version-only execution and public HTTP checks passed. Times below are UTC; Taiwan time is UTC+08:00. This response crossed into 22 October in Taiwan.

## Verified recovery

- Confirmed PHP **8.5.11** in the local backend runtime and identified PHP-FPM as the web execution model. Offline inspection of the exact deployed application artifact also confirmed PHP 8.5.11 in both its CLI and FPM binaries.
- Inventoried the deployed dev and prod backend services and their migration jobs. All four initially referenced the same pre-response application image digest and were subsequently updated to the patched application artifact.
- Temporarily restricted both Cloud Run backend services to `internal` ingress and verified public API `404` responses. Restored the intended ingress only after patched-image and cloud execution checks; both sites then returned `/up` 200, unauthenticated `/api/me` 401 and `/api/doc` 200. Public login and API use were interrupted during containment.
- Isolated the security change from unreleased reporting features by branching from `origin/main`.
- Prepared PHP **8.5.12** runtime and CI pins, plus explicit base-image pulls in the normal application build path.
- Identified a release propagation gap: upstream PHP source and Docker image metadata had been updated, while the required Docker Hub image tags were still unavailable.
- Completed an emergency local `linux/amd64` build using the official PHP Dockerfile at an exact upstream commit. Source checksum/signature validation passed; both the finished base and application image report PHP CLI and FPM 8.5.12. Existing backend tests passed (40 tests, 688 assertions), isolated runtime checks passed (41 checks), and the real application returned the expected 200/401/200 health/authentication/documentation responses.
- On resuming the response after an assistant usage-limit interruption, rechecked containment and verified that the official FPM and CLI patch tags had become available. Rebuilt the local native-arm64 development container from the official registry image; PHP 8.5.12, `/up` 200 and application ping 200/pong were verified without recreating the database or Redis containers.
- Deployed the verified source-built amd64 application artifact to dev and prod, updated both migration-job images, and executed version-only checks that reported CLI and FPM 8.5.12. Both new service revisions were ready with 100% traffic before public recovery checks.
- Committed the isolated security patch and merged MR 33. The normal main pipeline passed all 11 jobs, deployed the official-base artifact to dev and prod, and was followed by independent cloud version/digest and public HTTP checks.

## Application and deployment context

The backend is Laravel 13 running through Nginx and PHP-FPM inside a custom application container, with Supervisor starting those processes. The frontend is deployed separately. The cloud environments are demonstration sites named dev and prod; the label prod does not imply that this response involved real patient production records.

The application has JWT-protected Employee, HR and Helpdesk APIs. Those application controls address authentication and authorization. A defect in the PHP runtime has a different remediation boundary: replacing Laravel code, adding an authorization check, or updating Composer packages would not by itself replace the PHP interpreter embedded in a deployed image.

The response covered these runtime consumers:

| Component | Finding before remediation | Emergency recovery (final CI state below) |
| --- | --- | --- |
| Local Compose backend | PHP 8.5.11 directly observed; PHP-FPM configuration identified | Native-arm64 dev image rebuilt from official PHP 8.5.12 registry base; CLI/FPM and normal health/ping verified; database and Redis containers retained |
| Dev Cloud Run backend | Exact deployed artifact contained PHP 8.5.11; public ingress temporarily restricted | Patched artifact deployed to `medicare-dev-00013-6fh`, ready with 100% traffic; intended ingress restored and public checks passed |
| Prod Cloud Run backend | Same verified PHP 8.5.11 artifact; public ingress temporarily restricted | Same patched artifact deployed to `medicare-prod-00008-46r`, ready with 100% traffic; intended ingress restored and public checks passed |
| Dev migration job | Referenced the old application image | Updated to patched artifact; version-only execution `v8zk2` verified CLI/FPM 8.5.12 without running migrations or seeders |
| Prod migration job | Referenced the old application image | Updated to patched artifact; version-only execution `f48hw` verified CLI/FPM 8.5.12; ordinary job command retained |
| Frontend services | Do not execute PHP | No PHP runtime rebuild required |

The old application image digest was `sha256:21fa367f1a426276c95d90d1ef894fb972f243035938d84ad3ede2d0b9636000`. Both services resolved to that exact digest. At **2026-10-21 15:36:29Z**, offline inspection of that artifact confirmed `linux/amd64`, CLI PHP 8.5.11 and PHP-FPM 8.5.11 (`fpm-fcgi`). The checks used `docker run --rm --network none` with explicit PHP entry points, no environment-file injection and no volume mounts. This confirms the deployed artifact's vulnerable version without claiming that a request was executed inside a live cloud worker. Post-patch cloud job executions subsequently verified the new artifact in each environment; those results are recorded below separately from live web-worker inspection.

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
| **2026-10-21 15:42:20Z** | Finished source-built base verified: CLI and FPM 8.5.12, linux/amd64. The application build was launched next. Interactive execution was interrupted by an assistant usage limit before deployment; successful build completion was confirmed on resuming, and containment remained in place. |
| **2026-10-21 18:06:49Z** (22 October 02:06:49 Taiwan) | User resumed the response. Rechecked cloud ingress at 18:07:16–17Z: both backends remained internal with their previous revisions. |
| **2026-10-21 18:07:15Z** | Existing tests passed on patched PHP (40 tests, 688 assertions). Official FPM/CLI tags now resolved; the earlier registry blockage had ended. |
| **2026-10-21 18:07:52Z** | Real application smoke check passed: /up 200, /api/me 401 without authentication, /api/doc 200; generated test keys and in-memory SQLite only. |
| **2026-10-21 18:08:16Z** | Existing isolated runtime smoke script passed all 41 checks. |
| **2026-10-21 18:11:39Z** | Local native-arm64 dev image, built from the published official PHP 8.5.12 base, reported patched CLI/FPM versions. `/up` and application ping passed. Existing database and Redis containers were retained. |
| **2026-10-21 18:12:58.388552Z** | Dev patched revision `medicare-dev-00013-6fh` was ready with 100% traffic. Dev version-only job execution `v8zk2` reported CLI/FPM 8.5.12. Ingress was still internal before restoration. |
| **2026-10-21 18:13:37.616055Z** (22 October 02:13:37 Taiwan) | After restoring intended dev ingress, public checks passed: `/up` 200, `/api/me` 401 without authentication, `/api/doc` 200. |
| **2026-10-21 18:14:09Z** | Normal `main` pipeline 2973319344 started after the security merge. Its final result was checked separately from local validation. |
| **2026-10-21 18:14:47.118406Z** | Prod patched revision `medicare-prod-00008-46r` was ready with 100% traffic. Prod version-only job execution `f48hw` reported CLI/FPM 8.5.12. Ingress was still internal before restoration. |
| **2026-10-21 18:15:36.596686Z** (22 October 02:15:36 Taiwan) | After restoring intended prod ingress, the same public 200/401/200 checks passed. Both manual recovery paths had now been verified. |
| **2026-10-21 18:20:46Z** | Independently verified the CI-published official-base application artifact: linux/amd64, CLI/FPM/OPcache 8.5.12, immutable digest c6ed2f...aa70. |
| **2026-10-21 18:22:27.390098Z** | Final official-base dev release verified: revision medicare-dev-00014-z2x, 100% traffic, matching digest, cloud version-only execution and public 200/401/200. |
| **2026-10-21 18:23:14.273Z** | Normal main pipeline 2973319344 completed successfully; all 11 jobs passed, including the standard manually triggered prod deployment. |
| **2026-10-21 18:25:07.701895Z** | Final official-base prod release verified: revision medicare-prod-00009-xzq, same digest and PHP version as dev, 100% traffic and public 200/401/200. |

The audit-log replacement timestamps identify control-plane events; they are not a measured start and end of user-visible downtime. No exact incident duration, recovery duration or exposure window is claimed.

## Decisions and operational tradeoffs

### Restrict ingress while the replacement image is unavailable

The intended original backend ingress was `internal-and-cloud-load-balancing`. The response changed both backends to `internal`, verified that state through the Cloud Run control plane, and checked the public entry points. The observed `404` responses showed that the normal public path no longer reached the backend application during those checks. Internal access was not eliminated.

The tradeoff was deliberate downtime for the demonstration API. The frontend could still load, but login and protected operations depended on the unavailable backend. This response therefore makes no zero-downtime claim. The original public path was restored after the patched artifact, ready revision, traffic and version-only cloud execution checks. Public HTTP checks then confirmed normal health, unauthenticated API rejection and documentation access.

### Use a security branch based on the deployed feature baseline

The ongoing `backend/features` branch contained three reporting changes not merged into `main`. Combining them with an emergency runtime patch would have increased the change surface and made a security rollback harder to reason about.

The security branch was created from `origin/main` at `109f926f743b13adf8a7e2734f18f31783b4ace0`. The feature branch remained separate. The prepared patch changed the runtime base, the two CI PHP image references, normal backend base-image pull behavior, and the README security note. No schema change or business feature was required.

The initial checkpoint contained an uncommitted diff. The completed security commit is `6e08b1a0f4065b7c9a8def699765f47e2918b2b5`, merged through [MR 33](https://gitlab.com/woizhicheng0309/medicare/-/merge_requests/33) as main merge `20a82c71b27c749cd334ec25ff1ecf138d60cfc9`. The branch pipeline was skipped with `ci.skip` to avoid duplicate execution; the normal main pipeline did run. The normal main pipeline subsequently passed all 11 jobs; final official-base artifact promotion and independent verification are recorded below.

### Treat registry publication as a separate dependency

A version announcement, an upstream Dockerfile change, a registry tag and a running application container are four different artifacts. In this response they did not become available simultaneously.

The normal Dockerfile now names `php:8.5.12-fpm-trixie`, and CI names `php:8.5.12-cli`. The normal application build now requests `--pull`. These prevent silently relying on a previously cached floating base during ordinary builds, but they do not create a tag that the registry has not published. During the tag-publication gap, those exact pins could not be retrieved for a normal CI build. Both tags resolved on the 18:07Z recheck, allowing the normal main pipeline to use published official images.

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
| Source-built application manifest index used for manual recovery | `sha256:e37cc6afbf816b8ae094ee24083578a17b1f97e1fd247b2cee0ace48b5aa67f4` |
| Official published FPM tag, observed at 18:07Z | `sha256:8b4b4a764f7985137b13e4cb49dd59e967d9daa7946a731f9d06e31e748e39bb` |
| Official published CLI tag, observed at 18:07Z | `sha256:ce5d49625b9c50020dc1ce3c9cf4cdfa31cba31b6651e72daa3e6f5a010d234a` |

The helper scripts were downloaded from the same exact commit. HTTPS certificate verification remained enabled. A local Python certificate-trust problem was handled by using the system `curl` trust path, not by disabling TLS checks. The source build ran locally; no new paid Cloud Build or other cloud resource was provisioned for this fallback.

The first build failure was an executable-bit problem in the downloaded build context, not a PHP compiler failure. Raw file downloads do not preserve Git executable mode. Restoring mode `755` on the upstream helper scripts allowed the next attempt to continue.

### Do not confuse mitigation or a restart with the completed fix

Configuration-only changes such as disabling upload progress, changing error-display behavior, or changing memory and request limits were not accepted as the completed remediation. The response chose the patched runtime, with temporary network containment while it was being prepared.

Restarting an existing application container would start the same embedded interpreter. Updating a Dockerfile would affect a future build, not an existing revision. Updating a service image would also leave a migration job stale unless its image reference was updated separately. These were explicit deployment checks in the response plan.

No database reset, `migrate:fresh`, demo-data replacement, broad cache purge or unrelated feature merge was needed. The actual image-verification job executions overrode arguments to print and assert the PHP version; they did not run the default migration-and-seed script. Normal job commands were retained and subsequently used by the standard CI release.

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

| Evidence | Recorded result | What it supports; what it does not support |
| --- | --- | --- |
| PHP release announcement and primary advisories | Read and cross-checked | Supports the selected fix; does not prove a deployment changed |
| Local pre-patch `php -v` | PHP 8.5.11 observed | Confirms the local runtime needed an update |
| Offline checks of exact old deployed artifact | PHP 8.5.11 CLI/FPM at the recorded old digest | Confirms affected version in the artifact resolved by both cloud services |
| FPM/Nginx configuration and application review | Completed within reviewed paths | Identifies configuration and no obvious separate application code-execution chain; not a security certification or exploit demonstration |
| Containment control-plane changes and normal public requests | Both backends changed to internal; public API 404 observed | Confirms the tested public path was blocked at that time, not that every internal access path was eliminated |
| Source provenance | Fixed upstream commit; Dockerfile hash, source checksum and signature recorded | Supports build inputs and auditability |
| Patched source-built base and application images | CLI/FPM 8.5.12, linux/amd64; application index `sha256:e37cc6afbf816b8ae094ee24083578a17b1f97e1fd247b2cee0ace48b5aa67f4` | Identifies the actual emergency artifact; distinct from later official-registry CI output |
| Existing backend regression tests | 40 passed, 688 assertions on PHP 8.5.12 | Network disabled, SQLite in memory; no MySQL access |
| Existing isolated runtime smoke | 41 passed, 0 failed | Routing, FPM settings, extensions, Supervisor, logging and shutdown checked with fixtures |
| Local real-application production smoke | `/up` 200; unauthenticated `/api/me` 401; `/api/doc` 200 | Generated test keys; no project env file or external database attached |
| Dev rollout and cloud version execution | Revision `medicare-dev-00013-6fh`, 100% traffic; job `v8zk2` printed CLI/FPM 8.5.12 | Confirms service artifact configuration/readiness and actual execution of the same image in the cloud job; not an exec session inside a live service worker |
| Prod rollout and cloud version execution | Revision `medicare-prod-00008-46r`, 100% traffic; job `f48hw` printed CLI/FPM 8.5.12 | Same distinct configuration and execution evidence for prod |
| Migration jobs | Both image references updated to the patched application digest; version-only executions succeeded | No migrations, seeders or database reset were required for these checks |
| Restored public access | Intended ingress restored; both sites passed 200/401/200 checks at recorded times | Supports manual service recovery; does not replace every authenticated business workflow check |
| Local backend recreation | Official-registry PHP 8.5.12 native-arm64 dev image; `/up` 200 and ping 200/pong | Local runtime updated while existing database and Redis containers remained |
| Source control | Security commit `6e08b1a`; MR 33 merged as `20a82c7` | Security fix integrated separately from unreleased reporting features |
| Normal main CI rebuild and release | Pipeline 2973319344 passed all 11 jobs; final official-base artifact and both cloud environments independently verified | Completed routine release after the emergency recovery; see final addendum |
| Compromise investigation | No full forensic investigation recorded | No evidence here establishes compromise; not a finding that compromise was impossible or absent |

Raw environment files, credentials, application tokens, full service descriptions, private account details and unredacted cloud logs are excluded from this record. Selected successful/retried build logs and a SHA-256 manifest were retained privately with the interview material. The first failed source-build attempt was not saved as a separate raw log; its failure and correction are recorded in the timeline. Public documentation includes selected evidence rather than credentials or full service configurations.

## Recovery, verification and rollback

Manual recovery met the artifact, runtime and public-behavior checks recorded above: a pinned patched image, ready service revisions with 100% traffic, updated migration-job images, version-only cloud job executions, restored ingress and normal public HTTP responses. Cloud jobs supplied the direct remote PHP version evidence; the service control plane identified the same immutable image. No shell was opened inside a live web worker.

The manually deployed emergency artifact was built from verified official source. The subsequent normal main pipeline built from the published official registry bases and produced a different application digest containing PHP 8.5.12. Its successful release and independent verification are recorded separately below, preserving both artifact histories.

An assistant usage limit interrupted interactive execution after the application build had been launched but before deployment. The already-started local build could finish during the pause; no cloud deployment continued automatically. On the user's return, the first checks confirmed that containment remained in place. This interruption is part of the response history, not build time or a completed deployment.

The one branch pipeline was skipped to avoid duplicate execution, while [normal main pipeline 2973319344](https://gitlab.com/woizhicheng0309/medicare/-/pipelines/2973319344) ran after the merge. Its automatic dev deployment and standard manually triggered prod deployment both completed, separately from the earlier emergency recovery.

If a patched rollout fails, the first safe operational fallback is to keep or restore restricted ingress while diagnosing it. An old working revision is not an acceptable public rollback merely because it previously served requests: it may contain the vulnerable runtime. Any externally reachable rollback target must itself have a verified patched PHP version. The pre-response digest is retained for traceability, not endorsed as a recovery target.

This response does not require schema changes. Rebuilding the image and updating job references must preserve the existing database and demo records. Avoid broad rollback or cleanup commands that would change unrelated data.

## Interview material grounded in the evidence

An accurate short explanation after manual recovery is:

> A critical PHP runtime advisory affected the version embedded in my Laravel project's images. In a tool-assisted response, I checked primary PHP and Google guidance, inventoried services and jobs, and isolated a security branch. When the fixed registry tags were unavailable, we temporarily restricted public ingress and built from a fixed official source definition with checksum/signature verification. We verified PHP 8.5.12, passed existing tests and smoke checks, deployed the same immutable emergency artifact to dev and prod, checked version-only cloud job executions, and restored public access with normal HTTP responses. The ordinary CI release subsequently passed, and both environments were verified against its shared immutable digest.

The completed response demonstrates:

- Checking current primary advice against an earlier news report.
- Separating runtime exposure from application authentication and code-review findings.
- Inventorying services and operational jobs sharing a vulnerable image.
- Containing public exposure while documenting the availability cost.
- Keeping the urgent fix separate from unreleased features.
- Diagnosing registry publication delays and preserving source-build provenance.
- Validating actual binaries, promoting an immutable artifact and checking cloud readiness and public behavior.
- Recovering through a pause without assuming work had continued automatically.
- Separating a verified emergency deployment from a later normal CI rebuild.
- Reporting uncertainty and evidence limits without inventing an exploit, compromise finding or zero-downtime result.

No claim is made that a demonstrated attack was stopped, that every authenticated workflow was re-exercised, that all vulnerabilities were eliminated, or that performance improved.

## Follow-up

1. Keep the final pipeline, artifact and deployment evidence below with future runtime-update records.
2. Keep a lightweight runtime-version inventory and an emergency image-update checklist that includes Cloud Run jobs.
3. Retain request-size and memory controls as routine hardening without presenting them as substitutes for the runtime patch.
4. Decide separately whether further log review or forensic investigation is warranted; a patch response is not a forensic conclusion.
5. Retain sanitized evidence in durable project records. Temporary local logs are not the sole long-term evidence store.

## Emergency recovery evidence

| Item | Result |
| --- | --- |
| Source-built PHP base | CLI/FPM 8.5.12, linux/amd64, verified 15:42:20Z |
| Emergency application artifact | `sha256:e37cc6afbf816b8ae094ee24083578a17b1f97e1fd247b2cee0ace48b5aa67f4`; built from verified official source |
| Existing checks | 40 backend tests / 688 assertions; 41 runtime checks; real application 200/401/200 |
| Dev manual recovery | Revision `medicare-dev-00013-6fh`; job execution `v8zk2`; PHP CLI/FPM 8.5.12; public 200/401/200 observed 18:13:37.616055Z |
| Prod manual recovery | Revision `medicare-prod-00008-46r`; job execution `f48hw`; PHP CLI/FPM 8.5.12; public 200/401/200 observed 18:15:36.596686Z |
| Manual migration-job verification | Both updated to the emergency artifact; these version-only executions ran no schema/data commands. Later standard CI migration checks are recorded separately below. |
| Local development | Native-arm64 dev image rebuilt from official registry base; PHP 8.5.12 and health/ping verified 18:11:39Z; existing database/Redis containers retained |
| Security source control | Commit `6e08b1a0f4065b7c9a8def699765f47e2918b2b5`; [MR 33](https://gitlab.com/woizhicheng0309/medicare/-/merge_requests/33); main merge `20a82c71b27c749cd334ec25ff1ecf138d60cfc9` |
| Availability limits | Deliberate API interruption during containment; no exact user-visible downtime or exposure-window measurement claimed |
| Security limits | Version remediation verified; no exploit demonstration or complete forensic conclusion |

## Normal CI follow-through — verified final state

[Main pipeline 2973319344](https://gitlab.com/woizhicheng0309/medicare/-/pipelines/2973319344) succeeded at **2026-10-21 18:23:14.273Z** (22 October 02:23:14 Taiwan). All **11 jobs** passed, including syntax/dependency checks, existing backend tests, frontend validation, Secret Detection, image builds/publishing, automatic dev deployment and the standard manually triggered prod deployment.

The final application image was built from the now-published official PHP base. Its tag is the main merge commit `20a82c71b27c749cd334ec25ff1ecf138d60cfc9`; its immutable registry digest is `sha256:c6ed2f015f96ee82638b36e34ac52dad43c7b6fecd6edc22ef90173b2ca5aa70`. It is a single linux/amd64 manifest. Independent offline execution at 18:20:46Z confirmed CLI/FPM/OPcache 8.5.12. This differs from the earlier source-built emergency index `e37cc6...`, which remains part of the response history.

| Final check | Dev | Prod |
| --- | --- | --- |
| Ready revision | medicare-dev-00014-z2x | medicare-prod-00009-xzq |
| Traffic | 100% to the recorded revision | 100% to the recorded revision |
| Application digest | sha256:c6ed2f015f96ee82638b36e34ac52dad43c7b6fecd6edc22ef90173b2ca5aa70 | sha256:c6ed2f015f96ee82638b36e34ac52dad43c7b6fecd6edc22ef90173b2ca5aa70 |
| Version-only cloud execution | medicare-dev-migration-job-6ht8g | medicare-prod-migration-job-7jb2l |
| Cloud execution result | PHP CLI/FPM 8.5.12 | PHP CLI/FPM 8.5.12 |
| Ingress | internal-and-cloud-load-balancing | internal-and-cloud-load-balancing |
| Public requests | /up 200; /api/me 401; /api/doc 200 | /up 200; /api/me 401; /api/doc 200 |
| Final observation (UTC) | 2026-10-21T18:22:27.390098+00:00 | 2026-10-21T18:25:07.701895+00:00 |

The standard CI release ran its existing `migrate --force` step. Filtered job logs recorded **Nothing to migrate** for dev at 18:19:19.341349Z and prod at 18:22:10.328394Z. No new migrations were included, and the existing startup script seeds only when APP_ENV is local. No database reset or demonstration-data replacement was performed. The later verification executions overrode arguments for that execution only; both job defaults were rechecked as `/bin/sh /var/www/db-migrate-seed.sh`.

The final records therefore distinguish three results: the emergency source build and recovery, the native local rebuild from the official registry, and the successful normal cloud CI release. No claim of exploitation, complete forensic clearance or zero downtime is added by this completion.
