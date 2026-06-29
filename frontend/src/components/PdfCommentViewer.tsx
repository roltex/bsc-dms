import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Document, Page, pdfjs } from 'react-pdf'
import 'react-pdf/dist/Page/AnnotationLayer.css'
import 'react-pdf/dist/Page/TextLayer.css'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchTaskComments,
  createTaskComment,
  updateTaskComment,
  deleteTaskComment,
  type TaskComment,
} from '../api/tasks'
import { useAuth } from '../contexts/AuthContext'

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.min.mjs',
  import.meta.url,
).toString()

interface Props {
  pdfUrl: string
  taskId: number
  documentId: number
}

interface PendingPin {
  page: number
  x: number
  y: number
}

const ZOOM_LEVELS = [0.5, 0.75, 1, 1.25, 1.5, 2]

export default function PdfCommentViewer({ pdfUrl, taskId, documentId }: Props) {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [numPages, setNumPages] = useState(0)
  const [containerWidth, setContainerWidth] = useState(800)
  const [zoomIdx, setZoomIdx] = useState(2)
  const [activeCommentId, setActiveCommentId] = useState<number | null>(null)
  const [pendingPin, setPendingPin] = useState<PendingPin | null>(null)
  const [newBody, setNewBody] = useState('')
  const [replyBodies, setReplyBodies] = useState<Record<number, string>>({})
  const [showResolved, setShowResolved] = useState(false)
  const [commentMode, setCommentMode] = useState(false)
  const [panelOpen, setPanelOpen] = useState(true)
  const [currentPage, setCurrentPage] = useState(1)
  const containerRef = useRef<HTMLDivElement>(null)
  const scrollRef = useRef<HTMLDivElement>(null)
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const pageRefs = useRef<Map<number, HTMLDivElement>>(new Map())

  const zoom = ZOOM_LEVELS[zoomIdx]
  const pageWidth = Math.min(containerWidth - 48, 1100) * zoom

  useEffect(() => {
    const el = containerRef.current
    if (!el) return
    const obs = new ResizeObserver((entries) => {
      const w = entries[0]?.contentRect.width
      if (w && w > 100) setContainerWidth(w)
    })
    obs.observe(el)
    return () => obs.disconnect()
  }, [])

  useEffect(() => {
    const el = scrollRef.current
    if (!el || numPages === 0) return
    const handleScroll = () => {
      const pages = pageRefs.current
      let closest = 1
      let closestDist = Infinity
      pages.forEach((div, num) => {
        const rect = div.getBoundingClientRect()
        const containerRect = el.getBoundingClientRect()
        const dist = Math.abs(rect.top - containerRect.top)
        if (dist < closestDist) { closestDist = dist; closest = num }
      })
      setCurrentPage(closest)
    }
    el.addEventListener('scroll', handleScroll, { passive: true })
    return () => el.removeEventListener('scroll', handleScroll)
  }, [numPages])

  const { data: comments = [], isLoading: commentsLoading } = useQuery({
    queryKey: ['task-comments', taskId, documentId],
    queryFn: () => fetchTaskComments(taskId, documentId),
    refetchInterval: 30000,
  })

  const invalidateComments = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['task-comments', taskId, documentId] })
    queryClient.invalidateQueries({ queryKey: ['task', String(taskId)] })
  }, [queryClient, taskId, documentId])

  const createMut = useMutation({
    mutationFn: (p: Parameters<typeof createTaskComment>[1]) => createTaskComment(taskId, p),
    onSuccess: () => {
      invalidateComments()
      setPendingPin(null)
      setNewBody('')
      setCommentMode(false)
    },
  })

  const updateMut = useMutation({
    mutationFn: ({ commentId, payload }: { commentId: number; payload: Parameters<typeof updateTaskComment>[2] }) =>
      updateTaskComment(taskId, commentId, payload),
    onSuccess: invalidateComments,
  })

  const deleteMut = useMutation({
    mutationFn: (commentId: number) => deleteTaskComment(taskId, commentId),
    onSuccess: () => { invalidateComments(); setActiveCommentId(null) },
  })

  const visibleComments = useMemo(
    () => (showResolved ? comments : comments.filter((c) => !c.resolved)),
    [comments, showResolved],
  )

  const commentsByPage = useMemo(() => {
    const map = new Map<number, TaskComment[]>()
    for (const c of visibleComments) {
      if (c.page == null) continue
      const arr = map.get(c.page) || []
      arr.push(c)
      map.set(c.page, arr)
    }
    return map
  }, [visibleComments])

  const openCount = useMemo(() => comments.filter(c => !c.resolved).length, [comments])
  const resolvedCount = useMemo(() => comments.filter(c => c.resolved).length, [comments])

  const handlePageClick = (page: number, e: React.MouseEvent<HTMLDivElement>) => {
    if (!commentMode) return
    const rect = e.currentTarget.getBoundingClientRect()
    const x = ((e.clientX - rect.left) / rect.width) * 100
    const y = ((e.clientY - rect.top) / rect.height) * 100
    setPendingPin({ page, x, y })
    setNewBody('')
    setPanelOpen(true)
    setTimeout(() => textareaRef.current?.focus(), 50)
  }

  const submitComment = () => {
    if (!pendingPin || !newBody.trim()) return
    createMut.mutate({
      document_id: documentId,
      page: pendingPin.page,
      x_percent: Math.round(pendingPin.x * 100) / 100,
      y_percent: Math.round(pendingPin.y * 100) / 100,
      body: newBody.trim(),
    })
  }

  const submitReply = (parentId: number) => {
    const body = replyBodies[parentId]?.trim()
    if (!body) return
    const parent = comments.find((c) => c.id === parentId)
    if (!parent) return
    createMut.mutate({
      document_id: documentId,
      page: parent.page,
      x_percent: parent.x_percent,
      y_percent: parent.y_percent,
      body,
      parent_id: parentId,
    })
    setReplyBodies((prev) => ({ ...prev, [parentId]: '' }))
  }

  const scrollToComment = (id: number) => {
    setActiveCommentId(id)
    setPanelOpen(true)
    const el = document.getElementById(`comment-thread-${id}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
  }

  const scrollToPage = (page: number) => {
    const div = pageRefs.current.get(page)
    if (div) div.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const getInitials = (name: string) =>
    name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)

  const pinIndex = (comment: TaskComment) => {
    const idx = visibleComments.findIndex((c) => c.id === comment.id)
    return idx + 1
  }

  return (
    <div className="flex flex-col rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-800 dark:bg-slate-900" style={{ height: '82vh', minHeight: '500px' }}>
      {/* Top toolbar */}
      <div className="flex items-center gap-1 px-3 py-2 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
        {/* Comment mode toggle */}
        <button
          onClick={() => { setCommentMode(!commentMode); setPendingPin(null) }}
          className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
            commentMode
              ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/25'
              : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600'
          }`}
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
          </svg>
          {commentMode ? 'Click on PDF to pin' : 'Comment'}
        </button>

        {commentMode && (
          <button
            onClick={() => { setCommentMode(false); setPendingPin(null) }}
            className="px-2 py-1.5 rounded-lg text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700"
          >
            Cancel
          </button>
        )}

        <div className="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-1" />

        {/* Zoom controls */}
        <button
          onClick={() => setZoomIdx(Math.max(0, zoomIdx - 1))}
          disabled={zoomIdx === 0}
          className="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 transition-colors"
          title="Zoom out"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM13.5 10.5h-6" /></svg>
        </button>
        <span className="text-xs font-medium text-slate-600 dark:text-slate-300 w-12 text-center tabular-nums">
          {Math.round(zoom * 100)}%
        </span>
        <button
          onClick={() => setZoomIdx(Math.min(ZOOM_LEVELS.length - 1, zoomIdx + 1))}
          disabled={zoomIdx === ZOOM_LEVELS.length - 1}
          className="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 transition-colors"
          title="Zoom in"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" /></svg>
        </button>

        <div className="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-1" />

        {/* Page nav */}
        <div className="flex items-center gap-1">
          <button
            onClick={() => scrollToPage(Math.max(1, currentPage - 1))}
            disabled={currentPage <= 1}
            className="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 transition-colors"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
          </button>
          <span className="text-xs text-slate-600 dark:text-slate-300 tabular-nums whitespace-nowrap">
            {currentPage} / {numPages || '—'}
          </span>
          <button
            onClick={() => scrollToPage(Math.min(numPages, currentPage + 1))}
            disabled={currentPage >= numPages}
            className="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 transition-colors"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </div>

        <div className="flex-1" />

        {/* Comment stats + toggle */}
        {comments.length > 0 && (
          <div className="flex items-center gap-2 mr-1">
            {openCount > 0 && (
              <span className="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 font-medium">
                <span className="w-2 h-2 rounded-full bg-blue-500" />
                {openCount} open
              </span>
            )}
            {resolvedCount > 0 && (
              <span className="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400 font-medium">
                <span className="w-2 h-2 rounded-full bg-green-500" />
                {resolvedCount} resolved
              </span>
            )}
          </div>
        )}

        <button
          onClick={() => setPanelOpen(!panelOpen)}
          className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
            panelOpen
              ? 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200'
              : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'
          }`}
          title={panelOpen ? 'Hide comments panel' : 'Show comments panel'}
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
          </svg>
          {panelOpen ? 'Hide' : 'Comments'}
          {!panelOpen && comments.length > 0 && (
            <span className="bg-blue-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
              {openCount || comments.length}
            </span>
          )}
        </button>
      </div>

      {/* Main area: PDF + optional panel */}
      <div className="flex flex-1 overflow-hidden">
        {/* PDF scroll area */}
        <div
          ref={scrollRef}
          className="flex-1 overflow-auto"
        >
          <div ref={containerRef} className="min-h-full">
            <Document
              file={pdfUrl}
              onLoadSuccess={({ numPages: n }) => setNumPages(n)}
              loading={
                <div className="flex items-center justify-center py-32">
                  <div className="flex flex-col items-center gap-3">
                    <div className="animate-spin w-8 h-8 border-2 border-blue-400 border-t-transparent rounded-full" />
                    <span className="text-xs text-slate-400">Loading document...</span>
                  </div>
                </div>
              }
              error={
                <div className="flex items-center justify-center py-32 text-slate-400">
                  <div className="flex flex-col items-center gap-2">
                    <svg className="w-10 h-10" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <span className="text-sm">Failed to load PDF</span>
                  </div>
                </div>
              }
            >
              <div className="py-6 flex flex-col items-center gap-4">
                {Array.from({ length: numPages }, (_, i) => {
                  const pageNum = i + 1
                  const pageComments = commentsByPage.get(pageNum) || []
                  return (
                    <div
                      key={pageNum}
                      ref={(el) => { if (el) pageRefs.current.set(pageNum, el); else pageRefs.current.delete(pageNum) }}
                      className="relative shadow-xl rounded-sm"
                      style={{ width: pageWidth }}
                    >
                      <Page
                        pageNumber={pageNum}
                        width={pageWidth}
                        renderTextLayer={true}
                        renderAnnotationLayer={true}
                      />

                      {commentMode && (
                        <div
                          className="absolute inset-0 z-10 cursor-crosshair bg-blue-500/[0.03] hover:bg-blue-500/[0.06] transition-colors"
                          onClick={(e) => handlePageClick(pageNum, e)}
                        />
                      )}

                      {pageComments.map((c) => (
                        <button
                          key={c.id}
                          className={`absolute z-10 group transition-all duration-150 ${c.resolved ? 'opacity-50 hover:opacity-100' : ''}`}
                          style={{ left: `${c.x_percent}%`, top: `${c.y_percent}%` }}
                          onClick={(e) => { e.stopPropagation(); scrollToComment(c.id) }}
                        >
                          <div className={`w-7 h-7 -ml-3.5 -mt-3.5 rounded-full flex items-center justify-center text-[10px] font-bold shadow-lg ring-2 transition-transform group-hover:scale-110 ${
                            c.resolved
                              ? 'bg-green-500 text-white ring-green-300/50'
                              : activeCommentId === c.id
                                ? 'bg-blue-600 text-white ring-blue-300 scale-110'
                                : 'bg-blue-500 text-white ring-white/80 group-hover:ring-blue-300'
                          }`}>
                            {pinIndex(c)}
                          </div>
                          <div className="absolute left-4 top-0 hidden group-hover:block z-20 pointer-events-none">
                            <div className="bg-slate-900/90 text-white text-[11px] rounded-lg px-2.5 py-1.5 max-w-[200px] shadow-xl backdrop-blur whitespace-nowrap overflow-hidden text-ellipsis">
                              <span className="font-semibold">{c.user.name}</span>: {c.body.slice(0, 80)}{c.body.length > 80 ? '...' : ''}
                            </div>
                          </div>
                        </button>
                      ))}

                      {pendingPin && pendingPin.page === pageNum && (
                        <div
                          className="absolute z-10"
                          style={{ left: `${pendingPin.x}%`, top: `${pendingPin.y}%` }}
                        >
                          <div className="w-7 h-7 -ml-3.5 -mt-3.5 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-bold shadow-lg ring-2 ring-orange-300 animate-bounce">
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                          </div>
                        </div>
                      )}

                      {/* Page footer */}
                      <div className="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[10px] text-slate-500 dark:text-slate-500 font-medium tabular-nums">
                        {pageNum}
                      </div>
                    </div>
                  )
                })}
              </div>
            </Document>
          </div>
        </div>

        {/* Comment panel */}
        {panelOpen && (
          <div className="w-80 xl:w-96 flex-shrink-0 border-l border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex flex-col overflow-hidden">
            {/* Panel header */}
            <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between flex-shrink-0">
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Comments</h3>
                {comments.length > 0 && (
                  <span className="text-[10px] font-medium text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded-full">{comments.length}</span>
                )}
              </div>
              <label className="inline-flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={showResolved}
                  onChange={(e) => setShowResolved(e.target.checked)}
                  className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3 h-3"
                />
                Resolved
              </label>
            </div>

            {/* New comment composer */}
            {pendingPin && (
              <div className="p-3 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-b from-blue-50/80 to-white dark:from-blue-900/10 dark:to-slate-800 flex-shrink-0">
                <div className="flex items-center gap-2 mb-2.5">
                  <div className="w-5 h-5 rounded-full bg-orange-500 text-white flex items-center justify-center">
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                  </div>
                  <span className="text-xs font-medium text-slate-700 dark:text-slate-300">
                    New comment on page {pendingPin.page}
                  </span>
                  <button
                    onClick={() => { setPendingPin(null); setNewBody('') }}
                    className="ml-auto p-0.5 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-700/50"
                  >
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
                <textarea
                  ref={textareaRef}
                  value={newBody}
                  onChange={(e) => setNewBody(e.target.value)}
                  placeholder="Write your comment..."
                  rows={3}
                  className="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-2 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) submitComment()
                    if (e.key === 'Escape') { setPendingPin(null); setNewBody('') }
                  }}
                />
                <div className="flex items-center justify-between mt-2">
                  <span className="text-[10px] text-slate-400">Ctrl+Enter to post &middot; Esc to cancel</span>
                  <button
                    onClick={submitComment}
                    disabled={!newBody.trim() || createMut.isPending}
                    className="px-4 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-sm"
                  >
                    {createMut.isPending ? 'Posting...' : 'Post Comment'}
                  </button>
                </div>
              </div>
            )}

            {/* Comments list */}
            <div className="flex-1 overflow-y-auto">
              {commentsLoading ? (
                <div className="flex items-center justify-center py-16">
                  <div className="animate-spin w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full" />
                </div>
              ) : visibleComments.length === 0 && !pendingPin ? (
                <div className="flex flex-col items-center justify-center py-16 px-6 text-center">
                  <div className="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center mb-3">
                    <svg className="w-7 h-7 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                  </div>
                  <p className="text-sm font-medium text-slate-700 dark:text-slate-300">No comments yet</p>
                  <p className="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-[220px]">Click the "Comment" button, then click anywhere on the document to leave a note.</p>
                </div>
              ) : (
                <div>
                  {visibleComments.map((c) => (
                    <div
                      key={c.id}
                      id={`comment-thread-${c.id}`}
                      className={`border-b border-slate-100 dark:border-slate-700/50 transition-colors cursor-pointer hover:bg-slate-50/50 dark:hover:bg-slate-700/20 ${
                        activeCommentId === c.id ? 'bg-blue-50/60 dark:bg-blue-900/15 border-l-2 border-l-blue-500' : 'border-l-2 border-l-transparent'
                      }`}
                      onClick={() => setActiveCommentId(activeCommentId === c.id ? null : c.id)}
                    >
                      <div className="p-3">
                        <div className="flex items-start gap-2.5">
                          <div className={`flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm ${
                            c.resolved ? 'bg-green-500' : 'bg-blue-500'
                          }`}>
                            {pinIndex(c)}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between gap-2">
                              <span className="text-xs font-semibold text-slate-900 dark:text-white truncate">{c.user.name}</span>
                              <span className="text-[10px] text-slate-400 dark:text-slate-500 whitespace-nowrap flex-shrink-0">
                                {new Date(c.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                              </span>
                            </div>
                            <div className="flex items-center gap-1.5 mt-0.5 mb-1">
                              <span className="text-[10px] text-slate-400 dark:text-slate-500">Page {c.page}</span>
                              {c.resolved && (
                                <span className="text-[10px] font-medium text-green-600 dark:text-green-400 flex items-center gap-0.5">
                                  <svg className="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                  Resolved
                                </span>
                              )}
                              {c.replies?.length > 0 && (
                                <span className="text-[10px] text-slate-400">
                                  {c.replies.length} {c.replies.length === 1 ? 'reply' : 'replies'}
                                </span>
                              )}
                            </div>
                            <p className="text-[13px] text-slate-700 dark:text-slate-300 leading-relaxed break-words whitespace-pre-wrap">{c.body}</p>

                            <div className="flex items-center gap-3 mt-2">
                              <button
                                onClick={(e) => {
                                  e.stopPropagation()
                                  updateMut.mutate({ commentId: c.id, payload: { resolved: !c.resolved } })
                                }}
                                className={`text-[11px] font-medium transition-colors flex items-center gap-1 ${
                                  c.resolved
                                    ? 'text-green-600 dark:text-green-400 hover:text-orange-600'
                                    : 'text-slate-400 hover:text-green-600 dark:hover:text-green-400'
                                }`}
                              >
                                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {c.resolved ? 'Reopen' : 'Resolve'}
                              </button>
                              {c.user_id === user?.id && (
                                <button
                                  onClick={(e) => {
                                    e.stopPropagation()
                                    if (confirm('Delete this comment?')) deleteMut.mutate(c.id)
                                  }}
                                  className="text-[11px] text-slate-400 hover:text-red-500 font-medium transition-colors flex items-center gap-1"
                                >
                                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                  Delete
                                </button>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Expanded: replies + reply input */}
                      {activeCommentId === c.id && (
                        <div className="bg-slate-50/50 dark:bg-slate-900/20">
                          {c.replies?.length > 0 && (
                            <div className="px-3 pb-2 pt-1 space-y-2 ml-9 border-l-2 border-slate-200 dark:border-slate-700">
                              {c.replies.map((r) => (
                                <div key={r.id} className="flex items-start gap-2 py-1">
                                  <div className="flex-shrink-0 w-5 h-5 rounded-full bg-slate-300 dark:bg-slate-600 flex items-center justify-center text-[8px] font-bold text-white">
                                    {getInitials(r.user?.name || '?')}
                                  </div>
                                  <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-1.5">
                                      <span className="text-[11px] font-semibold text-slate-800 dark:text-slate-200">{r.user?.name}</span>
                                      <span className="text-[9px] text-slate-400">
                                        {new Date(r.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                      </span>
                                      {r.user_id === user?.id && (
                                        <button
                                          onClick={(e) => {
                                            e.stopPropagation()
                                            if (confirm('Delete this reply?')) deleteMut.mutate(r.id)
                                          }}
                                          className="text-[9px] text-slate-400 hover:text-red-500 font-medium ml-auto"
                                        >
                                          Delete
                                        </button>
                                      )}
                                    </div>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 break-words whitespace-pre-wrap leading-relaxed">{r.body}</p>
                                  </div>
                                </div>
                              ))}
                            </div>
                          )}

                          <div className="px-3 pb-3 pt-2 ml-9">
                            <div className="flex items-center gap-2">
                              <input
                                type="text"
                                value={replyBodies[c.id] || ''}
                                onChange={(e) => setReplyBodies((prev) => ({ ...prev, [c.id]: e.target.value }))}
                                placeholder="Write a reply..."
                                className="flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-2.5 py-1.5 text-xs placeholder:text-slate-400 focus:ring-1 focus:ring-blue-500"
                                onKeyDown={(e) => {
                                  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitReply(c.id) }
                                }}
                                onClick={(e) => e.stopPropagation()}
                              />
                              <button
                                onClick={(e) => { e.stopPropagation(); submitReply(c.id) }}
                                disabled={!replyBodies[c.id]?.trim() || createMut.isPending}
                                className="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-[10px] font-semibold hover:bg-blue-700 disabled:opacity-40 transition-colors"
                              >
                                Reply
                              </button>
                            </div>
                          </div>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
