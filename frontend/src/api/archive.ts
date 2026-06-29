import api from '../lib/api'

export interface ArchiveTask {
  id: number
  status: string
  registration_number: string | null
  updated_at: string
  created_at: string
  deadline: string | null
  amount: number | null
  category: { id: number; name: string } | null
  partner: { id: number; name: string } | null
  initiator: { id: number; name: string } | null
}

export interface ArchiveListResponse {
  data: ArchiveTask[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

export interface ArchiveParams {
  search?: string
  year?: number
  document_category_id?: number
  partner_id?: number
  status?: string
  sort?: string
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export async function fetchArchive(params?: ArchiveParams): Promise<ArchiveListResponse> {
  const { data } = await api.get('/archive', { params })
  return data
}

export function getArchiveExportUrl(params?: { year?: number; document_category_id?: number }): string {
  const searchParams = new URLSearchParams()
  if (params?.year) searchParams.set('year', String(params.year))
  if (params?.document_category_id) searchParams.set('document_category_id', String(params.document_category_id))
  const qs = searchParams.toString()
  return `/api/archive/export${qs ? '?' + qs : ''}`
}
