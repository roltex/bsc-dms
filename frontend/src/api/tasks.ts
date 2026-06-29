import api from '../lib/api'
import type { Task, WorkflowRoute } from '../types/task'

export interface TasksListResponse {
  data: Task[]
  current_page: number
  last_page: number
  total: number
}

export async function fetchTasks(params?: {
  status?: string
  document_category_id?: number
  search?: string
  partner_id?: number
  initiator_id?: number
  assigned_lawyer_id?: number
  route_type?: string
  workflow_route_id?: number
  fast_tracked?: boolean
  deadline_from?: string
  deadline_to?: string
  created_from?: string
  created_to?: string
  min_amount?: number
  max_amount?: number
  overdue?: boolean
  sort?: string
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}): Promise<TasksListResponse> {
  const { data } = await api.get('/tasks', { params })
  return data
}

export async function fetchTask(id: number): Promise<Task> {
  const { data } = await api.get(`/tasks/${id}`)
  return data
}

export async function createTask(formData: FormData): Promise<Task> {
  const { data } = await api.post('/tasks', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function updateTask(id: number, payload: Record<string, unknown>): Promise<Task> {
  const { data } = await api.put(`/tasks/${id}`, payload)
  return data
}

export async function submitTask(id: number): Promise<Task> {
  const { data } = await api.post(`/tasks/${id}/submit`)
  return data
}

export async function approveTask(id: number, comment?: string): Promise<Task> {
  const { data } = await api.post(`/tasks/${id}/approve`, { comment })
  return data
}

export async function rejectTask(id: number, comment: string): Promise<Task> {
  const { data } = await api.post(`/tasks/${id}/reject`, { comment })
  return data
}

export async function delegateTask(id: number, lawyerId: number, comment?: string): Promise<Task> {
  const { data } = await api.post(`/tasks/${id}/delegate`, { lawyer_id: lawyerId, comment })
  return data
}

export async function fastTrackTask(id: number, comment?: string): Promise<Task> {
  const { data } = await api.post(`/tasks/${id}/fast-track`, { comment })
  return data
}

export async function addTaskReviewer(id: number, userId: number, deadline?: string | null): Promise<Task> {
  const payload: Record<string, unknown> = { user_id: userId }
  if (deadline) payload.deadline = deadline
  const { data } = await api.post(`/tasks/${id}/reviewers`, payload)
  return data
}

export async function removeTaskReviewer(id: number, userId: number): Promise<Task> {
  const { data } = await api.delete(`/tasks/${id}/reviewers/${userId}`)
  return data
}

export async function uploadTaskDocument(taskId: number, file: File): Promise<unknown> {
  const fd = new FormData()
  fd.append('document', file)
  const { data } = await api.post(`/tasks/${taskId}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function uploadSignedDocument(
  taskId: number,
  signatureDataUrl: string
): Promise<unknown> {
  const { data } = await api.post(`/tasks/${taskId}/signed-document`, {
    signature: signatureDataUrl,
  })
  return data
}

export async function uploadDocumentStep(taskId: number, file: File, comment?: string): Promise<unknown> {
  const fd = new FormData()
  fd.append('document', file)
  if (comment) fd.append('comment', comment)
  const { data } = await api.post(`/tasks/${taskId}/upload-document-step`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function returnTask(id: number, comment?: string): Promise<Task> {
  const { data } = await api.post(`/tasks/${id}/return`, { comment })
  return data
}

export async function fetchAvailableActions(id: number): Promise<{ available_actions: string[]; active_steps: { step_id: number; step_name: string | null; role: string | null; status: string }[] }> {
  const { data } = await api.get(`/tasks/${id}/available-actions`)
  return data
}

export async function fetchWorkflowRoutes(): Promise<WorkflowRoute[]> {
  const { data } = await api.get('/workflow-routes')
  return data
}

export function getDocumentDownloadUrl(taskId: number, documentId: number): string {
  return `/api/tasks/${taskId}/documents/${documentId}/download`
}

export function getDocumentPreviewUrl(taskId: number, documentId: number): string {
  return `/api/tasks/${taskId}/documents/${documentId}/preview`
}

export function getSignatureUrl(taskId: number, documentId: number): string {
  return `/api/tasks/${taskId}/documents/${documentId}/signature`
}

// --- Task Comments ---

export interface TaskComment {
  id: number
  task_id: number
  document_id: number | null
  user_id: number
  user: { id: number; name: string }
  page: number | null
  x_percent: number | null
  y_percent: number | null
  body: string
  resolved: boolean
  parent_id: number | null
  replies: TaskComment[]
  created_at: string
  updated_at: string
}

export async function fetchTaskComments(taskId: number, documentId?: number): Promise<TaskComment[]> {
  const params: Record<string, unknown> = {}
  if (documentId) params.document_id = documentId
  const { data } = await api.get(`/tasks/${taskId}/comments`, { params })
  return data
}

export async function fetchGeneralComments(taskId: number): Promise<TaskComment[]> {
  const { data } = await api.get(`/tasks/${taskId}/comments`, { params: { type: 'general' } })
  return data
}

export async function createTaskComment(taskId: number, payload: {
  document_id?: number | null
  page?: number | null
  x_percent?: number | null
  y_percent?: number | null
  body: string
  parent_id?: number | null
}): Promise<TaskComment> {
  const { data } = await api.post(`/tasks/${taskId}/comments`, payload)
  return data
}

export function getAttachmentDownloadUrl(taskId: number, documentId: number): string {
  return `/api/tasks/${taskId}/attachments/${documentId}/download`
}

export async function uploadTaskAttachments(taskId: number, files: File[]): Promise<unknown> {
  const fd = new FormData()
  files.forEach(f => fd.append('files[]', f))
  const { data } = await api.post(`/tasks/${taskId}/attachments/upload`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function replaceTaskAttachment(taskId: number, documentId: number, file: File): Promise<unknown> {
  const fd = new FormData()
  fd.append('file', file)
  const { data } = await api.post(`/tasks/${taskId}/attachments/${documentId}/replace`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function deleteTaskAttachment(taskId: number, documentId: number): Promise<unknown> {
  const { data } = await api.delete(`/tasks/${taskId}/attachments/${documentId}`)
  return data
}

export async function updateTaskComment(taskId: number, commentId: number, payload: {
  body?: string
  resolved?: boolean
}): Promise<TaskComment> {
  const { data } = await api.patch(`/tasks/${taskId}/comments/${commentId}`, payload)
  return data
}

export async function deleteTaskComment(taskId: number, commentId: number): Promise<void> {
  await api.delete(`/tasks/${taskId}/comments/${commentId}`)
}

// --- Final Version Upload ---
export async function uploadFinalVersion(taskId: number, file: File): Promise<unknown> {
  const fd = new FormData()
  fd.append('document', file)
  const { data } = await api.post(`/tasks/${taskId}/upload-final`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

// --- Document Content (Online Editor) ---
export async function fetchDocumentContent(taskId: number): Promise<{ html: string }> {
  const { data } = await api.get(`/tasks/${taskId}/document-content`)
  return data
}

export async function saveDocumentContent(taskId: number, html: string): Promise<unknown> {
  const { data } = await api.put(`/tasks/${taskId}/document-content`, { html })
  return data
}

// --- Summary Report ---
export async function downloadSummaryReport(taskId: number): Promise<void> {
  const response = await api.get(`/tasks/${taskId}/summary-report`, {
    responseType: 'blob',
  })
  const blob = response.data as Blob
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `task-${taskId}-summary.xlsx`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

// --- EDS (NCALayer) Signing ---
export async function uploadEdsSignature(
  taskId: number,
  cmsSignature: string,
  signedData?: string
): Promise<unknown> {
  const { data } = await api.post(`/tasks/${taskId}/eds-sign`, {
    cms_signature: cmsSignature,
    signed_data: signedData || '',
  })
  return data
}

// --- AI Document Analysis ---
export async function analyzeDocument(documentId: number): Promise<Record<string, unknown>> {
  const { data } = await api.post('/documents/analyze', { document_id: documentId })
  return data
}

export async function compareDocuments(
  taskId: number,
  signedDocumentId: number,
  originalDocumentId?: number
): Promise<Record<string, unknown>> {
  const { data } = await api.post('/documents/compare', {
    task_id: taskId,
    signed_document_id: signedDocumentId,
    original_document_id: originalDocumentId,
  })
  return data
}

export async function validateDocument(
  documentId: number,
  templateName?: string
): Promise<Record<string, unknown>> {
  const { data } = await api.post('/documents/validate', {
    document_id: documentId,
    template_name: templateName,
  })
  return data
}

// --- E-Sign Status ---
export async function fetchEsignStatus(): Promise<Record<string, unknown>> {
  const { data } = await api.get('/integrations/esign/status')
  return data
}

// --- Paragraph Legal Search ---
export async function searchParagraph(query: string): Promise<Record<string, unknown>> {
  const { data } = await api.get('/integrations/paragraph/search', { params: { query } })
  return data
}
