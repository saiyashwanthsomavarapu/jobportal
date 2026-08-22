<style type="text/tailwindcss">
  @theme {
    --color-bg: #f4f5f8;
    --color-surface: #ffffff;
    --color-card2: #f8f9fc;
    --color-line: #e7e9f0;
    --color-line2: #d8dbe6;
    --color-accent: #1A4C8F;
    --color-accent-dark: #123863;
    --color-accent-soft: #eaf1fa;
    --color-ink: #111827;
    --color-ink2: #5b6072;
    --color-muted: #9aa0b4;
    --color-danger: #dc3545;
    --color-warn: #d97706;
    --color-success: #0f9d63;

    --font-head: "Syne", sans-serif;
    --font-sans: "DM Sans", sans-serif;

    --shadow-card: 0 1px 2px rgba(17, 24, 39, .04), 0 8px 24px -12px rgba(17, 24, 39, .08);
    --shadow-pop: 0 4px 14px rgba(26, 76, 143, .22);
  }

  :root:has(input.theme-controller[value=accelon]:checked),
  [data-theme="accelon"] {
    color-scheme: light;

    --color-base-100: #ffffff;
    --color-base-200: #f8f9fc;
    --color-base-300: #e7e9f0;
    --color-base-content: #111827;

    --color-primary: #1A4C8F;
    --color-primary-content: #ffffff;
    --color-secondary: #3d6ba8;
    --color-secondary-content: #ffffff;
    --color-accent: #1A4C8F;
    --color-accent-content: #ffffff;
    --color-neutral: #111827;
    --color-neutral-content: #ffffff;

    --color-info: #2563eb;
    --color-info-content: #ffffff;
    --color-success: #0f9d63;
    --color-success-content: #ffffff;
    --color-warning: #d97706;
    --color-warning-content: #ffffff;
    --color-error: #dc3545;
    --color-error-content: #ffffff;

    --radius-selector: 1rem;
    --radius-field: 0.5rem;
    --radius-box: 1rem;
    --size-selector: 0.25rem;
    --size-field: 0.25rem;
    --border: 1px;
    --depth: 0;
    --noise: 0;
  }

  body {
    font-size: 14px;
    -webkit-font-smoothing: antialiased;
  }

  @media (max-width: 767.98px) {
    #sidebar.is-open {
      translate: 0 0 !important;
      transform: translateX(0) !important;
    }

    #sidebarOverlay.is-open {
      display: block !important;
    }
  }

  ::-webkit-scrollbar {
    width: 6px;
    height: 6px;
  }

  ::-webkit-scrollbar-track {
    background: transparent;
  }

  ::-webkit-scrollbar-thumb {
    background: #d8dbe6;
    border-radius: 10px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: #9aa0b4;
  }

  .ql-toolbar {
    background: #f8f9fc !important;
    border: 1px solid #e7e9f0 !important;
    border-radius: 10px 10px 0 0 !important;
  }

  .ql-container {
    background: #ffffff !important;
    border: 1px solid #e7e9f0 !important;
    border-top: none !important;
    border-radius: 0 0 10px 10px !important;
    color: #111827 !important;
    font-family: "DM Sans", sans-serif !important;
    font-size: 13.5px !important;
    min-height: 130px;
  }

  .ql-toolbar .ql-stroke {
    stroke: #5b6072 !important;
  }

  .ql-toolbar .ql-fill {
    fill: #5b6072 !important;
  }

  .ql-toolbar button:hover .ql-stroke,
  .ql-toolbar button.ql-active .ql-stroke {
    stroke: #1A4C8F !important;
  }

  .ql-toolbar button:hover .ql-fill,
  .ql-toolbar button.ql-active .ql-fill {
    fill: #1A4C8F !important;
  }

  .ql-toolbar .ql-picker-label {
    color: #5b6072 !important;
  }

  .ql-editor.ql-blank::before {
    color: #9aa0b4 !important;
    font-style: normal !important;
  }

  .ql-editor {
    color: #111827 !important;
    min-height: 130px;
  }

  .ql-picker-options {
    background: #ffffff !important;
    border-color: #e7e9f0 !important;
  }

  .ql-picker-item {
    color: #5b6072 !important;
  }

  .ql-hidden-ta {
    display: none;
  }
</style>
