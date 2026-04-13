/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      /** أحجام إضافية مركزية — تفضيلها على text-[10px] / text-[11px] في الواجهة */
      fontSize: {
        /** 10px — نصوص ثانوية جداً (شارات، تلميحات كثيفة) */
        micro: ['0.625rem', { lineHeight: '0.875rem' }],
        /** 11px — أزرار/وسوم مدمجة */
        '2xs': ['0.6875rem', { lineHeight: '1rem' }],
      },
      fontFamily: {
        /** Keep aligned with `--font-sans` in `src/assets/main.css` */
        sans: ['Tajawal', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      colors: {
        /** لوحة ألوان هادئة: تركواز/زمرد مع تباين جيد للوضع الليلي */
        primary: {
          50:  '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
        },
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-out',
        'slide-up': 'slideUp 0.4s ease-out',
      },
      keyframes: {
        fadeIn:  { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
        slideUp: { '0%': { opacity: '0', transform: 'translateY(12px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
      },
    },
  },
  plugins: [],
}
