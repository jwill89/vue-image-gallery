/// <reference types="vite/client" />

/** App version, injected at build time by Vite from package.json. */
declare const __APP_VERSION__: string

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

