# 正式環境映像檔：node 建置靜態檔，再交給非 root 的 nginx 提供
# 執行時不需要任何環境變數，API 走同源的 /api（由 Load Balancer 導到 backend）

# 建置階段：pnpm 的版本由 package.json 的 packageManager 決定，corepack 會自動下載
FROM node:24.21-alpine AS build
RUN corepack enable
WORKDIR /app

# 先只複製套件清單：lock 沒變就用快取，不必重新下載套件
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

# 再複製程式碼建置：tsc 型別檢查 + vite build，輸出在 dist/
COPY . .
RUN pnpm build

# 正式映像檔：nginx 以非 root 執行、聽 8080，只放建置好的檔案
FROM nginxinc/nginx-unprivileged:1.30-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
