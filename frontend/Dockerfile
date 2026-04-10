FROM node:20-alpine AS development

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY docker-entrypoint-dev.sh /docker-entrypoint-dev.sh
RUN sed -i 's/\r$//' /docker-entrypoint-dev.sh 2>/dev/null || true
RUN chmod +x /docker-entrypoint-dev.sh

COPY . .

EXPOSE 5173

ENTRYPOINT ["/docker-entrypoint-dev.sh"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

FROM node:20-alpine AS builder

RUN apk add --no-cache git

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .

ARG VITE_DEPLOY_ENV=production
ARG VITE_APP_VERSION=
ARG VITE_BUILD_GIT_COMMIT=
ARG VITE_BUILD_GIT_BRANCH=
ARG VITE_BUILD_TIME=

ENV VITE_DEPLOY_ENV=$VITE_DEPLOY_ENV
ENV VITE_APP_VERSION=$VITE_APP_VERSION
ENV VITE_BUILD_GIT_COMMIT=$VITE_BUILD_GIT_COMMIT
ENV VITE_BUILD_GIT_BRANCH=$VITE_BUILD_GIT_BRANCH
ENV VITE_BUILD_TIME=$VITE_BUILD_TIME

RUN npm run build

FROM nginx:1.25-alpine AS production

COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
