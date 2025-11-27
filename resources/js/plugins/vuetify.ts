import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

// GOGIS Theme Configuration
const gogisTheme = {
  dark: false,
  colors: {
    primary: '#1c6434',
    'on-primary': '#ffffff',
    secondary: '#fecd07',
    'on-secondary': '#000000',
    accent: '#c39913',
    'on-accent': '#ffffff',
    error: '#ef4444',
    'on-error': '#ffffff',
    info: '#3b82f6',
    'on-info': '#ffffff',
    success: '#10b981',
    'on-success': '#ffffff',
    warning: '#f59e0b',
    'on-warning': '#000000',
    background: '#f9fafb',
    'on-background': '#1a1a1a',
    surface: '#ffffff',
    'on-surface': '#1a1a1a',
  },
}

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'gogisTheme',
    themes: {
      gogisTheme,
    },
    variations: {
      colors: ['primary', 'secondary', 'accent'],
      lighten: 5,
      darken: 5,
    },
  },
  defaults: {
    VBtn: {
      style: [
        'text-transform: none;',
      ],
      rounded: 'lg',
    },
    VCard: {
      rounded: 'lg',
      elevation: 2,
    },
    VTextField: {
      variant: 'outlined',
      color: 'primary',
    },
    VTextarea: {
      variant: 'outlined',
      color: 'primary',
    },

    VSelect: {
      variant: 'outlined',
      color: 'primary',
    },
  },
})

