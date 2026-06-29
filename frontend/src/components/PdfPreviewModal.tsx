import { useCallback, useEffect, useRef, useState } from 'react'
import { Document, Page, pdfjs } from 'react-pdf'
import 'react-pdf/dist/Page/AnnotationLayer.css'
import 'react-pdf/dist/Page/TextLayer.css'

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.min.mjs',
  import.meta.url,
).toString()

interface Props {
  open: boolean
  onClose: () => void
  pdfUrl: string | null
  title?: string
  loading?: boolean
}

const ZOOM_LEVELS = [0.5, 0.75, 1, 1.25, 1.5, 2]

export default function PdfPreviewModal({ open, onClose, pdfUrl, title, loading }: Props) {
  const [numPages, setNumPages] = useState(0)
  const [zoomIdx, setZoomIdx] = useState(2)
  const [currentPage, setCurrentPage] = useState(1)
  const [containerWidth, setContainerWidth] = useState(800)
  const containerRef = useRef<HTMLDivElement>(null)
  const scrollRef = useRef<HTMLDivElement>(null)
  const pageRefs = useRef<Map<number, HTMLDivElement>>(new Map())

  const zoom = ZOOM_LEVELS[zoomIdx]
  const pageWidth = Math.min(containerWidth - 48, 1100) * zoom

  useEffect(() => {
    if (!open) return
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', handler)
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', handler)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  useEffect(() => {
    if (!open) {
      setNumPages(0)
      setCurrentPage(1)
      setZoomIdx(2)
    }
  }, [open])

  useEffect(() => {
    const el = containerRef.current
    if (!el || !open) return
    const obs = new ResizeObserver((entries) => {
      const w = entries[0]?.contentRect.width
      if (w && w > 100) setContainerWidth(w)
    })
    obs.observe(el)
    return () => obs.disconnect()
  }, [open])

  useEffect(() => {
    const el = scrollRef.current
    if (!el || numPages === 0) return
    const handleScroll = () => {
      let closest = 1
      let closestDist = Infinity
      pageRefs.current.forEach((div, num) => {
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

  const scrollToPage = useCallback((page: number) => {
    const div = pageRefs.current.get(page)
    if (div) div.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, [])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex flex-col">
      <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

      <div className="relative z-10 flex flex-col m-4 sm:m-6 lg:m-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-800 dark:bg-slate-900 shadow-2xl overflow-hidden" style={{ height: 'calc(100vh - 5rem)' }}>
        {/* Toolbar */}
        <div className="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
          {title && (
            <h3 className="text-sm font-semibold text-slate-900 dark:text-white truncate mr-3">{title}</h3>
          )}

          <div className="w-px h-5 bg-slate-200 dark:bg-slate-700" />

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

          <div className="w-px h-5 bg-slate-200 dark:bg-slate-700" />

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

          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
            title="Close"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        {/* PDF area */}
        <div ref={scrollRef} className="flex-1 overflow-auto">
          <div ref={containerRef} className="min-h-full">
            {loading || !pdfUrl ? (
              <div className="flex items-center justify-center py-32">
                <div className="flex flex-col items-center gap-3">
                  <div className="animate-spin w-8 h-8 border-2 border-blue-400 border-t-transparent rounded-full" />
                  <span className="text-xs text-slate-400">Generating preview...</span>
                </div>
              </div>
            ) : (
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
                        <div className="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[10px] text-slate-500 font-medium tabular-nums">
                          {pageNum}
                        </div>
                      </div>
                    )
                  })}
                </div>
              </Document>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
