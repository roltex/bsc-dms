import api from '../lib/api'

export interface DocumentTemplate {
  id: number
  document_category_id: number
  name: string
  path: string | null
  editable_sections: string[] | null
  detected_variables: string[] | null
  extra_variables: string[] | null
  table_schema: Record<string, { columns: string[]; labels: Record<string, string> }> | null
  template_tables_count?: number
  is_custom: boolean
  created_at: string
  updated_at: string
  category: { id: number; name: string; code: string } | null
}

export interface TemplateTableColumn {
  key: string
  label: string
  source: string
}

export interface TemplateTable {
  id: number
  document_template_id: number
  name: string
  shortcode: string
  columns: TemplateTableColumn[]
  sort_order: number
}

export interface InventoryItem {
  id: number
  title: string
  description: string | null
  category: string | null
  price: string | null
  currency: string | null
  serial_number: string | null
  model_number: string | null
  status: string
}

export async function fetchTemplates(categoryId?: number): Promise<DocumentTemplate[]> {
  const params: Record<string, unknown> = {}
  if (categoryId) params.document_category_id = categoryId
  const { data } = await api.get('/document-templates', { params })
  return data
}

export function getTemplateDownloadUrl(templateId: number): string {
  return `/api/document-templates/${templateId}/download`
}

export async function fetchTemplateContent(
  templateId: number,
  variables: Record<string, string> = {},
  extraVariables: Record<string, string> = {},
  tableData: Record<string, Record<string, string>[]> = {}
): Promise<{ html: string }> {
  const { data } = await api.post(`/document-templates/${templateId}/content`, {
    variables,
    extra_variables: extraVariables,
    table_data: tableData,
  })
  return data
}

export async function getTemplatePreviewHtmlUrl(
  templateId: number,
  html: string
): Promise<string> {
  const { data } = await api.post(
    `/document-templates/${templateId}/preview-html`,
    { html },
    { responseType: 'blob' }
  )
  return URL.createObjectURL(data)
}

export async function getTemplateRawPreviewUrl(templateId: number): Promise<string> {
  const { data } = await api.post(
    `/document-templates/${templateId}/preview`,
    { variables: {}, extra_variables: {}, table_data: {} },
    { responseType: 'blob' }
  )
  return URL.createObjectURL(data)
}

export async function uploadCustomTemplate(
  file: File,
  categoryId: number,
  name?: string
): Promise<DocumentTemplate> {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('document_category_id', String(categoryId))
  if (name) fd.append('name', name)
  const { data } = await api.post('/document-templates/upload-custom', fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function getTemplatePreviewUrl(
  templateId: number,
  variables: Record<string, string>,
  extraVariables: Record<string, string>,
  tableData?: Record<string, Record<string, string>[]>
): Promise<string> {
  const { data } = await api.post(
    `/document-templates/${templateId}/preview`,
    { variables, extra_variables: extraVariables, table_data: tableData ?? {} },
    { responseType: 'blob' }
  )
  return URL.createObjectURL(data)
}

export async function fetchTemplateTables(templateId: number): Promise<TemplateTable[]> {
  const { data } = await api.get(`/document-templates/${templateId}/tables`)
  return data
}

export async function fetchInventoryItems(search?: string): Promise<InventoryItem[]> {
  const params: Record<string, unknown> = { per_page: 200 }
  if (search) params.search = search
  const { data } = await api.get('/inventory-items', { params })
  return data.data ?? data
}
