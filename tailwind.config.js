import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';

const brandBlue = {
  50: '#E8F3FF',
  100: '#D6E8FF',
  200: '#B5D4FF',
  300: '#88B7FF',
  400: '#5A8EFF',
  500: '#3466FF',
  600: '#123AFF',
  700: '#082CFA',
  800: '#0A29C9',
  900: '#102685',
  950: '#0B185B',
};

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      colors: {
        lightBlue: colors.sky,
        blue: brandBlue,
        brand: brandBlue,
        primary: brandBlue,
        blueGray: {
          50: '#F8FAFC',
          100: '#F1F5F9',
          200: '#E2E8F0',
          300: '#CBD5E1',
          400: '#94A3B8',
          500: '#64748B',
          600: '#475569',
          700: '#334155',
          800: '#1E293B',
          900: '#0F172A',
        },
      },
    },
  },
  plugins: [],
};
