import api from '../lib/api'

export interface FinalizedDocument {
  id: number
  user_id: number
  category: string
  name: string
  path: string
  mime_type: string | null
  size: number
  notes: string | null
  created_at: string
  updated_at: string
  user: { id: number; name: string } | null
}

export interface FinalizedDocsListResponse {
  data: FinalizedDocument[]
  current_page: number
  last_page: number
  total: number
}

export async function fetchFinalizedDocs(params?: {
  search?: string
  category?: string
  user_id?: number
  file_type?: string
  date_from?: string
  date_to?: string
  min_size?: number
  max_size?: number
  sort?: string
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}): Promise<FinalizedDocsListResponse> {
  const { data } = await api.get('/finalized-documents', { params })
  return data
}

export async function uploadFinalizedDoc(formData: FormData): Promise<FinalizedDocument> {
  const { data } = await api.post('/finalized-documents', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function downloadFinalizedDoc(id: number): Promise<void> {
  const response = await api.get(`/finalized-documents/${id}/download`, {
    responseType: 'blob',
    validateStatus: () => true,
  })

  if (response.status !== 200) {
    let msg = 'Download failed'
    if (response.data instanceof Blob && response.data.type === 'application/json') {
      const text = await response.data.text()
      try { msg = JSON.parse(text).message || msg } catch { /* ignore */ }
    }
    throw new Error(msg)
  }

  const blob = response.data as Blob
  const disposition = response.headers['content-disposition'] || ''
  const match = disposition.match(/filename="?([^";\n]+)"?/)
  const filename = match?.[1] || 'document.pdf'

  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

export async function deleteFinalizedDoc(id: number): Promise<void> {
  await api.delete(`/finalized-documents/${id}`)
}

export async function fetchFinalizedDocCategories(): Promise<Record<string, string>> {
  const { data } = await api.get('/finalized-documents/categories')
  return data
}
