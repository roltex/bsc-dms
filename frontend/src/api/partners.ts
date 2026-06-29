import api from '../lib/api'
import type { Partner, PartnerDocument } from '../types/partner'

export interface PartnersListResponse {
  data: Partner[]
  current_page: number
  last_page: number
  total: number
}

export async function fetchPartners(params?: {
  search?: string
  blacklisted?: boolean
  page?: number
}): Promise<PartnersListResponse> {
  const { data } = await api.get('/partners', { params })
  return data
}

export async function fetchPartner(id: number): Promise<Partner> {
  const { data } = await api.get(`/partners/${id}`)
  return data
}

export async function createPartner(payload: Record<string, unknown>): Promise<Partner> {
  const { data } = await api.post('/partners', payload)
  return data
}

export async function updatePartner(id: number, payload: Record<string, unknown>): Promise<Partner> {
  const { data } = await api.put(`/partners/${id}`, payload)
  return data
}

export async function checkBinIin(binIin: string): Promise<{ exists: boolean }> {
  const { data } = await api.get('/partners/check-bin-iin', { params: { bin_iin: binIin } })
  return data
}

export async function blacklistPartner(id: number, reason: string): Promise<Partner> {
  const { data } = await api.post(`/partners/${id}/blacklist`, { reason })
  return data
}

export async function unblacklistPartner(id: number): Promise<Partner> {
  const { data } = await api.post(`/partners/${id}/unblacklist`)
  return data
}

// Partner documents
export async function fetchPartnerDocuments(partnerId: number): Promise<PartnerDocument[]> {
  const { data } = await api.get(`/partners/${partnerId}/documents`)
  return data
}

export async function uploadPartnerDocument(partnerId: number, file: File, type: string = 'other'): Promise<PartnerDocument> {
  const fd = new FormData()
  fd.append('document', file)
  fd.append('type', type)
  const { data } = await api.post(`/partners/${partnerId}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function fetchPartnerDocumentTypes(): Promise<Record<string, string>> {
  const { data } = await api.get('/partner-document-types')
  return data
}

export async function deletePartnerDocument(partnerId: number, docId: number): Promise<void> {
  await api.delete(`/partners/${partnerId}/documents/${docId}`)
}

export function getPartnerDocumentDownloadUrl(partnerId: number, docId: number): string {
  return `/api/partners/${partnerId}/documents/${docId}/download`
}

// ADATA reliability check — two-step polling approach
export async function adataInitiate(bin: string): Promise<{ success: boolean; token?: string; message?: string }> {
  const { data } = await api.get(`/integrations/adata/initiate/${bin}`)
  return data
}

export async function adataPoll(token: string): Promise<{ status: string; data?: Record<string, unknown>; message?: string }> {
  const { data } = await api.get('/integrations/adata/poll', { params: { token } })
  return data
}

/**
 * Full check with client-side polling (up to ~60s).
 */
export async function checkAdataReliability(bin: string): Promise<Record<string, unknown>> {
  const init = await adataInitiate(bin)
  if (!init.success || !init.token) {
    return { status: 'error', bin_iin: bin, message: init.message || 'Failed to initiate ADATA check.' }
  }

  const maxAttempts = 30
  const intervalMs = 2000

  for (let i = 0; i < maxAttempts; i++) {
    await new Promise(r => setTimeout(r, intervalMs))
    const result = await adataPoll(init.token)

    if (result.status === 'ready') {
      return { status: 'live', bin_iin: bin, ...result.data }
    }
    if (result.status === 'error') {
      return { status: 'error', bin_iin: bin, message: result.message || 'ADATA check failed.' }
    }
  }

  return { status: 'error', bin_iin: bin, message: 'ADATA check timed out — data was not ready after 60 seconds.' }
}
