# 11. 費用與拆除

## 11.1 每月費用

估算（2026-10-09，asia-east1 牌價，美元、未稅，一個月以 730 小時算）：

| 項目 | 計算 | 每月 |
|---|---|---|
| Load Balancer 轉送規則 | 全域 LB 前 5 條共收一筆最低費用，$0.025/小時；這裡有 2 條（443、80） | $18.25 |
| Cloud SQL instance | db-f1-micro，$0.0105/小時 | $7.67 |
| Cloud SQL 儲存 | SSD 10GB × $0.17 | $1.70 |
| Cloud Run（4 個服務、2 個 Job） | 每個帳單帳戶每月有免費額度（18 萬 vCPU 秒、36 萬 GiB 秒、200 萬次請求），demo 流量用不完 | 約 $0 |
| Artifact Registry | 0.5GB 免費，之後 $0.10/GB | 不到 $1 |
| 其他（LB 資料處理、對外流量、Logging） | | 幾乎為 0 |
| 合計 | | 約 $28（新台幣約 900 元） |

- Load Balancer 佔約三分之二。NEG、backend service、URL map 不另外收費
- 靜態 IP 接在轉送規則上不收費；沒接上時每小時 $0.01
- 代管憑證免費
- 停掉 Cloud SQL 省不了錢：停機後 instance 不收費，但 IP 開始收 $0.01/小時
- prod 的 instance 最少改成 1 個（沒有冷啟動），每月多約 $10
- Artifact Registry 的舊映像檔會一直累積，每個 backend 約 200MB、frontend 約 26MB、base image 每個平台約 200MB。用不到的可以刪：`gcloud artifacts docker images delete "${AR}/apps/backend:<tag>"`

## 11.2 看實際費用

- 預算提醒：見 3.7
- 實際帳單：GCP 主控台 → Billing → Reports，依 SKU 分組
- 新的 Google Cloud 帳號有免費試用額度（金額和期限以官網為準），帳單會先扣額度

## 11.3 拆除

不用時把資源刪掉，才不會一直計費。**這些操作不能復原，Cloud SQL 刪掉後資料就沒了。**

### 整個專案一起刪（最簡單）

```bash
gcloud projects delete "${PROJECT_ID}"
```

- 專案裡所有資源都會停止，不再計費
- 30 天內可以在主控台的 IAM & Admin → Manage resources 復原；30 天後永久刪除，專案 ID 也不能再用
- GitLab 的三個專案和 DNS 紀錄要另外刪

### 只刪資源，保留專案

順序不能顛倒：要先刪用到別人的資源。

```bash
source ~/laravel-side-project.env
NAME=laravel-side-project

# Load Balancer
gcloud compute forwarding-rules delete "${NAME}-https" "${NAME}-http" --global --quiet
gcloud compute target-https-proxies delete "${NAME}-https-proxy" --global --quiet
gcloud compute target-http-proxies delete "${NAME}-http-proxy" --global --quiet
gcloud compute url-maps delete "${NAME}-lb" "${NAME}-http-redirect" --global --quiet
gcloud compute ssl-certificates delete "${NAME}-prod-cert" "${NAME}-dev-cert" --global --quiet
for svc in laravel-side-project-dev laravel-side-project-prod frontend-dev frontend-prod; do
  gcloud compute backend-services delete "${svc}-backend" --global --quiet
  gcloud compute network-endpoint-groups delete "${svc}-neg" --region "${REGION}" --quiet
done
gcloud compute addresses delete "${NAME}-lb-ip" --global --quiet   # 沒在用的靜態 IP 也會收費

# Cloud Run
for env in dev prod; do
  gcloud run services delete "laravel-side-project-${env}" --region "${REGION}" --quiet
  gcloud run jobs delete "laravel-side-project-${env}-migration-job" --region "${REGION}" --quiet
  gcloud run services delete "frontend-${env}" --region "${REGION}" --quiet
done

# Cloud SQL（資料會一起刪掉）
gcloud sql instances delete "${SQL_INSTANCE}" --quiet

# Artifact Registry（apps 的 tag 不可覆蓋，刪不掉時先關掉：
#   gcloud artifacts repositories update apps --location "${REGION}" --no-immutable-tags）
gcloud artifacts repositories delete apps --location "${REGION}" --quiet
gcloud artifacts repositories delete base-images --location "${REGION}" --quiet

# Service account（gitlab-ci 刪掉後，它的金鑰也一起失效）
gcloud iam service-accounts delete "${CI_SA}" --quiet
gcloud iam service-accounts delete "${RUNTIME_SA}" --quiet
gcloud iam service-accounts delete "${FE_SA}" --quiet
```

GitLab 那邊：刪掉三個專案的 CI 變數 `GCP_SERVICE_ACCOUNT_KEY`，base-image 的 Pipeline schedule 也要停掉，不然每月還會嘗試建置。
