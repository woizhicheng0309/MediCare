# 3. GCP 專案與共用資源

這一章建立 GCP 專案，以及 backend、frontend 都會用到的資源：Artifact Registry、service account、Cloud SQL。

- 需要的權限：用自己的 Google 帳號建專案，就是專案的 Owner
- 需要的時間：約 40 分鐘，大部分在等 Cloud SQL 建立

## 3.1 決定名稱，存成變數檔

先查帳單帳戶 ID：

```bash
gcloud billing accounts list    # 記下 ACCOUNT_ID，格式像 012345-6789AB-CDEF01
```

把所有名稱寫進一個檔案。`PROJECT_ID`、網域、GitLab 帳號、帳單帳戶換成你的，其他建議照用：

```bash
cat > ~/laravel-side-project.env <<'EOF'
# GCP 專案
export PROJECT_ID=your-project-id                     # 全球唯一，6–30 個字元：小寫英文、數字、連字號，英文開頭
export REGION=asia-east1                              # 台灣
export BILLING_ACCOUNT=012345-6789AB-CDEF01           # gcloud billing accounts list 查到的 ACCOUNT_ID

# 衍生的名稱，不用改
export AR="${REGION}-docker.pkg.dev/${PROJECT_ID}"    # Artifact Registry 的路徑前綴
export SQL_INSTANCE=laravel-side-project-mysql
export CONN="${PROJECT_ID}:${REGION}:${SQL_INSTANCE}"  # Cloud SQL 的連線名稱
export RUNTIME_SA="backend-runtime@${PROJECT_ID}.iam.gserviceaccount.com"
export FE_SA="frontend-runtime@${PROJECT_ID}.iam.gserviceaccount.com"
export CI_SA="gitlab-ci@${PROJECT_ID}.iam.gserviceaccount.com"

# 網域
export PROD_HOST=app.example.com
export DEV_HOST=dev.app.example.com

# gitlab.com 的帳號或群組
export GITLAB_NS=your-gitlab-account
EOF

source ~/laravel-side-project.env
```

**每開一個新的終端機，都要先 `source ~/laravel-side-project.env`。** 後面所有指令都用這些變數。

| 變數 | 本專案的值 |
|---|---|
| `PROJECT_ID` | `laravel-side-project`（已被使用，你要換一個） |
| `REGION` | `asia-east1` |
| `PROD_HOST` | `laravel-side-project.shuan1026.com` |
| `DEV_HOST` | `dev.laravel-side-project.shuan1026.com` |
| `GITLAB_NS` | `mark102555` |

Cloud Run 服務、Job、Cloud SQL、service account 的名稱只要在同一個專案裡不重複就好，所以照本專案的名稱即可。服務名稱也寫在 CI 設定裡，改名的話 CI 也要跟著改（第 4 章）。

## 3.2 建立專案、連結帳單

```bash
gcloud projects create "${PROJECT_ID}" --name="laravel-side-project"
gcloud billing projects link "${PROJECT_ID}" --billing-account="${BILLING_ACCOUNT}"
gcloud config set project "${PROJECT_ID}"

# 專案編號在 run.app 網址裡會用到，存進變數檔
echo "export PROJECT_NUMBER=$(gcloud projects describe "${PROJECT_ID}" --format='value(projectNumber)')" >> ~/laravel-side-project.env
source ~/laravel-side-project.env
echo "${PROJECT_NUMBER}"    # 一串數字
```

- `projects create` 失敗、訊息說 ID 已被使用：換一個 `PROJECT_ID`，改變數檔後重來
- 沒連結帳單，下一步啟用 API 會失敗

## 3.3 啟用 API

```bash
gcloud services enable \
  artifactregistry.googleapis.com iam.googleapis.com cloudresourcemanager.googleapis.com \
  sqladmin.googleapis.com run.googleapis.com compute.googleapis.com
```

| API | 用途 |
|---|---|
| Artifact Registry | 放映像檔 |
| IAM、Cloud Resource Manager | 建 service account、設定權限 |
| Cloud SQL Admin | Cloud SQL；Cloud Run 連 Cloud SQL 也需要 |
| Cloud Run | 服務和 Job |
| Compute Engine | Load Balancer、靜態 IP |

要一兩分鐘。Compute Engine 第一次啟用比較久，耐心等它回到提示字元。

## 3.4 Artifact Registry

放映像檔的地方，建兩個 repo：

```bash
gcloud artifacts repositories create base-images --repository-format=docker \
  --location="${REGION}" --description="base image，8.4、8.4-dev 這類 tag 會更新"

gcloud artifacts repositories create apps --repository-format=docker \
  --location="${REGION}" --immutable-tags --description="應用程式映像檔，tag 不可覆蓋"

# 讓本機的 Docker 用 gcloud 的登入身分存取 Artifact Registry，只要做一次
gcloud auth configure-docker "${REGION}-docker.pkg.dev"
```

| repo | 放什麼 | tag 可以覆蓋嗎 |
|---|---|---|
| `base-images` | base image（`nginx-php`） | 可以。`8.4`、`8.4-dev` 每次重建都要指向新版本 |
| `apps` | backend、frontend 的映像檔 | 不行。同一個版本號只能推一次，部署的內容一定對得上 git tag |

映像檔的完整路徑是 `${AR}/<repo>/<名稱>:<tag>`，例如 `${AR}/apps/backend:v0.1.0`。

`configure-docker` 會在 `~/.docker/config.json` 加一行，之後 `docker pull`、`docker push` 到 `${REGION}-docker.pkg.dev` 都用 gcloud 的登入身分。

## 3.5 Service account 和權限

service account 是給程式用的 Google 帳號。建三個：

```bash
gcloud iam service-accounts create backend-runtime --display-name="backend Cloud Run 執行用"
gcloud iam service-accounts create frontend-runtime --display-name="frontend Cloud Run 執行用（不給任何角色）"
gcloud iam service-accounts create gitlab-ci --display-name="GitLab CI 推映像檔、部署 Cloud Run 用"

# backend 執行時：連 Cloud SQL
gcloud projects add-iam-policy-binding "${PROJECT_ID}" \
  --member="serviceAccount:${RUNTIME_SA}" --role=roles/cloudsql.client --condition=None

# GitLab CI：部署 Cloud Run
gcloud projects add-iam-policy-binding "${PROJECT_ID}" \
  --member="serviceAccount:${CI_SA}" --role=roles/run.admin --condition=None

# GitLab CI：推映像檔（兩個 repo）
for repo in base-images apps; do
  gcloud artifacts repositories add-iam-policy-binding "${repo}" --location="${REGION}" \
    --member="serviceAccount:${CI_SA}" --role=roles/artifactregistry.writer
done

# GitLab CI：部署「以 backend-runtime、frontend-runtime 身分執行」的服務
for sa in "${RUNTIME_SA}" "${FE_SA}"; do
  gcloud iam service-accounts add-iam-policy-binding "${sa}" \
    --member="serviceAccount:${CI_SA}" --role=roles/iam.serviceAccountUser --condition=None
done
```

剛建好的 service account 要幾秒鐘才生效。設權限時出現「does not exist」，等一下再跑一次那一行即可。

| service account | 角色 | 範圍 | 為什麼 |
|---|---|---|---|
| `backend-runtime` | Cloud SQL Client | 專案 | backend 的服務和 migration Job 用它連 Cloud SQL |
| `frontend-runtime` | 無 | | frontend 只提供靜態檔，不需要任何 GCP 權限。不用預設的 Compute Engine service account，它的權限太大 |
| `gitlab-ci` | Cloud Run Admin | 專案 | 部署服務、更新和執行 Job、設定公開存取 |
| `gitlab-ci` | Artifact Registry Writer | 只在兩個 repo | 推映像檔，也能讀 base image |
| `gitlab-ci` | Service Account User | 只在 `backend-runtime`、`frontend-runtime` 上 | 部署「以這兩個帳號執行」的服務。只給在這兩個帳號上，不能代用專案裡其他 service account |

`gitlab-ci` 的金鑰在第 4 章建立，建好馬上貼到 GitLab，不要留在電腦上。

## 3.6 Cloud SQL

backend 的資料庫。建立要 10 到 30 分鐘，指令會一直等到完成，可以開另一個終端機（記得 `source` 變數檔）繼續做第 4、5 章。

```bash
gcloud sql instances create "${SQL_INSTANCE}" --region="${REGION}" \
  --database-version=MYSQL_8_4 --edition=enterprise --tier=db-f1-micro \
  --storage-type=SSD --storage-size=10 \
  --root-password="$(openssl rand -base64 24)"

for env in dev prod; do
  gcloud sql databases create "laravel_side_project_${env}" --instance="${SQL_INSTANCE}" \
    --charset=utf8mb4 --collation=utf8mb4_unicode_ci
done
```

- 一定要寫 `--edition=enterprise`：新的 MySQL instance 預設可能是 Enterprise Plus，那個規格貴很多，也不支援 `db-f1-micro`
- `db-f1-micro` 是最小的共享核心規格，demo 夠用
- dev、prod 共用一個 instance，各一個資料庫，省錢
- root 密碼隨機產生、沒有保存，App 不用 root。要用時重設：`gcloud sql users set-password root --host=% --instance="${SQL_INSTANCE}" --prompt-for-password`
- instance 有公開 IP，但沒開放任何網段，只能透過 Cloud Run 內建的連線或 `gcloud sql connect` 連入
- 自動備份沒開。要開：`gcloud sql instances patch "${SQL_INSTANCE}" --backup-start-time=18:00`（UTC，台灣凌晨 2 點）
- 資料庫帳號在第 6 章建立，密碼直接設進 Cloud Run，不另外保存

## 3.7 預算提醒

避免意外的費用。GCP 主控台 → Billing → Budgets & alerts → Create budget：

1. Scope：Projects 選這個專案
2. Amount：例如 US$40（正常每月約 US$28，見第 11 章）
3. Actions：預設在 50%、90%、100% 寄信給帳單管理員

## 3.8 確認

```bash
gcloud config list                                       # project 是你的 PROJECT_ID
gcloud artifacts repositories list --location="${REGION}" # base-images、apps
gcloud iam service-accounts list                          # backend-runtime、frontend-runtime、gitlab-ci
gcloud sql instances list                                 # STATUS 是 RUNNABLE
gcloud sql databases list --instance="${SQL_INSTANCE}"    # 有 laravel_side_project_dev、laravel_side_project_prod
```
