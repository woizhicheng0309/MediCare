# base-image

給 Laravel API 用的基底映像檔：PHP 8.4 + nginx + supervisord。推到 GCP Artifact Registry，專案的 Dockerfile 從這裡 FROM，部署到 Cloud Run。

## Tag

映像檔：`<region>-docker.pkg.dev/<project>/<repo>/nginx-php`，amd64、arm64 兩個平台都有。

| tag | 內容 | 用途 |
|---|---|---|
| `8.4-YYYYMMDD` | 正式版，後面是建置日期 | 專案 Dockerfile 的 FROM 寫死這個，升級時改日期 |
| `8.4` | 最新的正式版 | 臨時測試 |
| `8.4-dev` | 正式版加 composer、xdebug | 本機 docker compose |

## 內容

- 上游是 `php:8.4-fpm-trixie`，PHP 設定用 `php.ini-production`，dev 版用 `php.ini-development`
- PHP 擴充：官方內建的（含 OPcache），加上 gd（FreeType、JPEG）、zip、pdo_mysql
- nginx、supervisor：Debian 套件
- dev 版另外有 composer、git、unzip、xdebug（預設關閉）
- 沒有 cron；正式版沒有 composer
- 官方映像檔附的編譯工具（gcc、make 等）還在。要加擴充請改這個 repo，不要在專案的 Dockerfile 裡編譯

## 啟動流程

1. `entrypoint.sh`，以 root 執行：
   1. `/var/www` 的擁有者不是 root、也不是 www-data 時，把 www-data 的 uid、gid 改成一樣，給本機掛載專案目錄用
   2. 有設 `TIMEZONE` 就改時區，不設是 UTC
   3. 依檔名順序用 sh 執行 `/docker-entrypoint.d/*.sh`，任何一個失敗就停止啟動
2. supervisord 啟動 php-fpm 和 nginx，任一個結束都會自動重啟。所有輸出都導到容器的 stdout

nginx 聽 8080，透過 `/run/php-fpm.sock` 連 FPM；nginx 和 FPM 的 worker 都以 www-data 執行。

## 預設設定

專案要改設定時，整份覆蓋這幾個檔案：

| 檔案 | 預設內容 |
|---|---|
| `/etc/nginx/nginx.conf` | worker 1 個、body 上限 70M、gzip、log 寫到 stdout 和 stderr |
| `/etc/nginx/conf.d/default.conf` | root 是 `/var/www/public`。`public/` 下的檔案直接回，其他路徑交給 `index.php`；任何 `.php` 網址都回 404；擋 `.` 開頭的檔案；`/ping` 交給 FPM 回 pong |
| `/usr/local/etc/php-fpm.conf` | FPM 全域設定和 www pool：worker 上限 128、`clear_env = no`、`ping.path = /ping`、請求 60 秒逾時、memory_limit 128M、上傳上限 23M、`allow_url_fopen` 關閉 |
| `/usr/local/etc/php/conf.d/zz-base.ini` | `expose_php = Off`、`opcache.validate_timestamps = 0` |

## 環境變數

| 變數 | 說明 |
|---|---|
| `TIMEZONE` | 容器時區，例如 `Asia/Taipei`。不設是 UTC |
| `XDEBUG_MODE` | 只有 dev 版有用，例如 `debug`。不設就是關閉 |

## 本機測試

```bash
test/smoke.sh prod
test/smoke.sh dev
PLATFORM=linux/amd64 test/smoke.sh prod   # 在 Apple Silicon 上測 amd64，靠模擬，比較慢
```

會建置映像檔，掛上 `test/www` 啟動容器，檢查路由、PHP 設定、擴充、supervisord 自動重啟和 log。改了 base image 之後，推上去前先跑一次。

## CI

推到預設分支時，在 gitlab.com 的 shared runner 上建置 amd64、arm64 兩個平台並推上 Artifact Registry。

需要的 CI 變數，在 Settings → CI/CD → Variables 設定，全部勾 Protected：

| 變數 | 內容 | 目前的值 |
|---|---|---|
| `GCP_SERVICE_ACCOUNT_KEY` | CI 用 service account 的 JSON 金鑰，要有 Artifact Registry Writer 角色。存 base64 編碼後的單行字串（`base64 -i key.json \| tr -d '\n'`），Visibility 選 Masked and hidden | `gitlab-ci@laravel-side-project` 的金鑰 |
| `GCP_REGION` | Artifact Registry 的區域 | `asia-east1` |
| `GCP_PROJECT_ID` | GCP 專案 ID | `laravel-side-project` |
| `AR_REPO` | 放 base image 的 Artifact Registry repo。不要設成 tag 不可覆蓋，因為 `8.4`、`8.4-dev` 每次都會更新 | `base-images` |

每月重建：在 GitLab 的 Pipeline schedules（Build → Pipeline schedules）新增排程，例如每月 1 日，分支選預設分支。同一天建置兩次，當天的日期 tag 會被覆蓋。