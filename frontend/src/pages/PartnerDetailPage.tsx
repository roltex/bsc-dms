import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  fetchPartner,
  fetchPartnerDocuments,
  uploadPartnerDocument,
  deletePartnerDocument,
  getPartnerDocumentDownloadUrl,
  blacklistPartner,
  unblacklistPartner,
  checkAdataReliability,
} from '../api/partners'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'

export default function PartnerDetailPage() {
  const { id } = useParams()
  const { user } = useAuth()
  const { addToast } = useToast()
  const queryClient = useQueryClient()
  const [blacklistReason, setBlacklistReason] = useState('')
  const [showBlacklistModal, setShowBlacklistModal] = useState(false)
  const [reliability, setReliability] = useState<Record<string, unknown> | null>(null)
  const [reliabilityLoading, setReliabilityLoading] = useState(false)

  const { data: partner, isLoading } = useQuery({
    queryKey: ['partner', id],
    queryFn: () => fetchPartner(Number(id)),
    enabled: Boolean(id),
  })

  const { data: documents } = useQuery({
    queryKey: ['partner-documents', id],
    queryFn: () => fetchPartnerDocuments(Number(id)),
    enabled: Boolean(id),
  })

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadPartnerDocument(Number(id), file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner-documents', id] })
      addToast('Document uploaded')
    },
    onError: () => addToast('Upload failed', 'error'),
  })

  const deleteMutation = useMutation({
    mutationFn: (docId: number) => deletePartnerDocument(Number(id), docId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner-documents', id] })
      addToast('Document deleted')
    },
  })

  const blacklistMutation = useMutation({
    mutationFn: () => blacklistPartner(Number(id), blacklistReason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner', id] })
      setShowBlacklistModal(false)
      setBlacklistReason('')
      addToast('Partner blacklisted')
    },
    onError: () => addToast('Failed to blacklist', 'error'),
  })

  const unblacklistMutation = useMutation({
    mutationFn: () => unblacklistPartner(Number(id)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner', id] })
      addToast('Removed from blacklist')
    },
  })

  const isLawyer = user?.role === 'lawyer' || user?.role === 'administrator'
  const isBlacklisted = !!partner?.blacklisted_at

  async function handleReliabilityCheck() {
    if (!partner) return
    setReliabilityLoading(true)
    try {
      const data = await checkAdataReliability(partner.bin_iin)
      setReliability(data)
    } catch {
      addToast('Reliability check failed', 'error')
    } finally {
      setReliabilityLoading(false)
    }
  }

  if (isLoading || !partner) {
    return (
      <div className="space-y-4 animate-pulse">
        <div className="h-6 w-32 bg-slate-200 dark:bg-slate-700 rounded" />
        <div className="h-32 bg-slate-200 dark:bg-slate-700 rounded-xl" />
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="lg:col-span-2 h-48 bg-slate-200 dark:bg-slate-700 rounded-xl" />
          <div className="h-48 bg-slate-200 dark:bg-slate-700 rounded-xl" />
        </div>
      </div>
    )
  }

  const DOCUMENT_TYPE_LABELS: Record<string, string> = {
    vat_registration: 'VAT Registration',
    charter: 'Company Charter',
    bank_certificate: 'Bank Certificate',
    power_of_attorney: 'Power of Attorney',
    license: 'License',
    id_document: 'ID Document',
    contract: 'Contract',
    other: 'Other',
  }

  const DOCUMENT_TYPE_COLORS: Record<string, string> = {
    vat_registration: 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300',
    charter: 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300',
    bank_certificate: 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    power_of_attorney: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300',
    license: 'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-700 dark:text-cyan-300',
    id_document: 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300',
    contract: 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300',
    other: 'bg-slate-100 dark:bg-slate-600/30 text-slate-600 dark:text-slate-300',
  }

  const groupedDocuments = (documents ?? []).reduce<Record<string, typeof documents>>((acc, doc) => {
    const docType = (doc as { type?: string }).type || 'other'
    if (!acc[docType]) acc[docType] = []
    acc[docType]!.push(doc)
    return acc
  }, {})

  const initials = partner.name.split(/[\s-]+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('')

  return (
    <div className="space-y-5">
      {/* Breadcrumb */}
      <Link to="/partners" className="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Partners
      </Link>

      {/* Profile Header */}
      <div className={`rounded-xl border p-5 ${isBlacklisted ? 'border-red-200/70 dark:border-red-800/40 bg-red-50/30 dark:bg-red-500/5' : 'border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80'}`}>
        <div className="flex items-start gap-4">
          <div className={`flex-shrink-0 w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold ${
            isBlacklisted
              ? 'bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-400'
              : 'bg-blue-100 dark:bg-blue-500/15 text-blue-600 dark:text-blue-400'
          }`}>
            {initials}
          </div>
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2.5 flex-wrap">
              <h1 className="text-lg font-bold text-slate-900 dark:text-white">{partner.name}</h1>
              {isBlacklisted ? (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-400">
                  <span className="w-1.5 h-1.5 rounded-full bg-red-500" />
                  Blacklisted
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                  Active
                </span>
              )}
            </div>
            <div className="flex items-center gap-4 mt-1 flex-wrap">
              <span className="text-sm font-mono text-slate-500 dark:text-slate-400">{partner.bin_iin}</span>
              {partner.email && (
                <a href={`mailto:${partner.email}`} className="text-sm text-blue-600 dark:text-blue-400 hover:underline">{partner.email}</a>
              )}
              <span className="text-xs text-slate-400 dark:text-slate-500">
                Registered {new Date(partner.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
              </span>
            </div>
          </div>
          <Link
            to={`/partners/${partner.id}/edit`}
            className="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
            Edit
          </Link>
        </div>

        {/* Blacklist banner */}
        {isBlacklisted && (
          <div className="mt-4 p-3 rounded-lg bg-red-100/60 dark:bg-red-500/10 border border-red-200/60 dark:border-red-800/30">
            <div className="flex items-start gap-2">
              <svg className="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
              <div>
                <p className="text-sm font-medium text-red-700 dark:text-red-400">Blacklisted</p>
                <p className="text-xs text-red-600/80 dark:text-red-400/70 mt-0.5">{partner.blacklist_reason || 'No reason provided'}</p>
                <p className="text-[11px] text-red-500/60 dark:text-red-400/50 mt-1">Since {new Date(partner.blacklisted_at!).toLocaleDateString()}</p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Main Grid */}
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-4">

          {/* Details */}
          <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
              <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Details</h2>
            </div>
            <div className="p-5">
              <dl className="grid gap-4 sm:grid-cols-2">
                <DetailItem label="BIN / IIN" value={partner.bin_iin} mono />
                <DetailItem label="Email" value={partner.email || '---'} link={partner.email ? `mailto:${partner.email}` : undefined} />
                <div className="sm:col-span-2">
                  <DetailItem label="Bank Details" value={partner.bank_details || '---'} pre />
                </div>
              </dl>
            </div>
          </div>

          {/* Documents */}
          <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Documents</h2>
                {documents?.length ? (
                  <span className="inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full bg-slate-100 dark:bg-slate-700 text-[11px] font-bold text-slate-600 dark:text-slate-300">{documents.length}</span>
                ) : null}
              </div>
              <label className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer">
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                Upload
                <input
                  type="file"
                  className="hidden"
                  onChange={(e) => {
                    const f = e.target.files?.[0]
                    if (f) uploadMutation.mutate(f)
                    e.target.value = ''
                  }}
                />
              </label>
            </div>
            {!documents?.length ? (
              <div className="flex flex-col items-center justify-center py-10 px-6">
                <div className="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-2">
                  <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                </div>
                <p className="text-sm text-slate-500 dark:text-slate-400">No documents uploaded</p>
              </div>
            ) : (
              <div>
                {Object.entries(groupedDocuments).map(([docType, docs]) => (
                  <div key={docType}>
                    <div className="px-5 py-2 bg-slate-50/80 dark:bg-slate-700/20 border-b border-slate-100 dark:border-slate-700/40">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide ${DOCUMENT_TYPE_COLORS[docType] || DOCUMENT_TYPE_COLORS.other}`}>
                        {DOCUMENT_TYPE_LABELS[docType] || docType.replace(/_/g, ' ')}
                      </span>
                      <span className="ml-2 text-[11px] text-slate-400 dark:text-slate-500">{docs!.length}</span>
                    </div>
                    <div className="divide-y divide-slate-100 dark:divide-slate-700/40">
                      {docs!.map(doc => {
                        const ext = doc.name.split('.').pop()?.toUpperCase() ?? 'FILE'
                        const sizeStr = doc.size > 1048576 ? `${(doc.size / 1048576).toFixed(1)} MB` : `${(doc.size / 1024).toFixed(0)} KB`
                        return (
                          <div key={doc.id} className="group flex items-center gap-3 px-5 py-3 hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                            <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-violet-100 dark:bg-violet-500/15 flex items-center justify-center">
                              <span className="text-[10px] font-bold text-violet-600 dark:text-violet-400">{ext}</span>
                            </div>
                            <div className="flex-1 min-w-0">
                              <div className="flex items-center gap-2">
                                <p className="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{doc.name}</p>
                                <span className={`flex-shrink-0 inline-flex px-1.5 py-0.5 rounded text-[9px] font-medium ${DOCUMENT_TYPE_COLORS[(doc as { type?: string }).type || 'other'] || DOCUMENT_TYPE_COLORS.other}`}>
                                  {DOCUMENT_TYPE_LABELS[(doc as { type?: string }).type || 'other'] || (doc as { type?: string }).type || 'Other'}
                                </span>
                              </div>
                              <p className="text-[11px] text-slate-400 dark:text-slate-500">{sizeStr} · {new Date(doc.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                            </div>
                            <div className="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                              <a
                                href={getPartnerDocumentDownloadUrl(Number(id), doc.id)}
                                className="p-1.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors"
                                title="Download"
                              >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                              </a>
                              <button
                                onClick={() => deleteMutation.mutate(doc.id)}
                                className="p-1.5 rounded-md hover:bg-red-50 dark:hover:bg-red-500/10 text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                title="Delete"
                              >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                              </button>
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Sidebar */}
        <div className="space-y-4">
          {/* Reliability check */}
          <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
              <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Reliability Check</h2>
            </div>
            <div className="p-5">
              <button
                onClick={handleReliabilityCheck}
                disabled={reliabilityLoading}
                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors disabled:opacity-50"
              >
                {reliabilityLoading ? (
                  <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                ) : (
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                )}
                Check via ADATA
              </button>
              {reliability && reliability.status === 'error' && (
                <div className="mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40">
                  <p className="text-xs text-red-600 dark:text-red-400">{String(reliability.message)}</p>
                </div>
              )}
              {reliability && reliability.status === 'live' && (() => {
                const r = reliability as Record<string, string | number | boolean | null>
                const score = Number(r.reliability_score ?? 0)
                return (<div className="mt-4 space-y-3">
                  {/* Score bar */}
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/40">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Reliability Score</span>
                      <span className={`text-lg font-bold ${score >= 70 ? 'text-emerald-600 dark:text-emerald-400' : score >= 40 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}`}>
                        {score}/100
                      </span>
                    </div>
                    <div className="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                      <div
                        className={`h-full rounded-full transition-all ${score >= 70 ? 'bg-emerald-500' : score >= 40 ? 'bg-amber-500' : 'bg-red-500'}`}
                        style={{ width: `${score}%` }}
                      />
                    </div>
                  </div>

                  {/* Status flags */}
                  <div className="grid grid-cols-2 gap-2">
                    <div className="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-center">
                      <p className="text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">Company Status</p>
                      <p className={`text-xs font-semibold ${r.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
                        {r.is_active ? 'Active' : 'Inactive'}
                      </p>
                    </div>
                    <div className="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-center">
                      <p className="text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">VAT Payer</p>
                      <p className={`text-xs font-semibold ${r.is_nds_payer ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'}`}>
                        {r.is_nds_payer ? 'Yes' : 'No'}
                      </p>
                    </div>
                  </div>

                  {/* Problem flags */}
                  {(r.company_problems || r.financial_problems || r.unreliable_zakup || r.head_problems) && (
                    <div className="rounded-lg border border-red-200 dark:border-red-800/40 bg-red-50 dark:bg-red-900/10 p-3 space-y-1.5">
                      <p className="text-[10px] font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider">Issues Found</p>
                      {r.company_problems && <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5"><svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" /></svg>Company problems</p>}
                      {r.financial_problems && <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5"><svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" /></svg>Financial problems</p>}
                      {r.unreliable_zakup && <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5"><svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" /></svg>Unreliable procurement participant</p>}
                      {r.head_problems && <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5"><svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" /></svg>Director has issues</p>}
                    </div>
                  )}

                  {!(r.company_problems || r.financial_problems || r.unreliable_zakup || r.head_problems) && (
                    <div className="rounded-lg border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10 p-3 flex items-center gap-2">
                      <svg className="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      <p className="text-xs font-medium text-emerald-700 dark:text-emerald-400">No problems detected</p>
                    </div>
                  )}

                  {/* Company info */}
                  <div className="space-y-1.5 text-xs">
                    {r.company_name && (
                      <div className="flex justify-between gap-2 py-1 border-b border-slate-100 dark:border-slate-700/50">
                        <span className="text-slate-500 dark:text-slate-400 shrink-0">Company</span>
                        <span className="text-slate-800 dark:text-white font-medium text-right">{String(r.short_name || r.company_name)}</span>
                      </div>
                    )}
                    {r.director && (
                      <div className="flex justify-between gap-2 py-1 border-b border-slate-100 dark:border-slate-700/50">
                        <span className="text-slate-500 dark:text-slate-400 shrink-0">Director</span>
                        <span className="text-slate-800 dark:text-white font-medium text-right">{String(r.director)}</span>
                      </div>
                    )}
                    {r.registration_date && (
                      <div className="flex justify-between gap-2 py-1 border-b border-slate-100 dark:border-slate-700/50">
                        <span className="text-slate-500 dark:text-slate-400 shrink-0">Registered</span>
                        <span className="text-slate-800 dark:text-white font-medium">{String(r.registration_date)}</span>
                      </div>
                    )}
                    {r.employee_count != null && (
                      <div className="flex justify-between gap-2 py-1 border-b border-slate-100 dark:border-slate-700/50">
                        <span className="text-slate-500 dark:text-slate-400 shrink-0">Employees</span>
                        <span className="text-slate-800 dark:text-white font-medium">{String(r.employee_count)}</span>
                      </div>
                    )}
                    {r.oked && (
                      <div className="flex justify-between gap-2 py-1 border-b border-slate-100 dark:border-slate-700/50">
                        <span className="text-slate-500 dark:text-slate-400 shrink-0">Activity</span>
                        <span className="text-slate-800 dark:text-white font-medium text-right">{String(r.oked)}</span>
                      </div>
                    )}
                    {r.legal_address && (
                      <div className="py-1 border-b border-slate-100 dark:border-slate-700/50">
                        <span className="text-slate-500 dark:text-slate-400">Address</span>
                        <p className="text-slate-800 dark:text-white font-medium mt-0.5 leading-relaxed">{String(r.legal_address)}</p>
                      </div>
                    )}
                  </div>

                  {/* Source link */}
                  {r.source_link && (
                    <a
                      href={String(r.source_link)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1.5 text-[11px] text-blue-600 dark:text-blue-400 hover:underline mt-1"
                    >
                      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                      View full report on ADATA
                    </a>
                  )}
                </div>)
              })()}
            </div>
          </div>

          {/* Lawyer Actions */}
          {isLawyer && (
            <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
              <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
                <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Lawyer Actions</h2>
              </div>
              <div className="p-5">
                {isBlacklisted ? (
                  <button
                    onClick={() => unblacklistMutation.mutate()}
                    disabled={unblacklistMutation.isPending}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition-colors disabled:opacity-50"
                  >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Remove from Blacklist
                  </button>
                ) : (
                  <button
                    onClick={() => setShowBlacklistModal(true)}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 text-sm font-medium hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors"
                  >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                    Add to Blacklist
                  </button>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Blacklist modal */}
      <Modal
        open={showBlacklistModal}
        onClose={() => setShowBlacklistModal(false)}
        title="Blacklist Partner"
        footer={
          <>
            <Button variant="secondary" onClick={() => setShowBlacklistModal(false)}>Cancel</Button>
            <Button variant="danger" loading={blacklistMutation.isPending} disabled={!blacklistReason.trim()} onClick={() => blacklistMutation.mutate()}>
              Blacklist
            </Button>
          </>
        }
      >
        <p className="text-sm text-slate-600 dark:text-slate-300 mb-3">
          Provide a reason for blacklisting <strong>{partner.name}</strong>.
        </p>
        <textarea
          value={blacklistReason}
          onChange={(e) => setBlacklistReason(e.target.value)}
          rows={3}
          required
          placeholder="Required reason..."
          className="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none"
        />
      </Modal>
    </div>
  )
}

function DetailItem({ label, value, mono, pre, link }: {
  label: string; value: string; mono?: boolean; pre?: boolean; link?: string
}) {
  return (
    <div>
      <dt className="text-[11px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-1">{label}</dt>
      <dd className={`text-sm text-slate-800 dark:text-slate-100 ${mono ? 'font-mono' : ''} ${pre ? 'whitespace-pre-wrap' : ''}`}>
        {link ? <a href={link} className="text-blue-600 dark:text-blue-400 hover:underline">{value}</a> : value}
      </dd>
    </div>
  )
}
