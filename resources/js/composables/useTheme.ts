import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTheme as useVuetifyTheme } from 'vuetify'

export function useTheme() {
  const page = usePage()
  const vuetifyTheme = useVuetifyTheme()

  const appSettings = computed(() => page.props.appSettings as {
    siteName: string
    siteTagline: string
    logo: string
    primaryColor: string
    secondaryColor: string
    accentColor: string
    contactEmail: string
    contactPhone: string
    address: string
    fbLink: string
    instagramLink: string
    twitterLink: string
    linkedinLink: string
    footerText: string
  })

  // Apply theme colors to CSS variables and Vuetify theme
  const applyTheme = () => {
    if (typeof document !== 'undefined' && appSettings.value) {
      // Apply to CSS variables
      const root = document.documentElement
      root.style.setProperty('--color-primary', appSettings.value.primaryColor)
      root.style.setProperty('--color-secondary', appSettings.value.secondaryColor)
      root.style.setProperty('--color-accent', appSettings.value.accentColor)

      // Apply to Vuetify theme dynamically by updating the theme object
      const currentTheme = vuetifyTheme.themes.value.gogisTheme
      if (currentTheme && currentTheme.colors) {
        currentTheme.colors.primary = appSettings.value.primaryColor
        currentTheme.colors.secondary = appSettings.value.secondaryColor
        currentTheme.colors.accent = appSettings.value.accentColor
        currentTheme.colors['on-primary'] = '#ffffff'
        currentTheme.colors['on-secondary'] = '#000000'
        currentTheme.colors['on-accent'] = '#ffffff'
      }
    }
  }

  return {
    appSettings,
    applyTheme,
  }
}

