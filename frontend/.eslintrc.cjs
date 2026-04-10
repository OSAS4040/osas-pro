/**
 * Vue 3 + TypeScript + Vite — pragmatic defaults (does not block delivery on style-only noise).
 * @type {import('eslint').Linter.Config}
 */
module.exports = {
  root: true,
  env: {
    browser: true,
    es2022: true,
    node: true,
  },
  extends: [
    'eslint:recommended',
    'plugin:vue/vue3-recommended',
    'plugin:@typescript-eslint/recommended',
  ],
  parser: 'vue-eslint-parser',
  parserOptions: {
    ecmaVersion: 'latest',
    sourceType: 'module',
    parser: '@typescript-eslint/parser',
    extraFileExtensions: ['.vue'],
  },
  plugins: ['@typescript-eslint'],
  rules: {
    // TypeScript resolves symbols; eslint no-undef flags DOM/lib types in .vue incorrectly
    'no-undef': 'off',
    // Intentional fire-and-forget API calls use empty catch
    'no-empty': ['error', { allowEmptyCatch: true }],
    'no-unused-vars': 'off',
    '@typescript-eslint/no-unused-vars': [
      'warn',
      {
        argsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
        caughtErrorsIgnorePattern: '^_',
      },
    ],
    '@typescript-eslint/no-explicit-any': 'off',
    '@typescript-eslint/ban-ts-comment': 'off',
    '@typescript-eslint/no-require-imports': 'off',
    '@typescript-eslint/consistent-type-imports': 'off',

    'vue/multi-word-component-names': 'off',
    'vue/require-default-prop': 'off',
    'vue/html-self-closing': 'off',
    'vue/max-attributes-per-line': 'off',
    'vue/singleline-html-element-content-newline': 'off',
    'vue/first-attribute-linebreak': 'off',
    'vue/no-v-html': 'warn',

    'no-console': ['warn', { allow: ['warn', 'error', 'info'] }],
    'no-debugger': 'warn',
  },
  ignorePatterns: [
    'dist',
    'node_modules',
    'coverage',
    '*.min.js',
  ],
}
