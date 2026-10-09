# 4. GitLab 專案與 CI 設定

三個 repo 各放在一個 gitlab.com 專案，CI 由 gitlab.com 的 shared runner 執行，用 `gitlab-ci` service account 的金鑰登入 GCP。

先做完這一章的設定，第 5 到 7 章才推程式碼：base image 一推上 `main` 就會觸發 CI，變數沒設好會失敗。

## 4.1 建三個專案

gitlab.com 右上角的 **+** → New project/repository → Create blank project，建三次：

| Project name | 放什麼 | 本專案的位置 |
|---|---|---|
| `base-image` | base image | `gitlab.com/mark102555/base-image` |
| `laravel-side-project` | backend | `gitlab.com/mark102555/laravel-side-project` |
| `react-side-project` | frontend | `gitlab.com/mark102555/react-side-project` |

每個都這樣填：

- Project URL：選你的帳號或群組（`${GITLAB_NS}`）
- Visibility Level：Public 或 Private 都可以。本專案是 Public，任何人都看得到程式碼和 CI 的 log
- **取消勾選** Initialize repository with a README：專案要是空的，才能直接推既有的程式碼

第一次用 shared runner 時，gitlab.com 可能要求驗證帳號（手機或信用卡），照畫面指示完成即可。免費方案每月有 CI 分鐘數上限，以 GitLab 官網為準；這個專案最耗時的是 base image 的多平台建置，一個月一次。

## 4.2 CI 用的金鑰

建立 `gitlab-ci` 的 JSON 金鑰，base64 編碼成一行後複製到剪貼簿：

```bash
source ~/laravel-side-project.env
gcloud iam service-accounts keys create key.json --iam-account="${CI_SA}"
base64 -i key.json | tr -d '\n' | pbcopy    # 複製到剪貼簿
```

**先別關終端機**，照 4.3 把它貼到三個專案，貼完再刪：

```bash
rm key.json
pbcopy < /dev/null    # 清空剪貼簿
```

- 金鑰等於密碼，有它就能推映像檔、部署 Cloud Run。不要存檔、不要 commit、不要貼到聊天室
- 為什麼要 base64：GitLab 的 Masked 變數只接受特定字元，JSON 的換行、大括號不行。編碼成 base64 後才能設成 Masked，萬一被印出來，log 裡也只會看到遮罩
- 三個專案用同一把金鑰
- 查看、停用金鑰：

```bash
gcloud iam service-accounts keys list --iam-account="${CI_SA}" --managed-by=user
gcloud iam service-accounts keys delete <金鑰 ID> --iam-account="${CI_SA}"
```

## 4.3 CI 變數

每個專案：Settings → CI/CD → Variables → Add variable。

**`GCP_SERVICE_ACCOUNT_KEY`（三個專案都要）**

| 欄位 | 值 |
|---|---|
| Type | Variable |
| Environments | All（預設） |
| Visibility | **Masked and hidden** |
| Flags | 勾選 **Protect variable** |
| Key | `GCP_SERVICE_ACCOUNT_KEY` |
| Value | 4.2 複製的 base64 字串 |

**base-image 另外要三個變數**（Visibility 選 Visible，勾 Protect variable）：

| Key | Value |
|---|---|
| `GCP_REGION` | 你的 `${REGION}`，例如 `asia-east1` |
| `GCP_PROJECT_ID` | 你的 `${PROJECT_ID}` |
| `AR_REPO` | `base-images` |

backend、frontend 的 GCP 專案和區域寫在各自的 `.gitlab-ci.yml` 裡，第 6、7 章會改。

## 4.4 Protected tag

變數設成 Protected 後，只有 protected 分支或 protected tag 的 pipeline 拿得到。

- **base-image**：CI 在推到 `main` 時跑。`main` 預設就是 protected branch，不用另外設
- **backend、frontend**：CI 在打 tag 時跑，所以要把 tag 設成 protected。Settings → Repository → Protected tags，新增兩個：

| Tag | Allowed to create |
|---|---|
| `R*` | Maintainers |
| `v*` | Maintainers |

沒設的話，打 tag 後 CI 的 `build` 會登入 GCP 失敗。

## 4.5 取得程式碼

從本專案的 repo 複製一份，改成推到你的 GitLab 專案：

```bash
source ~/laravel-side-project.env
cd ~/work/laravel-side-project

git clone git@gitlab.com:mark102555/base-image.git base-image
git clone git@gitlab.com:mark102555/laravel-side-project.git backend
git clone git@gitlab.com:mark102555/react-side-project.git frontend

git -C base-image remote set-url origin "git@gitlab.com:${GITLAB_NS}/base-image.git"
git -C backend remote set-url origin "git@gitlab.com:${GITLAB_NS}/laravel-side-project.git"
git -C frontend remote set-url origin "git@gitlab.com:${GITLAB_NS}/react-side-project.git"

# 刪掉複製下來的版本 tag：推到新專案時不要帶過去，版本號從頭開始
for d in base-image backend frontend; do git -C "$d" tag -l | xargs -r git -C "$d" tag -d > /dev/null; done
```

**不要用 `git push --tags` 或 `git push --mirror`**：每個 R、v tag 都會觸發一次 CI。

先不要推，第 5、6、7 章會各自改好設定再推。

## 4.6 程式碼裡和 GCP 專案有關的設定

換成自己的 GCP 專案後，這些地方要改。第 5 到 7 章會帶著改，這裡先列出來：

| repo | 檔案 | 要改的 | 在哪一章改 |
|---|---|---|---|
| base-image | 不用改檔案 | 專案、區域由 CI 變數決定（4.3） | |
| backend | `.gitlab-ci.yml` | `GCP_PROJECT_ID`、`GCP_REGION`、`IMAGE` | 6.2 |
| backend | `Dockerfile` | `ARG BASE_IMAGE=` 的專案和日期 tag | 6.2 |
| backend | `docker-compose.yml` | `program`、`migration` 的 `image` | 6.2 |
| backend | `README.md` | 說明裡的專案名稱、映像檔路徑（不影響執行） | 6.2 |
| frontend | `.gitlab-ci.yml` | `GCP_PROJECT_ID`、`GCP_REGION`、`IMAGE` | 7.2 |

Cloud Run 服務的名稱寫在 `.gitlab-ci.yml` 的 `deploy-dev`、`deploy-prod`：backend 是 `laravel-side-project-dev`、`laravel-side-project-prod`，frontend 是 `frontend-dev`、`frontend-prod`。照這份手冊建服務就不用改。
