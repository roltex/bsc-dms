import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import api from '../lib/api'

interface Branding {
  appName: string
  companyName: string
}

const defaults: Branding = { appName: 'DMS', companyName: '' }

const BrandingContext = createContext<Branding>(defaults)

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBranding] = useState<Branding>(defaults)

  useEffect(() => {
    api
      .get('/branding')
      .then(({ data }) => {
        const b: Branding = {
          appName: data.app_name || 'DMS',
          companyName: data.company_name || '',
        }
        setBranding(b)
        document.title = b.appName
      })
      .catch(() => {})
  }, [])

  return <BrandingContext.Provider value={branding}>{children}</BrandingContext.Provider>
}

export function useBranding(): Branding {
  return useContext(BrandingContext)
}
