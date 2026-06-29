import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import {
  fetchFinalizedDocs,
  uploadFinalizedDoc,
  deleteFinalizedDoc,
  downloadFinalizedDoc,
  fetchFinalizedDocCategories,
} from '../api/finalizedDocs'
import { fetchUsers } from '../api/users'
import { useToast } from '../contexts/ToastContext'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'

const FILE_TYPE_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'All types' },
  { value: 'pdf', label: 'PDF' },
  { value: 'docx', label: 'Word' },
  { value: 'xlsx', label: 'Excel' },
  { value: 'image', label: 'Image' },
  { value: 'other', label: 'Other' },
]

const SIZE_OPTIONS: { value: string; label: string; min?: number; max?: number }[] = [
  { value: '', label: 'Any size' },
  { value: 'small', label: '< 100 KB', max: 102400 },
  { value: 'medium', label: '100 KB – 1 MB', min: 102400, max: 1048576 },
  { value: 'large', label: '1 – 10 MB', min: 1048576, max: 10485760 },
  { value: 'huge', label: '> 10 MB', min: 10485760 },
]

type SortField = 'created_at' | 'name' | 'size'
type SortDir = 'asc' | 'desc'

const CATEGORY_COLORS: Record<string, string> = {
  licenses: 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400',
  court_materials: 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-400',
  corporate_docs: 'bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-400',
  government_inspections: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400',
  other: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400',
}

const CATEGORY_SHORT: Record<string, string> = {
  licenses: 'Licenses',
  court_materials: 'Court',
  corporate_docs: 'Corporate',
  government_inspections: 'Gov. Inspections',
  other: 'Other',
}

function formatSize(bytes: number): string {
  if (bytes > 1048576) return `${(bytes / 1048576).toFixed(1)} MB`
  if (bytes > 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${bytes} B`
}

function getFileExt(name: string, mimeType?: string | null): string {
  const parts = name.split('.')
  if (parts.length > 1) {
    const ext = parts.pop()!.toUpperCase()
    if (ext.length <= 5) return ext
  }
  if (mimeType) {
    if (mimeType.includes('pdf')) return 'PDF'
    if (mimeType.includes('word') || mimeType.includes('docx')) return 'DOCX'
    if (mimeType.includes('sheet') || mimeType.includes('xlsx') || mimeType.includes('excel')) return 'XLSX'
    if (mimeType.includes('image')) return 'IMG'
  }
  return 'DOC'
}

export default function FinalizedDocsPage() {
  const { addToast } = useToast()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [uploaderId, setUploaderId] = useState<string>('')
  const [fileType, setFileType] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [sizeBucket, setSizeBucket] = useState('')
  const [showAdvanced, setShowAdvanced] = useState(false)
  const [sortField, setSortField] = useState<SortField>('created_at')
  const [sortDir, setSortDir] = useState<SortDir>('desc')
  const [page, setPage] = useState(1)
  const [showUpload, setShowUpload] = useState(false)
  const [uploadCategory, setUploadCategory] = useState('')
  const [uploadNotes, setUploadNotes] = useState('')
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [deleteConfirm, setDeleteConfirm] = useState<number | null>(null)

  const { data: categories } = useQuery({
    queryKey: ['finalized-doc-categories'],
    queryFn: fetchFinalizedDocCategories,
  })

  const { data: users } = useQuery({
    queryKey: ['users-all'],
    queryFn: () => fetchUsers(),
  })

  const sizeSel = SIZE_OPTIONS.find(o => o.value === sizeBucket)

  const { data, isLoading } = useQuery({
    queryKey: ['finalized-docs', search, category, uploaderId, fileType, dateFrom, dateTo, sizeBucket, sortField, sortDir, page],
    queryFn: () => fetchFinalizedDocs({
      search: search || undefined,
      category: category || undefined,
      user_id: uploaderId ? Number(uploaderId) : undefined,
      file_type: fileType || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      min_size: sizeSel?.min,
      max_size: sizeSel?.max,
      sort: sortField,
      dir: sortDir,
      page,
      per_page: 20,
    }),
  })

  const uploadMutation = useMutation({
    mutationFn: () => {
      const fd = new FormData()
      fd.append('document', uploadFile!)
      fd.append('category', uploadCategory)
      if (uploadNotes) fd.append('notes', uploadNotes)
      return uploadFinalizedDoc(fd)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['finalized-docs'] })
      setShowUpload(false)
      setUploadFile(null)
      setUploadCategory('')
      setUploadNotes('')
      addToast('Document uploaded')
    },
    onError: () => addToast('Upload failed', 'error'),
  })

  const [downloadingId, setDownloadingId] = useState<number | null>(null)

  const handleDownload = async (id: number) => {
    setDownloadingId(id)
    try {
      await downloadFinalizedDoc(id)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Download failed'
      addToast(msg, 'error')
    } finally {
      setDownloadingId(null)
    }
  }

  const deleteMutation = useMutation({
    mutationFn: deleteFinalizedDoc,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['finalized-docs'] })
      setDeleteConfirm(null)
      addToast('Document deleted')
    },
  })

  const docs = data?.data ?? []
  const total = data?.total ?? 0
  const categoryLabels = categories || {}

  function toggleSort(field: SortField) {
    if (sortField === field) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc')
    } else {
      setSortField(field)
      setSortDir(field === 'name' ? 'asc' : 'desc')
    }
    setPage(1)
  }

  const uploaderName = users?.find(u => u.id === Number(uploaderId))?.name
  const fileTypeLabel = FILE_TYPE_OPTIONS.find(o => o.value === fileType)?.label
  const sizeLabel = SIZE_OPTIONS.find(o => o.value === sizeBucket)?.label
  type Chip = { label: string; clear: () => void }
  const activeChips: Chip[] = [
    search ? { label: `Search: "${search}"`, clear: () => { setSearch(''); setPage(1) } } : null,
    category ? { label: `Category: ${categoryLabels[category] || category}`, clear: () => { setCategory(''); setPage(1) } } : null,
    uploaderId ? { label: `Uploader: ${uploaderName || '—'}`, clear: () => { setUploaderId(''); setPage(1) } } : null,
    fileType && fileType !== '' ? { label: `Type: ${fileTypeLabel}`, clear: () => { setFileType(''); setPage(1) } } : null,
    sizeBucket ? { label: `Size: ${sizeLabel}`, clear: () => { setSizeBucket(''); setPage(1) } } : null,
    dateFrom ? { label: `From: ${dateFrom}`, clear: () => { setDateFrom(''); setPage(1) } } : null,
    dateTo ? { label: `To: ${dateTo}`, clear: () => { setDateTo(''); setPage(1) } } : null,
  ].filter((c): c is Chip => c !== null)

  const clearAll = () => {
    setSearch(''); setCategory(''); setUploaderId(''); setFileType(''); setDateFrom(''); setDateTo(''); setSizeBucket(''); setPage(1)
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white">Finalized Documents</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {total} document{total !== 1 ? 's' : ''} — licenses, court materials, corporate docs
          </p>
        </div>
        <button
          onClick={() => setShowUpload(true)}
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors shadow-sm"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
          </svg>
          Upload Document
        </button>
      </div>

      {/* Table Card */}
      <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
        {/* Toolbar */}
        <div className="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 space-y-3">
          <div className="flex flex-wrap items-center gap-2.5">
            {/* Search */}
            <div className="relative flex-1 min-w-[200px] max-w-sm">
              <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <input
                type="search"
                placeholder="Search by name, notes, uploader..."
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1) }}
                className="w-full pl-9 pr-4 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all"
              />
            </div>

            {/* Category filter */}
            <select
              value={category}
              onChange={(e) => { setCategory(e.target.value); setPage(1) }}
              className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
            >
              <option value="">All categories</option>
              {Object.entries(categoryLabels).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>

            {/* File Type */}
            <select
              value={fileType}
              onChange={(e) => { setFileType(e.target.value); setPage(1) }}
              className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
            >
              {FILE_TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>

            {/* Advanced toggle */}
            <button
              onClick={() => setShowAdvanced(s => !s)}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all ${
                showAdvanced || uploaderId || sizeBucket || dateFrom || dateTo
                  ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300'
                  : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/40'
              }`}
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
              Advanced
              {(uploaderId || sizeBucket || dateFrom || dateTo) && (
                <span className="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-blue-500 text-[9px] font-bold text-white">{[uploaderId, sizeBucket, dateFrom, dateTo].filter(Boolean).length}</span>
              )}
            </button>

            {activeChips.length > 0 && (
              <button
                onClick={clearAll}
                className="text-xs text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors inline-flex items-center gap-1 ml-auto"
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                Clear all
              </button>
            )}
          </div>

          {/* Advanced panel */}
          {showAdvanced && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/50">
              <div>
                <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Uploader</label>
                <select
                  value={uploaderId}
                  onChange={(e) => { setUploaderId(e.target.value); setPage(1) }}
                  className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                >
                  <option value="">All uploaders</option>
                  {users?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
              </div>
              <div>
                <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">File size</label>
                <select
                  value={sizeBucket}
                  onChange={(e) => { setSizeBucket(e.target.value); setPage(1) }}
                  className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                >
                  {SIZE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
              <div>
                <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Uploaded from</label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => { setDateFrom(e.target.value); setPage(1) }}
                  className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                />
              </div>
              <div>
                <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Uploaded to</label>
                <input
                  type="date"
                  value={dateTo}
                  min={dateFrom || undefined}
                  onChange={(e) => { setDateTo(e.target.value); setPage(1) }}
                  className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                />
              </div>
            </div>
          )}

          {/* Active filter chips */}
          {activeChips.length > 0 && (
            <div className="flex items-center gap-1.5 flex-wrap">
              <span className="text-[11px] text-slate-400 dark:text-slate-500 mr-1">Active:</span>
              {activeChips.map((chip, i) => (
                <span key={i} className="group inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-md bg-blue-50 dark:bg-blue-500/10 text-[11px] font-medium text-blue-700 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/50">
                  {chip.label}
                  <button onClick={chip.clear} className="inline-flex items-center justify-center w-3.5 h-3.5 rounded-sm text-blue-400 dark:text-blue-500 hover:bg-blue-500/20 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">
                    <svg className="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </span>
              ))}
            </div>
          )}
        </div>

        {/* Table */}
        {isLoading ? (
          <div className="divide-y divide-slate-100 dark:divide-slate-700/40">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="px-4 py-3.5 animate-pulse">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 bg-slate-200 dark:bg-slate-700 rounded-lg" />
                  <div className="flex-1 space-y-1.5">
                    <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-2/5" />
                    <div className="h-2.5 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : !docs.length ? (
          <div className="flex flex-col items-center justify-center py-16 px-6">
            <div className="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-3">
              <svg className="w-7 h-7 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
            </div>
            <p className="text-sm font-medium text-slate-600 dark:text-slate-300">No documents found</p>
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
              {activeChips.length > 0 ? 'Try adjusting your filters' : 'Upload your first finalized document'}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full table-fixed">
              <colgroup>
                <col className="w-[40%]" />
                <col className="w-[15%]" />
                <col className="w-[15%] hidden md:table-column" />
                <col className="w-[8%]" />
                <col className="w-[12%]" />
                <col className="w-[10%]" />
              </colgroup>
              <thead>
                <tr className="border-b border-slate-100 dark:border-slate-700/50">
                  <SortTh active={sortField === 'name'} dir={sortDir} onClick={() => toggleSort('name')}>Document</SortTh>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Category</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 hidden md:table-cell">Uploaded By</th>
                  <SortTh active={sortField === 'size'} dir={sortDir} onClick={() => toggleSort('size')}>Size</SortTh>
                  <SortTh active={sortField === 'created_at'} dir={sortDir} onClick={() => toggleSort('created_at')}>Date</SortTh>
                  <th className="text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/40">
                {docs.map(doc => {
                  const ext = getFileExt(doc.name, doc.mime_type)
                  const extColor = ext === 'PDF' ? 'bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-400'
                    : ext === 'DOCX' || ext === 'DOC' ? 'bg-blue-100 dark:bg-blue-500/15 text-blue-600 dark:text-blue-400'
                    : ext === 'XLSX' || ext === 'XLS' ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400'

                  return (
                    <tr key={doc.id} className="group hover:bg-slate-50/70 dark:hover:bg-slate-700/20 transition-colors">
                      {/* Document name */}
                      <td className="px-4 py-3 overflow-hidden">
                        <div className="flex items-center gap-3 min-w-0">
                          <div className={`flex-shrink-0 w-9 h-9 rounded-lg ${extColor} flex items-center justify-center overflow-hidden`}>
                            <span className="text-[10px] font-bold leading-none">{ext}</span>
                          </div>
                          <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-slate-800 dark:text-white truncate">{doc.name}</p>
                            {doc.notes && (
                              <p className="text-[11px] text-slate-400 dark:text-slate-500 truncate">{doc.notes}</p>
                            )}
                          </div>
                        </div>
                      </td>

                      {/* Category */}
                      <td className="px-4 py-3">
                        <span className={`inline-flex px-2 py-0.5 rounded-md text-[11px] font-semibold whitespace-nowrap ${CATEGORY_COLORS[doc.category] ?? CATEGORY_COLORS.other}`}>
                          {CATEGORY_SHORT[doc.category] || doc.category}
                        </span>
                      </td>

                      {/* Uploaded by */}
                      <td className="px-4 py-3 hidden md:table-cell">
                        <span className="text-sm text-slate-500 dark:text-slate-400">{doc.user?.name ?? '—'}</span>
                      </td>

                      {/* Size */}
                      <td className="px-4 py-3">
                        <span className="text-xs text-slate-500 dark:text-slate-400 font-mono">{formatSize(doc.size)}</span>
                      </td>

                      {/* Date */}
                      <td className="px-4 py-3">
                        <span className="text-xs text-slate-400 dark:text-slate-500">
                          {new Date(doc.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <button
                            onClick={() => handleDownload(doc.id)}
                            disabled={downloadingId === doc.id}
                            className="p-1.5 rounded-md text-slate-400 dark:text-slate-500 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors disabled:opacity-40"
                            title="Download"
                          >
                            {downloadingId === doc.id ? (
                              <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            ) : (
                              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            )}
                          </button>
                          <button
                            onClick={() => setDeleteConfirm(doc.id)}
                            className="p-1.5 rounded-md text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                            title="Delete"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {data && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-700/50">
            <p className="text-xs text-slate-400 dark:text-slate-500">
              Showing {docs.length} of {data.total} · Page {data.current_page} of {data.last_page}
            </p>
            {data.last_page > 1 && (
              <div className="flex gap-1.5">
                <button
                  onClick={() => setPage(p => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-40 disabled:pointer-events-none transition-colors"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage(p => Math.min(data.last_page, p + 1))}
                  disabled={page >= data.last_page}
                  className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-40 disabled:pointer-events-none transition-colors"
                >
                  Next
                </button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Upload Modal */}
      <Modal
        open={showUpload}
        onClose={() => setShowUpload(false)}
        title="Upload Finalized Document"
        footer={
          <>
            <Button variant="secondary" onClick={() => setShowUpload(false)}>Cancel</Button>
            <Button loading={uploadMutation.isPending} disabled={!uploadFile || !uploadCategory} onClick={() => uploadMutation.mutate()}>
              Upload
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="block text-[13px] font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              Category <span className="text-red-500">*</span>
            </label>
            <select
              value={uploadCategory}
              onChange={(e) => setUploadCategory(e.target.value)}
              required
              className="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
            >
              <option value="">Select category</option>
              {Object.entries(categoryLabels).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-[13px] font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              File <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              {uploadFile ? (
                <div className="flex items-center gap-3 p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                  <div className="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-500/15 flex items-center justify-center">
                    <span className="text-[10px] font-bold text-blue-600 dark:text-blue-400">{getFileExt(uploadFile.name)}</span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-slate-800 dark:text-white truncate">{uploadFile.name}</p>
                    <p className="text-[11px] text-slate-400">{formatSize(uploadFile.size)}</p>
                  </div>
                  <button onClick={() => setUploadFile(null)} className="p-1 rounded text-slate-400 hover:text-red-500 transition-colors">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
              ) : (
                <label className="flex flex-col items-center justify-center p-6 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 dark:hover:border-blue-500 bg-slate-50 dark:bg-slate-900/40 cursor-pointer transition-colors">
                  <svg className="w-8 h-8 text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                  <p className="text-sm font-medium text-slate-600 dark:text-slate-300">Click to choose file</p>
                  <p className="text-[11px] text-slate-400 mt-0.5">PDF, DOCX, XLSX — Max 20MB</p>
                  <input type="file" className="hidden" onChange={(e) => setUploadFile(e.target.files?.[0] ?? null)} />
                </label>
              )}
            </div>
          </div>
          <div>
            <label className="block text-[13px] font-medium text-slate-700 dark:text-slate-300 mb-1.5">Notes</label>
            <textarea
              value={uploadNotes}
              onChange={(e) => setUploadNotes(e.target.value)}
              rows={3}
              placeholder="Optional description..."
              className="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all resize-none"
            />
          </div>
        </div>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        open={deleteConfirm !== null}
        onClose={() => setDeleteConfirm(null)}
        title="Delete Document"
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleteConfirm(null)}>Cancel</Button>
            <Button variant="danger" loading={deleteMutation.isPending} onClick={() => deleteConfirm && deleteMutation.mutate(deleteConfirm)}>
              Delete
            </Button>
          </>
        }
      >
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Are you sure you want to delete this document? This action cannot be undone.
        </p>
      </Modal>
    </div>
  )
}

function SortTh({ active, dir, onClick, children }: {
  active: boolean; dir: SortDir; onClick: () => void; children: React.ReactNode
}) {
  return (
    <th
      onClick={onClick}
      className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 cursor-pointer hover:text-slate-700 dark:hover:text-slate-200 transition-colors select-none"
    >
      <span className="inline-flex items-center gap-1">
        {children}
        {active ? (
          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor">
            {dir === 'asc'
              ? <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
              : <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            }
          </svg>
        ) : (
          <svg className="w-3 h-3 opacity-30" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
          </svg>
        )}
      </span>
    </th>
  )
}
