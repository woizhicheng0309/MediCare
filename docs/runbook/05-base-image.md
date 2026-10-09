# 5. base image

repo：`base-image`。給 Laravel API 用的基底映像檔：PHP 8.4 + nginx + supervisord。backend 的正式映像檔從這裡 FROM，本機開發也用它的 dev 版，兩邊的 PHP、nginx、擴充都一樣。

## 5.1 映像檔和 tag

映像檔：`${AR}/base-images/nginx-php`，amd64、arm64 兩個平台都有。

| tag | 內容 | 誰用 |
|---|---|---|
| `8.4-YYYYMMDD` | 正式版，後面是建置日期（UTC） | backend 的 `Dockerfile` 寫死這個 |
| `8.4` | 最新的正式版 | 臨時測試 |
| `8.4-dev` | 正式版加 composer、git、unzip、xdebug（預設關閉） | backend 本機的 docker compose |

backend 寫死日期 tag，base image 重建不會自動影響正式環境，要主動升級（5.8）。

## 5.2 內容

- 上游：官方 `php:8.4-fpm-trixie`
- PHP 設定：正式版用 `php.ini-production`，dev 版用 `php.ini-development`
- PHP 擴充：官方內建的（含 OPcache），加上 gd（FreeType、JPEG）、zip、pdo_mysql
- nginx、supervisor：Debian 套件
- 沒有 cron；正式版沒有 composer
- 官方映像檔附的編譯工具（gcc、make 等）還在。要加擴充請改這個 repo，不要在 backend 的 Dockerfile 裡編譯

| 檔案 | 用途 |
|---|---|
| `Dockerfile` | 兩個 target：`prod`（正式版）、`dev`（從 prod 再加開發工具） |
| `entrypoint.sh` | 容器啟動時先執行的腳本，見 5.3 |
| `nginx/nginx.conf`、`nginx/default.conf` | nginx 設定 |
| `php/php-fpm.conf` | PHP-FPM 設定 |
| `php/conf.d/zz-base.ini`、`php/conf.d-dev/zz-dev.ini` | PHP 設定，dev 版多一個 |
| `supervisor/` | supervisord 設定：管 nginx 和 PHP-FPM |
| `test/smoke.sh` | 本機測試腳本，見 5.6 |
| `.gitlab-ci.yml` | 推到 `main` 時建置並推上 Artifact Registry |

## 5.3 啟動流程

1. `entrypoint.sh`，以 root 執行：
   1. `/var/www` 的擁有者不是 root、也不是 www-data 時，把 www-data 的 uid、gid 改成一樣。本機掛載專案目錄時，容器裡寫的檔案才不會變成別人的
   2. 有設 `TIMEZONE` 就改時區，不設是 UTC
   3. 依檔名順序執行 `/docker-entrypoint.d/*.sh`，任何一個失敗就停止啟動。backend 用它在啟動時快取 Laravel 設定
2. supervisord 啟動 PHP-FPM 和 nginx，任一個結束都會自動重啟。所有輸出都導到容器的 stdout、stderr

nginx 聽 8080，透過 unix socket `/run/php-fpm.sock` 把請求交給 PHP-FPM；nginx 和 PHP-FPM 的 worker 都以 www-data 執行。

## 5.4 預設設定

backend 直接用這些預設值，沒有覆蓋。要改時，在使用的專案裡整份覆蓋這幾個檔案：

| 檔案 | 預設內容 |
|---|---|
| `/etc/nginx/nginx.conf` | worker 1 個、request body 上限 70M、gzip、log 寫到 stdout 和 stderr |
| `/etc/nginx/conf.d/default.conf` | 根目錄是 `/var/www/public`。`public/` 下的檔案直接回，其他路徑交給 `index.php`；任何 `.php` 網址都回 404；擋 `.` 開頭的檔案；`/ping` 交給 PHP-FPM 回 `pong` |
| `/usr/local/etc/php-fpm.conf` | worker 上限 128、`clear_env = no`（worker 才讀得到 Cloud Run 的環境變數）、`ping.path = /ping`、請求 60 秒逾時、memory_limit 128M、上傳上限 23M、`allow_url_fopen` 關閉 |
| `/usr/local/etc/php/conf.d/zz-base.ini` | `expose_php = Off`、`opcache.validate_timestamps = 0`（正式版不檢查檔案變更，dev 版會檢查） |

| 環境變數 | 說明 |
|---|---|
| `TIMEZONE` | 容器時區，例如 `Asia/Taipei`。不設是 UTC |
| `XDEBUG_MODE` | 只有 dev 版有用，例如 `debug`。不設就是關閉 |

## 5.5 第一次建置

確認第 4 章的 CI 變數（`GCP_SERVICE_ACCOUNT_KEY`、`GCP_REGION`、`GCP_PROJECT_ID`、`AR_REPO`）都設好了，再推：

```bash
source ~/laravel-side-project.env
cd ~/work/laravel-side-project/base-image
git push -u origin main
```

1. 到 GitLab 專案的 Build → Pipelines，等 `build` 變成綠色。CI 的機器是 amd64，arm64 要靠模擬，會跑比較久
2. 確認推上去的 tag：

```bash
gcloud artifacts docker tags list "${AR}/base-images/nginx-php"     # 8.4、8.4-dev、8.4-<今天日期>
docker buildx imagetools inspect "${AR}/base-images/nginx-php:8.4"  # 看得到 linux/amd64、linux/arm64
```

**記下 `8.4-YYYYMMDD` 這個日期 tag**，第 6 章 backend 的 `Dockerfile` 要用。

失敗時點進 job 看 log：

| log 裡看到 | 原因 |
|---|---|
| `docker login` 失敗、`unauthorized` | `GCP_SERVICE_ACCOUNT_KEY` 沒設、貼錯，或不是 base64 |
| 映像檔路徑變成 `-docker.pkg.dev//...` | `GCP_REGION`、`GCP_PROJECT_ID`、`AR_REPO` 漏設，或沒勾 Protect variable |
| `denied`、`permission` | `gitlab-ci` 沒有 `base-images` 的 Artifact Registry Writer（3.5） |

### 不用 CI，從本機推

例如 CI 分鐘數用完時。在 Apple Silicon 上建 amd64 靠模擬，會跑很久：

```bash
cd ~/work/laravel-side-project/base-image
IMAGE="${AR}/base-images/nginx-php"
docker buildx build --platform linux/amd64,linux/arm64 --provenance=false --target prod \
  --tag "${IMAGE}:8.4-$(date -u +%Y%m%d)" --tag "${IMAGE}:8.4" --push .
docker buildx build --platform linux/amd64,linux/arm64 --provenance=false --target dev \
  --tag "${IMAGE}:8.4-dev" --push .
```

## 5.6 本機測試

改了 base image 之後，推上去前先在本機測：

```bash
cd ~/work/laravel-side-project/base-image
test/smoke.sh prod
test/smoke.sh dev
PLATFORM=linux/amd64 test/smoke.sh prod   # 在 Apple Silicon 上測 amd64，靠模擬，比較慢
```

腳本會建置映像檔，掛上 `test/www` 啟動容器，檢查：路由、PHP 設定、擴充、PHP-FPM 被殺掉後 supervisord 會自動重啟、log 有輸出。全部 PASS 才推。

## 5.7 每月重建

官方 PHP 映像檔會更新安全修補，每月重建一次才拿得到。GitLab 專案的 Build → Pipeline schedules → New schedule：

| 欄位 | 值 |
|---|---|
| Description | 每月重建 |
| Interval Pattern | Custom：`0 3 1 * *`（每月 1 日 3:00） |
| Cron timezone | Asia/Taipei |
| Select target branch or tag | `main` |

重建會推上新的 `8.4-<日期>`，並更新 `8.4`、`8.4-dev`。backend 要自己決定什麼時候升級（5.8）。

## 5.8 改 base image、升級 backend

1. 改 base-image，照 5.6 在本機測試
2. 推到 `main`，CI 建置並推上 `8.4-<今天日期>`、`8.4`、`8.4-dev`
3. 到 backend 的 `Dockerfile`，把 `ARG BASE_IMAGE=` 的日期換成新的
4. backend 打 R tag 部署到 dev 驗證，沒問題再打 v tag 部署 prod（第 9 章）

## 5.9 注意

- 同一天建置兩次，當天的日期 tag 會被覆蓋。所以 `base-images` 不能設成 tag 不可覆蓋
- `8.4-dev` 每次重建都會更新。本機要拿到新版：`docker compose pull`
- 映像檔大約 200 MB 上下，Artifact Registry 每月 0.5 GB 免費，之後每 GB 約 US$0.10。舊的日期 tag 用不到可以刪：`gcloud artifacts docker images delete "${AR}/base-images/nginx-php:8.4-<日期>"`
