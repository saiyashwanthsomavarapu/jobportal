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

    --font-head: "Manrope", sans-serif;
    --font-sans: "DM Sans", sans-serif;
    --text-caption: 11px;
    --text-meta: 12px;
    --text-body: 14px;
    --text-section: 16px;

    --shadow-card: 0 1px 2px rgba(17, 24, 39, .04), 0 8px 24px -12px rgba(17, 24, 39, .08);
    --shadow-pop: 0 4px 14px rgba(26, 76, 143, .22);
  }

  :root,
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

    /* daisyUI 4 (legacy) semantic vars — locked to the light Accelon palette, no dark fallback */
    --p: #1A4C8F;  --pc: #ffffff;
    --s: #3d6ba8;  --sc: #ffffff;
    --a: #1A4C8F;  --ac: #ffffff;
    --n: #111827;  --nc: #ffffff;
    --b1: #ffffff; --b2: #f8f9fc; --b3: #e7e9f0; --bc: #111827;
    --in: #2563eb; --ic: #ffffff;
    --su: #0f9d63; --suc: #ffffff;
    --wa: #d97706; --wac: #ffffff;
    --er: #dc3545; --erc: #ffffff;
    --btn-text-case: none;
  }

  body {
    font-family: var(--font-sans);
    font-size: 14px;
    line-height: 1.5;
    font-weight: 400;
    font-feature-settings: "kern" 1, "liga" 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }

  button,
  input,
  select,
  textarea {
    font-family: inherit;
    font-size: inherit;
  }

  .font-head,
  h1,
  h2,
  h3 {
    font-family: var(--font-head);
  }

  .tabular-nums,
  time {
    font-variant-numeric: tabular-nums;
  }

  /* Shared interaction language: consistent pointers and darker hover feedback. */
  :where(
    a[href],
    button:not(:disabled),
    select:not(:disabled),
    summary,
    [role="button"],
    input[type="checkbox"]:not(:disabled),
    input[type="radio"]:not(:disabled),
    input[type="submit"]:not(:disabled),
    input[type="button"]:not(:disabled),
    input[type="reset"]:not(:disabled),
    label[for],
    .btn:not(.btn-disabled)
  ) {
    cursor: pointer;
  }

  :where(button:disabled, select:disabled, input:disabled, .btn-disabled) {
    cursor: not-allowed;
  }

  @media (hover: hover) and (pointer: fine) {
    :where(a[href], button:not(:disabled), .btn:not(.btn-disabled)) {
      transition:
        color 160ms ease,
        background-color 160ms ease,
        border-color 160ms ease,
        box-shadow 160ms ease,
        filter 160ms ease,
        transform 160ms ease;
    }

    #sidebar nav a:hover {
      background-color: #dbe5f1 !important;
      color: #123863 !important;
    }

    .table tbody tr:hover {
      background-color: #e5ebf4 !important;
    }

    a.card:hover,
    .card[role="button"]:hover {
      background-color: #eef2f7 !important;
      border-color: #cbd5e1 !important;
      box-shadow: 0 8px 22px -10px rgba(17, 24, 39, .25);
    }

    .btn:hover:not(:disabled):not(.btn-disabled),
    button:hover:not(:disabled) {
      filter: brightness(.9);
    }

    :where(input, select, textarea):not(:disabled):hover {
      border-color: #9aa8bb !important;
    }
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