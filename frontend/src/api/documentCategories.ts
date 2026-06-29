import api from '../lib/api'

export interface DocumentCategory {
  id: number
  name: string
  code: string
  default_lawyer_id: number | null
}

export async function fetchDocumentCategories(): Promise<DocumentCategory[]> {
  const { data } = await api.get<DocumentCategory[]>('/document-categories')
  return data
}
