# MediCare Health Group

## Active learning requirements (updated 2026-10-08)

- The user is building the application personally, step by step, starting with PHP and Laravel fundamentals. Provide explanations and small implementation steps; do not implement features on the user's behalf unless explicitly requested.
- The active learning application is in `backend`, using the `medicare_learning` MySQL database. The earlier agent-built MVP was retained separately as a reference; the older MVP instructions below describe that reference, not the current learning application's completed features.
- Follow MVC: Models handle data and reusable domain rules, Controllers coordinate requests, and Blade Views present data. Routes map requests to Controllers.
- Use English for project content, names, UI text, and examples.
- Enter and display TWD amounts as whole dollars; the current visits schema stores amounts in integer cents.
- Planned requirement: add **JWT authentication** in a later learning step. It has not yet been implemented in the learning application. Introduce it with explanations and user-written code while preserving MVC and the Employee/HR authorization requirements.
- User-provided reference: [Implementing JWT authentication in Laravel 11](https://blog.logrocket.com/implementing-jwt-authentication-laravel-11/). The current application uses Laravel 13; verify package compatibility and current primary documentation before selecting a JWT package or adapting the tutorial. The reference is not a request to downgrade Laravel or install dependencies now.
- Background clarification (2026-10-08): the user's ASP Medical customer service role was specifically in the Helpdesk department. Duties included answering client-company employees' questions, approving claims, preparing guarantee letters, and checking panel clinics/hospitals.
- Organization clarification: Helpdesk staff belong to ASP Medical, the Third Party Administrator, rather than to a client company's HR department. The TPA manages medical benefits for client companies and coordinates between those companies and hospitals/clinics. In this fictional project, MediCare Health Group is the TPA operator; client companies are its customers. Model Helpdesk as an internal TPA role with authorized client-service access, not as a client-company HR account.
- Add **Helpdesk** as an application user role alongside Employee and HR. Keep MVP delivery first; more complex workflows can be developed incrementally. Helpdesk duties are business context, not authorization to implement all of them immediately.
- Proposed minimal Helpdesk scope: employee benefit/visit lookup and approval/rejection of the current visit submissions. Detailed claims workflows, guarantee-letter generation, and panel-network administration remain later work. This proposal is not yet an implemented feature or a confirmed full scope.
- Open authorization question: whether Helpdesk can serve all client companies or only assigned companies. Do not infer this access from the Helpdesk role name. Employee access stays limited to self; HR access stays limited to the user's own company.
- HR business context (2026-10-08): based on the user's Helpdesk experience, client-company HR primarily needs employees' medical leave records and company-level medical benefit reporting: overall spending, most frequently visited clinics, common diseases, and frequently purchased medication categories. A dedicated HR dashboard is later work, after the MVP.
- Treat the proposed disease and medication rankings as company-level aggregate reporting; the earlier restriction against HR viewing individual visit/medication details remains in place unless the user explicitly changes it. Medical leave record fields and visibility still need definition before implementation. The current learning schema does not yet include medical leave, diagnosis, or medication data; do not invent these fields or metrics as completed functionality. Preserve the current company-only HR benefit-assignment scope for the MVP.

PHP 後端實習作品：以企業醫療福利第三方管理（TPA）情境，實作登入、員工本人查詢與 HR 公司福利指派。

## 本機展示

目前使用 Laravel 13、PHP 8.5、MySQL；網站位於 `backend`，頁面使用 Blade 與原生 CSS，不需 Node 建置即可展示。

```sh
cd backend
composer install
# 初次安裝才需要：cp .env.example .env && php artisan key:generate
# 在 MySQL 建立 medicare_mvp，並設定 .env 的 DB_* 值
php artisan migrate
php artisan db:seed
php artisan serve
```

開啟 http://127.0.0.1:8000/login 。目前 Mac 的 MySQL 已建立 `medicare_mvp` 與 `medicare_mvp_test`；本機設定使用既有 root 無密碼帳號，僅作本機開發。

| 角色 | Acme 帳戶 | Nova 帳戶 |
|---|---|---|
| Employee | employee@acme.test | employee@nova.test |
| HR | hr@acme.test | hr@nova.test |
| 同公司另一位員工 | colleague@acme.test | colleague@nova.test |
| 尚未指派方案 | unassigned@acme.test | unassigned@nova.test |

共用示範密碼：`MedicareDemo!2026`。全部都是虛構資料。DemoSeeder 僅允許 local/testing；重跑不會新增重複資料，也不會覆蓋 HR 的既有方案指派，但會更新示範方案與就診內容。

## 已完成的功能

- 登入／登出：session 認證、登入後更換 session ID、CSRF 防護、每 IP 每分鐘最多 5 次登入請求。
- Employee：本人年度福利、coverage、就診診所、用藥、核銷狀態與餘額；查詢不接受前端指定其他員工身分。
- HR：本公司員工、方案清單、當年度方案指派；不提供就診／用藥查詢。
- 公司隔離：HR 的員工與方案查詢皆限制 company_id，資料庫也用複合外鍵拒絕跨公司指派。
- 金額以整數 cents 儲存、以 TWD 顯示，避免浮點誤差；私人頁面使用 private/no-store 快取標頭。

福利規則已由使用者於 2026-10-07 確認：每人每曆年一個方案；僅當年 approved 的 covered amount 扣額；換方案保留當年使用額；HR 不看就診與用藥。年度使用 Asia/Taipei。歷史就診仍可查閱，前一年度方案不受今年換方案影響。

餘額 = max(年度額度 − 當年已核銷 covered amount, 0)。若換到較低額度而造成超額，會顯示超額提示，不會抹除原有使用紀錄。年度不會自動續約，新年度需重新指派。

## 五分鐘展示流程

1. 用 Employee 登入，確認診所、藥物與三筆就診紀錄，其中待核銷 TWD 420 不扣額。基本方案額度 12,000、已使用 2,100.50、餘額 9,899.50。
2. 登出，改用 Acme HR 登入，把工程師改成主管方案並儲存。
3. 改用 Employee 登入，確認額度 24,000、已使用仍為 2,100.50、餘額變為 21,899.50。
4. 改用 Nova HR 登入，確認只能看到 Nova 員工與方案；Acme HR 沒有臨床明細。
5. 執行測試展示同公司他人、跨公司員工／方案、偽造公司與年度參數的權限阻擋。

本次瀏覽器驗證已將 `employee@acme.test` 改為主管方案，故目前頁面顯示 21,899.50 餘額；可用 HR 改回基本方案再重播流程。

## 驗證

```sh
cd backend
php artisan test --compact
# MySQL 測試只使用 medicare_mvp_test，需事先建立空的專用測試 DB
vendor/bin/phpunit --configuration=phpunit.mysql.xml
vendor/bin/pint --dirty --format agent
```

MySQL 測試設定的 DB_DATABASE 強制為 medicare_mvp_test，會重建該測試資料庫。SQLite 測試使用記憶體資料庫。涵蓋登入、登出、CSRF、節流、角色、本人與公司隔離、複合外鍵、年度邊界、待核銷排除、方案更新、空狀態與重複 seeding。

## 從實作理解 Laravel

先依這條實際流程閱讀：`backend/routes/web.php` 定義網址 → `SessionController` 登入 → `EmployeeController` 依 session 身分查 DB → `resources/views/medicare/employee.blade.php` 顯示資料。

HR 儲存則是 `AssignBenefitRequest` 驗證身分與公司方案 → `HrController::assign` 鎖定本公司 Employee → 更新年度指派。SQL 的 `WHERE company_id`、`WHERE user_id` 與資料庫外鍵，是權限隔離的核心，不依賴畫面隱藏按鈕。

Laravel migration 是可追蹤的資料表變更；Seeder 建立示範資料；Feature test 模擬 HTTP 請求驗證結果。登入實作使用 [Laravel 官方 session authentication](https://laravel.com/framework/docs/authentication)，未引入另一套前端或 token 認證系統。

## 開發者背景與來源

已讀取目前可存取的 ChatGPT 與 Codex 相關對話，並非完整帳號歷史。

- Codex「整理實習專案背景」：使用者直接說明，微程式未開 DevOps 實習，改準備 PHP 後端實習；MediCare 是本次作品。使用者有兩年多 ASP Medical Health Group 客服經驗，熟悉 TPA、panel clinic、企業不同職位的福利等級與 coverage。
- 同一對話與本次指示：無 PHP／Laravel 經驗，使用 Mac、Homebrew；有投履歷時間壓力，要求今天完成 MVP、從實作學習，不安排獨立練習。
- ChatGPT「討論個人作品方向」：曾以 PulseOps 為 DevOps 作品，擔心不能獨力完成 server 與頁面，也在意 AI 使用額度；因此協作應提供可理解的實作、取捨與驗證證據。
- ChatGPT「整理Debug經歷」：舊助理摘要列出 AWS、RDS／IAM、WSL2／Minikube、網路與 CI/CD 排錯案例。尚未回查原始 log，不把這些摘要當成已驗證成果或自動寫入履歷。
- ChatGPT「CPE 題單揭曉」：使用者提到幫學生預測題目，可確認有與學生交流 CPE 的情境；不據此推定正式職稱或程式熟練程度。

## MVP 範圍

本次包含可操作的本機網站、MySQL 與權限測試。就診使用假資料；尚未實作診所輸入／核銷工作流、員工新增、方案新增編輯、自動年度續約、正式病歷串接或公開部署。coverage 目前為說明文字，沒有自動裁定醫療給付的規則引擎。
