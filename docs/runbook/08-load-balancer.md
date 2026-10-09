# 8. Load Balancer 與網域

建一個 GCP 的外部 HTTPS Load Balancer（LB），兩個網域共用：依網域分 dev、prod，再依路徑分 backend、frontend。做完後 Cloud Run 改成只允許經由 LB 連線。

需要的時間：指令幾分鐘就跑完，但 HTTPS 憑證要等 DNS 生效後 15 到 60 分鐘才發下來，之後可能再 20 分鐘才全部通。

## 8.1 會建的資源

名稱都以 `laravel-side-project` 開頭，或用 Cloud Run 服務名稱加後綴：

| 資源 | 名稱 | 用途 |
|---|---|---|
| 靜態 IP | `laravel-side-project-lb-ip` | LB 的固定 IP，DNS 指向它 |
| serverless NEG | `<服務名>-neg`，4 個 | 把 LB 的流量送到 Cloud Run 服務 |
| backend service | `<服務名>-backend`，4 個 | LB 的後端，一個接一個 NEG |
| URL map | `laravel-side-project-lb` | 依網域、路徑決定送到哪個 backend service |
| 代管憑證 | `laravel-side-project-prod-cert`、`laravel-side-project-dev-cert` | HTTPS 憑證，Google 自動申請、續期 |
| HTTPS proxy、轉送規則 | `laravel-side-project-https-proxy`、`laravel-side-project-https`（443） | 接 HTTPS |
| HTTP 的 URL map、proxy、轉送規則 | `laravel-side-project-http-redirect`、`-http-proxy`、`-http`（80） | HTTP 一律 301 轉到 HTTPS |

路由規則：

| 網域 | 路徑 | 送到 |
|---|---|---|
| `${PROD_HOST}` | `/api`、`/api/*`、`/docs`、`/docs/*`、`/up` | backend：`laravel-side-project-prod` |
| `${PROD_HOST}` | 其他 | frontend：`frontend-prod` |
| `${DEV_HOST}` | `/api`、`/api/*`、`/docs`、`/docs/*`、`/up` | backend：`laravel-side-project-dev` |
| `${DEV_HOST}` | 其他 | frontend：`frontend-dev` |
| 其他（例如直接用 IP 連） | 全部 | frontend：`frontend-prod` |

`/up` 也要導到 backend：不然經網域打 `/up` 會被前端回 200（前端找不到的路徑都回首頁），健康檢查就會誤判。

## 8.2 靜態 IP、DNS

```bash
source ~/laravel-side-project.env
NAME=laravel-side-project

gcloud compute addresses create "${NAME}-lb-ip" --global --ip-version IPV4
gcloud compute addresses describe "${NAME}-lb-ip" --global --format='value(address)'   # 記下這個 IP
```

到網域的 DNS 管理介面（本專案是 GoDaddy）新增兩筆 A 紀錄，都指向上面的 IP：

| 類型 | 名稱 | 值 |
|---|---|---|
| A | `${PROD_HOST}` 去掉網域的部分，例如 `app` | LB 的 IP |
| A | `${DEV_HOST}` 去掉網域的部分，例如 `dev.app` | LB 的 IP |

確認生效（可能要幾分鐘到一小時）：

```bash
dig +short "${PROD_HOST}"    # LB 的 IP
dig +short "${DEV_HOST}"     # LB 的 IP
```

## 8.3 NEG 和 backend service

四個 Cloud Run 服務各一組：

```bash
for svc in laravel-side-project-dev laravel-side-project-prod frontend-dev frontend-prod; do
  gcloud compute network-endpoint-groups create "${svc}-neg" --region "${REGION}" \
    --network-endpoint-type serverless --cloud-run-service "${svc}"
  gcloud compute backend-services create "${svc}-backend" --global --load-balancing-scheme EXTERNAL_MANAGED
  gcloud compute backend-services add-backend "${svc}-backend" --global \
    --network-endpoint-group "${svc}-neg" --network-endpoint-group-region "${REGION}"
done
```

## 8.4 URL map

```bash
gcloud compute url-maps create "${NAME}-lb" --global --default-service frontend-prod-backend

for env in prod dev; do
  if [ "${env}" = prod ]; then host="${PROD_HOST}"; else host="${DEV_HOST}"; fi
  be="${NAME}-${env}-backend"
  gcloud compute url-maps add-path-matcher "${NAME}-lb" --global --path-matcher-name "${env}" \
    --new-hosts "${host}" --default-service "frontend-${env}-backend" \
    --path-rules "/api=${be},/api/*=${be},/docs=${be},/docs/*=${be},/up=${be}"
done
```

## 8.5 憑證和 HTTPS

```bash
# Google 代管憑證，每個網域一張
gcloud compute ssl-certificates create "${NAME}-prod-cert" --global --domains "${PROD_HOST}"
gcloud compute ssl-certificates create "${NAME}-dev-cert" --global --domains "${DEV_HOST}"

# HTTPS proxy + 443 轉送規則
gcloud compute target-https-proxies create "${NAME}-https-proxy" --global --url-map "${NAME}-lb" \
  --ssl-certificates "${NAME}-prod-cert,${NAME}-dev-cert"
gcloud compute forwarding-rules create "${NAME}-https" --global --load-balancing-scheme EXTERNAL_MANAGED \
  --network-tier PREMIUM --address "${NAME}-lb-ip" --target-https-proxy "${NAME}-https-proxy" --ports 443
```

## 8.6 HTTP 轉 HTTPS

```bash
cat > http-redirect.yaml <<EOF
name: ${NAME}-http-redirect
defaultUrlRedirect:
  redirectResponseCode: MOVED_PERMANENTLY_DEFAULT
  httpsRedirect: true
EOF
gcloud compute url-maps import "${NAME}-http-redirect" --global --source http-redirect.yaml --quiet
rm http-redirect.yaml

gcloud compute target-http-proxies create "${NAME}-http-proxy" --global --url-map "${NAME}-http-redirect"
gcloud compute forwarding-rules create "${NAME}-http" --global --load-balancing-scheme EXTERNAL_MANAGED \
  --network-tier PREMIUM --address "${NAME}-lb-ip" --target-http-proxy "${NAME}-http-proxy" --ports 80
```

HTTP 轉 HTTPS 不用等憑證，幾分鐘後就生效：`curl -I "http://${PROD_HOST}/"` 回 301。

## 8.7 等憑證生效

```bash
for env in prod dev; do
  gcloud compute ssl-certificates describe "${NAME}-${env}-cert" --global \
    --format='value(name,managed.status,managed.domainStatus)'
done
```

- `PROVISIONING` 變成 `ACTIVE`，通常 15 到 60 分鐘，前提是 DNS 已經指到 LB 的 IP
- `FAILED_NOT_VISIBLE`：DNS 還沒指過來，或指錯 IP。確認 8.2 的 `dig`
- 變成 `ACTIVE` 後，HTTPS 可能還要再等約 20 分鐘才全部通。這段期間 `curl` 會看到 `SSL_ERROR_SYSCALL`，屬正常

## 8.8 驗證分流

```bash
for host in "${DEV_HOST}" "${PROD_HOST}"; do
  echo "== ${host}"
  for p in / /settings /api/projects /api/doc /docs /up; do
    printf '%-14s %s | %s\n' "${p}" \
      "$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "https://${host}${p}")" \
      "$(curl -s "https://${host}${p}" | head -c 2000 | grep -o '<title>[^<]*</title>' | head -1)"
  done
  curl -s -o /dev/null -w 'http→https %{http_code}\n' "http://${host}/"
done
```

| 路徑 | 預期 | 誰回應 |
|---|---|---|
| `/`、`/settings` | 200 `text/html`，`<title>frontend</title>` | frontend |
| `/api/projects` | 200 `application/json` | backend |
| `/api/doc` | 200 `text/html`，`<title>backend</title>` | backend（Swagger UI） |
| `/docs` | 200 `application/json` | backend（Swagger 文件） |
| `/up` | 200 `text/html`，`<title>Laravel</title>` | backend |
| `http://...` | 301 | LB |

再用瀏覽器打開兩個網域：登入（`admin0001`）、到 Settings 新增一個專案、回首頁看圖表、登出。

## 8.9 只允許經由 LB 連線

網域都正常後，把四個服務的 ingress 改成只允許 LB：

```bash
for svc in laravel-side-project-dev laravel-side-project-prod frontend-dev frontend-prod; do
  gcloud run services update "${svc}" --region "${REGION}" --ingress internal-and-cloud-load-balancing
done

# 直接連 run.app 會回 404，網域照常
curl -s -o /dev/null -w '%{http_code}\n' "https://frontend-dev-${PROJECT_NUMBER}.${REGION}.run.app/"               # 404
curl -s -o /dev/null -w '%{http_code}\n' "https://laravel-side-project-dev-${PROJECT_NUMBER}.${REGION}.run.app/up"  # 404
curl -s -o /dev/null -w '%{http_code}\n' "https://${DEV_HOST}/up"                                                   # 200
```

ingress 是服務層級的設定，改了不會產生新的 revision，CI 部署新版時也會保留。

## 8.10 之後要改路由

改 URL map 用「匯出、改、匯入」，先留一份備份：

```bash
gcloud compute url-maps export "${NAME}-lb" --global --destination url-map-backup.yaml
cp url-map-backup.yaml url-map.yaml
# 用編輯器改 url-map.yaml，例如在 pathRules 的 paths 加一個路徑
gcloud compute url-maps import "${NAME}-lb" --global --source url-map.yaml
```

- 新設定要 2 到 3 分鐘才在所有節點生效。這段時間有些請求還會走舊設定，例如 `/settings` 偶爾回 backend 的 404
- 建議先改 dev 的 path matcher，驗證過再改 prod
- 出問題時匯入備份：`gcloud compute url-maps import "${NAME}-lb" --global --source url-map-backup.yaml`
- 改 URL map 不會動到 IP、憑證和轉送規則
