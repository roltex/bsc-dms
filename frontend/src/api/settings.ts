import api from '../lib/api'

export interface GoogleStatus {
  enabled: boolean
  configured: boolean
}

export interface GoogleSettings {
  google_drive_enabled: boolean
  google_client_id: string
  google_client_secret: string
  google_authorized: boolean
}

export async function fetchGoogleStatus(): Promise<GoogleStatus> {
  const { data } = await api.get('/settings/google-status')
  return data
}

export async function fetchGoogleSettings(): Promise<GoogleSettings> {
  const { data } = await api.get('/settings/google')
  return data
}

export async function saveGoogleSettings(settings: Omit<GoogleSettings, 'google_authorized'>): Promise<void> {
  await api.put('/settings/google', settings)
}

export async function fetchGoogleAuthUrl(): Promise<string> {
  const { data } = await api.get('/settings/google-auth-url')
  return data.url
}

export async function googleDisconnect(): Promise<void> {
  await api.post('/settings/google-disconnect')
}

export async function templateGoogleEdit(
  templateId: number,
  variables: Record<string, string> = {},
  extraVariables: Record<string, string> = {},
  tableData: Record<string, Record<string, string>[]> = {}
): Promise<{ fileId: string; editUrl: string }> {
  const { data } = await api.post(`/document-templates/${templateId}/google-edit`, {
    variables,
    extra_variables: extraVariables,
    table_data: tableData,
  })
  return data
}

export async function templateGoogleSync(
  templateId: number,
  fileId: string,
  deleteAfter = false,
  variables: Record<string, string> = {},
  extraVariables: Record<string, string> = {},
  tableData: Record<string, Record<string, string>[]> = {}
): Promise<string> {
  const { data } = await api.post(
    `/document-templates/${templateId}/google-sync`,
    { file_id: fileId, delete_after: deleteAfter, variables, extra_variables: extraVariables, table_data: tableData },
    { responseType: 'blob' }
  )
  return URL.createObjectURL(data)
}

export async function taskGoogleEdit(taskId: number): Promise<{ fileId: string; editUrl: string }> {
  const { data } = await api.post(`/tasks/${taskId}/google-edit`)
  return data
}

export async function taskGoogleSync(
  taskId: number,
  fileId: string,
  deleteAfter = false
): Promise<unknown> {
  const { data } = await api.post(`/tasks/${taskId}/google-sync`, {
    file_id: fileId,
    delete_after: deleteAfter,
  })
  return data
}
