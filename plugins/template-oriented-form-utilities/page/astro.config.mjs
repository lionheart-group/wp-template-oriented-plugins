// @ts-check
import { defineConfig } from 'astro/config';

import tailwindcss from '@tailwindcss/vite';

// https://astro.build/config
export default defineConfig({
  site: 'https://lionheart-group.github.io/template-oriented-form-utilities/',
  base: '/template-oriented-form-utilities/',
  vite: {
    plugins: [tailwindcss()]
  }
});
