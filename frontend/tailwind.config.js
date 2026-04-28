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
        /** هوية أساسية: بنفسجي هادئ (ميل إلى البنفسج القمحي) — تباين جيد مع الوضع الليلي */
        primary: {
          50:  '#faf8ff',
          100: '#f3edff',
          200: '#e9e1fc',
          300: '#d4c5f9',
          400: '#c4b5fd',
          500: '#a78bfa',
          600: '#8b5cf6',
          700: '#7c3aed',
          800: '#6d28d9',
          900: '#5b21b6',
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
