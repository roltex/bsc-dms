import { useQuery } from '@tanstack/react-query'
import { useState, useCallback } from 'react'
import { fetchDocumentCategories } from '../api/documentCategories'
import { fetchTemplates, getTemplateDownloadUrl, getTemplateRawPreviewUrl } from '../api/templates'
import { Card, CardBody, CardHeader } from '../components/ui/Card'
import { SkeletonTable } from '../components/ui/Skeleton'
import PdfPreviewModal from '../components/PdfPreviewModal'

export default function TemplatesPage() {
  const [categoryId, setCategoryId] = useState<number | undefined>()
  const [previewOpen, setPreviewOpen] = useState(false)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [previewTitle, setPreviewTitle] = useState('')
  const [previewLoading, setPreviewLoading] = useState(false)

  const handlePreview = useCallback(async (templateId: number, name: string) => {
    setPreviewTitle(name)
    setPreviewUrl(null)
    setPreviewLoading(true)
    setPreviewOpen(true)
    try {
      const url = await getTemplateRawPreviewUrl(templateId)
      setPreviewUrl(url)
    } catch {
      setPreviewUrl(null)
    } finally {
      setPreviewLoading(false)
    }
  }, [])

  const closePreview = useCallback(() => {
    setPreviewOpen(false)
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl)
      setPreviewUrl(null)
    }
  }, [previewUrl])

  const { data: categories } = useQuery({
    queryKey: ['document-categories'],
    queryFn: fetchDocumentCategories,
  })

  const { data: templates, isLoading } = useQuery({
    queryKey: ['templates', categoryId],
    queryFn: () => fetchTemplates(categoryId),
  })

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-white">Document Templates</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">
          Browse and download approved company templates for your documents.
        </p>
      </div>

      <div className="mb-4">
        <select
          value={categoryId ?? ''}
          onChange={(e) => setCategoryId(e.target.value ? Number(e.target.value) : undefined)}
          className="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2 text-sm"
        >
          <option value="">All categories</option>
          {categories?.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      </div>

      {isLoading ? (
        <SkeletonTable rows={5} cols={3} />
      ) : !templates?.length ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center text-slate-500 dark:text-slate-400">
              <svg className="h-12 w-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <p>No templates available{categoryId ? ' for this category' : ''}.</p>
              <p className="text-sm mt-1">Templates can be added by administrators in the admin panel.</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {templates.map((template) => (
            <Card key={template.id}>
              <CardHeader>
                <h3 className="font-medium text-slate-900 dark:text-white">{template.name}</h3>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-slate-500 dark:text-slate-400 mb-3">
                  Category: {template.category?.name || '---'}
                </p>
                {template.detected_variables && template.detected_variables.length > 0 && (
                  <div className="flex flex-wrap gap-1 mb-3">
                    {template.detected_variables.map(v => (
                      <span key={v} className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        {`{{${v}}}`}
                      </span>
                    ))}
                  </div>
                )}
                {template.editable_sections && template.editable_sections.length > 0 && (
                  <p className="text-xs text-slate-400 dark:text-slate-500 mb-3">
                    Editable sections: {template.editable_sections.join(', ')}
                  </p>
                )}
                {template.path ? (
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => handlePreview(template.id, template.name)}
                      className="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                    >
                      <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      Preview
                    </button>
                    <a
                      href={getTemplateDownloadUrl(template.id)}
                      className="inline-flex items-center gap-1.5 text-sm text-blue-600 dark:text-blue-400 hover:underline font-medium"
                    >
                      <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                      Download
                    </a>
                  </div>
                ) : (
                  <span className="text-sm text-slate-400">No file available</span>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      <PdfPreviewModal
        open={previewOpen}
        onClose={closePreview}
        pdfUrl={previewUrl}
        title={previewTitle}
        loading={previewLoading}
      />
    </div>
  )
}
