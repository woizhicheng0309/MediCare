# 1. 專案概觀

## 1.1 這是什麼

示範「PHP API + React 前端部署到 GCP Cloud Run」的架構。功能只是範例，重點是部署架構，之後會換成真正的功能：

- 登入、登出（JWT）
- 專案（project）和系統（system）的新增、修改、刪除、查詢
- 首頁的專案統計圖表
- API 文件（Swagger）

## 1.2 架構

```
                     瀏覽器
                       │ HTTPS
                       ▼
   Load Balancer（固定 IP，Google 代管憑證，HTTP 自動轉 HTTPS）
     依網域分 prod、dev，再依路徑分：
       /api、/docs、/up ──► backend：Cloud Run 服務 laravel-side-project-prod / -dev
       其他路徑         ──► frontend：Cloud Run 服務 frontend-prod / frontend-dev

 backend 的容器：supervisord 管 nginx 和 PHP-FPM，nginx 透過 unix socket 把請求交給 PHP-FPM
     │ Cloud Run 內建的 Cloud SQL 連線
     ▼
 Cloud SQL for MySQL 8.4（一個 instance，dev、prod 各一個資料庫）

 frontend 的容器：nginx 提供建置好的 React 靜態檔

 GitLab CI（gitlab.com）
   打 tag ──► 建置映像檔 ──► Artifact Registry
   手動按 deploy ──► backend：先跑 migration Job，成功才換新版服務
                 └► frontend：換新版服務
```

一個請求怎麼走：

- 打開 `https://<網域>/settings`：LB 看路徑不是 `/api` 開頭，送到 frontend；nginx 找不到 `settings` 這個檔案，回 `index.html`，由 React 的路由顯示設定頁
- 設定頁呼叫 `https://<網域>/api/projects`：LB 送到 backend；nginx 交給 PHP-FPM 執行 Laravel，查 MySQL，回 JSON
- 前端和 API 用同一個網域，瀏覽器不會擋，也不用處理 CORS

## 1.3 三個 repo

| repo | 本專案的位置 | 內容 |
|---|---|---|
| base-image | `gitlab.com/mark102555/base-image` | PHP 8.4 + nginx + supervisord 的基底映像檔。backend 的映像檔從這裡 FROM，本機開發也用它的 dev 版 |
| backend | `gitlab.com/mark102555/laravel-side-project` | Laravel API、Dockerfile、本機 docker compose、CI |
| frontend | `gitlab.com/mark102555/react-side-project` | React 前端、Dockerfile、nginx 設定、CI |

三個 repo 都是 public，不要把密碼、金鑰、`.env` 推上去。

## 1.4 兩個環境

| | dev | prod |
|---|---|---|
| 本專案的網址 | https://dev.laravel-side-project.shuan1026.com | https://laravel-side-project.shuan1026.com |
| backend 的 Cloud Run 服務 | `laravel-side-project-dev` | `laravel-side-project-prod` |
| backend 的 migration Job | `laravel-side-project-dev-migration-job` | `laravel-side-project-prod-migration-job` |
| frontend 的 Cloud Run 服務 | `frontend-dev` | `frontend-prod` |
| 資料庫 / 帳號 | `laravel_side_project_dev` / `laravel_dev` | `laravel_side_project_prod` / `laravel_prod` |
| 怎麼部署 | 打 `R1.2.3.4` 格式的 tag，再手動觸發 `deploy-dev` | 打 `v1.2.3` 格式的 tag，再手動觸發 `deploy-prod` |

## 1.5 重要的設計

| 設計 | 說明 |
|---|---|
| 同一個網域，依路徑分流 | `/api`、`/docs`、`/up` 到 backend，其他到 frontend。前端一律呼叫相對路徑 `/api`，dev、prod 用同一個前端映像檔 |
| 服務只能經由 LB 連線 | Cloud Run 的 ingress 只允許 Load Balancer，直接連 `*.run.app` 會回 404 |
| 映像檔裡沒有 `.env` | 正式環境的設定全部是 Cloud Run 的環境變數；只有本機開發用 `.env` |
| 機密也是一般環境變數 | `APP_KEY`、資料庫密碼、`JWT_SECRET` 直接設在 Cloud Run，沒有用 Secret Manager，保持簡單。主控台看得到明文，不要截圖外流 |
| log 寫到 stdout、stderr | Cloud Run 只收這兩個，nginx、PHP-FPM、Laravel 的 log 都導到這裡，在 Cloud Logging 查 |
| config 在容器啟動時快取 | 正式環境啟動時跑 `php artisan optimize`，設定讀的是當下的環境變數；本機不快取，改了程式和 `.env` 立刻生效 |
| migration 不在容器啟動時跑 | 部署時先由 migration Job 跑 migrate 和 seed，成功才換新版服務。多個 instance 同時啟動也不會重複跑 |
| 打 tag 才建置，部署要手動按 | 推分支、合併都不會觸發 CI。`R` 開頭的 tag 部署 dev，`v` 開頭的部署 prod |
| 映像檔的 tag 不可覆蓋 | 同一個版本號只能建一次，部署的內容一定對得上 git tag |
| CI 只換映像檔 | 記憶體、環境變數、Cloud SQL 連線等設定都在 GCP 上，不在 repo 裡 |
| 本機和正式環境用同一個基底 | backend 本機用 base image 的 dev 版，正式環境用 prod 版，PHP、nginx、擴充都一樣 |
| 沒有排程 | 沒有 cron，也沒有 Laravel scheduler |
| 沒有流量時縮到 0 | Cloud Run 的 instance 最少 0 個，省錢；代價是閒置後第一個請求要等冷啟動（約 4 到 8 秒） |

## 1.6 從零建置的流程

```
2. 帳號與工具
   │
3. GCP 專案與共用資源（專案、API、Artifact Registry、service account、Cloud SQL）
   │
4. GitLab 專案與 CI 設定（三個專案、金鑰、變數、protected tag）
   │
5. base image（推到 main，CI 建置）
   │
6. backend（本機跑起來 → CI 建第一版 → 建 Cloud Run 服務和 migration Job）
   │
7. frontend（本機跑起來 → CI 建第一版 → 建 Cloud Run 服務）
   │
8. Load Balancer 與網域（DNS、分流、HTTPS）
   │
9. 第一次正式發版（用 CI 部署 dev、prod）
```

前一步沒做完，後一步會卡住：

- backend 的映像檔從 base image 建出來，所以 5 要在 6 前面
- Cloud Run 服務建立時就要指定映像檔，所以先讓 CI 建一版，再建服務
- Load Balancer 要把流量送到 Cloud Run 服務，所以 8 要在 6、7 後面

## 1.7 需要準備的

| 項目 | 說明 |
|---|---|
| Google 帳號 | 能建 GCP 專案，並有可用的帳單帳戶（要綁信用卡） |
| gitlab.com 帳號 | 放三個 repo、跑 CI |
| 網域 | 你能新增 DNS 紀錄的網域，例如 `example.com`。HTTPS 憑證需要它 |
| 電腦 | macOS 或 Linux，能跑 Docker Desktop |
| 費用 | 每月約 US$28，見第 11 章 |
