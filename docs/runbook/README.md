# laravel-side-project 建置與操作手冊

這組文件寫給剛接手這個專案的人：從建立 GCP 專案開始，一步一步建出整套環境，再到日常開發、發版和維運。

整套環境包含：

- **base image**：PHP 8.4 + nginx + supervisord 的基底映像檔
- **backend**：Laravel 12 的 API
- **frontend**：React 的前端
- **GCP**：Cloud Run、Cloud SQL、Artifact Registry、Load Balancer
- **GitLab CI**：打 tag 建置映像檔，手動部署到 dev、prod

## 怎麼讀

**從零建置**：照編號順序讀、照順序做。第一次大約要半天，大部分時間在等 Cloud SQL 建立、CI 建置和 HTTPS 憑證生效。

**只在既有環境開發**：讀 1、2，再看 6.3（backend 本機開發）、7.3（frontend 本機開發）和 9（發版）。

## 文件

| # | 文件 | 內容 |
|---|---|---|
| 1 | [專案概觀](01-overview.md) | 架構、三個 repo、兩個環境、重要的設計、從零建置的流程 |
| 2 | [帳號與工具](02-tools.md) | 要準備的帳號、網域，安裝 Git、Docker、gcloud、Node |
| 3 | [GCP 專案與共用資源](03-gcp-project.md) | 建專案、連結帳單、啟用 API、Artifact Registry、service account、Cloud SQL |
| 4 | [GitLab 專案與 CI 設定](04-gitlab.md) | 建三個 GitLab 專案、CI 金鑰和變數、protected tag、取得程式碼 |
| 5 | [base image](05-base-image.md) | 內容、第一次建置、本機測試、每月重建、升級 |
| 6 | [backend](06-backend.md) | 改設定、本機開發、第一版映像檔、資料庫帳號、Cloud Run 服務和 migration Job |
| 7 | [frontend](07-frontend.md) | 改設定、本機開發、第一版映像檔、Cloud Run 服務 |
| 8 | [Load Balancer 與網域](08-load-balancer.md) | 靜態 IP、DNS、依路徑分流、HTTPS 憑證、只允許經由 LB 連線 |
| 9 | [發版與部署](09-release.md) | 第一次正式發版、tag 規則、確認結果、回滾、改設定 |
| 10 | [日常維運與排錯](10-operations.md) | log、連資料庫、在雲端執行指令、常見問題、資源用量、冷啟動 |
| 11 | [費用與拆除](11-cost-teardown.md) | 每月費用、預算提醒、拆除資源 |
| 12 | [附錄](12-appendix.md) | 名詞、本專案的資源清單、和 GCP 專案有關的設定、已知問題 |

## 寫法慣例

- 指令以 macOS 的 zsh 為準，Linux 大多相同
- 指令裡的 `${PROJECT_ID}` 這類變數，在第 3 章存成一個檔案，每開一個新的終端機先 `source` 一次
- 「本專案的值」指目前實際運作的環境，例如 GCP 專案 `laravel-side-project`、網域 `laravel-side-project.shuan1026.com`。從零建置時換成你自己的
