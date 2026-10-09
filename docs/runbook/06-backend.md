# 6. backend

repo：`laravel-side-project`。純 API 的 Laravel 12 專案，部署在 Cloud Run，資料庫是 Cloud SQL。

## 6.1 概觀

| 項目 | 內容 |
|---|---|
| 框架 | Laravel 12、PHP 8.4 |
| 登入 | JWT（`tymon/jwt-auth`），用帳號名稱和密碼登入，token 有效 60 分鐘 |
| 資料庫 | MySQL 8.4。本機用 docker compose 的 mysql，正式環境用 Cloud SQL |
| API 文件 | Swagger（`l5-swagger`），網址 `/api/doc` |
| 正式環境 | 映像檔以 base image 為底，nginx + PHP-FPM，Cloud Run 服務 `laravel-side-project-dev`、`laravel-side-project-prod` |

API 都在 `routes/home.php`，網址前面自動加 `/api`：

| 方法 | 路徑 | 要登入 | 說明 |
|---|---|---|---|
| POST | `/api/login` | | `name`、`password`，回 `token` |
| POST | `/api/register` | | 註冊。`name`、`email`、`password`、`password_confirmation` |
| GET | `/api/user` | ✓ | 目前登入的使用者 |
| POST | `/api/logout` | ✓ | 登出，token 立刻失效 |
| POST | `/api/refresh` | ✓ | 換一個新的 token |
| GET | `/api/projects`、`/api/projects/{id}`、`/api/projects/{id}/systems` | | 查專案 |
| POST / PUT / DELETE | `/api/projects`、`/api/projects/{id}` | ✓ | 新增、修改、刪除專案 |
| GET | `/api/systems`、`/api/systems/{id}` | | 查系統 |
| POST / PUT / DELETE | `/api/systems`、`/api/systems/{id}` | ✓ | 新增、修改、刪除系統 |
| GET | `/api/project-stats` | | 專案數、系統數、各專案的系統數 |
| GET | `/up` | | 健康檢查，Cloud Run 的 startup probe 用 |

- 要登入的 API 帶 `Authorization: Bearer <token>`
- `/api` 的錯誤一律回 JSON：401 未登入、404、422 驗證失敗、429 太多次
- 登入每分鐘限 5 次（同一個 IP）

## 6.2 改設定，推上 GitLab

把 GCP 專案和 base image 的日期 tag 換成你的。`BASE_TAG` 填第 5 章記下的日期 tag：

```bash
source ~/laravel-side-project.env
cd ~/work/laravel-side-project/backend
BASE_TAG=8.4-20261009    # 換成 5.5 記下的 tag

sed -i '' \
  -e "s#asia-east1-docker.pkg.dev/laravel-side-project/#${AR}/#g" \
  -e "s#^  GCP_PROJECT_ID: .*#  GCP_PROJECT_ID: ${PROJECT_ID}#" \
  -e "s#^  GCP_REGION: .*#  GCP_REGION: ${REGION}#" \
  .gitlab-ci.yml Dockerfile docker-compose.yml README.md
sed -i '' -e "s#nginx-php:8.4-[0-9]\{8\}#nginx-php:${BASE_TAG}#" Dockerfile

git diff    # 確認：專案 ID、區域、base image 的 tag 都換成你的
```

**建議換掉管理員密碼。** seeder 建立的管理員帳號 `admin0001`，密碼寫在 `database/seeders/AdminUserSeeder.php` 的 `Hash::make('...')` 裡，原本的密碼在公開的 repo 裡人人看得到。用編輯器換成你的密碼，註解裡的密碼也一起改。GitLab 專案是 Public 的話，新密碼一樣看得到；要保密就把專案設成 Private。

```bash
git add -A
git commit -m "Use my GCP project"
git push -u origin main
```

推 `main` 不會觸發 CI，只有打 tag 會。

## 6.3 本機開發

### 第一次啟動

確認 Docker Desktop 在跑，而且做過 3.4 的 `gcloud auth configure-docker`：

```bash
cd ~/work/laravel-side-project/backend

# 1. 建立本機設定檔
cp .env.example .env

# 2. 安裝 PHP 套件（用 base image dev 版裡的 composer）
#    --no-deps：只啟動 program，不帶起 mysql 和 migration
docker compose run --rm --no-deps program composer install

# 3. 產生 APP_KEY、JWT_SECRET，寫進 .env
docker compose run --rm --no-deps program php artisan key:generate
docker compose run --rm --no-deps program php artisan jwt:secret --force

# 4. 啟動
docker compose up -d
```

第一次要下載映像檔，需要幾分鐘。啟動的服務：

| 服務 | 做什麼 |
|---|---|
| `mysql` | MySQL 8.4。第一次啟動會建 `laravel` 資料庫，以及測試用的 `testing` 資料庫 |
| `migration` | 等 mysql 就緒後跑 `db-migrate-seed.sh`（建表、建管理員帳號），跑完就結束 |
| `program` | nginx + PHP-FPM，等 migration 成功才啟動。專案目錄掛在容器的 `/var/www`，改了程式立刻生效 |
| `admin` | phpMyAdmin，平常不啟動，要用時加 `--profile admin` |

### 確認

| 位址 | 預期 |
|---|---|
| http://localhost:8080/up | 回 200 |
| http://localhost:8080/api/doc | Swagger 文件（第一次打開時才產生，要等一兩秒） |
| `127.0.0.1:3307` | MySQL，帳號 `root`，密碼是 `.env` 的 `DB_PASSWORD` |
| http://localhost:8081 | phpMyAdmin，要先 `docker compose --profile admin up -d` |

試著登入：

```bash
curl -X POST http://localhost:8080/api/login \
  -H 'Accept: application/json' \
  -d name=admin0001 -d password=<seeder 裡的密碼>
```

回傳裡有 `token` 就成功了。

### 常用指令

| 指令 | 用途 |
|---|---|
| `docker compose ps` | 看服務狀態 |
| `docker compose logs -f program` | 看 nginx、PHP、Laravel 的 log |
| `docker compose exec program php artisan ...` | 執行 artisan |
| `docker compose exec program composer ...` | 執行 composer |
| `docker compose exec program vendor/bin/phpunit` | 跑測試，用 mysql 裡的 `testing` 資料庫 |
| `docker compose exec program php artisan l5-swagger:generate` | 改了 Swagger 註解後重新產生文件 |
| `docker compose restart program` | 重啟網站容器 |
| `docker compose pull` | 拿最新的 base image dev 版 |
| `docker compose down` | 停止，資料庫資料保留 |
| `docker compose down -v` | 停止並刪除資料庫資料 |

- port 被佔用時，改 `.env` 的 `DOCKER_NGINX_PORT`、`DOCKER_MYSQL_PORT`、`DOCKER_PHPMYADMIN_PORT`
- 要用 xdebug：在 `docker-compose.yml` 的 `program` 加上環境變數 `XDEBUG_MODE: debug`，再 `docker compose up -d`

### 專案結構

| 路徑 | 內容 |
|---|---|
| `app/` | Controller、Model、Service、Request（驗證規則）、Middleware |
| `routes/home.php` | API 路由，網址前面會自動加 `/api` |
| `database/migrations/` | 建表、改欄位 |
| `database/seeders/` | 初始資料，目前只有管理員帳號 |
| `config/` | Laravel 設定，值大多從環境變數讀 |
| `bootstrap/app.php` | 路由、middleware、錯誤處理的設定。`/api` 的錯誤一律回 JSON 在這裡設定 |
| `Dockerfile` | 正式環境映像檔，分兩個階段：先用 composer 裝套件、快取路由和 view，再複製到乾淨的 base image |
| `docker/entrypoint.d/05-optimize.sh` | 容器啟動時跑 `php artisan optimize`，快取 config、route、view、event。本機有 `.env`，會跳過 |
| `app/Http/Middleware/GenerateSwaggerDocsIfMissing.php` | Swagger 文件在第一次讀取 `/docs` 時才產生，不在容器啟動時產生 |
| `db-migrate-seed.sh` | 跑 migrate 和 seed。本機給 compose 的 `migration` 用，正式環境給 migration Job 用 |
| `docker-compose.yml` | 本機開發環境 |
| `docker/mysql/create-testing-database.sql` | 本機 mysql 第一次啟動時建 `testing` 資料庫 |
| `.gitlab-ci.yml` | CI，見第 9 章 |
| `.env.example` | 本機設定範本 |

### 一個請求怎麼跑

```
curl /api/projects
  → nginx（8080）：public/ 底下有這個檔案就直接回，沒有就交給 PHP-FPM
  → PHP-FPM：由 worker 執行 public/index.php
  → Laravel：路由到 ProjectController，查 MySQL，回 JSON
```

- 網址結尾是 `.php` 的一律回 404，只有 `index.php` 會被執行
- `.` 開頭的檔案（例如 `.env`）一律擋掉
- `/ping` 由 PHP-FPM 直接回 `pong`，給 Cloud Run 的 liveness probe 用

### 開發流程

1. 從 `main` 開分支：`git switch -c feature/xxx`
2. 改程式，跑測試：`docker compose exec program vendor/bin/phpunit`
3. commit、push，在 GitLab 開 Merge Request 合進 `main`
4. 要部署時打 tag，見第 9 章

注意：

- migration 要寫成舊版程式也能跑：先加欄位，下一版才刪。部署時先跑 migration，而且回滾服務時 migration 不會跟著回滾
- 新增環境變數時，`.env.example` 要加，Cloud Run 的 dev、prod 服務和 migration Job 也要加（9.8）
- `.env` 不會進 git，也不會進映像檔
- 已知問題：`tests/Feature/ExampleTest.php` 會失敗，它測 `GET /` 要回 200，但這個專案沒有網頁路由

### 讓其他人也能在本機開發

本機的 compose 要從 Artifact Registry 拉 base image 的 dev 版。專案 Owner 已經有權限；其他人要另外加讀取權限：

```bash
gcloud artifacts repositories add-iam-policy-binding base-images \
  --location="${REGION}" --member="user:對方的 email" --role=roles/artifactregistry.reader
```

## 6.4 建第一版映像檔

Cloud Run 服務建立時就要指定映像檔，所以先讓 CI 建一版：

```bash
cd ~/work/laravel-side-project/backend
git switch main && git pull
git tag -a R0.1.0.0 -m "First build"
git push origin R0.1.0.0
```

1. 到 GitLab 的 Build → Pipelines，等 `build` 變成綠色（約 1 到 2 分鐘）
2. **先不要按 `deploy-dev`**：服務還沒建，6.6 才按
3. 確認映像檔推上去了：`gcloud artifacts docker tags list "${AR}/apps/backend"`

`build` 失敗的話：

| log 裡看到 | 原因 |
|---|---|
| `unauthorized`、`denied` | CI 變數沒設好，或 `R*` 沒設成 protected tag（第 4 章） |
| `manifest unknown`，找不到 base image | `Dockerfile` 的 `BASE_IMAGE` 專案或日期 tag 錯了（6.2） |
| `tag immutable` | 這個 tag 推過了，打新的 tag，例如 `R0.1.0.1` |

## 6.5 Cloud Run 服務和 migration Job

dev、prod 各做一次：先照下面做 dev，再把第一行改成 `ENV_NAME=prod` 做一次 prod。

```bash
source ~/laravel-side-project.env
ENV_NAME=dev     # 做 prod 時改成 prod
if [ "${ENV_NAME}" = prod ]; then HOST="${PROD_HOST}"; else HOST="${DEV_HOST}"; fi
SVC="laravel-side-project-${ENV_NAME}"
JOB="${SVC}-migration-job"
IMAGE="${AR}/apps/backend:R0.1.0.0"
DB_USER="laravel_${ENV_NAME}"
DB_PASSWORD="$(openssl rand -hex 24)"

# 1. 資料庫帳號，密碼隨機產生，直接設進 Cloud Run，不另外保存
gcloud sql users create "${DB_USER}" --instance="${SQL_INSTANCE}" --password="${DB_PASSWORD}"

# 2. 環境變數檔：裡面有機密，權限設成只有自己能讀，用完就刪
touch env.yaml && chmod 600 env.yaml
cat > env.yaml <<EOF
APP_ENV: production
APP_DEBUG: "false"
APP_KEY: "base64:$(openssl rand -base64 32)"
APP_URL: "https://${HOST}"
DB_CONNECTION: mysql
DB_SOCKET: "/cloudsql/${CONN}"
DB_DATABASE: "laravel_side_project_${ENV_NAME}"
DB_USERNAME: "${DB_USER}"
DB_PASSWORD: "${DB_PASSWORD}"
JWT_SECRET: "$(openssl rand -hex 32)"
LOG_CHANNEL: stderr
CACHE_STORE: database
L5_SWAGGER_BASE_PATH: "https://${HOST}"
EOF

# 3. migration Job：建立，並執行一次（建表、建管理員帳號）
gcloud run jobs create "${JOB}" --image "${IMAGE}" --region "${REGION}" \
  --command bash --args /var/www/db-migrate-seed.sh \
  --service-account "${RUNTIME_SA}" --set-cloudsql-instances "${CONN}" \
  --env-vars-file env.yaml --max-retries 0
gcloud run jobs execute "${JOB}" --region "${REGION}" --wait

# 4. 服務
gcloud run deploy "${SVC}" --image "${IMAGE}" --region "${REGION}" \
  --service-account "${RUNTIME_SA}" --add-cloudsql-instances "${CONN}" \
  --env-vars-file env.yaml \
  --cpu 1 --memory 512Mi --concurrency 80 --min-instances 0 --max-instances 1 \
  --execution-environment gen2 --cpu-boost \
  --startup-probe httpGet.path=/up,httpGet.port=8080,periodSeconds=1,failureThreshold=120,timeoutSeconds=1 \
  --liveness-probe httpGet.path=/ping,httpGet.port=8080,periodSeconds=30,failureThreshold=3,timeoutSeconds=3 \
  --ingress all --allow-unauthenticated

# 5. 清掉機密
rm env.yaml
unset DB_PASSWORD
```

### 確認（經 run.app 網址）

```bash
URL="https://${SVC}-${PROJECT_NUMBER}.${REGION}.run.app"
curl -s -o /dev/null -w 'up %{http_code}\n' "${URL}/up"                                         # 200
curl -s -H 'Accept: application/json' "${URL}/api/projects"; echo                               # []
curl -s -X POST "${URL}/api/login" -H 'Accept: application/json' \
  -d name=admin0001 -d password=<seeder 裡的密碼>; echo                                          # 有 token
```

- `APP_URL`、Swagger 的網址一開始就設成網域。網域在第 8 章才會通，所以這時在 `run.app` 上打開 Swagger 試打 API 會失敗，屬預期
- 第 8 章做完後，ingress 會改成只允許 LB，`run.app` 網址就不能直接連了

### 設定說明

| 設定 | 值 | 說明 |
|---|---|---|
| `DB_SOCKET` | `/cloudsql/<連線名稱>` | Cloud Run 內建的 Cloud SQL 連線會在這個路徑開一個 unix socket，不用設 `DB_HOST` |
| `CACHE_STORE` | `database` | 多個 instance 共用，登入限流和登出後的 token 黑名單才會一致 |
| `LOG_CHANNEL` | `stderr` | Laravel 的 log 由 Cloud Run 收集 |
| `APP_KEY`、`JWT_SECRET` | 隨機產生 | dev、prod 各一組，不要共用。改了 `JWT_SECRET`，所有人都要重新登入 |
| `--max-retries 0` | | migration 失敗不重試，馬上回報 |
| `--cpu 1 --memory 512Mi --concurrency 80` | | 一個 instance 1 vCPU、512MB，同時最多處理 80 個請求。量測結果見 10.5 |
| `--min-instances 0 --max-instances 1` | | 沒流量時縮到 0，不收 instance 費用；最多 1 個 |
| `--execution-environment gen2 --cpu-boost` | | 第二代執行環境；啟動時暫時給更多 CPU，縮短冷啟動 |
| startup probe `/up` | 每 1 秒，最多 2 分鐘 | Laravel 起來才開始接流量。間隔短，一起來就能接請求 |
| liveness probe `/ping` | 每 30 秒，連續 3 次失敗 | PHP-FPM 掛了就重啟容器 |
| `--ingress all` | | 暫時讓 `run.app` 能直接連；第 8 章改成只允許 LB |
| `--allow-unauthenticated` | | 允許任何人呼叫，登入由 Laravel 自己處理 |

## 6.6 用 CI 部署一次 dev

服務和 Job 都建好後，確認 CI 的部署流程能用：到 6.4 那條 pipeline，按 `deploy-dev` 旁邊的 ▶。

deploy job 會：

1. 把 migration Job 換成這個版本的映像檔，執行一次
2. migration 成功才部署服務

跑完後確認：

```bash
gcloud run services describe laravel-side-project-dev --region "${REGION}" \
  --format='value(spec.template.spec.containers[0].image,status.latestReadyRevisionName)'   # R0.1.0.0、revision 00002
gcloud run jobs executions list --job laravel-side-project-dev-migration-job --region "${REGION}" --limit 2
```

失敗的話看 job 的 log：

| log 裡看到 | 原因 |
|---|---|
| `Permission 'iam.serviceaccounts.actAs' denied` | `gitlab-ci` 沒有 `backend-runtime` 的 Service Account User（3.5） |
| `Job ... not found`、`Service ... not found` | 服務名稱和 `.gitlab-ci.yml` 裡的不一樣 |
| 停在 migration | migrate 或 seed 出錯，看 migration Job 的 log（10.1） |

prod 等第 8 章網域通了，在第 9 章用 v tag 正式部署。
