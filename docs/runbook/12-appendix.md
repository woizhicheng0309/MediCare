# 12. 附錄

## 12.1 名詞

| 名詞 | 說明 |
|---|---|
| GCP 專案 | GCP 上資源和帳單的單位。專案 ID 全球唯一 |
| Cloud Run | 跑容器的服務。依請求量自動開關 instance，沒有流量時可以縮到 0 |
| instance | 一個正在執行的容器 |
| revision | 每次部署或改設定都會產生一個新版本，可以把流量切回舊版 |
| Cloud Run Job | 跑完就結束的工作，這裡用來跑 migration |
| ingress | 誰可以連到服務：`all` 是任何人，`internal-and-cloud-load-balancing` 是只有 LB |
| concurrency | 一個 instance 同時處理的請求數上限 |
| 冷啟動 | 沒有 instance 時，第一個請求要等容器啟動 |
| startup probe、liveness probe | Cloud Run 定期打的健康檢查。startup 通過才開始接流量；liveness 連續失敗就重啟容器 |
| Artifact Registry（AR） | 放容器映像檔的地方 |
| tag 不可覆蓋（immutable tags） | 同一個 tag 推過就不能再推，確保版本號對應的內容不會變 |
| Load Balancer（LB） | 對外的入口，負責 HTTPS 和依網域、路徑分流 |
| serverless NEG | 讓 LB 把流量送到 Cloud Run 的連接點 |
| backend service | LB 的後端設定，一個接一個 NEG |
| URL map | LB 依網域、路徑決定送到哪個 backend service 的規則 |
| Google 代管憑證 | LB 用的 HTTPS 憑證，Google 自動申請和續期 |
| Cloud SQL | Google 代管的 MySQL |
| service account | 給程式用的 Google 帳號，例如 CI 用 `gitlab-ci` 部署 |
| base image | 自己做的基底映像檔，backend 的映像檔從它建出來 |
| PHP-FPM | 執行 PHP 的行程池。nginx 收到請求後交給它的 worker 執行 |
| supervisord | 在一個容器裡管理多個程式（nginx、PHP-FPM），掛了會自動重啟 |
| migration | Laravel 建表、改欄位的程式 |
| seed | 寫入初始資料，這裡是管理員帳號 |
| JWT | 登入後拿到的 token，之後的請求帶著它證明身分 |
| Swagger | API 文件，`/api/doc` 是網頁，`/docs` 是 JSON |
| SPA | 單頁應用程式。React 在瀏覽器裡切換頁面，伺服器對所有頁面路徑都回同一個 `index.html` |
| Vite | 前端的開發伺服器和建置工具 |
| pnpm、corepack | pnpm 是 Node 的套件管理工具；corepack 是 Node 內建的工具，照專案指定的版本自動下載 pnpm |
| protected tag、protected variable | GitLab 的保護設定。protected 變數只有 protected 分支和 protected tag 的 pipeline 拿得到 |
| pipeline、job | GitLab CI 每次觸發跑一條 pipeline，裡面有多個 job（`build`、`deploy-dev` 等） |

## 12.2 本專案的資源清單

GCP 專案 `laravel-side-project`（編號 838838006454），region `asia-east1`：

| 類型 | 名稱 |
|---|---|
| Artifact Registry | `base-images`（放 `nginx-php`）、`apps`（tag 不可覆蓋，放 `backend`、`frontend`） |
| Service account | `backend-runtime`、`frontend-runtime`（沒有任何角色）、`gitlab-ci` |
| Cloud SQL | `laravel-side-project-mysql`（MySQL 8.4、Enterprise、db-f1-micro），資料庫 `laravel_side_project_dev`、`laravel_side_project_prod`，帳號 `laravel_dev`、`laravel_prod` |
| Cloud Run 服務 | backend：`laravel-side-project-dev`、`laravel-side-project-prod`；frontend：`frontend-dev`、`frontend-prod` |
| Cloud Run Job | `laravel-side-project-dev-migration-job`、`laravel-side-project-prod-migration-job` |
| 靜態 IP | `laravel-side-project-lb-ip`（34.120.238.68） |
| serverless NEG | `laravel-side-project-dev-neg`、`laravel-side-project-prod-neg`、`frontend-dev-neg`、`frontend-prod-neg` |
| backend service | `laravel-side-project-dev-backend`、`laravel-side-project-prod-backend`、`frontend-dev-backend`、`frontend-prod-backend` |
| URL map | `laravel-side-project-lb`（HTTPS 用）、`laravel-side-project-http-redirect`（HTTP 轉 HTTPS） |
| 代管憑證 | `laravel-side-project-prod-cert`、`laravel-side-project-dev-cert` |
| proxy | `laravel-side-project-https-proxy`、`laravel-side-project-http-proxy` |
| 轉送規則 | `laravel-side-project-https`（443）、`laravel-side-project-http`（80） |

其他：

| 項目 | 值 |
|---|---|
| 網域 | prod `laravel-side-project.shuan1026.com`、dev `dev.laravel-side-project.shuan1026.com`（DNS 在 GoDaddy） |
| GitLab | `gitlab.com/mark102555/base-image`、`gitlab.com/mark102555/laravel-side-project`、`gitlab.com/mark102555/react-side-project` |
| 版本（2026-10-09） | backend `R0.3.1.0`、`v0.3.1`；frontend `R0.2.1.0`、`v0.2.1`；base image `8.4-20261008` |

## 12.3 和 GCP 專案有關的設定

換 GCP 專案、區域時要改的地方，見 4.6。另外三個地方不在 repo 裡：

| 位置 | 內容 |
|---|---|
| base-image 的 GitLab CI 變數 | `GCP_REGION`、`GCP_PROJECT_ID`、`AR_REPO`（4.3） |
| 三個 GitLab 專案的 CI 變數 | `GCP_SERVICE_ACCOUNT_KEY`（4.2、4.3） |
| Cloud Run 服務和 Job 的環境變數 | `APP_URL`、`L5_SWAGGER_BASE_PATH` 等，換網域時要改（6.5、9.8） |

## 12.4 已知問題與待辦

| 項目 | 說明 |
|---|---|
| 管理員密碼寫死在程式裡 | seeder 的密碼在 repo 裡，repo 是 public 就人人看得到。`/api/register` 也開放任何人註冊。之後要關掉註冊的話，seeder 改成從環境變數讀密碼 |
| 兩個資料庫帳號權限太大 | `laravel_dev`、`laravel_prod` 用 gcloud 建立，預設有 `cloudsqlsuperuser` 角色，看得到彼此的資料庫。要隔開的話，用 SQL 調整權限 |
| Laravel 12 的安全修正到 2027-02-24 | 之前要升 Laravel 13。Swagger 文件寫在 docblock 註解裡，要靠 `doctrine/annotations` 解析；Laravel 13 要用的 l5-swagger 11 不再帶它，升級時要在 `composer.json` 明確加上，不然 Swagger 文件會是空的 |
| TypeScript 7 | frontend 停在 TypeScript 6.0，因為 `typescript-eslint` 還不支援 7。支援後再升 |
| `ExampleTest` 會失敗 | 它測 `GET /` 要回 200，但這個專案沒有網頁路由。可以刪掉或改測 `/up` |
| 路由載入兩次 | `routes/home.php` 在 `bootstrap/app.php` 和 `RouteServiceProvider` 都有載入，結果一樣，只是多做一次。可以拿掉其中一個 |
| frontend 的打包大小 | 主程式 gzip 後約 302KB，Vite 會提醒超過 500KB（未壓縮）。要縮小的話，頁面改成 lazy loading |
| 冷啟動 | 約 4 到 8 秒，可以縮短的做法見 10.6 |
| base image 要每月重建 | 記得設 Pipeline schedule（5.7），才拿得到 PHP 的安全修補 |
| 資料庫沒有自動備份 | demo 環境沒開。正式使用前要開（3.6） |

## 12.5 各 repo 的 README

| 文件 | 內容 |
|---|---|
| base-image 的 `README.md` | 內容、tag、預設設定、本機測試、CI 變數 |
| backend 的 `README.md` | 本機開發、正式映像檔的組成、部署 |
| frontend 的 `README.md` | 架構、專案結構、本機開發、部署、回滾 |
