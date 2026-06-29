import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { fetchDocumentCategories } from '../api/documentCategories'
import { fetchPartners } from '../api/partners'
import { fetchTemplates, getTemplatePreviewUrl, uploadCustomTemplate, fetchTemplateTables, fetchInventoryItems } from '../api/templates'
import type { DocumentTemplate, TemplateTable, InventoryItem } from '../api/templates'
import { createTask, fetchWorkflowRoutes } from '../api/tasks'
import { fetchGoogleStatus, templateGoogleEdit, templateGoogleSync } from '../api/settings'
import type { WorkflowRoute } from '../types/task'
import { useToast } from '../contexts/ToastContext'
import { useAuth } from '../contexts/AuthContext'
import Button from '../components/ui/Button'
import { Card, CardBody, CardHeader } from '../components/ui/Card'
import Select from '../components/ui/Select'
import Input from '../components/ui/Input'
import Textarea from '../components/ui/Textarea'

const KNOWN_AUTO_KEYS = [
  'CONTRACTOR_NAME', 'CONTRACTOR_BIN_IIN', 'CONTRACTOR_BANK_DETAILS', 'CONTRACTOR_EMAIL',
  'COMPANY_NAME', 'COMPANY_APP_NAME',
  'TASK_NUMBER', 'TASK_CATEGORY', 'COMMERCIAL_TERMS',
  'VALIDITY_FROM', 'VALIDITY_TO', 'DEADLINE', 'CURRENT_DATE', 'INITIATOR_NAME',
  'COMPANY_SIGN', 'PARTNER_SIGN',
]

export default function CreateTaskPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const { addToast } = useToast()
  const { user } = useAuth()

  const [form, setForm] = useState(() => {
    const defaults = {
      document_category_id: '',
      partner_id: '',
      route_type: 'standard',
      workflow_route_id: '',
      commercial_terms: '',
      amount: '',
      validity_from: '',
      validity_to: '',
      deadline: '',
    }
    const prefillKeys = [
      'document_category_id', 'partner_id', 'workflow_route_id',
      'commercial_terms', 'amount', 'validity_from', 'validity_to', 'deadline',
    ] as const
    for (const key of prefillKeys) {
      const val = searchParams.get(key)
      if (val) defaults[key] = val
    }
    return defaults
  })
  const [selectedTemplate, setSelectedTemplate] = useState<DocumentTemplate | null>(null)
  const [extraVars, setExtraVars] = useState<Record<string, string>>({})
  const [autoOverrides, setAutoOverrides] = useState<Record<string, string>>({})
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const previewDebounceRef = useRef<ReturnType<typeof setTimeout>>(undefined)

  const [customUploading, setCustomUploading] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [taskAttachments, setTaskAttachments] = useState<File[]>([])

  const [tableData, setTableData] = useState<Record<string, Record<string, string>[]>>({})
  const [tableItemSearch, setTableItemSearch] = useState('')
  const [addingRowForTable, setAddingRowForTable] = useState<string | null>(null)

  const [useStepDeadlines, setUseStepDeadlines] = useState(false)
  const [stepDurations, setStepDurations] = useState<Record<number, number>>({})

  const [googleFileId, setGoogleFileId] = useState<string | null>(null)
  const [googleEditLoading, setGoogleEditLoading] = useState(false)
  const [googleSyncLoading, setGoogleSyncLoading] = useState(false)
  const [googleSynced, setGoogleSynced] = useState(false)

  const { data: categories } = useQuery({
    queryKey: ['document-categories'],
    queryFn: fetchDocumentCategories,
  })
  const { data: partnersData } = useQuery({
    queryKey: ['partners-all'],
    queryFn: () => fetchPartners({}),
  })
  const { data: templates } = useQuery({
    queryKey: ['templates', form.document_category_id],
    queryFn: () => fetchTemplates(form.document_category_id ? Number(form.document_category_id) : undefined),
    enabled: Boolean(form.document_category_id),
  })
  const { data: workflowRoutes } = useQuery({
    queryKey: ['workflow-routes'],
    queryFn: fetchWorkflowRoutes,
  })
  const { data: googleStatus } = useQuery({
    queryKey: ['google-status'],
    queryFn: fetchGoogleStatus,
  })
  const { data: templateTables } = useQuery({
    queryKey: ['template-tables', selectedTemplate?.id],
    queryFn: () => fetchTemplateTables(selectedTemplate!.id),
    enabled: Boolean(selectedTemplate?.id),
  })
  const { data: inventoryItems } = useQuery({
    queryKey: ['inventory-items-all'],
    queryFn: () => fetchInventoryItems(),
    enabled: Boolean(templateTables && templateTables.length > 0),
  })

  const prefillTemplateId = searchParams.get('template_id')
  useEffect(() => {
    if (prefillTemplateId && templates?.length && !selectedTemplate) {
      const match = templates.find(t => String(t.id) === prefillTemplateId)
      if (match) setSelectedTemplate(match)
    }
  }, [templates, prefillTemplateId, selectedTemplate])

  const filteredRoutes = useMemo(() => {
    if (!workflowRoutes) return []
    if (!form.document_category_id) return workflowRoutes
    const catId = Number(form.document_category_id)
    const linked = workflowRoutes.filter(
      r => r.category_ids && r.category_ids.length > 0 && r.category_ids.includes(catId)
    )
    return linked.length > 0 ? linked : workflowRoutes
  }, [workflowRoutes, form.document_category_id])

  const selectedRoute: WorkflowRoute | undefined = workflowRoutes?.find(
    (r) => String(r.id) === form.workflow_route_id
  )
  const selectedPartner = partnersData?.data?.find((p) => String(p.id) === form.partner_id)

  const firstStep = selectedRoute?.steps?.slice().sort((a, b) => a.sort_order - b.sort_order)[0]
  const partnerStartsFlow = firstStep?.role === 'partner'
  const templateHasFile = Boolean(selectedTemplate?.path)

  const detectedVars = useMemo(() => selectedTemplate?.detected_variables ?? [], [selectedTemplate])
  const extraVarKeys = useMemo(() => selectedTemplate?.extra_variables ?? [], [selectedTemplate])
  const allVars = useMemo(() => {
    const merged = new Set([...detectedVars, ...extraVarKeys])
    return Array.from(merged)
  }, [detectedVars, extraVarKeys])

  const autoVars = useMemo(() => allVars.filter(v => KNOWN_AUTO_KEYS.includes(v)), [allVars])
  const customVars = useMemo(() => allVars.filter(v => !KNOWN_AUTO_KEYS.includes(v)), [allVars])

  const NON_EDITABLE_AUTO = ['COMPANY_SIGN', 'PARTNER_SIGN', 'TASK_NUMBER', 'COMPANY_NAME', 'COMPANY_APP_NAME']

  const PARTNER_KEYS = ['CONTRACTOR_NAME', 'CONTRACTOR_BIN_IIN', 'CONTRACTOR_BANK_DETAILS', 'CONTRACTOR_EMAIL']
  const tplNeedsPartner = allVars.some(v => PARTNER_KEYS.includes(v))
  const tplNeedsTerms = allVars.includes('COMMERCIAL_TERMS')
  const tplNeedsValidFrom = allVars.includes('VALIDITY_FROM')
  const tplNeedsValidTo = allVars.includes('VALIDITY_TO')

  function getAutoDefault(key: string): string {
    switch (key) {
      case 'CONTRACTOR_NAME': return selectedPartner?.name || ''
      case 'CONTRACTOR_BIN_IIN': return selectedPartner?.bin_iin || ''
      case 'CONTRACTOR_BANK_DETAILS': return selectedPartner?.bank_details || ''
      case 'CONTRACTOR_EMAIL': return selectedPartner?.email || ''
      case 'TASK_NUMBER': return ''
      case 'TASK_CATEGORY': {
        const cat = categories?.find(c => String(c.id) === form.document_category_id)
        return cat?.name || ''
      }
      case 'COMMERCIAL_TERMS': return form.commercial_terms || ''
      case 'VALIDITY_FROM': return form.validity_from || ''
      case 'VALIDITY_TO': return form.validity_to || ''
      case 'DEADLINE': return form.deadline || ''
      case 'CURRENT_DATE': return new Date().toLocaleDateString()
      case 'INITIATOR_NAME': return user?.name || ''
      case 'COMPANY_NAME': return '(auto)'
      case 'COMPANY_APP_NAME': return '(auto)'
      case 'COMPANY_SIGN': return '[Signature]'
      case 'PARTNER_SIGN': return '[Signature]'
      default: return ''
    }
  }

  const buildVariablesMap = useCallback(() => {
    const vars: Record<string, string> = {}
    for (const key of autoVars) {
      const v = key in autoOverrides ? autoOverrides[key] : getAutoDefault(key)
      if (v && v !== '(auto)') vars[key] = v
    }
    return vars
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoVars, selectedPartner, form, categories, user, autoOverrides])

  const loadPreview = useCallback(async () => {
    if (!selectedTemplate || !selectedTemplate.path) return
    setPreviewLoading(true)
    try {
      if (previewUrl) URL.revokeObjectURL(previewUrl)
      const url = await getTemplatePreviewUrl(selectedTemplate.id, buildVariablesMap(), extraVars, tableData)
      setPreviewUrl(url)
    } catch {
      setPreviewUrl(null)
    } finally {
      setPreviewLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedTemplate, buildVariablesMap, extraVars, tableData])

  const googleDocsEnabled = googleStatus?.enabled && googleStatus?.configured

  const handleGoogleEdit = useCallback(async () => {
    if (!selectedTemplate || !googleDocsEnabled) return
    setGoogleEditLoading(true)
    const newTab = window.open('about:blank', '_blank')
    if (newTab) {
      newTab.document.write(`<!DOCTYPE html><html><head><title>Opening Google Docs...</title><style>*{margin:0;padding:0;box-sizing:border-box}body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#e2e8f0}.loader{text-align:center}.spinner{width:48px;height:48px;border:3px solid rgba(96,165,250,.2);border-top-color:#60a5fa;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 24px}@keyframes spin{to{transform:rotate(360deg)}}h1{font-size:18px;font-weight:600;margin-bottom:8px}p{font-size:13px;color:#94a3b8}</style></head><body><div class="loader"><div class="spinner"></div><h1>Opening Google Docs</h1><p>Preparing your document for editing...</p></div></body></html>`)
      newTab.document.close()
    }
    try {
      const result = await templateGoogleEdit(selectedTemplate.id, buildVariablesMap(), extraVars, tableData)
      setGoogleFileId(result.fileId)
      if (newTab) newTab.location.href = result.editUrl
      addToast('Document opened in Google Docs. Edit it there, then click "Sync from Google Docs" when done.')
    } catch (err: unknown) {
      if (newTab) newTab.close()
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Failed to open Google Docs'
      addToast(msg, 'error')
    } finally {
      setGoogleEditLoading(false)
    }
  }, [selectedTemplate, googleDocsEnabled, buildVariablesMap, extraVars, addToast])

  const handleGoogleSync = useCallback(async () => {
    if (!selectedTemplate || !googleFileId) return
    setGoogleSyncLoading(true)
    try {
      if (previewUrl) URL.revokeObjectURL(previewUrl)
      const url = await templateGoogleSync(selectedTemplate.id, googleFileId, false, buildVariablesMap(), extraVars, tableData)
      setPreviewUrl(url)
      setGoogleSynced(true)
      addToast('Document synced from Google Docs. Preview updated.')
    } catch {
      addToast('Failed to sync from Google Docs', 'error')
    } finally {
      setGoogleSyncLoading(false)
    }
  }, [selectedTemplate, googleFileId, previewUrl, addToast, buildVariablesMap, extraVars, tableData])

  const handleCustomUpload = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file || !form.document_category_id) return
    setCustomUploading(true)
    try {
      const tpl = await uploadCustomTemplate(file, Number(form.document_category_id))
      queryClient.invalidateQueries({ queryKey: ['templates'] })
      setSelectedTemplate(tpl)
      setExtraVars({})
      setTableData({})
      setGoogleFileId(null)
      setGoogleSynced(false)
      addToast('Custom template uploaded successfully', 'success')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        || 'Failed to upload template'
      addToast(msg, 'error')
    } finally {
      setCustomUploading(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }, [form.document_category_id, queryClient, addToast])

  useEffect(() => {
    if (!selectedTemplate) {
      if (previewUrl) { URL.revokeObjectURL(previewUrl); setPreviewUrl(null) }
      return
    }
    if (previewDebounceRef.current) clearTimeout(previewDebounceRef.current)
    previewDebounceRef.current = setTimeout(loadPreview, 800)
    return () => { if (previewDebounceRef.current) clearTimeout(previewDebounceRef.current) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedTemplate, loadPreview, extraVars, autoOverrides, tableData])

  useEffect(() => {
    return () => { if (previewUrl) URL.revokeObjectURL(previewUrl) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!selectedRoute) {
      setStepDurations({})
      return
    }
    const next: Record<number, number> = {}
    for (const s of selectedRoute.steps) {
      next[s.id] = typeof s.duration_days === 'number' ? s.duration_days : 1
    }
    setStepDurations(next)
  }, [selectedRoute])

  const totalStepDays = useMemo(() => {
    if (!selectedRoute) return 0
    return selectedRoute.steps.reduce((sum, s) => sum + (Number(stepDurations[s.id]) || 0), 0)
  }, [selectedRoute, stepDurations])

  const computedStepDates = useMemo(() => {
    if (!selectedRoute) return {} as Record<number, string>
    const out: Record<number, string> = {}
    const start = new Date()
    start.setHours(0, 0, 0, 0)
    let cumulative = 0
    for (const s of selectedRoute.steps) {
      cumulative += Number(stepDurations[s.id]) || 0
      const d = new Date(start)
      d.setDate(d.getDate() + cumulative)
      out[s.id] = d.toISOString().slice(0, 10)
    }
    return out
  }, [selectedRoute, stepDurations])

  const minDeadlineIso = useMemo(() => {
    if (!useStepDeadlines || totalStepDays <= 0) return ''
    const d = new Date()
    d.setHours(0, 0, 0, 0)
    d.setDate(d.getDate() + totalStepDays)
    return d.toISOString().slice(0, 10)
  }, [useStepDeadlines, totalStepDays])

  useEffect(() => {
    if (!useStepDeadlines || !minDeadlineIso) return
    setForm(f => {
      if (!f.deadline || f.deadline < minDeadlineIso) return { ...f, deadline: minDeadlineIso }
      return f
    })
  }, [useStepDeadlines, minDeadlineIso])

  const createMutation = useMutation({
    mutationFn: createTask,
    onSuccess: (task) => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] })
      addToast('Task created successfully')
      navigate(`/tasks/${task.id}`, { replace: true })
    },
    onError: () => addToast('Failed to create task', 'error'),
  })

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!partnerStartsFlow && !selectedTemplate) {
      addToast('Please select a document template', 'error')
      return
    }
    if (!form.deadline) {
      addToast('Deadline is required', 'error')
      return
    }
    if (new Date(form.deadline) <= new Date()) {
      addToast('Deadline must be in the future', 'error')
      return
    }
    if (useStepDeadlines && totalStepDays > 0 && minDeadlineIso && form.deadline < minDeadlineIso) {
      addToast(`Deadline must be on or after ${minDeadlineIso} (sum of step durations)`, 'error')
      return
    }
    if (form.amount && parseFloat(form.amount) < 0) {
      addToast('Amount cannot be negative', 'error')
      return
    }
    if (tplNeedsTerms && !form.commercial_terms?.trim()) {
      addToast('Commercial terms are required', 'error')
      return
    }
    if (form.validity_from && form.validity_to && new Date(form.validity_to) <= new Date(form.validity_from)) {
      addToast('Valid To must be after Valid From', 'error')
      return
    }
    const fd = new FormData()
    fd.append('document_category_id', form.document_category_id)
    fd.append('partner_id', form.partner_id)
    fd.append('route_type', form.route_type)
    if (form.workflow_route_id) fd.append('workflow_route_id', form.workflow_route_id)
    if (form.commercial_terms) fd.append('commercial_terms', form.commercial_terms)
    if (form.amount) fd.append('amount', form.amount)
    if (form.validity_from) fd.append('validity_from', form.validity_from)
    if (form.validity_to) fd.append('validity_to', form.validity_to)
    if (form.deadline) fd.append('deadline', form.deadline)
    if (selectedTemplate) fd.append('template_id', String(selectedTemplate.id))
    if (selectedTemplate && customVars.length > 0) {
      for (const key of customVars) {
        fd.append(`extra_variables[${key}]`, extraVars[key] || '')
      }
    }
    const hasTableData = Object.values(tableData).some(rows => rows.length > 0)
    if (hasTableData) {
      for (const [shortcode, rows] of Object.entries(tableData)) {
        rows.forEach((row, ri) => {
          for (const [col, val] of Object.entries(row)) {
            fd.append(`table_data[${shortcode}][${ri}][${col}]`, val)
          }
        })
      }
    }
    if (useStepDeadlines && selectedRoute) {
      for (const s of selectedRoute.steps) {
        const v = Math.max(0, Math.min(365, Number(stepDurations[s.id]) || 0))
        fd.append(`step_durations[${s.id}]`, String(v))
      }
    }
    if (googleFileId) {
      fd.append('google_file_id', googleFileId)
    }
    for (const file of taskAttachments) {
      fd.append('documents[]', file)
    }
    createMutation.mutate(fd)
  }

  const err = createMutation.error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } } | undefined

  const categoryTemplates = templates ?? []

  return (
    <div>
      <h1 className="text-2xl font-semibold text-slate-900 dark:text-white mb-6">New Task</h1>

      <form onSubmit={handleSubmit}>
        {err?.response?.data?.message && (
          <div className="rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 px-4 py-3 text-sm mb-6">
            {err.response.data.message}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* LEFT COLUMN: Form + Template Selection */}
          <div className="lg:col-span-1 space-y-5">

            {/* Category & Partner */}
            <Card>
              <CardHeader><h2 className="font-semibold text-sm text-slate-900 dark:text-white">Task Setup</h2></CardHeader>
              <CardBody className="space-y-4">
                <Select
                  label="Category"
                  required
                  value={form.document_category_id}
                  onChange={(e) => {
                    setForm(f => ({ ...f, document_category_id: e.target.value, workflow_route_id: '' }))
                    setSelectedTemplate(null)
                    setExtraVars({})
                    setTableData({})
                    setGoogleFileId(null)
                    setGoogleSynced(false)
                  }}
                  placeholder="Select category"
                  options={categories?.map(c => ({ value: c.id, label: c.name })) || []}
                  error={err?.response?.data?.errors?.document_category_id?.[0]}
                />
                <Select
                  label="Workflow Route"
                  value={form.workflow_route_id}
                  onChange={(e) => {
                    const route = workflowRoutes?.find(r => String(r.id) === e.target.value)
                    setForm(f => ({ ...f, workflow_route_id: e.target.value, route_type: route?.slug || f.route_type }))
                    const first = route?.steps?.slice().sort((a, b) => a.sort_order - b.sort_order)[0]
                    if (first?.role === 'partner') {
                      setSelectedTemplate(null)
                      setExtraVars({})
                      setGoogleFileId(null)
                      setGoogleSynced(false)
                    }
                  }}
                  placeholder="Select workflow"
                  options={filteredRoutes.map(r => ({ value: r.id, label: `${r.name}${r.is_default ? ' (default)' : ''}` }))}
                />
                <div>
                  <Input
                    label="Deadline"
                    type="date"
                    required
                    value={form.deadline}
                    min={useStepDeadlines && minDeadlineIso ? minDeadlineIso : undefined}
                    onChange={(e) => setForm(f => ({ ...f, deadline: e.target.value }))}
                  />
                  {useStepDeadlines && totalStepDays > 0 && (
                    <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                      Auto-calculated from step durations: {totalStepDays} day{totalStepDays !== 1 ? 's' : ''} (earliest {minDeadlineIso}). You can pick a later date but not earlier.
                    </p>
                  )}
                </div>
              </CardBody>
            </Card>

            {/* Workflow Preview / Step Deadlines */}
            {selectedRoute && selectedRoute.steps.length > 0 && (() => {
              const colors: Record<string, string> = {
                manager: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
                lawyer: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
                initiator: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
                partner: 'bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300',
                gm: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
              }
              return (
                <div className="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 p-3">
                  <div className="flex items-center justify-between mb-2 gap-2">
                    <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Workflow Steps</p>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <span className="text-[11px] text-slate-500 dark:text-slate-400">Use step deadlines</span>
                      <span className="relative inline-flex">
                        <input
                          type="checkbox"
                          className="sr-only peer"
                          checked={useStepDeadlines}
                          onChange={(e) => setUseStepDeadlines(e.target.checked)}
                        />
                        <span className="w-8 h-4 bg-slate-300 dark:bg-slate-600 rounded-full peer-checked:bg-blue-500 transition-colors"></span>
                        <span className="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition-transform peer-checked:translate-x-4"></span>
                      </span>
                    </label>
                  </div>

                  {!useStepDeadlines ? (
                    <div className="flex flex-wrap items-center gap-1">
                      {selectedRoute.steps.map((step, i) => (
                        <div key={step.id} className="flex items-center gap-1">
                          {i > 0 && <svg className="w-3 h-3 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>}
                          <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${colors[step.role] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'}`}>
                            {step.name}
                          </span>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="space-y-1.5">
                      {selectedRoute.steps.map((step, i) => {
                        const days = Number(stepDurations[step.id]) || 0
                        const dueBy = computedStepDates[step.id]
                        return (
                          <div key={step.id} className="flex items-center gap-2 bg-white dark:bg-slate-800/60 rounded-md border border-slate-200 dark:border-slate-700 px-2 py-1.5">
                            <span className="text-[10px] font-mono text-slate-400 w-4">{i + 1}</span>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-medium whitespace-nowrap ${colors[step.role] || 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'}`}>
                              {step.name}
                            </span>
                            <span className="text-[10px] text-slate-400 uppercase tracking-wider hidden sm:inline">{step.role}</span>
                            <div className="flex-1" />
                            <div className="flex items-center gap-1">
                              <input
                                type="number"
                                min={0}
                                max={365}
                                value={days}
                                onChange={(e) => {
                                  const v = Math.max(0, Math.min(365, parseInt(e.target.value || '0', 10) || 0))
                                  setStepDurations(prev => ({ ...prev, [step.id]: v }))
                                }}
                                className="w-14 rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-1.5 py-0.5 text-right"
                              />
                              <span className="text-[10px] text-slate-400">d</span>
                            </div>
                            <span className="text-[10px] text-slate-400 dark:text-slate-500 w-20 text-right tabular-nums">{dueBy}</span>
                          </div>
                        )
                      })}
                      <div className="flex items-center justify-between pt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Total: <span className="font-semibold text-slate-700 dark:text-slate-200">{totalStepDays}</span> day{totalStepDays !== 1 ? 's' : ''}</span>
                        <span>Earliest deadline: <span className="font-semibold text-slate-700 dark:text-slate-200">{minDeadlineIso || '—'}</span></span>
                      </div>
                    </div>
                  )}
                </div>
              )
            })()}

            {/* Template Selection — hidden when partner starts flow */}
            {form.document_category_id && !partnerStartsFlow && (
              <Card>
                <CardHeader>
                  <h2 className="font-semibold text-sm text-slate-900 dark:text-white">
                    Document Template
                    {categoryTemplates.length > 0 && (
                      <span className="ml-2 text-xs font-normal text-slate-400">({categoryTemplates.length})</span>
                    )}
                  </h2>
                </CardHeader>
                <CardBody>
                  {categoryTemplates.length > 0 && (
                    <div className="space-y-2">
                      {categoryTemplates.map(t => {
                        const isSelected = selectedTemplate?.id === t.id
                        const varCount = t.detected_variables?.length ?? 0
                        return (
                          <button
                            key={t.id}
                            type="button"
                            onClick={() => {
                    setSelectedTemplate(isSelected ? null : t)
                      setExtraVars({})
                      setTableData({})
                      setGoogleFileId(null)
                      setGoogleSynced(false)
                      setTaskAttachments([])
                            }}
                            className={`w-full text-left rounded-lg border-2 p-3 transition-all ${
                              isSelected
                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500/30'
                                : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'
                            }`}
                          >
                            <div className="flex items-center justify-between">
                              <span className={`text-sm font-medium ${isSelected ? 'text-blue-700 dark:text-blue-300' : 'text-slate-900 dark:text-white'}`}>
                                {t.name}
                              </span>
                              <div className="flex items-center gap-2">
                                {t.is_custom && (
                                  <span className="px-1.5 py-0.5 rounded text-[9px] font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                                    Custom
                                  </span>
                                )}
                                {isSelected && (
                                  <svg className="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
                                  </svg>
                                )}
                              </div>
                            </div>
                            {varCount > 0 && (
                              <div className="flex flex-wrap gap-1 mt-1.5">
                                {t.detected_variables!.slice(0, 4).map(v => (
                                  <span key={v} className="px-1.5 py-0.5 rounded text-[9px] font-mono bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                    {v}
                                  </span>
                                ))}
                                {varCount > 4 && (
                                  <span className="text-[9px] text-slate-400 self-center">+{varCount - 4}</span>
                                )}
                              </div>
                            )}
                          </button>
                        )
                      })}
                    </div>
                  )}
                  {categoryTemplates.length === 0 && (
                    <p className="text-sm text-slate-400 dark:text-slate-500 italic mb-3">No templates available for this category.</p>
                  )}
                  <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/50">
                    <input
                      ref={fileInputRef}
                      type="file"
                      accept=".docx,.doc"
                      onChange={handleCustomUpload}
                      className="hidden"
                    />
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      disabled={customUploading}
                      className="w-full flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 dark:hover:border-blue-500 p-3 transition-all text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10"
                    >
                      {customUploading ? (
                        <>
                          <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                          </svg>
                          <span className="text-xs font-medium">Uploading...</span>
                        </>
                      ) : (
                        <>
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                          </svg>
                          <span className="text-xs font-medium">Upload Custom Template</span>
                          <span className="text-[10px] text-slate-400 dark:text-slate-500">(.docx)</span>
                        </>
                      )}
                    </button>
                  </div>
                </CardBody>
              </Card>
            )}

          </div>

          {/* RIGHT COLUMN: Variables + Preview */}
          <div className="lg:col-span-2 space-y-5">

            {/* Partner starts flow — info message */}
            {partnerStartsFlow && (
              <div className="flex items-center justify-center py-24 border-2 border-dashed border-teal-200 dark:border-teal-700/50 rounded-xl bg-teal-50/30 dark:bg-teal-900/10">
                <div className="text-center px-6">
                  <div className="w-16 h-16 rounded-2xl bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center mx-auto mb-4">
                    <svg className="w-8 h-8 text-teal-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                  </div>
                  <p className="text-lg font-semibold text-teal-700 dark:text-teal-300 mb-2">Partner Will Upload Document</p>
                  <p className="text-sm text-teal-600/80 dark:text-teal-400/80 max-w-md">
                    The first step of the selected workflow is assigned to the partner.
                    No document template is needed — the partner will upload their document when they receive the task.
                  </p>
                  {firstStep && (
                    <div className="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-teal-100 dark:bg-teal-900/30 border border-teal-200 dark:border-teal-800/40">
                      <span className="w-2 h-2 rounded-full bg-teal-500" />
                      <span className="text-xs font-medium text-teal-700 dark:text-teal-300">
                        First step: {firstStep.name} ({firstStep.action_type.replace(/_/g, ' ')})
                      </span>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Contract Details & Variables */}
            {!partnerStartsFlow && selectedTemplate && (() => {
              const FORM_HANDLED = [...PARTNER_KEYS, 'COMMERCIAL_TERMS', 'VALIDITY_FROM', 'VALIDITY_TO']
              const editableAutoVars = autoVars.filter(v => !NON_EDITABLE_AUTO.includes(v) && !FORM_HANDLED.includes(v))
              const nonEditableAutoVars = autoVars.filter(v => NON_EDITABLE_AUTO.includes(v))
              const hasFormFields = tplNeedsPartner || tplNeedsTerms || tplNeedsValidFrom || tplNeedsValidTo
              const hasVariables = editableAutoVars.length > 0 || nonEditableAutoVars.length > 0 || customVars.length > 0

              return (
              <Card>
                <CardBody className="p-0">
                  {/* Section 1: Core Contract Fields */}
                  {(hasFormFields || true) && (
                    <div className="px-5 pt-5 pb-4 space-y-3">
                      <p className="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Contract</p>
                      {tplNeedsPartner && (
                        <Select
                          label="Partner"
                          required
                          value={form.partner_id}
                          onChange={(e) => setForm(f => ({ ...f, partner_id: e.target.value }))}
                          placeholder="Select partner"
                          options={partnersData?.data?.map(p => ({ value: p.id, label: `${p.name} (${p.bin_iin})` })) || []}
                          error={err?.response?.data?.errors?.partner_id?.[0]}
                        />
                      )}
                      <div className="grid grid-cols-2 gap-3">
                        <Input
                          label="Amount"
                          type="number"
                          value={form.amount}
                          onChange={(e) => setForm(f => ({ ...f, amount: e.target.value }))}
                          placeholder="0.00"
                        />
                        {tplNeedsTerms && (
                          <Textarea
                            label="Commercial Terms"
                            required
                            value={form.commercial_terms}
                            onChange={(e) => setForm(f => ({ ...f, commercial_terms: e.target.value }))}
                            rows={1}
                          />
                        )}
                      </div>
                      {(tplNeedsValidFrom || tplNeedsValidTo) && (
                        <div className="grid grid-cols-2 gap-3">
                          {tplNeedsValidFrom && (
                            <Input label="Valid From" type="date" value={form.validity_from} onChange={(e) => setForm(f => ({ ...f, validity_from: e.target.value }))} />
                          )}
                          {tplNeedsValidTo && (
                            <Input label="Valid To" type="date" value={form.validity_to} onChange={(e) => setForm(f => ({ ...f, validity_to: e.target.value }))} />
                          )}
                        </div>
                      )}
                    </div>
                  )}

                  {/* Section 2: Template Variables */}
                  {hasVariables && (
                    <div className="px-5 pb-4 pt-3 border-t border-slate-100 dark:border-slate-700/50 space-y-3">
                      <div className="flex items-center justify-between">
                        <p className="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                          {customVars.length > 0 && editableAutoVars.length === 0 ? 'Custom Fields' : 'Template Variables'}
                        </p>
                        {(editableAutoVars.length > 0 || customVars.length > 0) && (
                          <span className="text-[10px] text-slate-400 dark:text-slate-600">
                            {editableAutoVars.length + customVars.length} field{editableAutoVars.length + customVars.length !== 1 ? 's' : ''}
                          </span>
                        )}
                      </div>
                      {(editableAutoVars.length > 0 || customVars.length > 0) && (
                        <div className="grid grid-cols-2 gap-3">
                          {editableAutoVars.map(v => {
                            const defaultVal = getAutoDefault(v)
                            const currentVal = v in autoOverrides ? autoOverrides[v] : defaultVal
                            const isOverridden = v in autoOverrides && autoOverrides[v] !== defaultVal
                            return (
                              <div key={v} className="relative">
                                <Input
                                  label={v.replace(/_/g, ' ')}
                                  value={currentVal}
                                  onChange={(e) => setAutoOverrides(prev => ({ ...prev, [v]: e.target.value }))}
                                />
                                {isOverridden && (
                                  <button
                                    type="button"
                                    onClick={() => setAutoOverrides(prev => { const n = { ...prev }; delete n[v]; return n })}
                                    className="absolute top-0 right-0 text-[10px] text-amber-500 hover:text-amber-600 font-medium"
                                  >
                                    reset
                                  </button>
                                )}
                              </div>
                            )
                          })}
                          {customVars.map(v => {
                            const editableSections = selectedTemplate?.editable_sections as string[] | undefined
                            const isReadOnly = editableSections && editableSections.length > 0 && !editableSections.includes(v)
                            return (
                              <Input
                                key={v}
                                label={v.replace(/_/g, ' ')}
                                placeholder={isReadOnly ? 'Read-only' : `{{${v}}}`}
                                value={extraVars[v] || ''}
                                onChange={(e) => setExtraVars(prev => ({ ...prev, [v]: e.target.value }))}
                                readOnly={isReadOnly}
                              />
                            )
                          })}
                        </div>
                      )}
                      {nonEditableAutoVars.length > 0 && (
                        <div className="flex flex-wrap items-center gap-1.5 pt-1">
                          {nonEditableAutoVars.map(v => (
                            <span
                              key={v}
                              className={`inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full ${
                                v === 'COMPANY_SIGN' || v === 'PARTNER_SIGN'
                                  ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-500 dark:text-indigo-400'
                                  : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'
                              }`}
                            >
                              <code className="font-mono">{v}</code>
                              <span className="opacity-60">{v === 'COMPANY_SIGN' || v === 'PARTNER_SIGN' ? '· signed later' : '· auto'}</span>
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </CardBody>
              </Card>
              )
            })()}

            {/* Template Tables — inventory item rows */}
            {!partnerStartsFlow && selectedTemplate && templateTables && templateTables.length > 0 && (() => {
              const filteredItems = inventoryItems?.filter(item => {
                if (!tableItemSearch.trim()) return true
                const s = tableItemSearch.toLowerCase()
                return item.title.toLowerCase().includes(s)
                  || item.serial_number?.toLowerCase().includes(s)
                  || item.model_number?.toLowerCase().includes(s)
                  || item.category?.toLowerCase().includes(s)
              }) ?? []

              const addRowFromItem = (table: TemplateTable, item: InventoryItem) => {
                const row: Record<string, string> = { _inventory_id: String(item.id) }
                for (const col of table.columns) {
                  if (col.source.startsWith('inventory:')) {
                    const field = col.source.replace('inventory:', '') as keyof InventoryItem
                    row[col.key] = String(item[field] ?? '')
                  } else {
                    row[col.key] = ''
                  }
                }
                setTableData(prev => ({
                  ...prev,
                  [table.shortcode]: [...(prev[table.shortcode] || []), row],
                }))
                setAddingRowForTable(null)
                setTableItemSearch('')
              }

              const removeRow = (shortcode: string, idx: number) => {
                setTableData(prev => ({
                  ...prev,
                  [shortcode]: (prev[shortcode] || []).filter((_, i) => i !== idx),
                }))
              }

              const updateCell = (shortcode: string, rowIdx: number, key: string, value: string) => {
                setTableData(prev => ({
                  ...prev,
                  [shortcode]: (prev[shortcode] || []).map((r, i) =>
                    i === rowIdx ? { ...r, [key]: value } : r
                  ),
                }))
              }

              return (
                <div className="space-y-4">
                  {templateTables.map(table => {
                    const rows = tableData[table.shortcode] || []
                    return (
                      <Card key={table.id}>
                        <CardHeader>
                          <div className="flex items-center justify-between">
                            <h2 className="font-semibold text-sm text-slate-900 dark:text-white">
                              <svg className="w-4 h-4 inline-block mr-1.5 text-indigo-500 -mt-0.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M21.375 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M12 17.25v-5.25" />
                              </svg>
                              {table.name}
                              {rows.length > 0 && (
                                <span className="ml-2 text-xs font-normal text-slate-400">({rows.length} row{rows.length !== 1 ? 's' : ''})</span>
                              )}
                            </h2>
                            <span className="text-[10px] font-mono text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">
                              {'{{'}TABLE:{table.shortcode}{'}}'}
                            </span>
                          </div>
                        </CardHeader>
                        <CardBody className="p-0">
                          {rows.length > 0 && (
                            <div className="overflow-x-auto">
                              <table className="w-full text-xs">
                                <thead>
                                  <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                    <th className="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400 w-8">#</th>
                                    {table.columns.map(col => (
                                      <th key={col.key} className="px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400">
                                        {col.label}
                                        {col.source !== 'custom' && (
                                          <span className="ml-1 text-[9px] font-normal text-slate-400 dark:text-slate-600">auto</span>
                                        )}
                                      </th>
                                    ))}
                                    <th className="px-3 py-2 w-8" />
                                  </tr>
                                </thead>
                                <tbody>
                                  {rows.map((row, ri) => (
                                    <tr key={ri} className="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                      <td className="px-3 py-1.5 text-slate-400 font-mono">{ri + 1}</td>
                                      {table.columns.map(col => {
                                        const isCustom = col.source === 'custom'
                                        return (
                                          <td key={col.key} className="px-3 py-1.5">
                                            {isCustom ? (
                                              <input
                                                type="text"
                                                value={row[col.key] || ''}
                                                onChange={(e) => updateCell(table.shortcode, ri, col.key, e.target.value)}
                                                className="w-full bg-transparent border-b border-slate-200 dark:border-slate-700 focus:border-blue-500 dark:focus:border-blue-400 outline-none text-xs py-0.5 text-slate-900 dark:text-white"
                                                placeholder={col.label}
                                              />
                                            ) : (
                                              <span className="text-slate-700 dark:text-slate-300">{row[col.key] || '—'}</span>
                                            )}
                                          </td>
                                        )
                                      })}
                                      <td className="px-3 py-1.5">
                                        <button
                                          type="button"
                                          onClick={() => removeRow(table.shortcode, ri)}
                                          className="p-0.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-500 transition-colors"
                                        >
                                          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                          </svg>
                                        </button>
                                      </td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            </div>
                          )}

                          {/* Add Row - Inventory Item Selector */}
                          <div className="p-3 border-t border-slate-100 dark:border-slate-700/50">
                            {addingRowForTable === table.shortcode ? (
                              <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                  <div className="relative flex-1">
                                    <svg className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                    <input
                                      type="text"
                                      value={tableItemSearch}
                                      onChange={(e) => setTableItemSearch(e.target.value)}
                                      placeholder="Search inventory items..."
                                      className="w-full pl-8 pr-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none"
                                      autoFocus
                                    />
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => { setAddingRowForTable(null); setTableItemSearch('') }}
                                    className="px-3 py-2 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                                  >
                                    Cancel
                                  </button>
                                </div>
                                <div className="max-h-48 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                  {filteredItems.length === 0 ? (
                                    <div className="px-3 py-4 text-center text-xs text-slate-400">No inventory items found</div>
                                  ) : (
                                    filteredItems.map(item => (
                                      <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => addRowFromItem(table, item)}
                                        className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-900/20 border-b border-slate-100 dark:border-slate-700/50 last:border-0 transition-colors"
                                      >
                                        <div className="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                          <span className="text-[9px] font-bold text-indigo-600 dark:text-indigo-400">{item.category?.slice(0, 2).toUpperCase() || 'IT'}</span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                          <p className="text-xs font-medium text-slate-900 dark:text-white truncate">{item.title}</p>
                                          <p className="text-[10px] text-slate-400 dark:text-slate-500">
                                            {item.category}{item.serial_number ? ` · S/N: ${item.serial_number}` : ''}{item.price ? ` · ${item.price} ${item.currency || ''}` : ''}
                                          </p>
                                        </div>
                                        <span className={`text-[9px] font-medium px-1.5 py-0.5 rounded ${item.status === 'available' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'}`}>
                                          {item.status}
                                        </span>
                                      </button>
                                    ))
                                  )}
                                </div>
                              </div>
                            ) : (
                              <button
                                type="button"
                                onClick={() => setAddingRowForTable(table.shortcode)}
                                className="w-full flex items-center justify-center gap-2 py-2 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-indigo-400 dark:hover:border-indigo-500 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors text-xs"
                              >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add Row from Inventory
                              </button>
                            )}
                          </div>
                        </CardBody>
                      </Card>
                    )
                  })}
                </div>
              )
            })()}

            {/* Task Attachment Upload — when template has no file */}
            {!partnerStartsFlow && selectedTemplate && !templateHasFile && (() => {
              const MAX_FILE = 20 * 1024 * 1024
              const MAX_TOTAL = 100 * 1024 * 1024
              const totalSize = taskAttachments.reduce((s, f) => s + f.size, 0)
              const fmtSize = (b: number) => b < 1024 * 1024 ? `${(b / 1024).toFixed(0)} KB` : `${(b / (1024 * 1024)).toFixed(1)} MB`
              const getExt = (name: string) => name.split('.').pop()?.toLowerCase() || ''
              const fileIcon = (name: string) => {
                const ext = getExt(name)
                if (['pdf'].includes(ext)) return { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400', label: 'PDF' }
                if (['doc', 'docx'].includes(ext)) return { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400', label: 'DOC' }
                if (['xls', 'xlsx'].includes(ext)) return { bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400', label: 'XLS' }
                if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-600 dark:text-purple-400', label: 'IMG' }
                if (['zip', 'rar', '7z'].includes(ext)) return { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-600 dark:text-amber-400', label: 'ZIP' }
                if (['ppt', 'pptx'].includes(ext)) return { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-600 dark:text-orange-400', label: 'PPT' }
                if (['txt', 'csv'].includes(ext)) return { bg: 'bg-slate-100 dark:bg-slate-700', text: 'text-slate-600 dark:text-slate-400', label: 'TXT' }
                return { bg: 'bg-slate-100 dark:bg-slate-700', text: 'text-slate-500 dark:text-slate-400', label: 'FILE' }
              }
              const handleFiles = (e: React.ChangeEvent<HTMLInputElement>) => {
                const incoming = Array.from(e.target.files || [])
                const rejected: string[] = []
                const accepted: File[] = []
                let runningTotal = totalSize
                for (const f of incoming) {
                  if (f.size > MAX_FILE) { rejected.push(`${f.name} exceeds 20 MB`); continue }
                  if (runningTotal + f.size > MAX_TOTAL) { rejected.push(`${f.name} would exceed 100 MB total`); continue }
                  accepted.push(f)
                  runningTotal += f.size
                }
                if (accepted.length > 0) setTaskAttachments(prev => [...prev, ...accepted])
                if (rejected.length > 0) addToast(rejected.join('; '), 'error')
                e.target.value = ''
              }
              return (
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-sm text-slate-900 dark:text-white">
                      <svg className="w-4 h-4 inline-block mr-1.5 text-blue-500 -mt-0.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                      </svg>
                      Attach Documents
                      {taskAttachments.length > 0 && (
                        <span className="ml-2 text-xs font-normal text-slate-400">({taskAttachments.length})</span>
                      )}
                    </h2>
                    {taskAttachments.length > 0 && (
                      <span className={`text-[10px] font-medium px-2 py-0.5 rounded-full ${totalSize > MAX_TOTAL * 0.8 ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'}`}>
                        {fmtSize(totalSize)} / 100 MB
                      </span>
                    )}
                  </div>
                </CardHeader>
                <CardBody>
                  {taskAttachments.length > 0 && (
                    <div className="space-y-1.5 mb-3">
                      {taskAttachments.map((file, idx) => {
                        const fi = fileIcon(file.name)
                        const oversize = file.size > MAX_FILE
                        return (
                          <div key={`${file.name}-${idx}`} className={`flex items-center gap-3 p-2 rounded-lg border ${oversize ? 'border-red-300 dark:border-red-800/50 bg-red-50/50 dark:bg-red-900/10' : 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50'}`}>
                            <div className={`w-8 h-8 rounded-lg ${fi.bg} flex items-center justify-center flex-shrink-0`}>
                              <span className={`text-[9px] font-bold ${fi.text}`}>{fi.label}</span>
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className="text-xs font-medium text-slate-900 dark:text-white truncate">{file.name}</p>
                              <p className={`text-[10px] ${oversize ? 'text-red-500' : 'text-slate-400 dark:text-slate-500'}`}>
                                {fmtSize(file.size)}{oversize && ' — exceeds 20 MB limit'}
                              </p>
                            </div>
                            <button
                              type="button"
                              onClick={() => setTaskAttachments(prev => prev.filter((_, i) => i !== idx))}
                              className="p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-500 transition-colors"
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        )
                      })}
                    </div>
                  )}
                  <label className={`w-full flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 dark:hover:border-blue-500 transition-all text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 cursor-pointer ${taskAttachments.length > 0 ? 'p-3' : 'p-8 flex-col gap-3'}`}>
                    <input type="file" multiple className="sr-only" onChange={handleFiles} />
                    {taskAttachments.length > 0 ? (
                      <>
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span className="text-xs font-medium">Add more files</span>
                      </>
                    ) : (
                      <>
                        <div className="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                          <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                          </svg>
                        </div>
                        <div className="text-center">
                          <p className="text-sm font-medium">Upload documents</p>
                          <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Max 20 MB per file, 100 MB total</p>
                        </div>
                      </>
                    )}
                  </label>
                </CardBody>
              </Card>
              )
            })()}

            {/* Google Docs Editing */}
            {!partnerStartsFlow && selectedTemplate && templateHasFile && googleDocsEnabled && (
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-sm text-slate-900 dark:text-white">
                      <svg className="w-4 h-4 inline-block mr-1.5 text-blue-500 -mt-0.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                      </svg>
                      Edit Document
                      {googleSynced && <span className="ml-2 text-xs font-normal text-emerald-500">synced</span>}
                      {googleFileId && !googleSynced && <span className="ml-2 text-xs font-normal text-amber-500">editing in Google Docs</span>}
                    </h2>
                  </div>
                </CardHeader>
                <CardBody>
                  {!googleFileId ? (
                    <div className="text-center py-8">
                      <div className="w-16 h-16 rounded-2xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center mx-auto mb-4">
                        <svg className="w-8 h-8 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M14.727 6.727H14V0H4.91c-.905 0-1.637.732-1.637 1.636v20.728c0 .904.732 1.636 1.636 1.636h14.182c.904 0 1.636-.732 1.636-1.636V6.727h-6.001zM20 24H4c-1.1 0-2-.9-2-2V2c0-1.1.9-2 2-2h10l6 6v16c0 1.1-.9 2-2 2z" />
                        </svg>
                      </div>
                      <p className="text-sm text-slate-500 dark:text-slate-400 font-medium mb-1">Edit document in Google Docs</p>
                      <p className="text-xs text-slate-400 dark:text-slate-500 mb-4">
                        Opens the document in a new tab with full Google Docs editing capabilities. Preserves all formatting, tables, and layout.
                      </p>
                      <button
                        type="button"
                        onClick={handleGoogleEdit}
                        disabled={googleEditLoading}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-sm"
                      >
                        {googleEditLoading ? (
                          <>
                            <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                            Opening Google Docs...
                          </>
                        ) : (
                          <>
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            Edit in Google Docs
                          </>
                        )}
                      </button>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <div className="rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/40 p-4">
                        <div className="flex items-start gap-3">
                          <svg className="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                          </svg>
                          <div>
                            <p className="text-sm font-medium text-blue-800 dark:text-blue-300">Document is open in Google Docs</p>
                            <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">
                              Make your edits in the Google Docs tab, then click "Sync from Google Docs" below to pull changes back.
                            </p>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <button
                          type="button"
                          onClick={handleGoogleSync}
                          disabled={googleSyncLoading}
                          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 transition-colors shadow-sm"
                        >
                          {googleSyncLoading ? (
                            <>
                              <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                              Syncing...
                            </>
                          ) : (
                            <>
                              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" />
                              </svg>
                              Sync from Google Docs
                            </>
                          )}
                        </button>
                        <a
                          href={`https://docs.google.com/document/d/${googleFileId}/edit`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-lg text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                        >
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                          </svg>
                          Open Google Docs
                        </a>
                      </div>
                      {googleSynced && (
                        <p className="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                          Changes synced. Preview updated below.
                        </p>
                      )}
                    </div>
                  )}
                </CardBody>
              </Card>
            )}

            {/* Document Preview */}
            {!partnerStartsFlow && selectedTemplate && templateHasFile && (
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-sm text-slate-900 dark:text-white">
                      Document Preview
                    </h2>
                    <div className="flex items-center gap-2">
                      <span className="text-[11px] text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">
                        {selectedTemplate.name}
                      </span>
                      <button
                        type="button"
                        onClick={() => loadPreview()}
                        disabled={previewLoading}
                        className="text-[11px] text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
                      >
                        {previewLoading ? 'Loading...' : 'Refresh'}
                      </button>
                    </div>
                  </div>
                </CardHeader>
                <CardBody className="p-0">
                  {previewLoading && !previewUrl ? (
                    <div className="flex items-center justify-center py-20">
                      <div className="flex flex-col items-center gap-3">
                        <svg className="w-8 h-8 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <span className="text-sm text-slate-500 dark:text-slate-400">Generating preview...</span>
                      </div>
                    </div>
                  ) : previewUrl ? (
                    <div className="relative">
                      {previewLoading && (
                        <div className="absolute top-2 right-2 z-10">
                          <div className="flex items-center gap-1.5 bg-blue-500 text-white text-[10px] font-medium px-2 py-1 rounded-full shadow-lg">
                            <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            Updating...
                          </div>
                        </div>
                      )}
                      <iframe
                        src={previewUrl}
                        className="w-full border-0 rounded-b-xl bg-white"
                        style={{ height: '600px' }}
                        title="Document Preview"
                      />
                    </div>
                  ) : (
                    <div className="flex items-center justify-center py-20 text-center">
                      <div>
                        <svg className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <p className="text-sm text-slate-400 dark:text-slate-500">
                          Fill in the form details to see a live preview
                        </p>
                      </div>
                    </div>
                  )}
                </CardBody>
              </Card>
            )}

            {/* No Template Selected Placeholder */}
            {!partnerStartsFlow && !selectedTemplate && form.document_category_id && (
              <div className="flex items-center justify-center py-24 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                <div className="text-center">
                  <svg className="w-16 h-16 text-slate-300 dark:text-slate-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" strokeWidth={0.8} stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                  <p className="text-slate-500 dark:text-slate-400 font-medium">Select a template from the left</p>
                  <p className="text-sm text-slate-400 dark:text-slate-500 mt-1">Choose a document template to preview and configure</p>
                </div>
              </div>
            )}

            {!partnerStartsFlow && !form.document_category_id && (
              <div className="flex items-center justify-center py-24 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                <div className="text-center">
                  <svg className="w-16 h-16 text-slate-300 dark:text-slate-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" strokeWidth={0.8} stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                  </svg>
                  <p className="text-slate-500 dark:text-slate-400 font-medium">Start by selecting a category</p>
                  <p className="text-sm text-slate-400 dark:text-slate-500 mt-1">This will show available document templates</p>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Submit bar */}
        <div className="mt-8 flex items-center gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
          <Button type="submit" loading={createMutation.isPending} disabled={!partnerStartsFlow && !selectedTemplate}>
            Create Task
          </Button>
          <Button type="button" variant="secondary" onClick={() => navigate('/tasks')}>
            Cancel
          </Button>
          {partnerStartsFlow ? (
            <span className="text-xs text-teal-500 dark:text-teal-400 ml-auto flex items-center gap-1.5">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
              Partner will upload document
            </span>
          ) : selectedTemplate ? (
            <span className="text-xs text-slate-400 dark:text-slate-500 ml-auto">
              Template: {selectedTemplate.name}
            </span>
          ) : null}
        </div>
      </form>

    </div>
  )
}
