# 10. 日常維運與排錯

以下指令都先 `source ~/laravel-side-project.env`。

## 10.1 看 log

GCP 主控台：Logging → Logs Explorer，或在 Cloud Run 的服務頁面點 Logs。用指令：

```bash
# backend 服務最近一小時的 log（frontend 把服務名稱換成 frontend-dev）
gcloud logging read 'resource.type="cloud_run_revision" AND resource.labels.service_name="laravel-side-project-dev"' \
  --freshness=1h --limit=50 --format='value(timestamp,textPayload)'

# 只看錯誤
gcloud logging read 'resource.type="cloud_run_revision" AND resource.labels.service_name="laravel-side-project-dev" AND severity>=ERROR' \
  --freshness=1d --limit=20 --format='value(timestamp,textPayload)'

# migration Job 的 log
gcloud logging read 'resource.type="cloud_run_job" AND resource.labels.job_name="laravel-side-project-dev-migration-job"' \
  --freshness=1d --limit=50 --order=asc --format='value(timestamp,textPayload)'
```

backend 的 log 裡會看到：

- `entrypoint: running /docker-entrypoint.d/05-optimize.sh`：容器啟動時快取 Laravel 設定
- supervisord 啟動 nginx、PHP-FPM 的訊息
- `STARTUP HTTP probe succeeded`：可以開始接請求了
- nginx 的存取紀錄（每個請求一行，`/ping` 不記）
- Laravel 的 log

frontend 的 log 只有 nginx 的存取紀錄和錯誤。

## 10.2 連到 Cloud SQL

```bash
export PATH="$(brew --prefix mysql-client)/bin:${PATH}"   # 2.7 裝的 mysql-client
gcloud sql connect "${SQL_INSTANCE}" --user=laravel_dev --database=laravel_side_project_dev
```

- 密碼是 Cloud Run 服務的環境變數 `DB_PASSWORD`：主控台的 Cloud Run → 服務 → Revisions → 點最新的 revision → Variables & Secrets
- `gcloud sql connect` 會暫時把你的 IP 加進白名單 5 分鐘
- 小心操作 prod 的資料庫

## 10.3 在雲端執行一次 artisan 指令

例如查 migration 狀態、刪除一筆資料。借用 migration Job 執行一次，只覆蓋這一次的參數，Job 本身的設定不變：

```bash
JOB=laravel-side-project-dev-migration-job
NAME=$(gcloud run jobs execute "${JOB}" --region "${REGION}" --wait \
  --args='^@^-c@php /var/www/artisan migrate:status' --format='value(metadata.name)')

# 看輸出
gcloud logging read "resource.type=cloud_run_job AND labels.\"run.googleapis.com/execution_name\"=${NAME}" \
  --freshness=10m --order=asc --format='value(textPayload)'
```

- Job 原本的指令是 `bash`，`--args` 改成 `-c` 和要執行的指令
- `^@^` 表示參數之間用 `@` 分隔。指令裡有逗號也沒關係（gcloud 預設用逗號分隔）
- 要跑 tinker 的程式碼，例如刪掉某個帳號：`--args='^@^-c@php /var/www/artisan tinker --execute="App\Models\User::whereName(\"某帳號\")->delete();"'`
- 執行完確認 Job 的參數沒被改：`gcloud run jobs describe "${JOB}" --region "${REGION}" --format='value(spec.template.spec.template.spec.containers[0].args)'` 應該還是 `/var/www/db-migrate-seed.sh`

## 10.4 常見問題

**本機**

| 狀況 | 原因 | 怎麼辦 |
|---|---|---|
| `docker compose up` 拉不到 `nginx-php:8.4-dev`（denied、unauthorized） | 沒執行 `gcloud auth configure-docker`，或沒有 Artifact Registry 的讀取權限 | 3.4；其他人要加權限見 6.3 |
| 8080 或 3307 被佔用 | 其他容器或程式 | 改 `.env` 的 `DOCKER_*` port；frontend 用 `BACKEND_URL` 指到新的 port |
| `program` 一直沒起來 | `migration` 失敗，`program` 會等它成功才啟動 | `docker compose logs migration` |
| 改了 `.env` 沒生效 | 手動跑過 `php artisan optimize` 或 `config:cache`，留下了快取 | `docker compose exec program php artisan optimize:clear` |
| `pnpm dev` 的畫面打 API 都失敗 | backend 的 compose 沒起來，或 port 不是 8080 | 照 6.3 啟動 backend；用 `BACKEND_URL` 指定 port |
| `pnpm install` 出現 Ignored build scripts | pnpm 11 預設不執行套件的安裝腳本 | `pnpm approve-builds <套件>`，會寫進 `pnpm-workspace.yaml` |
| 在 Mac 上用模擬建 amd64 映像檔，卡在下載套件 | 像是暫時的網路問題 | 中斷後重跑 |
| 推映像檔推到奇怪的名稱 | zsh 把 `"$VAR:l..."` 當成修飾字 | 寫成 `"${VAR}:..."`（2.9） |

**CI**

| 狀況 | 原因 | 怎麼辦 |
|---|---|---|
| `build` 登入 GCP 失敗 | 變數沒設、不是 base64，或 tag 沒設成 protected tag | 第 4 章 |
| `build` 推不上去，訊息有 tag、immutable | 這個 tag 已經推過 | 打新 tag |
| backend 的 `build` 找不到 base image | `Dockerfile` 的 `BASE_IMAGE` 專案或日期 tag 錯了 | 6.2、5.5 |
| deploy 出現 `iam.serviceaccounts.actAs` | `gitlab-ci` 沒有執行用 service account 的 Service Account User | 3.5 |
| deploy 停在 migration | migrate 或 seed 出錯 | 看 migration Job 的 log（10.1）。服務仍是舊版；修好後打新 tag |
| 部署成功，但流量沒切到新版 | 之前回滾時把流量固定在舊 revision | `gcloud run services update-traffic <服務> --region "${REGION}" --to-latest` |

**正式環境**

| 狀況 | 原因 | 怎麼辦 |
|---|---|---|
| 直接連 `*.run.app` 回 404 | ingress 只允許 LB，這是正常的 | 用網域連 |
| 網域的 HTTPS 連不上（`SSL_ERROR_SYSCALL`） | 憑證還在申請，或剛生效還沒傳播完 | 8.7，再等一下 |
| 閒置後第一個請求很慢（約 4 到 8 秒） | 冷啟動，見 10.6 | 正常 |
| 502 或 503 | 容器還在啟動，或 PHP-FPM 掛了 | 看 log。PHP-FPM 掛了 supervisord 會重啟；一直失敗時 liveness probe 會重啟整個容器 |
| 新版 backend 起不來，log 停在 `05-optimize.sh` | `php artisan optimize` 失敗，多半是 `config/` 的設定檔有錯 | 看 log 的錯誤訊息。新 revision 起不來，流量會留在舊版 |
| `/api/doc` 打開沒有內容 | 第一次讀取時產生 Swagger 文件失敗 | log 裡找 `swagger: generating docs failed` |
| 登入回 429「Too Many Attempts.」 | 登入每分鐘限 5 次（同一個 IP） | 等一分鐘 |
| 在頁面停一段時間後，存檔時被導回登入頁 | JWT 60 分鐘過期 | 重新登入 |
| 新增的前端頁面，網址打開是 backend 的 404 或 JSON | 路由用了 `/api`、`/docs`、`/up` 開頭 | 換一個路徑 |
| 剛改完 URL map，有些路徑還是舊的服務回應 | 新設定要 2 到 3 分鐘才全部生效 | 等一下再試 |

## 10.5 資源用量

目前 dev、prod 都是 1 vCPU、512Mi、concurrency 80、instance 最少 0 個、最多 1 個。

2026-10-09 對 backend 量測（打 `GET /api/projects`）：

| 環境 | 同時請求數 | 每秒處理 | 平均延遲 | 記憶體 |
|---|---|---|---|---|
| 本機（限制成 1 vCPU、512MB） | 80 | 164 | 489 ms | 163 MiB |
| Cloud Run dev | 20 | 50 | 398 ms | 沒有超出 |
| Cloud Run dev | 80 | 52 | 1524 ms | 沒有超出 |

- 瓶頸是 CPU，不是記憶體。1 vCPU 在同時 10 個請求左右就滿了，再多只會排隊
- instance 最多 1 個時，concurrency 維持 80：滿載時變慢，但不會被拒絕
- 之後把 instance 上限調高（例如 3 個）時，concurrency 改成 20，讓 Cloud Run 早點開新的 instance
- 資料庫連線上限：所有 instance 的 concurrency 加起來要小於 Cloud SQL 的 `max_connections`
- 功能變多後要重新量。量法：本機用 `docker run --cpus 1 --memory 512m` 跑正式映像檔，用 macOS 內建的 `ab` 逐步增加同時請求數，看 `docker stats`

## 10.6 冷啟動

instance 最少 0 個，閒置一段時間（最多約 15 分鐘）後會縮到 0。下一個請求進來時要先啟動容器，backend 從啟動到能接請求約 4 到 8 秒（2026-10-09 量 5 次，平均 5.7 秒）：

| 階段 | 時間 |
|---|---|
| 啟動 hook：`php artisan optimize` | 2 到 5 秒。第一個 PHP 行程要從映像檔讀進大量檔案，是主要的耗時 |
| supervisord 啟動 nginx、PHP-FPM | 約 2 秒 |
| startup probe 確認 `/up` 正常 | 每 1 秒探一次，nginx 起來後 1 秒內 |

frontend 是 nginx 提供靜態檔，從啟動到能接請求約 0.3 秒。

看某一次啟動花了多久：

```bash
REV=$(gcloud run services describe laravel-side-project-prod --region "${REGION}" --format='value(status.latestReadyRevisionName)')
gcloud logging read "resource.labels.revision_name=${REV} AND (textPayload:\"entrypoint: running\" OR textPayload:\"supervisord started\" OR textPayload:\"STARTUP HTTP probe\")" \
  --freshness=1d --order=asc --format='value(timestamp,textPayload)'
```

還要再縮短的話：

- 啟動 hook 只跑 `php artisan config:cache` 和 `event:cache`，不跑完整的 `optimize`。route、view 在建置映像檔時已經快取過，`optimize` 會再做一次
- prod 的 instance 最少改成 1 個，就沒有冷啟動，每月多約 US$10：`gcloud run services update laravel-side-project-prod --region "${REGION}" --min-instances 1`
