# Tienda en línea: se compila con Node y la sirve nginx, que reenvía /api/tienda/ al backend PHP
# (mismo origen para el navegador: sin CORS y con la cookie de sesión de la tienda)

FROM node:22-bookworm-slim AS compilacion
WORKDIR /app
COPY package.json package-lock.json ./
# Sin scripts de instalación: la tienda no necesita el binario de Electron
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.tienda.config.mjs ./
COPY src/tienda src/tienda
COPY src/compartido src/compartido
RUN npx vite build --config vite.tienda.config.mjs

FROM nginx:1.27-alpine
COPY docker/web/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=compilacion /app/dist-tienda /usr/share/nginx/html
