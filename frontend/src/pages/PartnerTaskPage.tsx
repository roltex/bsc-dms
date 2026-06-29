import { useState, useRef } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import SignaturePad from 'signature_pad'
import { useBranding } from '../contexts/BrandingContext'

const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } })

function usePartnerTask(token: string) {
  return useQuery({
    queryKey: ['partner-task', token],
    queryFn: async () => {
      const { data } = await api.get(`/partner-access/${token}`)
      return data as {
        task: {
          id: number; status: string
          partner: { name: string } | null
          initiator: { name: string } | null
          documents: { id: number; version: number; is_signed: boolean; created_at: string }[]
        }
        step: { id: number; name: string; role: string; action_type: string }
        partner_name: string
        expires_at: string
        can_act: boolean
        action_taken?: string | null
        available_actions?: string[]
      }
    },
    retry: false,
  })
}

export default function PartnerTaskPage() {
  const { token } = useParams<{ token: string }>()
  const { appName } = useBranding()
  const queryClient = useQueryClient()
  const { data, isLoading, error } = usePartnerTask(token!)

  const [rejectReason, setRejectReason] = useState('')
  const [showRejectForm, setShowRejectForm] = useState(false)
  const [showSignModal, setShowSignModal] = useState(false)
  const [doneState, setDoneState] = useState<{ type: 'signed' | 'rejected' | 'uploaded' } | null>(null)
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const signPadRef = useRef<SignaturePad | null>(null)
  const uploadInputRef = useRef<HTMLInputElement>(null)
  const [uploadFile, setUploadFile] = useState<File | null>(null)

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['partner-task', token] })

  const rejectMutation = useMutation({
    mutationFn: () => api.post(`/partner-access/${token}/reject`, { comment: rejectReason }),
    onSuccess: () => { invalidate(); setShowRejectForm(false); setDoneState({ type: 'rejected' }) },
    onError: () => {},
  })

  const signMutation = useMutation({
    mutationFn: (signature: string) => {
      return api.post(`/partner-access/${token}/sign`, { signature })
    },
    onSuccess: () => { invalidate(); setShowSignModal(false); setDoneState({ type: 'signed' }) },
    onError: () => {},
  })

  const uploadDocMutation = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData()
      fd.append('document', file)
      fd.append('advance', '1')
      return api.post(`/partner-access/${token}/upload-document`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onSuccess: () => { invalidate(); setUploadFile(null); setDoneState({ type: 'uploaded' }) },
    onError: () => {},
  })

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 flex items-center justify-center">
        <div className="animate-pulse text-slate-400 text-lg">Loading...</div>
      </div>
    )
  }

  if (error) {
    const errMsg = axios.isAxiosError(error) ? error.response?.data?.message : 'Invalid or expired link'
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 flex items-center justify-center p-4">
        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-10 max-w-md w-full text-center border border-slate-200 dark:border-slate-700">
          <div className="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-5">
            <svg className="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
          </div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white mb-2">Access Denied</h1>
          <p className="text-slate-500 dark:text-slate-400">{errMsg}</p>
        </div>
      </div>
    )
  }

  if (!data) return null

  const { task, can_act, partner_name, available_actions } = data
  const actions = available_actions ?? []
  const isUploadStep = data.step.action_type === 'upload_document'
  const canSign = can_act && !isUploadStep && (data.step.action_type === 'sign' || actions.includes('approved'))
  const canUpload = can_act && isUploadStep
  const canReject = can_act && actions.includes('rejected')
  const companyName = task.initiator?.name ?? 'Unknown'

  if (doneState) {
    const isSuccess = doneState.type === 'signed' || doneState.type === 'uploaded'
    const titles: Record<string, string> = { signed: 'Document Signed Successfully', uploaded: 'Document Uploaded Successfully', rejected: 'Document Rejected' }
    const messages: Record<string, string> = {
      signed: `Your signed document has been submitted. The initiator (${companyName}) has been notified.`,
      uploaded: `Your document has been uploaded and submitted. The initiator (${companyName}) has been notified.`,
      rejected: `You have rejected this document. The initiator (${companyName}) has been notified.`,
    }
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 flex items-center justify-center p-4">
        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-10 max-w-md w-full text-center border border-slate-200 dark:border-slate-700">
          <div className={`w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 ${
            isSuccess ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'
          }`}>
            {isSuccess ? (
              <svg className="w-10 h-10 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            ) : (
              <svg className="w-10 h-10 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            )}
          </div>
          <h1 className={`text-2xl font-bold mb-3 ${isSuccess ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'}`}>
            {titles[doneState.type]}
          </h1>
          <p className="text-slate-500 dark:text-slate-400">{messages[doneState.type]}</p>
          <div className="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <p className="text-xs text-slate-400">You can close this page now.</p>
          </div>
        </div>
      </div>
    )
  }

  const actionTaken = data.action_taken

  const latestDoc = task.documents.length > 0
    ? task.documents.reduce((a, b) => a.version > b.version ? a : b)
    : null

  const activePreviewId = latestDoc?.id ?? null

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
      <div className="max-w-4xl mx-auto px-4 py-8">

        {/* Header */}
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Document Review</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-1">
            From <span className="font-semibold text-slate-700 dark:text-slate-200">{companyName}</span>
            {' '}&middot;{' '}
            Hello, <span className="font-medium">{partner_name}</span>
          </p>
        </div>

        {/* Status banner when already acted */}
        {!can_act && actionTaken && (
          <div className={`rounded-xl p-4 mb-4 flex items-center gap-3 ${
            ['signed', 'uploaded', 'approved'].includes(actionTaken)
              ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'
              : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
          }`}>
            {['signed', 'uploaded', 'approved'].includes(actionTaken) ? (
              <svg className="w-6 h-6 text-green-600 dark:text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            ) : (
              <svg className="w-6 h-6 text-red-600 dark:text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            )}
            <div>
              <p className={`font-semibold text-sm ${['signed', 'uploaded', 'approved'].includes(actionTaken) ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'}`}>
                {actionTaken === 'signed' ? 'You have signed this document' : actionTaken === 'uploaded' ? 'You have uploaded your document' : actionTaken === 'approved' ? 'You have approved this document' : 'You have rejected this document'}
              </p>
              <p className={`text-xs mt-0.5 ${['signed', 'uploaded', 'approved'].includes(actionTaken) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                {['signed', 'uploaded', 'approved'].includes(actionTaken)
                  ? 'The initiator has been notified. You can download the document for your records.'
                  : 'The initiator has been notified of your rejection.'}
              </p>
            </div>
          </div>
        )}

        {/* PDF Preview */}
        {activePreviewId && (
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden mb-4">
            <div className="flex items-center justify-between px-4 py-2.5 bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
              <div className="flex items-center gap-2">
                <svg className="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 13h1v3.5a.5.5 0 001 0V13h1a.5.5 0 000-1h-3a.5.5 0 000 1zm-2 0h.7c.8 0 1.3.6 1.3 1.2 0 .7-.5 1.3-1.3 1.3H7v1a.5.5 0 01-1 0v-3a.5.5 0 01.5-.5zm.5 1.5h.2c.3 0 .3-.2.3-.3s0-.2-.3-.2H7v.5zm5 0c0 1.1.7 2 1.8 2h.2a.5.5 0 000-1h-.2c-.5 0-.8-.4-.8-1s.3-1 .8-1h.2a.5.5 0 000-1h-.2c-1.1 0-1.8.9-1.8 2z" />
                </svg>
                <span className="text-xs font-medium text-slate-600 dark:text-slate-300">Document Preview</span>
              </div>
              <a
                href={`/api/partner-access/${token}/documents/${activePreviewId}/download`}
                className="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download
              </a>
            </div>
            <iframe
              src={`/api/partner-access/${token}/documents/${activePreviewId}/preview`}
              className="w-full border-0"
              style={{ height: '70vh', minHeight: '500px' }}
              title="Document Preview"
            />
          </div>
        )}

        {/* Show "Final Version" badge when there are multiple versions */}
        {task.documents.length > 1 && latestDoc && (
          <div className="flex items-center gap-2 mb-4 px-1">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/40 text-xs font-medium text-emerald-700 dark:text-emerald-300">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Final Version (v{latestDoc.version})
            </span>
          </div>
        )}

        {task.documents.length === 0 && (
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-10 mb-4 text-center">
            <svg className="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <p className="text-slate-400 text-sm">No documents attached.</p>
          </div>
        )}

        {/* Actions — only when partner can still act */}
        {can_act && (canSign || canUpload || canReject) && <div className="flex flex-col gap-3">
          {canUpload && (
            <div className="rounded-xl border-2 border-dashed border-blue-300 dark:border-blue-700 bg-blue-50/50 dark:bg-blue-900/10 p-6 text-center">
              <input ref={uploadInputRef} type="file" className="hidden" accept=".doc,.docx,.pdf" onChange={(e) => { const f = e.target.files?.[0]; if (f) setUploadFile(f) }} />
              <svg className="w-12 h-12 mx-auto mb-3 text-blue-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
              <p className="text-base font-semibold text-slate-700 dark:text-slate-300 mb-1">Upload Your Document</p>
              <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">Select a PDF, DOC, or DOCX file to upload and submit</p>
              {uploadFile ? (
                <div className="space-y-3">
                  <div className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-sm">
                    <svg className="w-4 h-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    <span className="text-slate-700 dark:text-slate-300 truncate max-w-[200px]">{uploadFile.name}</span>
                    <button onClick={() => setUploadFile(null)} className="text-slate-400 hover:text-red-500 ml-1">
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                  <div className="flex gap-2 justify-center">
                    <button onClick={() => { setUploadFile(null); uploadInputRef.current && (uploadInputRef.current.value = '') }} className="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                      Change File
                    </button>
                    <button
                      onClick={() => uploadDocMutation.mutate(uploadFile)}
                      disabled={uploadDocMutation.isPending}
                      className="px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-sm flex items-center gap-2"
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                      {uploadDocMutation.isPending ? 'Uploading & Sending...' : 'Upload & Send'}
                    </button>
                  </div>
                </div>
              ) : (
                <button
                  onClick={() => uploadInputRef.current?.click()}
                  className="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm"
                >
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                  Choose File
                </button>
              )}
            </div>
          )}

          {canSign && (
            <button
              onClick={() => setShowSignModal(true)}
              className="flex-1 py-3.5 px-6 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold text-base transition-colors shadow-sm flex items-center justify-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
              </svg>
              {data.step.action_type === 'sign' ? 'Sign & Submit Document' : 'Approve'}
            </button>
          )}

          {canReject && (
            !showRejectForm ? (
              <button
                onClick={() => setShowRejectForm(true)}
                className="flex-1 py-3.5 px-6 bg-white dark:bg-slate-800 hover:bg-red-50 dark:hover:bg-red-900/10 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded-xl font-semibold text-base transition-colors flex items-center justify-center gap-2"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Reject
              </button>
            ) : (
              <div className="flex-1 bg-white dark:bg-slate-800 rounded-xl border border-red-200 dark:border-red-800 p-4 space-y-3">
                <p className="text-sm font-medium text-red-700 dark:text-red-400">Why are you rejecting?</p>
                <textarea
                  placeholder="Enter reason..."
                  value={rejectReason}
                  onChange={e => setRejectReason(e.target.value)}
                  rows={2}
                  className="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                />
                <div className="flex gap-2">
                  <button
                    onClick={() => { setShowRejectForm(false); setRejectReason('') }}
                    className="flex-1 py-2 px-3 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-sm font-medium"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={() => rejectMutation.mutate()}
                    disabled={!rejectReason.trim() || rejectMutation.isPending}
                    className="flex-1 py-2 px-3 bg-red-600 text-white rounded-lg text-sm font-semibold disabled:opacity-50"
                  >
                    {rejectMutation.isPending ? 'Rejecting...' : 'Confirm Reject'}
                  </button>
                </div>
              </div>
            )
          )}
        </div>}

        <p className="text-center text-xs text-slate-400 mt-8">{appName}</p>
      </div>

      {/* Sign Modal — just signature pad, no file upload */}
      {showSignModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" onClick={() => setShowSignModal(false)}>
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">Sign Document</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-5">
              Draw your signature below. It will be placed on the document automatically.
            </p>
            <div className="space-y-4">
              <div>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-medium text-slate-700 dark:text-slate-300">Your Signature</p>
                  <button onClick={() => signPadRef.current?.clear()} className="text-xs text-blue-600 hover:underline">Clear</button>
                </div>
                <canvas
                  ref={el => {
                    if (el && !signPadRef.current) {
                      canvasRef.current = el
                      el.width = el.offsetWidth
                      el.height = 180
                      signPadRef.current = new SignaturePad(el, { penColor: '#1a2236', backgroundColor: 'rgba(0,0,0,0)' })
                    }
                  }}
                  className="w-full h-44 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 bg-white cursor-crosshair touch-none"
                />
                <p className="text-xs text-slate-400 text-center mt-1">Draw your signature above</p>
              </div>
              <div className="flex gap-3 pt-2">
                <button
                  onClick={() => setShowSignModal(false)}
                  className="flex-1 py-2.5 px-4 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg font-medium text-sm"
                >
                  Cancel
                </button>
                <button
                  disabled={signMutation.isPending}
                  onClick={() => {
                    if (!signPadRef.current || signPadRef.current.isEmpty()) return
                    const sig = signPadRef.current.toDataURL()
                    signMutation.mutate(sig)
                  }}
                  className="flex-1 py-2.5 px-4 bg-green-600 text-white rounded-lg font-semibold text-sm disabled:opacity-50"
                >
                  {signMutation.isPending ? 'Signing...' : 'Confirm & Sign'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
