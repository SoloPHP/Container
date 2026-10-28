import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Solo Container',
  description: 'Lightweight PSR-11 dependency injection container for PHP 8.4+, with auto-wiring, interface binding and lazy services.',
  base: '/Container/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/Container/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#ec4899' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Solo Container' }],
    ['meta', { property: 'og:description', content: 'Lightweight PSR-11 dependency injection container for PHP 8.4+' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Features', link: '/features/services' },
      { text: 'API', link: '/api/container' },
      { text: 'v4.0.0', link: '/guide/upgrading' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/SoloPHP/Container' },
          { text: 'Packagist', link: 'https://packagist.org/packages/solophp/container' },
          { text: 'SoloPHP', link: 'https://github.com/SoloPHP' }
        ]
      }
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick Start', link: '/guide/quick-start' },
          { text: 'Static Analysis', link: '/guide/static-analysis' },
          { text: 'Upgrading to 4.0', link: '/guide/upgrading' }
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Services & Factories', link: '/features/services' },
          { text: 'Auto-wiring', link: '/features/autowiring' },
          { text: 'Interface Binding', link: '/features/binding' },
          { text: 'Lazy Services', link: '/features/lazy' },
          { text: 'Injection by Id', link: '/features/inject' },
          { text: 'Circular Dependencies', link: '/features/circular-dependencies' }
        ]
      },
      {
        text: 'API Reference',
        items: [
          { text: 'Container', link: '/api/container' },
          { text: 'Attributes', link: '/api/attributes' },
          { text: 'Exceptions', link: '/api/exceptions' }
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/SoloPHP/Container' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 SoloPHP'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/SoloPHP/Container/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  }
})
