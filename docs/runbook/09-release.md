# 9. 發版與部署

## 9.1 第一次正式發版

從零建置的最後一步：用 CI 把 backend、frontend 部署到 prod。dev 在 6.6、7.6 已經用 CI 部署過 `R0.1.0.0`，對同一個 commit 打 v tag：

```bash
cd ~/work/laravel-side-project/backend
git tag -a v0.1.0 -m "First release" "R0.1.0.0^{}"
git push origin v0.1.0

cd ~/work/laravel-side-project/frontend
git tag -a v0.1.0 -m "First release" "R0.1.0.0^{}"
git push origin v0.1.0
```

`"R0.1.0.0^{}"` 指的是 `R0.1.0.0` 那個 commit。

1. 兩個專案的 Build → Pipelines，等 `build` 變成綠色
2. 先按 backend 的 `deploy-prod`，跑完再按 frontend 的 `deploy-prod`
3. 照 9.7 確認 prod

到這裡，整套環境就建好了。

## 9.2 平常的流程

```
開分支改程式 → Merge Request 合進 main
  → 打 R tag ──► CI build ──► 手動按 deploy-dev ──► 在 dev 驗證
  → 對同一個 commit 打 v tag ──► CI build ──► 手動按 deploy-prod ──► 在 prod 驗證
```

- 推分支、合併到 `main` 都不會觸發 CI，只有 tag 會
- 部署一定要手動按，打 tag 只會建置映像檔
- backend、frontend 各自打 tag、各自部署

## 9.3 tag 規則

| tag | 範例 | 觸發 |
|---|---|---|
| `R` + 四段數字 | `R0.2.0.0` | `build` + `deploy-dev`（手動） |
| `v` + 三段數字 | `v0.2.0` | `build` + `deploy-prod`（手動） |
| 其他 tag、推分支 | | 不觸發任何 job |

- 版本號沒有強制規則。習慣上 R 的前三段對應準備要發的 v，第四段是 dev 的第幾次嘗試：`R0.2.0.0`、`R0.2.0.1` 試過，沒問題就發 `v0.2.0`
- 不相容的變更（例如帳號改名、API 改格式）升第二段，修正升第三段
- 打 tag 前先看目前最新的版本號：`git fetch --tags && git tag --sort=-creatordate | head -5`
- 映像檔的 tag 不可覆蓋，同一個 tag 不能重新建置。要重建就打新 tag，例如 `R0.2.0.1`

## 9.4 backend 的 pipeline

| job | 什麼時候跑 | 做什麼 |
|---|---|---|
| `build` | 推 R 或 v tag 時自動 | 建置映像檔，推到 `${AR}/apps/backend:<git tag>` |
| `deploy-dev` | R tag，手動 | 部署到 `laravel-side-project-dev` |
| `deploy-prod` | v tag，手動 | 部署到 `laravel-side-project-prod` |

deploy job 做的事：

1. 用金鑰登入 GCP，登入後刪掉金鑰檔
2. `gcloud run jobs deploy <服務名>-migration-job`：把 migration Job 換成這個版本的映像檔
3. `gcloud run jobs execute <服務名>-migration-job --wait`：跑 migrate 和 seed。失敗就停在這裡，服務維持舊版
4. `gcloud run deploy <服務名> --image ...`：部署服務

## 9.5 frontend 的 pipeline

| job | 什麼時候跑 | 做什麼 |
|---|---|---|
| `build` | 推 R 或 v tag 時自動 | 建置映像檔（裡面跑 `pnpm build`，含型別檢查），推到 `${AR}/apps/frontend:<git tag>` |
| `deploy-dev` | R tag，手動 | `gcloud run deploy frontend-dev` |
| `deploy-prod` | v tag，手動 | `gcloud run deploy frontend-prod` |

## 9.6 發版步驟

**部署到 dev**

```bash
git switch main && git pull
git tag -a R0.2.0.0 -m "這次改了什麼"
git push origin R0.2.0.0
```

1. GitLab 的 Build → Pipelines，等 `build` 變成綠色（約 1 到 2 分鐘）
2. 點進 pipeline，按 `deploy-dev` 旁邊的 ▶
3. 跑完後照 9.7 確認 dev

**部署到 prod**：dev 確認沒問題，對同一個 commit 打 v tag：

```bash
git tag -a v0.2.0 -m "這次改了什麼" "R0.2.0.0^{}"
git push origin v0.2.0
```

等 `build` 完成，按 `deploy-prod` 的 ▶。

- `build` 成功、deploy 失敗時，修好原因（例如 GCP 上的設定）後可以直接重跑 deploy job，不用重建
- 程式有錯要修：改程式、合進 `main`，打新的 R tag

## 9.7 確認部署結果

```bash
source ~/laravel-side-project.env
ENV_NAME=dev     # prod 時改成 prod
if [ "${ENV_NAME}" = prod ]; then HOST="${PROD_HOST}"; else HOST="${DEV_HOST}"; fi

# 服務目前跑的映像檔和 revision
for svc in "laravel-side-project-${ENV_NAME}" "frontend-${ENV_NAME}"; do
  gcloud run services describe "${svc}" --region "${REGION}" \
    --format='value(metadata.name,spec.template.spec.containers[0].image.basename(),status.latestReadyRevisionName)'
done

# backend 的 migration Job 最近的執行紀錄（SUCCEEDED 是 1 代表成功）
gcloud run jobs executions list --job "laravel-side-project-${ENV_NAME}-migration-job" --region "${REGION}" --limit 3

# 經網域確認
curl -s -o /dev/null -w 'up %{http_code}\n' "https://${HOST}/up"                              # 200
curl -s -o /dev/null -w 'api %{http_code}\n' "https://${HOST}/api/projects"                   # 200
curl -s "https://${HOST}/" | grep -o '<title>[^<]*</title>'                                    # <title>frontend</title>

# 近 30 分鐘的錯誤 log（0 筆最好）
gcloud logging read "resource.type=cloud_run_revision AND severity>=ERROR AND (resource.labels.service_name=laravel-side-project-${ENV_NAME} OR resource.labels.service_name=frontend-${ENV_NAME})" \
  --freshness=30m --format='value(timestamp,resource.labels.service_name,textPayload)'
```

再用瀏覽器打開網域：登入、到 Settings 新增和刪除一個專案、回首頁看圖表、登出。

## 9.8 改環境變數、資源、probe

CI 只換映像檔，其他設定要直接改 GCP 上的服務。dev、prod 各自改；backend 的環境變數，服務和 migration Job 都要改（Job 跑 migration 時也會讀）：

```bash
# 新增或修改環境變數
gcloud run services update laravel-side-project-dev --region "${REGION}" --update-env-vars KEY=VALUE
gcloud run jobs update laravel-side-project-dev-migration-job --region "${REGION}" --update-env-vars KEY=VALUE

# 刪除環境變數
gcloud run services update laravel-side-project-dev --region "${REGION}" --remove-env-vars KEY
gcloud run jobs update laravel-side-project-dev-migration-job --region "${REGION}" --remove-env-vars KEY

# 資源（量測見 10.5）
gcloud run services update laravel-side-project-dev --region "${REGION}" \
  --cpu 1 --memory 512Mi --concurrency 80 --min-instances 0 --max-instances 1
```

- 每次修改都會產生新的 revision，幾十秒內生效。backend 啟動時會重新快取 config，不用另外處理
- 也可以在 GCP 主控台改：Cloud Run → 點服務 → Edit & deploy new revision → Variables & Secrets
- 機密（資料庫密碼、`APP_KEY`、`JWT_SECRET`）也是一般環境變數，在主控台看得到明文。不要截圖、不要貼到聊天室或 issue
- 改 `JWT_SECRET` 後所有人都要重新登入；改 `APP_KEY` 後，用它加密的資料會解不開

## 9.9 回滾

Cloud Run 每次部署都會留下一個 revision，可以把流量切回舊的：

```bash
gcloud run revisions list --service laravel-side-project-prod --region "${REGION}" --limit 5
gcloud run services update-traffic laravel-side-project-prod --region "${REGION}" \
  --to-revisions <revision 名稱>=100
```

- 幾秒內生效，不用重新建置
- backend 的 migration 不會跟著回滾，所以 migration 要寫成舊版程式也能跑（6.3 開發流程）
- 回滾後流量會固定在那個 revision。之後再部署新版，新版不會自動接到流量，修好後要執行：

```bash
gcloud run services update-traffic laravel-side-project-prod --region "${REGION}" --to-latest
```

## 9.10 backend 和 frontend 的版本關係

- 兩邊的版本號各自獨立
- API 有不相容的變更時，**先部署 backend**，而且新的 backend 要讓舊版前端還能用（例如先加新欄位，舊欄位保留一版）。前端部署後，下一版 backend 才拿掉舊的
- 只改前端或只改 backend 時，另一邊不用動

## 9.11 升級 base image

見 5.8：改 backend 的 `Dockerfile` 的 `BASE_IMAGE` 日期 tag，照一般流程發版。
