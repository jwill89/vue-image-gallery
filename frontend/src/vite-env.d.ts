/// <reference types="vite/client" />

/** App version, injected at build time by Vite from package.json. */
declare const __APP_VERSION__: string

// Note: `.vue` single-file components are typed by vue-tsc directly (the build
// and IDE both use it), so no `declare module '*.vue'` shim is needed here.
