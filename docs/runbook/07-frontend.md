# 7. frontend

repo：`react-side-project`。React 的前端，呼叫 backend 的 API，部署在 Cloud Run。

## 7.1 概觀

| 項目 | 內容 |
|---|---|
| 技術 | React 19、TypeScript 6、Vite 8、react-router 7、MUI 9、MUI X Charts 9 |
| 套件管理 | pnpm（版本寫在 `package.json` 的 `packageManager`），Node 24（`.nvmrc`） |
| 功能 | 登入、首頁的專案統計圖表、設定頁的專案和系統管理 |
| 正式環境 | nginx（`nginxinc/nginx-unprivileged`，非 root，聽 8080）提供建置好的靜態檔。Cloud Run 服務 `frontend-dev`、`frontend-prod` |

### 和 backend 怎麼連

前端和 backend 用同一個網域，前端一律呼叫相對路徑 `/api`：

| 環境 | 誰把 `/api` 轉給 backend |
|---|---|
| 正式環境 | Load Balancer 依路徑分流：`/api`、`/docs`、`/up` 到 backend，其他到前端（第 8 章） |
| 本機開發 | Vite 開發伺服器的 proxy 轉給 backend 的 docker compose |

好處：

- 不用處理 CORS
- dev、prod 用同一個映像檔，執行時不需要任何環境變數

限制：

- 前端的路由不能用 `/api`、`/docs`、`/up` 開頭，不然會被導到 backend
- 經網域打 `/ping` 會由前端回應。`/ping` 只給 Cloud Run 直接打 backend 容器用，不受影響

### 登入怎麼運作

- 登入成功後，JWT 存在瀏覽器的 `localStorage`
- 要登入的請求帶 `Authorization: Bearer <token>`；每個請求都帶 `Accept: application/json`
- backend 回 401（token 過期或已登出）時，前端清掉 token，受保護的頁面會導回 `/login`
- 設定頁要登入才能進；首頁不用

## 7.2 改設定，推上 GitLab

```bash
source ~/laravel-side-project.env
cd ~/work/laravel-side-project/frontend

sed -i '' \
  -e "s#asia-east1-docker.pkg.dev/laravel-side-project/#${AR}/#g" \
  -e "s#^  GCP_PROJECT_ID: .*#  GCP_PROJECT_ID: ${PROJECT_ID}#" \
  -e "s#^  GCP_REGION: .*#  GCP_REGION: ${REGION}#" \
  .gitlab-ci.yml README.md

git diff    # 確認專案 ID、區域換成你的
git add -A
git commit -m "Use my GCP project"
git push -u origin main
```

## 7.3 本機開發

### 第一次啟動

先照 6.3 把 backend 的 compose 跑起來，API 在 http://localhost:8080。

```bash
cd ~/work/laravel-side-project/frontend
pnpm install
pnpm dev          # http://localhost:5173
```

- Vite 會把 `/api`、`/docs` 轉給 `http://localhost:8080`，和正式環境一樣是同源
- backend 的 port 不是 8080 時：`BACKEND_URL=http://localhost:<port> pnpm dev`
- 登入帳號是 backend 的 `admin0001`，密碼見 6.2

打開 http://localhost:5173：首頁有專案統計，側邊選單有 Login。登入後可以到 Settings 新增專案和系統，回首頁看圖表。

### 常用指令

| 指令 | 用途 |
|---|---|
| `pnpm dev` | 開發伺服器，改了程式立刻生效 |
| `pnpm lint` | eslint |
| `pnpm build` | 型別檢查（`tsc -b`）和建置，輸出在 `dist/` |
| `pnpm preview` | 用 `dist/` 啟動，一樣會轉送 `/api` |
| `pnpm audit` | 檢查套件的已知漏洞 |

pnpm 11 預設不執行套件的安裝腳本。需要的 esbuild 已經寫在 `pnpm-workspace.yaml` 的 `allowBuilds`；之後新增的套件需要時，執行 `pnpm approve-builds <套件>`。

### 用正式映像檔測試

```bash
docker build -t frontend:local .
docker run --rm -p 8081:8080 frontend:local    # http://localhost:8081
```

只有靜態檔，`/api` 不會轉到 backend，畫面上的資料會載入失敗。可以用來確認：直接打 `/settings` 也會回前端頁面、`/assets/` 底下的檔案有一年的快取。

### 專案結構

| 路徑 | 用途 |
|---|---|
| `src/main.tsx`、`src/App.tsx` | 進入點，依序包主題、登入狀態、路由 |
| `src/routes/index.tsx` | 路由表 |
| `src/layouts/MainLayout.tsx` | 側邊選單、頁首；選中的專案用 `useOutletContext` 傳給頁面 |
| `src/contexts/AuthContext.tsx`、`src/contexts/useAuth.ts` | 登入狀態。API 回 401 時清掉 token |
| `src/components/ProtectedRoute.tsx` | 要登入才能看的頁面 |
| `src/pages/` | 首頁、登入、設定 |
| `src/components/` | 選單、頁首、專案切換、首頁的統計卡片 |
| `src/services/api/` | 呼叫 backend 的函式 |
| `src/shared-theme/` | MUI 主題 |
| `src/types/` | API 回應的型別 |
| `vite.config.ts` | Vite 設定，包含本機的 proxy |
| `nginx.conf` | 正式環境的 nginx：找不到的路徑回 `index.html`、`/assets/` 快取一年、`index.html` 不快取、安全 header |
| `Dockerfile` | 正式環境映像檔：`node` 建置，`nginx-unprivileged` 提供 |
| `.gitlab-ci.yml` | CI，見第 9 章 |

### 開發流程

1. 從 `main` 開分支，改程式
2. `pnpm lint`、`pnpm build` 都要通過
3. commit、push，在 GitLab 開 Merge Request 合進 `main`
4. 要部署時打 tag，見第 9 章

新增頁面時，路由不要用 `/api`、`/docs`、`/up` 開頭。

## 7.4 建第一版映像檔

```bash
cd ~/work/laravel-side-project/frontend
git switch main && git pull
git tag -a R0.1.0.0 -m "First build"
git push origin R0.1.0.0
```

1. 到 GitLab 的 Build → Pipelines，等 `build` 變成綠色（約 1 到 2 分鐘）
2. **先不要按 `deploy-dev`**，7.5 建好服務再按
3. 確認：`gcloud artifacts docker tags list "${AR}/apps/frontend"`

## 7.5 Cloud Run 服務

dev、prod 一次建好，設定和 backend 一致。前端不需要環境變數、資料庫，也沒有 migration Job：

```bash
source ~/laravel-side-project.env
for env in dev prod; do
  gcloud run deploy "frontend-${env}" --region "${REGION}" \
    --image "${AR}/apps/frontend:R0.1.0.0" \
    --port 8080 --cpu 1 --memory 512Mi --concurrency 80 --min-instances 0 --max-instances 1 \
    --execution-environment gen2 --cpu-boost \
    --service-account "${FE_SA}" \
    --ingress all --allow-unauthenticated
done
```

- startup probe 用預設的 TCP 8080，nginx 一起來就能接請求
- `--ingress all` 是暫時的，第 8 章改成只允許 LB

確認（經 run.app 網址）：

```bash
URL="https://frontend-dev-${PROJECT_NUMBER}.${REGION}.run.app"
curl -s "${URL}/" | grep -o '<title>[^<]*</title>'              # <title>frontend</title>
curl -s "${URL}/settings" | grep -o '<title>[^<]*</title>'      # 直接打子頁面也回前端
curl -sI "${URL}/" | grep -i '^cache-control'                   # no-cache
```

這時 `run.app` 後面沒有接 backend，瀏覽器打開會看到畫面，但資料載入失敗，屬預期。第 8 章接上 Load Balancer 後才完整。

## 7.6 用 CI 部署一次 dev

到 7.4 那條 pipeline，按 `deploy-dev` 旁邊的 ▶。frontend 的 deploy job 只做一件事：`gcloud run deploy frontend-dev --image ...`。

```bash
gcloud run services describe frontend-dev --region "${REGION}" \
  --format='value(spec.template.spec.containers[0].image,status.latestReadyRevisionName,spec.template.spec.serviceAccountName)'
```

映像檔是 `R0.1.0.0`、revision 是 `00002`、service account 是 `frontend-runtime` 就對了。出現 `iam.serviceaccounts.actAs` 的錯誤，是 `gitlab-ci` 少了 `frontend-runtime` 的 Service Account User（3.5）。
