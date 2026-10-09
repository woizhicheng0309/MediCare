# 2. 帳號與工具

以 macOS 為例，Linux 的做法類似。

## 2.1 帳號和網域

| 項目 | 要做的事 |
|---|---|
| Google 帳號 | 登入 https://console.cloud.google.com ，到 Billing 確認有一個狀態是 Active 的帳單帳戶。第一次用 GCP 會要求綁信用卡 |
| gitlab.com | 註冊帳號。記下你的帳號名稱（或要放 repo 的群組名稱），第 4 章會用到 |
| 網域 | 準備一個你能管理 DNS 的網域，決定 prod、dev 各用哪個主機名稱，例如 `app.example.com`、`dev.app.example.com`。本專案用 `laravel-side-project.shuan1026.com`、`dev.laravel-side-project.shuan1026.com`，DNS 在 GoDaddy |

## 2.2 Git

macOS 第一次在終端機打 `git` 會提示安裝 Xcode Command Line Tools，照著裝就有 git。接著設定身分：

```bash
git config --global user.name "你的名字"
git config --global user.email "你的 email"
```

## 2.3 gitlab.com 的 SSH 金鑰

```bash
ssh-keygen -t ed25519 -C "你的 email" -f ~/.ssh/id_ed25519_gitlab_com
cat ~/.ssh/id_ed25519_gitlab_com.pub   # 複製這一行
```

1. 到 gitlab.com 右上角的頭像 → Edit profile → SSH Keys → Add new key，貼上剛剛複製的公鑰
2. 在 `~/.ssh/config` 加上：

   ```
   Host gitlab.com
       HostName gitlab.com
       User git
       IdentityFile ~/.ssh/id_ed25519_gitlab_com
       IdentitiesOnly yes
   ```

3. 測試：`ssh -T git@gitlab.com`，看到 `Welcome to GitLab, @你的帳號!` 就成功了

## 2.4 Docker Desktop

從 https://www.docker.com/products/docker-desktop/ 下載安裝，打開後讓它在背景執行。

- 檢查：`docker version`，Client 和 Server 都有版本號就好
- Apple Silicon（M 系列）也能建置、執行 amd64 的映像檔，靠模擬，會比較慢。Cloud Run 只跑 amd64，所以在 Mac 上手動建要推上去的映像檔時，一定要加 `--platform linux/amd64`

## 2.5 Google Cloud CLI（gcloud）

照官方說明安裝：https://cloud.google.com/sdk/docs/install 。裝好後登入：

```bash
gcloud init          # 登入 Google 帳號。這時還沒有專案，選專案的步驟可以先跳過
gcloud auth list     # 確認登入的帳號
```

第 3 章會建專案並設成預設專案。

## 2.6 Node 24 和 pnpm（frontend 用）

```bash
brew install node@24
echo 'export PATH="$(brew --prefix node@24)/bin:${PATH}"' >> ~/.zshrc
source ~/.zshrc
node -v              # v24.x
corepack enable      # 啟用 pnpm。pnpm 的版本寫在 frontend 的 package.json，corepack 會自動下載
```

沒有 Homebrew 的話，先照 https://brew.sh 安裝；也可以用 nvm，在 frontend 目錄執行 `nvm install`，會照 `.nvmrc` 裝 Node 24。

## 2.7 其他工具

macOS 內建，不用另外裝：`curl`、`openssl`、`base64`、`dig`、`pbcopy`。

連 Cloud SQL 時才需要 MySQL 的指令列工具（第 10 章）：

```bash
brew install mysql-client
```

## 2.8 不需要安裝的

PHP、Composer、MySQL 都在容器裡跑，本機不用安裝。

## 2.9 先知道：zsh 的三個陷阱

macOS 預設的 shell 是 zsh，和 bash 有幾個不同，這份手冊的指令都已經避開：

| 陷阱 | 例子 | 怎麼避開 |
|---|---|---|
| 變數後面的 `:l`、`:h`、`:t`、`:r` 會被當成修飾字 | `"$IMAGE:local-1"` 變成 `.../backendocal-1`（`:l` 被當成轉小寫） | 變數一律寫成 `"${IMAGE}:local-1"` |
| `path` 是特殊變數，和 `PATH` 連動 | `for path in / /api; do ...` 會把 `PATH` 蓋掉，之後的指令都找不到 | 迴圈變數不要叫 `path` |
| 變數裡的字串不會自動拆成多個參數 | `CMD="docker run --rm"; $CMD ...` 會被當成一個叫 `docker run --rm` 的指令 | 寫成函式，或改用 `sh` 執行腳本 |

## 2.10 工作目錄

建議把三個 repo 放在同一個資料夾：

```
~/work/laravel-side-project/
├── base-image/
├── backend/
└── frontend/
```

```bash
mkdir -p ~/work/laravel-side-project
```
