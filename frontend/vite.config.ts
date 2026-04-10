import { execSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import { resolveViteApiProxyTarget } from './src/config/resolveViteApiProxyTarget'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  const publicHost = (env.VITE_DEV_PUBLIC_HOST || process.env.VITE_DEV_PUBLIC_HOST || '').trim()

  const insideDocker = fs.existsSync('/.dockerenv')

  const getGit = (args: string) => {
    try {
      return execSync(`git ${args}`, {
        encoding: 'utf-8',
        stdio: ['ignore', 'pipe', 'ignore'],
      }).trim()
    } catch {
      return ''
    }
  }

  const appVersion = getGit('describe --tags --always') || 'dev'
  const gitCommit = getGit('rev-parse --short HEAD')
  const gitBranch = getGit('rev-parse --abbrev-ref HEAD')
  const buildTimeIso = new Date().toISOString()
  const deployEnv = mode

  const proxyTarget = resolveViteApiProxyTarget(insideDocker, env, process.env)

  const server: Record<string, unknown> = {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
    /** يقلّل احتمال بقاء صفحة /login القديمة في كاش المتصفح أثناء التطوير */
    ...(mode === 'development' ? { headers: { 'Cache-Control': 'no-store' } } : {}),
    proxy: {
      '/api': {
        target: proxyTarget,
        changeOrigin: true,
        secure: false,
      },
      '/storage': {
        target: proxyTarget,
        changeOrigin: true,
        secure: false,
      },
    },
  }

  if (publicHost !== '') {
    server.allowedHosts = [publicHost, '.ngrok-free.dev', 'localhost', '127.0.0.1', '.localhost']
    server.hmr = {
      host: publicHost,
      clientPort: 443,
      protocol: 'wss',
    }
  }

  return {
    appType: 'spa',
    plugins: [
      vue(),
      VitePWA({
        registerType: 'autoUpdate',
        includeAssets: ['pwa-192.png', 'pwa-512.png'],
        devOptions: {
          enabled: false,
        },
        workbox: {
          globPatterns: ['**/*.{js,css,html,ico,png,svg,webp,woff2,webmanifest}'],
          navigateFallback: '/index.html',
          navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//],
          runtimeCaching: [
            {
              urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
              handler: 'CacheFirst',
              options: {
                cacheName: 'google-fonts-stylesheets',
                expiration: {
                  maxEntries: 16,
                  maxAgeSeconds: 60 * 60 * 24 * 365,
                },
              },
            },
            {
              urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
              handler: 'CacheFirst',
              options: {
                cacheName: 'google-fonts-webfonts',
                expiration: {
                  maxEntries: 16,
                  maxAgeSeconds: 60 * 60 * 24 * 365,
                },
              },
            },
            {
              urlPattern: ({ url }) =>
                url.pathname.startsWith('/api') || url.pathname.startsWith('/sanctum'),
              handler: 'NetworkOnly',
            },
          ],
        },
        manifest: {
          name: 'أسس برو',
          short_name: 'أسس برو',
          description: 'منصة تشغيل أعمالك بذكاء',
          theme_color: '#4f46e5',
          background_color: '#f8fafc',
          display: 'standalone',
          lang: 'ar',
          dir: 'rtl',
          scope: '/',
          start_url: '/',
          icons: [
            {
              src: '/pwa-192.png',
              sizes: '192x192',
              type: 'image/png',
              purpose: 'any',
            },
            {
              src: '/pwa-512.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'any',
            },
          ],
        },
      }),
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src'),
      },
    },
    server,
    preview: {
      host: '0.0.0.0',
      port: 4173,
      strictPort: true,
      /** مطابقة dev: اختبارات Playwright على `vite preview` تحتاج بروكسي API */
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
          secure: false,
        },
        '/storage': {
          target: proxyTarget,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    define: {
      __APP_VERSION__: JSON.stringify(appVersion),
      __APP_BUILD_TIME__: JSON.stringify(buildTimeIso),
      __GIT_COMMIT__: JSON.stringify(gitCommit),
      __GIT_BRANCH__: JSON.stringify(gitBranch),
      __DEPLOY_ENV__: JSON.stringify(deployEnv),
    },
    build: {
      outDir: 'dist',
      rollupOptions: {
        output: {
          manualChunks: {
            vendor: ['vue', 'vue-router', 'pinia'],
            axios: ['axios'],
          },
        },
      },
    },
  }
})
