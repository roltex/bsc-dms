import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import React, { lazy, Suspense, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  fetchTask,
  submitTask,
  approveTask,
  rejectTask,
  returnTask,
  delegateTask,
  fastTrackTask,
  addTaskReviewer,
  removeTaskReviewer,
  uploadSignedDocument,
  uploadFinalVersion,
  uploadDocumentStep,
  downloadSummaryReport,
  analyzeDocument,
  searchParagraph,
  getDocumentDownloadUrl,
  getDocumentPreviewUrl,
  getSignatureUrl,
  getAttachmentDownloadUrl,
  createTaskComment,
  uploadTaskAttachments,
  replaceTaskAttachment,
  deleteTaskAttachment,
} from '../api/tasks'
import { fetchGoogleStatus, taskGoogleEdit, taskGoogleSync } from '../api/settings'
import { fetchUsers } from '../api/users'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'
import Button from '../components/ui/Button'
import { StatusBadge } from '../components/ui/Badge'
import Badge from '../components/ui/Badge'
import { TaskStepIndicator } from '../components/ui/StepIndicator'
import Modal from '../components/ui/Modal'
import SignaturePad, { type SignaturePadHandle } from '../components/ui/SignaturePad'
const PdfCommentViewer = lazy(() => import('../components/PdfCommentViewer'))

export default function TaskDetailPage() {
  const { id } = useParams()
  const { user } = useAuth()
  const { addToast } = useToast()
  const queryClient = useQueryClient()
  const [rejectComment, setRejectComment] = useState('')
  const [approveComment, setApproveComment] = useState('')
  const [returnComment, setReturnComment] = useState('')
  const [showDelegateModal, setShowDelegateModal] = useState(false)
  const [showReviewerModal, setShowReviewerModal] = useState(false)
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null)
  const [reviewerDeadlineDays, setReviewerDeadlineDays] = useState<number>(0)
  const [googleFileId, setGoogleFileId] = useState<string | null>(null)
  const [googleEditLoading, setGoogleEditLoading] = useState(false)
  const [googleSyncLoading, setGoogleSyncLoading] = useState(false)
  const [showSignModal, setShowSignModal] = useState(false)
  const [previewDocId, setPreviewDocId] = useState<number | null>(null)
  const signaturePadRef = useRef<SignaturePadHandle>(null)
  const [aiLoading, setAiLoading] = useState(false)
  const [aiResult, setAiResult] = useState<Record<string, unknown> | null>(null)
  const [paragraphQuery, setParagraphQuery] = useState('')
  const [paragraphResults, setParagraphResults] = useState<unknown[] | null>(null)
  const [paragraphLoading, setParagraphLoading] = useState(false)
  const finalVersionRef = useRef<HTMLInputElement>(null)
  const uploadDocRef = useRef<HTMLInputElement>(null)
  const [uploadDocComment, setUploadDocComment] = useState('')
  const [newComment, setNewComment] = useState('')
  const [commentLoading, setCommentLoading] = useState(false)
  const [attachFiles, setAttachFiles] = useState<File[]>([])
  const [attachUploading, setAttachUploading] = useState(false)
  const [replacingId, setReplacingId] = useState<number | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activeAttVersions, setActiveAttVersions] = useState<Record<string, number>>({})

  const taskId = Number(id)

  const { data: task, isLoading } = useQuery({
    queryKey: ['task', id],
    queryFn: () => fetchTask(taskId),
    enabled: Boolean(id),
  })

  const { data: lawyers } = useQuery({
    queryKey: ['users', 'lawyer'],
    queryFn: () => fetchUsers('lawyer'),
    enabled: showDelegateModal,
  })

  const { data: allUsers } = useQuery({
    queryKey: ['users'],
    queryFn: () => fetchUsers(),
    enabled: showReviewerModal,
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['task', id] })
    queryClient.invalidateQueries({ queryKey: ['tasks'] })
  }

  const submitMutation = useMutation({
    mutationFn: () => submitTask(taskId),
    onSuccess: () => { invalidate(); addToast('Task submitted for approval') },
    onError: () => addToast('Failed to submit', 'error'),
  })

  const approveMutation = useMutation({
    mutationFn: () => approveTask(taskId, approveComment || undefined),
    onSuccess: () => { invalidate(); setApproveComment(''); addToast('Task approved') },
    onError: () => addToast('Failed to approve', 'error'),
  })

  const rejectMutation = useMutation({
    mutationFn: () => rejectTask(taskId, rejectComment),
    onSuccess: () => { invalidate(); setRejectComment(''); addToast('Task rejected') },
    onError: () => addToast('Failed to reject', 'error'),
  })

  const returnMutation = useMutation({
    mutationFn: () => returnTask(taskId, returnComment || undefined),
    onSuccess: () => { invalidate(); setReturnComment(''); addToast('Task returned for revision') },
    onError: () => addToast('Failed to return task', 'error'),
  })

  const delegateMutation = useMutation({
    mutationFn: () => delegateTask(taskId, selectedUserId!, ''),
    onSuccess: () => { invalidate(); setShowDelegateModal(false); addToast('Task delegated') },
  })

  const fastTrackMutation = useMutation({
    mutationFn: () => fastTrackTask(taskId),
    onSuccess: () => { invalidate(); addToast('Task fast-tracked and approved') },
  })

  const addReviewerMutation = useMutation({
    mutationFn: () => {
      const deadlineIso = reviewerDeadlineDays > 0
        ? new Date(Date.now() + reviewerDeadlineDays * 24 * 60 * 60 * 1000).toISOString()
        : null
      return addTaskReviewer(taskId, selectedUserId!, deadlineIso)
    },
    onSuccess: () => { invalidate(); setShowReviewerModal(false); setReviewerDeadlineDays(0); addToast('Reviewer added') },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Failed to add reviewer'
      addToast(msg, 'error')
    },
  })

  const removeReviewerMutation = useMutation({
    mutationFn: (userId: number) => removeTaskReviewer(taskId, userId),
    onSuccess: () => { invalidate(); addToast('Reviewer removed') },
    onError: () => addToast('Failed to remove reviewer', 'error'),
  })

  const uploadSignedMutation = useMutation({
    mutationFn: (signature: string) =>
      uploadSignedDocument(taskId, signature),
    onSuccess: () => {
      setShowSignModal(false)
      addToast('Document signed and approved successfully')
      invalidate()
    },
    onError: () => addToast('Failed to sign document', 'error'),
  })

  const uploadFinalMutation = useMutation({
    mutationFn: (file: File) => uploadFinalVersion(taskId, file),
    onSuccess: () => { invalidate(); addToast('Final version uploaded and workflow advanced') },
    onError: () => addToast('Failed to upload final version', 'error'),
  })

  const uploadDocStepMutation = useMutation({
    mutationFn: (file: File) => uploadDocumentStep(taskId, file, uploadDocComment || undefined),
    onSuccess: () => { invalidate(); setUploadDocComment(''); addToast('Document uploaded and sent successfully') },
    onError: () => addToast('Failed to upload document', 'error'),
  })

  const summaryMutation = useMutation({
    mutationFn: () => downloadSummaryReport(taskId),
    onSuccess: () => addToast('Summary report downloaded'),
    onError: () => addToast('Failed to download summary', 'error'),
  })

  const isFinalVersionStep = (task?.current_step_action_type ?? 'review') === 'create_final'
  const isUploadDocStep = (task?.current_step_action_type ?? 'review') === 'upload_document'

  const { data: googleStatus } = useQuery({
    queryKey: ['google-status'],
    queryFn: fetchGoogleStatus,
  })

  const handleAiAnalysis = async () => {
    if (!activeDocId) return
    setAiLoading(true)
    try {
      const result = await analyzeDocument(activeDocId)
      setAiResult(result)
    } catch {
      addToast('AI analysis failed', 'error')
    } finally {
      setAiLoading(false)
    }
  }

  const handleParagraphSearch = async () => {
    if (!paragraphQuery.trim()) return
    setParagraphLoading(true)
    try {
      const result = await searchParagraph(paragraphQuery)
      setParagraphResults((result as { results?: unknown[] }).results ?? [])
    } catch {
      addToast('Legal search failed', 'error')
    } finally {
      setParagraphLoading(false)
    }
  }

  if (isLoading || !task) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="h-10 bg-slate-200 dark:bg-slate-700 rounded-xl w-64" />
        <div className="h-16 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
        <div className="grid gap-5 lg:grid-cols-3">
          <div className="h-52 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          <div className="h-52 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          <div className="h-52 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
        </div>
      </div>
    )
  }

  const isInitiator = task.initiator_id === user?.id
  const isLawyer = user?.role === 'lawyer' || user?.role === 'administrator'

  const currentActionType = task.current_step_action_type ?? 'review'
  const isSignStep = currentActionType === 'sign'

  const isPartnerStep = task.status === 'pending_partner'
  const isCompleted = ['approved', 'archived'].includes(task.status)

  const actions = task.available_actions ?? []
  const hasAction = (a: string) => actions.includes(a)

  const canSubmit = task.status === 'draft' && isInitiator
  const canApprove = !isPartnerStep && hasAction('approved')
  const canReject = !isPartnerStep && hasAction('rejected')
  const canReturn = !isPartnerStep && hasAction('needs_revision')
  const canDelegate = ['pending_lawyer', 'pending_final_lawyer'].includes(task.status) && isLawyer && !isSignStep
  const canFastTrack = ['pending_lawyer', 'pending_final_lawyer'].includes(task.status) && isLawyer && !isSignStep
  const canAddReviewer = ['pending_lawyer', 'pending_final_lawyer'].includes(task.status) && isLawyer && !isSignStep
  const hasAnyAction = canSubmit || canApprove || canReject || canReturn || canDelegate || canFastTrack || canAddReviewer

  const isOverdue = task.deadline && new Date(task.deadline) < new Date() && !['approved', 'archived', 'rejected', 'draft'].includes(task.status)
  const allDocs = task.documents?.slice().sort((a, b) => b.version - a.version) ?? []
  const docs = allDocs.filter(d => !d.is_attachment)
  const attachments = allDocs.filter(d => d.is_attachment)
  const activeDocId = previewDocId ?? docs[0]?.id ?? null
  const activeDoc = docs.find(d => d.id === activeDocId)

  const approveLabel = (() => {
    const steps = task.workflow_route?.steps?.sort((a, b) => a.sort_order - b.sort_order)
    const curIdx = steps?.findIndex(s => s.sort_order === task.current_step) ?? -1
    const next = steps && curIdx >= 0 && curIdx < steps.length - 1 ? steps[curIdx + 1] : null
    const suffix = next ? ` & Send to ${next.name}` : ' & Complete'
    const labels: Record<string, string> = {
      sign: `Sign${suffix}`,
      confirm: `Confirm${suffix}`,
      upload_document: `Upload${suffix}`,
      review: next ? `Approve${suffix}` : 'Approve',
      approve: next ? `Approve${suffix}` : 'Approve',
    }
    return labels[currentActionType] ?? (next ? `Approve${suffix}` : 'Approve')
  })()

  const statusConfig: Record<string, { gradient: string; icon: React.ReactNode; title: string; desc: string }> = {
    approved: {
      gradient: 'from-emerald-500/10 via-emerald-500/5 to-transparent dark:from-emerald-500/20 dark:via-emerald-500/10',
      icon: <svg className="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
      title: 'Approved',
      desc: 'This task has been fully approved and completed.',
    },
    rejected: {
      gradient: 'from-red-500/10 via-red-500/5 to-transparent dark:from-red-500/20 dark:via-red-500/10',
      icon: <svg className="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
      title: 'Rejected',
      desc: 'This task has been rejected.',
    },
  }

  const banner = statusConfig[task.status]
  const revisionActivity = task.status === 'needs_revision'
    ? task.activities?.slice().reverse().find(a => a.action === 'partner_rejected' || a.action === 'returned_for_revision')
    : null

  return (
    <div className="space-y-5">
      {/* ── Header ── */}
      <div className="flex items-center gap-4">
        <Link to="/tasks" className="flex-shrink-0 p-2 -ml-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition-all">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
        </Link>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-3 flex-wrap">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Task #{task.id}</h1>
            <StatusBadge status={task.status} />
            {task.documents?.some(d => d.is_signed) && <Badge color="green">Signed</Badge>}
            {task.fast_tracked && <Badge color="purple">Fast-tracked</Badge>}
            {isOverdue && <Badge color="red" dot>Overdue</Badge>}
          </div>
          <div className="flex items-center gap-4 mt-1 text-xs text-slate-500 dark:text-slate-400">
            {task.registration_number && <span className="font-mono">Reg. {task.registration_number}</span>}
            {task.category && <span>{task.category.name}</span>}
            {task.partner && <span>{task.partner.name}</span>}
          </div>
        </div>
      </div>

      {/* ── Status Banner ── */}
      {banner && (
        <div className={`relative overflow-hidden rounded-2xl bg-gradient-to-r ${banner.gradient} border border-slate-200/60 dark:border-slate-700/60 px-5 py-4`}>
          <div className="flex items-center gap-3">
            {banner.icon}
            <div>
              <p className="text-sm font-semibold text-slate-800 dark:text-white">{banner.title}</p>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{banner.desc}</p>
            </div>
          </div>
        </div>
      )}
      {task.status === 'needs_revision' && (
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-500/20 dark:via-amber-500/10 border border-slate-200/60 dark:border-slate-700/60 px-5 py-4">
          <div className="flex items-center gap-3">
            <svg className="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" /></svg>
            <div>
              <p className="text-sm font-semibold text-slate-800 dark:text-white">Needs Revision</p>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Partner rejected. Please revise and re-submit.</p>
            </div>
          </div>
          {revisionActivity?.comment && (
            <div className="mt-3 ml-8 p-3 bg-amber-50/60 dark:bg-amber-900/20 rounded-xl border border-amber-200/40 dark:border-amber-800/30">
              <p className="text-xs text-amber-800 dark:text-amber-300 italic">"{revisionActivity.comment}"</p>
            </div>
          )}
        </div>
      )}

      {/* ── Workflow Progress ── */}
      <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 p-4 backdrop-blur-sm">
        <TaskStepIndicator routeType={task.route_type} currentStep={task.current_step} status={task.status} workflowSteps={task.workflow_route?.steps} activeSteps={task.active_steps} />
        {task.step_durations && task.workflow_route?.steps && Object.keys(task.step_durations).length > 0 && (() => {
          const steps = task.workflow_route.steps.slice().sort((a, b) => a.sort_order - b.sort_order)
          const durations = task.step_durations as Record<string, number>
          const start = new Date(task.created_at)
          start.setHours(0, 0, 0, 0)
          const now = new Date()
          now.setHours(0, 0, 0, 0)
          let cumulative = 0
          const dotColors: Record<string, string> = {
            manager: 'bg-blue-500',
            lawyer: 'bg-purple-500',
            initiator: 'bg-amber-500',
            partner: 'bg-teal-500',
            gm: 'bg-orange-500',
          }
          const fmt = (d: Date) => d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
          return (
            <div className="mt-4 pt-4 border-t border-slate-200/60 dark:border-slate-700/60">
              <div className="flex items-center justify-between mb-3">
                <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Step Due Dates</p>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">
                  Total <span className="font-semibold text-slate-600 dark:text-slate-300">{Object.values(durations).reduce((a, b) => a + (Number(b) || 0), 0)}d</span>
                </p>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                {steps.map((step, i) => {
                  const days = Number(durations[String(step.id)] ?? durations[step.id as unknown as string]) || 0
                  const plannedStartOffset = cumulative
                  cumulative += days
                  const plannedStart = new Date(start)
                  plannedStart.setDate(plannedStart.getDate() + plannedStartOffset)
                  const due = new Date(start)
                  due.setDate(due.getDate() + cumulative)
                  const isCompleted = step.sort_order < task.current_step
                  const isActive = step.sort_order === task.current_step
                  const isOverdueStep = !isCompleted && due < now
                  const msDay = 1000 * 60 * 60 * 24
                  const daysLeft = Math.ceil((due.getTime() - now.getTime()) / msDay)
                  const elapsedDaysRaw = Math.max(0, (Date.now() - plannedStart.getTime()) / msDay)
                  const usedDays = isCompleted ? days : Math.min(days, elapsedDaysRaw)
                  const progressPct = days > 0 ? Math.min(100, (usedDays / days) * 100) : 0
                  const overflowPct = !isCompleted && days > 0 && elapsedDaysRaw > days
                    ? Math.min(100, ((elapsedDaysRaw - days) / days) * 100)
                    : 0
                  const activeInfo = isActive ? task.active_steps?.find(s => s.step_id === step.id) : undefined
                  const waitingDays = activeInfo?.started_at
                    ? Math.max(0, Math.floor((Date.now() - new Date(activeInfo.started_at).getTime()) / msDay))
                    : null
                  const stateCls = isCompleted
                    ? 'border-emerald-200/60 dark:border-emerald-700/40 bg-emerald-50/50 dark:bg-emerald-900/10'
                    : isOverdueStep
                    ? 'border-red-200 dark:border-red-800/50 bg-red-50/60 dark:bg-red-900/15'
                    : isActive
                    ? 'border-blue-300 dark:border-blue-700 bg-blue-50/70 dark:bg-blue-900/20 ring-1 ring-blue-200 dark:ring-blue-800/50'
                    : 'border-slate-200/70 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/40'
                  const barGradient = isCompleted
                    ? 'bg-gradient-to-r from-emerald-400 to-emerald-500'
                    : isOverdueStep
                    ? 'bg-gradient-to-r from-red-400 to-red-500'
                    : isActive
                    ? 'bg-gradient-to-r from-blue-400 to-blue-500'
                    : 'bg-gradient-to-r from-slate-300 to-slate-400 dark:from-slate-600 dark:to-slate-500'
                  const trackCls = isCompleted
                    ? 'bg-emerald-100/70 dark:bg-emerald-900/30'
                    : isOverdueStep
                    ? 'bg-red-100/70 dark:bg-red-900/30'
                    : isActive
                    ? 'bg-blue-100/70 dark:bg-blue-900/30'
                    : 'bg-slate-200/70 dark:bg-slate-700/40'
                  const pctTextCls = isCompleted
                    ? 'text-emerald-700 dark:text-emerald-300'
                    : isOverdueStep
                    ? 'text-red-700 dark:text-red-300'
                    : isActive
                    ? 'text-blue-700 dark:text-blue-300'
                    : 'text-slate-500 dark:text-slate-400'
                  const leftTextCls = isCompleted
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : daysLeft < 0
                    ? 'text-red-600 dark:text-red-400 font-semibold'
                    : daysLeft <= 1
                    ? 'text-amber-600 dark:text-amber-400 font-medium'
                    : 'text-slate-500 dark:text-slate-400'
                  const displayPct = Math.round(isCompleted ? 100 : progressPct + overflowPct)
                  return (
                    <div key={step.id} className={`relative rounded-xl border px-3 pt-2.5 pb-2.5 ${stateCls}`}>
                      <div className="flex items-center gap-2 mb-1.5">
                        <span className={`inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold shrink-0 ${isCompleted ? 'bg-emerald-500 text-white' : isActive ? 'bg-blue-500 text-white ring-2 ring-blue-200 dark:ring-blue-800/60' : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400'}`}>
                          {isCompleted ? (
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={3} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                          ) : i + 1}
                        </span>
                        <span className="text-[12px] font-medium text-slate-800 dark:text-slate-100 truncate flex-1" title={step.name}>{step.name}</span>
                        {isCompleted ? (
                          <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-medium shrink-0">Done</span>
                        ) : isActive ? (
                          <span className="inline-flex items-center gap-1 text-[9px] px-1.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-medium shrink-0">
                            <span className="relative flex h-1.5 w-1.5">
                              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                              <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-blue-500"></span>
                            </span>
                            Active
                          </span>
                        ) : isOverdueStep ? (
                          <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 font-medium shrink-0">Overdue</span>
                        ) : null}
                      </div>
                      <div className="flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400 mb-2 flex-wrap">
                        <span className={`inline-block w-1.5 h-1.5 rounded-full ${dotColors[step.role] || 'bg-slate-400'}`}></span>
                        <span className="uppercase tracking-wider">{step.role}</span>
                        <span className="text-slate-300 dark:text-slate-600">•</span>
                        <span>{days}d</span>
                        <span className="text-slate-300 dark:text-slate-600">•</span>
                        <span className={`tabular-nums font-semibold ${isOverdueStep ? 'text-red-600 dark:text-red-400' : isCompleted ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300'}`}>{fmt(due)}</span>
                        {isActive && waitingDays !== null && (
                          <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 font-medium" title="Days since this step became active">
                            <svg className="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {waitingDays}d waiting
                          </span>
                        )}
                      </div>
                      <div className="space-y-1">
                        <div className={`relative h-2 rounded-full overflow-hidden ${trackCls}`}>
                          <div className={`absolute inset-y-0 left-0 ${barGradient} transition-all duration-500 ease-out shadow-sm`} style={{ width: `${progressPct}%` }}>
                            {isActive && !isOverdueStep && (
                              <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-pulse"></div>
                            )}
                          </div>
                          {overflowPct > 0 && (
                            <div
                              className="absolute inset-y-0 left-0 bg-red-500/90 transition-all duration-500 ease-out"
                              style={{
                                width: `${overflowPct}%`,
                                backgroundImage: 'repeating-linear-gradient(45deg, rgba(255,255,255,0.18) 0 4px, transparent 4px 8px)',
                              }}
                            ></div>
                          )}
                          {isActive && !isOverdueStep && progressPct > 0 && progressPct < 100 && (
                            <div
                              className="absolute top-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-white border-2 border-blue-500 shadow-md transition-all duration-500"
                              style={{ left: `calc(${progressPct}% - 5px)` }}
                            ></div>
                          )}
                        </div>
                        <div className="flex items-center justify-between text-[9px] tabular-nums">
                          <span className="text-slate-400 dark:text-slate-500">
                            {isCompleted ? (
                              <>{days}d / {days}d</>
                            ) : (
                              <>{Math.floor(usedDays * 10) / 10}d / {days}d used</>
                            )}
                          </span>
                          <span className="flex items-center gap-1.5">
                            <span className={`font-semibold ${pctTextCls}`}>{displayPct}%</span>
                            <span className="text-slate-300 dark:text-slate-600">•</span>
                            {isCompleted ? (
                              <span className="text-emerald-600 dark:text-emerald-400 font-medium">Complete</span>
                            ) : isOverdueStep ? (
                              <span className={leftTextCls}>{Math.abs(daysLeft)}d overdue</span>
                            ) : (
                              <span className={leftTextCls}>{daysLeft}d left</span>
                            )}
                          </span>
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          )
        })()}
      </div>

      {/* ── Main Content Grid ── */}
      <div className="grid gap-5 lg:grid-cols-12">
        {/* Left: AI Tools + Timeline */}
        <div className="lg:col-span-8 space-y-5">
          {/* AI & Legal Tools (Lawyers only) */}
          {isLawyer && (
            <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
              <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
                <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">AI & Legal Tools</h2>
              </div>
              <div className="p-5 space-y-4">
                <button className="w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl text-sm text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-all disabled:opacity-50" onClick={handleAiAnalysis} disabled={aiLoading || !activeDocId}>
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                  {aiLoading ? 'Analyzing...' : 'AI Document Analysis'}
                </button>

                {aiResult && (
                  <div className="rounded-xl bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-800/30 p-4 text-sm space-y-2">
                    <p className="font-medium text-indigo-700 dark:text-indigo-300">Analysis Result</p>
                    {(aiResult as { status?: string }).status === 'disabled' ? (
                      <p className="text-xs text-slate-500">{(aiResult as { message?: string }).message}</p>
                    ) : (aiResult as { status?: string }).status === 'success' ? (
                      <div className="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                        {((aiResult as { analysis?: { summary?: string } }).analysis?.summary) && <p>{(aiResult as { analysis: { summary: string } }).analysis.summary}</p>}
                        {((aiResult as { analysis?: { findings?: Array<{ type: string; severity: string; description: string }> } }).analysis?.findings ?? []).map((f: { type: string; severity: string; description: string }, i: number) => (
                          <div key={i} className="flex gap-2">
                            <span className={`shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium ${f.severity === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : f.severity === 'medium' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'}`}>{f.severity}</span>
                            <span>{f.description}</span>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="text-xs text-red-500">{(aiResult as { message?: string }).message || 'Analysis failed'}</p>
                    )}
                  </div>
                )}

                <div className="border-t border-slate-100 dark:border-slate-700/50 pt-3">
                  <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Paragraph Legal Search</p>
                  <div className="flex gap-2">
                    <input type="text" value={paragraphQuery} onChange={e => setParagraphQuery(e.target.value)} placeholder="Search legal database..." className="flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-900 dark:text-white px-3 py-2 text-sm" onKeyDown={e => e.key === 'Enter' && handleParagraphSearch()} />
                    <button className="px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-200 dark:hover:bg-slate-600 transition disabled:opacity-50" onClick={handleParagraphSearch} disabled={paragraphLoading}>
                      {paragraphLoading ? '...' : 'Search'}
                    </button>
                  </div>
                  {paragraphResults !== null && (
                    <div className="mt-2 text-xs text-slate-500">
                      {paragraphResults.length === 0 ? <p>No results found. Make sure Paragraph API is configured in Admin settings.</p> : (
                        <div className="space-y-1 max-h-40 overflow-y-auto">
                          {(paragraphResults as Array<{ title?: string; url?: string }>).map((r, i) => (
                            <div key={i} className="p-2 rounded bg-slate-50 dark:bg-slate-700/50">
                              {r.title && <p className="font-medium text-slate-700 dark:text-slate-300">{r.title}</p>}
                              {r.url && <a href={r.url} target="_blank" rel="noopener noreferrer" className="text-blue-500 underline">{r.url}</a>}
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Timeline Card */}
          <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
              <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Activity</h2>
              <span className="text-[11px] text-slate-400 dark:text-slate-500">{task.activities?.length ?? 0} events</span>
            </div>
            <div className="max-h-72 overflow-y-auto">
              {task.activities?.length ? (
                <div className="p-4 space-y-0">
                  {task.activities.map((a, idx) => {
                    const isLast = idx === (task.activities?.length ?? 0) - 1
                    const colorClass =
                      a.action.includes('reject') || a.action.includes('returned') ? 'bg-red-500' :
                      a.action.includes('approved') || a.action.includes('signed') ? 'bg-emerald-500' :
                      a.action.includes('submitted') || a.action.includes('created') ? 'bg-blue-500' :
                      a.action.includes('comment') ? 'bg-purple-500' :
                      'bg-slate-300 dark:bg-slate-600'
                    return (
                      <div key={a.id} className="flex gap-3 pb-4 last:pb-0">
                        <div className="flex flex-col items-center">
                          <div className={`w-2.5 h-2.5 rounded-full ${colorClass} ring-4 ring-white dark:ring-slate-800 flex-shrink-0 mt-1.5`} />
                          {!isLast && <div className="w-px flex-1 bg-slate-200 dark:bg-slate-700 mt-1" />}
                        </div>
                        <div className="flex-1 min-w-0 -mt-0.5">
                          <p className="text-[13px] text-slate-700 dark:text-slate-200 leading-snug">
                            <span className="font-semibold text-slate-900 dark:text-white">{a.user?.name ?? 'System'}</span>
                            {' '}<span className="text-slate-500 dark:text-slate-400">{a.action.replace(/_/g, ' ')}</span>
                          </p>
                          <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                            {new Date(a.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                          </p>
                          {a.comment && (
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 bg-slate-50 dark:bg-slate-700/40 rounded-lg px-3 py-2 break-words line-clamp-3 italic">"{a.comment}"</p>
                          )}
                        </div>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <div className="p-6 text-center">
                  <p className="text-xs text-slate-400 dark:text-slate-500">No activity yet</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Right: Actions + Details */}
        <div className="lg:col-span-4 space-y-5">
          <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
              <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Actions</h2>
            </div>
            <div className="p-5 space-y-3">
              {canSubmit && (
                <Button className="w-full" loading={submitMutation.isPending} onClick={() => submitMutation.mutate()}>
                  <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                  Submit for Approval
                </Button>
              )}

              {canApprove && isUploadDocStep && (
                <div className="space-y-2.5">
                  <div className="rounded-xl border-2 border-dashed border-blue-300 dark:border-blue-700 bg-blue-50/50 dark:bg-blue-900/10 p-4 text-center">
                    <input ref={uploadDocRef} type="file" className="hidden" accept=".doc,.docx,.pdf" onChange={(e) => { const f = e.target.files?.[0]; if (f) uploadDocStepMutation.mutate(f) }} />
                    <svg className="w-10 h-10 mx-auto mb-2 text-blue-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Upload Document Required</p>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-3">Upload a PDF, DOC, or DOCX file to proceed</p>
                    <button
                      className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-50"
                      onClick={() => uploadDocRef.current?.click()}
                      disabled={uploadDocStepMutation.isPending}
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                      {uploadDocStepMutation.isPending ? 'Uploading & Sending...' : 'Choose File & Send'}
                    </button>
                  </div>
                  <textarea
                    placeholder="Add a comment (optional)..."
                    rows={2}
                    value={uploadDocComment}
                    onChange={(e) => setUploadDocComment(e.target.value)}
                    className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-900 dark:text-white px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all resize-none"
                  />
                </div>
              )}

              {canApprove && !isUploadDocStep && (
                <div className="space-y-2.5">
                  <textarea
                    placeholder="Add a comment (optional)..."
                    rows={2}
                    value={approveComment}
                    onChange={(e) => setApproveComment(e.target.value)}
                    className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-900 dark:text-white px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all resize-none"
                  />
                  <Button
                    variant="success"
                    className="w-full"
                    loading={isSignStep ? uploadSignedMutation.isPending : approveMutation.isPending}
                    onClick={isSignStep ? () => setShowSignModal(true) : () => approveMutation.mutate()}
                  >
                    {isSignStep && <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>}
                    {approveLabel}
                  </Button>
                </div>
              )}

              {canReject && (
                <div className="space-y-2.5">
                  {!canApprove && <div className="border-t border-slate-100 dark:border-slate-700/50 -mx-5 my-1" />}
                  <textarea
                    placeholder="Rejection reason (required)..."
                    rows={2}
                    value={rejectComment}
                    onChange={(e) => setRejectComment(e.target.value)}
                    className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-900 dark:text-white px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-red-500/20 focus:border-red-400 transition-all resize-none"
                  />
                  <Button variant="danger" className="w-full" loading={rejectMutation.isPending} disabled={!rejectComment.trim()} onClick={() => rejectMutation.mutate()}>
                    <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                    Reject
                  </Button>
                </div>
              )}

              {canReturn && (
                <div className="space-y-2.5">
                  <div className="border-t border-slate-100 dark:border-slate-700/50 -mx-5 my-1" />
                  <textarea
                    placeholder="Revision comment (optional)..."
                    rows={2}
                    value={returnComment}
                    onChange={(e) => setReturnComment(e.target.value)}
                    className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-900 dark:text-white px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400 transition-all resize-none"
                  />
                  <button
                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-all disabled:opacity-50"
                    disabled={returnMutation.isPending}
                    onClick={() => returnMutation.mutate()}
                  >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                    Return for Revision
                  </button>
                </div>
              )}

              {(canFastTrack || canDelegate || canAddReviewer) && (
                <>
                  <div className="border-t border-slate-100 dark:border-slate-700/50 -mx-5 my-1" />
                  <div className="space-y-2">
                    {canFastTrack && (
                      <button className="w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all" onClick={() => fastTrackMutation.mutate()} disabled={fastTrackMutation.isPending}>
                        <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                        Fast-track Approve
                      </button>
                    )}
                    {canDelegate && (
                      <button className="w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all" onClick={() => { setSelectedUserId(null); setShowDelegateModal(true) }}>
                        <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        Delegate to Lawyer
                      </button>
                    )}
                    {canAddReviewer && (
                      <button className="w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all" onClick={() => { setSelectedUserId(null); setReviewerDeadlineDays(0); setShowReviewerModal(true) }}>
                        <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>
                        Add Reviewer
                      </button>
                    )}
                  </div>
                </>
              )}

              {canApprove && (isFinalVersionStep || !!task.can_edit_attachments) && googleStatus?.enabled && googleStatus?.configured && docs.length > 0 && (
                <div className="space-y-2.5">
                  <div className="border-t border-slate-100 dark:border-slate-700/50 -mx-5 my-1" />

                  {!googleFileId ? (
                    <button
                      className="w-full flex items-center justify-center gap-2.5 px-4 py-3 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors disabled:opacity-50"
                      onClick={async () => {
                        setGoogleEditLoading(true)
                        const newTab = window.open('about:blank', '_blank')
                        if (newTab) {
                          newTab.document.write(`<!DOCTYPE html><html><head><title>Opening Google Docs...</title><style>*{margin:0;padding:0;box-sizing:border-box}body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#e2e8f0}.loader{text-align:center}.spinner{width:48px;height:48px;border:3px solid rgba(96,165,250,.2);border-top-color:#60a5fa;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 24px}@keyframes spin{to{transform:rotate(360deg)}}h1{font-size:18px;font-weight:600;margin-bottom:8px}p{font-size:13px;color:#94a3b8}</style></head><body><div class="loader"><div class="spinner"></div><h1>Opening Google Docs</h1><p>Preparing your document for editing...</p></div></body></html>`)
                          newTab.document.close()
                        }
                        try {
                          const result = await taskGoogleEdit(taskId)
                          setGoogleFileId(result.fileId)
                          if (newTab) newTab.location.href = result.editUrl
                          addToast('Document opened in Google Docs')
                        } catch (err: unknown) {
                          if (newTab) newTab.close()
                          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Failed to open Google Docs'
                          addToast(msg, 'error')
                        } finally {
                          setGoogleEditLoading(false)
                        }
                      }}
                      disabled={googleEditLoading}
                    >
                      {googleEditLoading ? (
                        <>
                          <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                          Opening...
                        </>
                      ) : (
                        <>
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                          Edit in Google Docs
                        </>
                      )}
                    </button>
                  ) : (
                    <div className="space-y-2">
                      <div className="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/40 p-3">
                        <p className="text-xs text-blue-700 dark:text-blue-300 font-medium">Document is open in Google Docs</p>
                        <p className="text-[11px] text-blue-600 dark:text-blue-400 mt-0.5">Click sync when you're done editing.</p>
                      </div>
                      <button
                        className="w-full flex items-center justify-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors disabled:opacity-50"
                        onClick={async () => {
                          setGoogleSyncLoading(true)
                          try {
                            await taskGoogleSync(taskId, googleFileId)
                            invalidate()
                            setGoogleFileId(null)
                            addToast('Document synced from Google Docs. New version created.')
                          } catch {
                            addToast('Failed to sync from Google Docs', 'error')
                          } finally {
                            setGoogleSyncLoading(false)
                          }
                        }}
                        disabled={googleSyncLoading}
                      >
                        {googleSyncLoading ? (
                          <>
                            <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                            Syncing...
                          </>
                        ) : (
                          <>
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" /></svg>
                            Sync from Google Docs
                          </>
                        )}
                      </button>
                      <a
                        href={`https://docs.google.com/document/d/${googleFileId}/edit`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        Re-open in Google Docs
                      </a>
                    </div>
                  )}
                  <p className="text-[11px] text-slate-400 dark:text-slate-500 text-center">Edit in Google Docs, sync changes, then approve to advance.</p>
                </div>
              )}

              {isFinalVersionStep && canApprove && (
                <div className="space-y-2.5">
                  <div className="border-t border-slate-100 dark:border-slate-700/50 -mx-5 my-1" />
                  <input ref={finalVersionRef} type="file" accept=".doc,.docx,.pdf" className="hidden" onChange={(e) => {
                    const f = e.target.files?.[0]
                    if (f) uploadFinalMutation.mutate(f)
                  }} />
                  <button className="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs text-slate-500 dark:text-slate-400 border border-dashed border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors disabled:opacity-50" onClick={() => finalVersionRef.current?.click()} disabled={uploadFinalMutation.isPending}>
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                    {uploadFinalMutation.isPending ? 'Uploading...' : 'Upload File Instead'}
                  </button>
                </div>
              )}

              {!hasAnyAction && !isCompleted && (() => {
                const roleLabels: Record<string, string> = {
                  partner: 'Partner', manager: 'Manager', lawyer: 'Lawyer',
                  initiator: 'Initiator', gm: 'General Manager',
                }
                const stepName = task.current_step_name
                const stepRole = task.current_step_role
                const roleLabel = roleLabels[stepRole ?? ''] ?? stepRole ?? ''
                const actionLabels: Record<string, string> = {
                  review: 'review', sign: 'sign', submit: 'submit',
                  create_final: 'create final document', approve: 'approve',
                  upload_document: 'upload a document', confirm: 'confirm',
                }
                const actionLabel = actionLabels[task.current_step_action_type ?? ''] ?? 'take action'

                const iconColor = isPartnerStep ? 'text-purple-400' : stepRole === 'manager' ? 'text-blue-400' : stepRole === 'lawyer' ? 'text-emerald-400' : stepRole === 'gm' ? 'text-amber-400' : 'text-indigo-400'
                const bgColor = isPartnerStep ? 'bg-purple-50 dark:bg-purple-900/20' : stepRole === 'manager' ? 'bg-blue-50 dark:bg-blue-900/20' : stepRole === 'lawyer' ? 'bg-emerald-50 dark:bg-emerald-900/20' : stepRole === 'gm' ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-indigo-50 dark:bg-indigo-900/20'

                return (
                  <div className="text-center py-5 px-3">
                    <div className={`w-11 h-11 rounded-full ${bgColor} flex items-center justify-center mx-auto mb-3`}>
                      <svg className={`w-5 h-5 ${iconColor}`} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <p className="text-sm text-slate-600 dark:text-slate-300 font-semibold mb-1">
                      Waiting for {roleLabel}
                    </p>
                    <p className="text-xs text-slate-400 dark:text-slate-500 leading-relaxed">
                      {stepName ? (
                        <>The <span className="font-medium text-slate-500 dark:text-slate-400">{stepName}</span> step requires the {roleLabel.toLowerCase()} to {actionLabel}</>
                      ) : (
                        <>This step is assigned to another role</>
                      )}
                    </p>
                  </div>
                )
              })()}

              {isCompleted && (
                <div className="space-y-2">
                  <div className="border-t border-slate-100 dark:border-slate-700/50 -mx-5 my-1" />
                  <button className="w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all disabled:opacity-50" onClick={() => summaryMutation.mutate()} disabled={summaryMutation.isPending}>
                    <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    {summaryMutation.isPending ? 'Downloading...' : 'Download Summary Report'}
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Details Card */}
          <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
              <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Details</h2>
            </div>
            <div className="p-5">
              <div className="grid grid-cols-2 gap-x-5 gap-y-4">
                <DetailItem label="Category" value={task.category?.name} />
                <DetailItem label="Partner">
                  <Link to={`/partners/${task.partner_id}`} className="text-blue-600 dark:text-blue-400 hover:underline decoration-blue-300 underline-offset-2">{task.partner?.name}</Link>
                </DetailItem>
                <DetailItem label="Initiator" value={task.initiator?.name} />
                <DetailItem label="Workflow" value={task.workflow_route?.name || task.route_type} />
                {task.assigned_lawyer && <DetailItem label="Lawyer" value={task.assigned_lawyer.name} />}
                {task.deadline && (
                  <DetailItem label="Deadline">
                    <span className={isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : ''}>{new Date(task.deadline).toLocaleDateString()}</span>
                  </DetailItem>
                )}
                {task.amount != null && <DetailItem label="Amount" value={`$${Number(task.amount).toLocaleString()}`} />}
                {(task.validity_from || task.validity_to) && (
                  <DetailItem label="Validity" className="col-span-2" value={`${task.validity_from ? new Date(task.validity_from).toLocaleDateString() : '—'} – ${task.validity_to ? new Date(task.validity_to).toLocaleDateString() : '—'}`} />
                )}
              </div>

              {task.commercial_terms && (
                <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                  <p className="text-[11px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1.5">Commercial Terms</p>
                  <p className="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{task.commercial_terms}</p>
                </div>
              )}

              {task.reviewers?.length > 0 && (
                <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                  <span className="text-[11px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-2">Reviewers</span>
                  <div className="flex flex-wrap gap-1.5">
                    {task.reviewers.map(r => {
                      const dl = r.pivot?.deadline ? new Date(r.pivot.deadline) : null
                      const isOverdue = dl ? dl.getTime() < Date.now() : false
                      const fmtDl = dl ? dl.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : null
                      const stateCls = isOverdue
                        ? 'border-red-200 dark:border-red-700/60 bg-red-50/60 dark:bg-red-900/15'
                        : dl
                        ? 'border-blue-200 dark:border-blue-700/60 bg-blue-50/60 dark:bg-blue-900/15'
                        : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60'
                      return (
                        <div key={r.id} className={`group inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 ${stateCls}`}>
                          <span className="text-xs font-medium text-slate-700 dark:text-slate-200">{r.name}</span>
                          {dl && (
                            <span className={`text-[10px] tabular-nums ${isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-500 dark:text-slate-400'}`} title={dl.toLocaleString()}>
                              {isOverdue ? 'overdue · ' : 'due '}{fmtDl}
                            </span>
                          )}
                          {canAddReviewer && (
                            <button
                              type="button"
                              onClick={() => removeReviewerMutation.mutate(r.id)}
                              disabled={removeReviewerMutation.isPending}
                              className="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full text-slate-400 hover:text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 transition-all disabled:opacity-40"
                              title="Remove reviewer"
                            >
                              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                          )}
                        </div>
                      )
                    })}
                  </div>
                </div>
              )}

              {task.partner_access && (
                <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                  <p className="text-[11px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Partner Access Link</p>
                  <div className="flex items-center gap-2">
                    <input type="text" readOnly value={task.partner_access.url} className="flex-1 min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-xs font-mono text-slate-600 dark:text-slate-300 px-3 py-2 truncate" onClick={e => (e.target as HTMLInputElement).select()} />
                    <button onClick={() => { navigator.clipboard.writeText(task.partner_access!.url); addToast('Copied') }} className="px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors flex-shrink-0">Copy</button>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Attachments Card */}
          {(attachments.length > 0 || task.can_edit_attachments) && (() => {
            const canEdit = task.can_edit_attachments && !isCompleted
            const getFileIcon = (name: string) => {
              const ext = name.split('.').pop()?.toLowerCase() || ''
              if (ext === 'pdf') return { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400', label: 'PDF' }
              if (['doc', 'docx'].includes(ext)) return { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400', label: 'DOC' }
              if (['xls', 'xlsx'].includes(ext)) return { bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400', label: 'XLS' }
              if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-600 dark:text-purple-400', label: 'IMG' }
              if (['zip', 'rar', '7z'].includes(ext)) return { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-600 dark:text-amber-400', label: 'ZIP' }
              if (['ppt', 'pptx'].includes(ext)) return { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-600 dark:text-orange-400', label: 'PPT' }
              return { bg: 'bg-slate-100 dark:bg-slate-700', text: 'text-slate-500 dark:text-slate-400', label: 'FILE' }
            }
            return (
            <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
              <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">
                  Attachments
                </h2>
                <span className="text-[11px] text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                  {attachments.length} file{attachments.length !== 1 ? 's' : ''}
                  {canEdit && <span className="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold">editable</span>}
                </span>
              </div>
              <div className="p-0">
                {(() => {
                  const versionMap: Record<number, typeof attachments> = {}
                  for (const att of attachments) {
                    const v = att.version
                    if (!versionMap[v]) versionMap[v] = []
                    versionMap[v].push(att)
                  }
                  const versionNums = Object.keys(versionMap).map(Number).sort((a, b) => a - b)
                  const latestVer = versionNums[versionNums.length - 1] ?? 1
                  const activeVer = (activeAttVersions['_global'] != null && versionMap[activeAttVersions['_global']]) ? activeAttVersions['_global'] : latestVer
                  const activeFiles = versionMap[activeVer] || []

                  return (
                    <>
                      {versionNums.length > 1 && (
                        <div className="flex items-center gap-1 px-4 py-2 border-b border-slate-100 dark:border-slate-700/40 bg-slate-50/30 dark:bg-slate-800/30 overflow-x-auto">
                          <span className="text-[10px] font-medium text-slate-400 dark:text-slate-500 mr-1">Version:</span>
                          {versionNums.map(v => (
                            <button
                              key={v}
                              onClick={() => setActiveAttVersions(prev => ({ ...prev, _global: v }))}
                              className={`px-3 py-1 rounded-lg text-[11px] font-semibold whitespace-nowrap transition-all ${v === activeVer
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/40'
                              }`}
                            >
                              v{v}
                              {v === latestVer && <span className="ml-1 text-[9px] opacity-70">latest</span>}
                            </button>
                          ))}
                        </div>
                      )}

                      <div className="p-4 space-y-1.5">
                        {activeFiles.map(att => {
                          const fi = getFileIcon(att.original_name || att.path || '')
                          const fname = att.original_name || att.path?.split('/').pop() || `File`
                          const isReplacing = replacingId === att.id
                          return (
                            <div key={att.id} className="flex items-center gap-3 p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-700/60 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all group">
                              <div className={`w-9 h-9 rounded-lg ${fi.bg} flex items-center justify-center flex-shrink-0`}>
                                <span className={`text-[9px] font-bold ${fi.text}`}>{fi.label}</span>
                              </div>
                              <div className="flex-1 min-w-0">
                                <p className="text-xs font-medium text-slate-900 dark:text-white truncate">{fname}</p>
                                <p className="text-[10px] text-slate-400 dark:text-slate-500">
                                  {new Date(att.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                                </p>
                              </div>
                              <div className="flex items-center gap-1 flex-shrink-0">
                                <a href={getAttachmentDownloadUrl(task.id, att.id)} title="Download" className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                  </svg>
                                </a>
                                {canEdit && activeVer === latestVer && (
                                  <label title="Replace with new version" className={`p-1.5 rounded-lg cursor-pointer transition-colors ${isReplacing ? 'text-amber-500 bg-amber-50 dark:bg-amber-900/20' : 'text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20'}`}>
                                    <input type="file" className="sr-only" onChange={async (e) => {
                                      const file = e.target.files?.[0]
                                      if (!file) return
                                      e.target.value = ''
                                      if (file.size > 20 * 1024 * 1024) { addToast('File exceeds 20 MB', 'error'); return }
                                      setReplacingId(att.id)
                                      try {
                                        await replaceTaskAttachment(taskId, att.id, file)
                                        invalidate()
                                        setActiveAttVersions(prev => { const n = { ...prev }; delete n['_global']; return n })
                                        addToast(`Replaced "${fname}" → new version created`)
                                      } catch { addToast('Failed to replace attachment', 'error') }
                                      finally { setReplacingId(null) }
                                    }} />
                                    {isReplacing
                                      ? <div className="w-4 h-4 border-2 border-amber-400/30 border-t-amber-500 rounded-full animate-spin" />
                                      : <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" /></svg>
                                    }
                                  </label>
                                )}
                                {canEdit && activeVer === latestVer && (
                                  <button
                                    title="Remove from attachments"
                                    disabled={deletingId === att.id}
                                    onClick={async () => {
                                      if (!confirm(`Remove "${fname}" from attachments?`)) return
                                      setDeletingId(att.id)
                                      try {
                                        await deleteTaskAttachment(taskId, att.id)
                                        invalidate()
                                        setActiveAttVersions(prev => { const n = { ...prev }; delete n['_global']; return n })
                                        addToast(`Removed "${fname}"`)
                                      } catch { addToast('Failed to remove attachment', 'error') }
                                      finally { setDeletingId(null) }
                                    }}
                                    className={`p-1.5 rounded-lg transition-colors ${deletingId === att.id ? 'text-red-500 bg-red-50 dark:bg-red-900/20' : 'text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'}`}
                                  >
                                    {deletingId === att.id
                                      ? <div className="w-4 h-4 border-2 border-red-400/30 border-t-red-500 rounded-full animate-spin" />
                                      : <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    }
                                  </button>
                                )}
                              </div>
                            </div>
                          )
                        })}
                        {activeFiles.length === 0 && (
                          <p className="text-center text-xs text-slate-400 dark:text-slate-500 py-2">No files in this version</p>
                        )}
                      </div>
                    </>
                  )
                })()}

                {canEdit && (
                  <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/50">
                    {attachFiles.length > 0 && (
                      <div className="space-y-1 mb-2">
                        {attachFiles.map((f, i) => {
                          const fi = getFileIcon(f.name)
                          return (
                            <div key={`${f.name}-${i}`} className="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                              <div className={`w-7 h-7 rounded ${fi.bg} flex items-center justify-center flex-shrink-0`}>
                                <span className={`text-[8px] font-bold ${fi.text}`}>{fi.label}</span>
                              </div>
                              <div className="flex-1 min-w-0">
                                <p className="text-[11px] font-medium text-slate-900 dark:text-white truncate">{f.name}</p>
                                <p className="text-[10px] text-slate-400">{f.size < 1024 * 1024 ? `${(f.size / 1024).toFixed(0)} KB` : `${(f.size / 1024 / 1024).toFixed(1)} MB`}</p>
                              </div>
                              <button type="button" onClick={() => setAttachFiles(prev => prev.filter((_, idx) => idx !== i))} className="p-0.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-500 transition-colors">
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                              </button>
                            </div>
                          )
                        })}
                        <button
                          disabled={attachUploading}
                          onClick={async () => {
                            if (!attachFiles.length) return
                            setAttachUploading(true)
                            try {
                              await uploadTaskAttachments(taskId, attachFiles)
                              setAttachFiles([])
                              invalidate()
                              setActiveAttVersions(prev => { const n = { ...prev }; delete n['_global']; return n })
                              addToast('Attachments uploaded (new version)')
                            } catch { addToast('Failed to upload attachments', 'error') }
                            finally { setAttachUploading(false) }
                          }}
                          className="w-full flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors disabled:opacity-40"
                        >
                          {attachUploading ? <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : (
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                          )}
                          {attachUploading ? 'Uploading...' : `Upload ${attachFiles.length} file${attachFiles.length !== 1 ? 's' : ''}`}
                        </button>
                      </div>
                    )}
                    <label className={`w-full flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 dark:hover:border-blue-500 transition-all text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 cursor-pointer ${attachFiles.length > 0 ? 'p-2' : 'p-4'}`}>
                      <input type="file" multiple className="sr-only" onChange={(e) => {
                        const files = Array.from(e.target.files || []).filter(f => f.size <= 20 * 1024 * 1024)
                        if (files.length) setAttachFiles(prev => [...prev, ...files])
                        e.target.value = ''
                      }} />
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                      </svg>
                      <span className="text-xs font-medium">{attachFiles.length > 0 ? 'Add more files' : 'Upload new attachments'}</span>
                    </label>
                  </div>
                )}

                {attachments.length === 0 && !canEdit && (
                  <p className="text-center text-xs text-slate-400 dark:text-slate-500 py-3">No attachments</p>
                )}
              </div>
            </div>
            )
          })()}
        </div>
      </div>

      {/* ── Table Data Section ── */}
      {task.table_data && Object.keys(task.table_data).length > 0 && (
        <div className="space-y-4">
          {Object.entries(task.table_data).map(([shortcode, rows]) => {
            if (!Array.isArray(rows) || rows.length === 0) return null
            const columns = Object.keys(rows[0]).filter(k => k !== '_inventory_id')
            return (
              <div key={shortcode} className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
                <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                  <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <svg className="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M21.375 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M12 17.25v-5.25" />
                    </svg>
                    Table: {shortcode}
                  </h2>
                  <span className="text-[11px] text-slate-400 dark:text-slate-500">{rows.length} row{rows.length !== 1 ? 's' : ''}</span>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <th className="px-4 py-2.5 text-left font-semibold text-slate-500 dark:text-slate-400 w-10">#</th>
                        {columns.map(col => (
                          <th key={col} className="px-4 py-2.5 text-left font-semibold text-slate-500 dark:text-slate-400 capitalize">
                            {col.replace(/_/g, ' ')}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((row, ri) => (
                        <tr key={ri} className="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                          <td className="px-4 py-2 text-slate-400 font-mono">{ri + 1}</td>
                          {columns.map(col => (
                            <td key={col} className="px-4 py-2 text-slate-700 dark:text-slate-300">
                              {row[col] || '—'}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )
          })}
        </div>
      )}

      {/* ── Comments Section ── */}
      <div className="rounded-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 backdrop-blur-sm overflow-hidden">
        <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
          <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Comments</h2>
          <span className="text-[11px] text-slate-400 dark:text-slate-500">{task.comments?.length ?? 0}</span>
        </div>
        <div className="p-5">
          <div className="flex gap-3 mb-5">
            <div className="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
              <span className="text-xs font-bold text-blue-600 dark:text-blue-400">{user?.name?.charAt(0)?.toUpperCase() || 'U'}</span>
            </div>
            <div className="flex-1">
              <textarea
                value={newComment}
                onChange={(e) => setNewComment(e.target.value)}
                placeholder="Write a comment..."
                rows={2}
                className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-900 dark:text-white px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all resize-none"
              />
              <div className="flex justify-end mt-2">
                <button
                  disabled={!newComment.trim() || commentLoading}
                  onClick={async () => {
                    if (!newComment.trim()) return
                    setCommentLoading(true)
                    try {
                      await createTaskComment(taskId, { body: newComment.trim() })
                      setNewComment('')
                      invalidate()
                      addToast('Comment added')
                    } catch { addToast('Failed to add comment', 'error') }
                    finally { setCommentLoading(false) }
                  }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  {commentLoading ? (
                    <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                  ) : (
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                  )}
                  Send
                </button>
              </div>
            </div>
          </div>

          {(task.comments?.length ?? 0) > 0 ? (
            <div className="space-y-4 border-t border-slate-100 dark:border-slate-700/50 pt-4">
              {task.comments!.map(c => (
                <div key={c.id} className="flex gap-3">
                  <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                    <span className="text-xs font-bold text-slate-500 dark:text-slate-400">{c.user?.name?.charAt(0)?.toUpperCase() || '?'}</span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-xs font-semibold text-slate-900 dark:text-white">{c.user?.name || 'Unknown'}</span>
                      <span className="text-[10px] text-slate-400 dark:text-slate-500">{new Date(c.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
                    <p className="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap break-words leading-relaxed">{c.body}</p>
                    {c.replies?.length > 0 && (
                      <div className="mt-3 ml-2 pl-3 border-l-2 border-slate-200 dark:border-slate-700 space-y-3">
                        {c.replies.map(r => (
                          <div key={r.id} className="flex gap-2.5">
                            <div className="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                              <span className="text-[9px] font-bold text-slate-500 dark:text-slate-400">{r.user?.name?.charAt(0)?.toUpperCase() || '?'}</span>
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-2 mb-0.5">
                                <span className="text-[11px] font-semibold text-slate-800 dark:text-slate-200">{r.user?.name}</span>
                                <span className="text-[10px] text-slate-400">{new Date(r.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                              </div>
                              <p className="text-xs text-slate-600 dark:text-slate-400 whitespace-pre-wrap break-words">{r.body}</p>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-4 border-t border-slate-100 dark:border-slate-700/50">
              <p className="text-xs text-slate-400 dark:text-slate-500">No comments yet. Be the first to comment.</p>
            </div>
          )}
        </div>
      </div>

      {/* ── Document Viewer ── */}
      {docs.length > 0 ? (
        <div className="space-y-3">
          <div className="flex items-center justify-between px-1">
            <h2 className="text-[13px] font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Documents</h2>
            {activeDocId && (
              <a href={getDocumentDownloadUrl(task.id, activeDocId)} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Download
              </a>
            )}
          </div>

          {/* Version tabs */}
          <div className="flex items-center gap-2 overflow-x-auto pb-1 px-1">
            {docs.map(d => (
              <button
                key={d.id}
                onClick={() => setPreviewDocId(d.id)}
                className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium transition-all whitespace-nowrap ${
                  d.id === activeDocId
                    ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/80 dark:ring-slate-600'
                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/40'
                }`}
              >
                <span>v{d.version}</span>
                {d.is_signed && (
                  <span className="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                    <svg className="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Signed
                  </span>
                )}
              </button>
            ))}

            {activeDoc && (
              <div className="flex items-center gap-3 text-[11px] text-slate-400 dark:text-slate-500 ml-auto flex-shrink-0">
                <span>{new Date(activeDoc.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                {activeDoc.signer && (
                  <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                    {activeDoc.signer.name}
                  </span>
                )}
                {activeDoc.signature_path && (
                  <img src={getSignatureUrl(task.id, activeDoc.id)} alt="Signature" className="h-6 object-contain dark:invert opacity-60" />
                )}
              </div>
            )}
          </div>

          {/* PDF Viewer */}
          {activeDocId && (
            <Suspense fallback={
              <div className="flex items-center justify-center py-32 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-200/60 dark:border-slate-700/60">
                <div className="flex flex-col items-center gap-3">
                  <div className="animate-spin w-8 h-8 border-2 border-blue-400 border-t-transparent rounded-full" />
                  <span className="text-xs text-slate-400">Loading document viewer...</span>
                </div>
              </div>
            }>
              <PdfCommentViewer
                pdfUrl={getDocumentPreviewUrl(task.id, activeDocId)}
                taskId={task.id}
                documentId={activeDocId}
              />
            </Suspense>
          )}
        </div>
      ) : attachments.length === 0 && (
        <div className="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/50 p-12">
          <div className="flex flex-col items-center justify-center text-slate-400">
            <div className="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center mb-4">
              <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
            </div>
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No documents uploaded yet</p>
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">Documents will appear here once they're uploaded</p>
          </div>
        </div>
      )}

      {/* ── Modals ── */}
      <Modal open={showDelegateModal} onClose={() => setShowDelegateModal(false)} title="Delegate to Lawyer" footer={<><Button variant="secondary" onClick={() => setShowDelegateModal(false)}>Cancel</Button><Button loading={delegateMutation.isPending} disabled={!selectedUserId} onClick={() => delegateMutation.mutate()}>Delegate</Button></>}>
        <select value={selectedUserId ?? ''} onChange={(e) => setSelectedUserId(Number(e.target.value) || null)} className="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2.5 text-sm">
          <option value="">Select a lawyer</option>
          {lawyers?.map(l => <option key={l.id} value={l.id}>{l.name} ({l.email})</option>)}
        </select>
      </Modal>

      <Modal open={showReviewerModal} onClose={() => setShowReviewerModal(false)} title="Add Reviewer" footer={<><Button variant="secondary" onClick={() => setShowReviewerModal(false)}>Cancel</Button><Button loading={addReviewerMutation.isPending} disabled={!selectedUserId} onClick={() => addReviewerMutation.mutate()}>Add</Button></>}>
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">Reviewer</label>
            <select value={selectedUserId ?? ''} onChange={(e) => setSelectedUserId(Number(e.target.value) || null)} className="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2.5 text-sm">
              <option value="">Select a user</option>
              {allUsers?.filter(u => !task.reviewers?.some(r => r.id === u.id)).map(u => <option key={u.id} value={u.id}>{u.name} ({u.role})</option>)}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">
              Deadline <span className="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
            </label>
            <div className="rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2.5">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setReviewerDeadlineDays(Math.max(0, reviewerDeadlineDays - 1))}
                  className="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-40"
                  disabled={reviewerDeadlineDays <= 0}
                  title="Decrease by 1 day"
                >
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14" /></svg>
                </button>
                <div className="flex-1 flex items-baseline justify-center gap-1.5">
                  <input
                    type="number"
                    min={0}
                    max={365}
                    value={reviewerDeadlineDays}
                    onChange={(e) => {
                      const v = Math.max(0, Math.min(365, parseInt(e.target.value || '0', 10) || 0))
                      setReviewerDeadlineDays(v)
                    }}
                    className="w-16 text-center text-lg font-semibold tabular-nums bg-transparent text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 rounded-md py-0.5"
                  />
                  <span className="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    {reviewerDeadlineDays === 1 ? 'day' : 'days'}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={() => setReviewerDeadlineDays(Math.min(365, reviewerDeadlineDays + 1))}
                  className="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-40"
                  disabled={reviewerDeadlineDays >= 365}
                  title="Increase by 1 day"
                >
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 5v14M5 12h14" /></svg>
                </button>
              </div>
              <div className="flex items-center gap-1.5 mt-2.5 flex-wrap">
                {[0, 1, 3, 7, 14, 30].map(d => (
                  <button
                    key={d}
                    type="button"
                    onClick={() => setReviewerDeadlineDays(d)}
                    className={`px-2.5 py-1 rounded-lg text-[11px] font-medium tabular-nums transition-all ${
                      reviewerDeadlineDays === d
                        ? 'bg-blue-500 text-white shadow-sm'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                    }`}
                  >
                    {d === 0 ? 'None' : `${d}d`}
                  </button>
                ))}
              </div>
            </div>
            <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5 flex items-center gap-1.5">
              {reviewerDeadlineDays > 0 ? (
                <>
                  <svg className="w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                  <span>Due <span className="font-semibold text-slate-600 dark:text-slate-300">{new Date(Date.now() + reviewerDeadlineDays * 86400000).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}</span></span>
                </>
              ) : (
                <span>No deadline — reviewer has unlimited time.</span>
              )}
            </p>
          </div>
        </div>
      </Modal>

      <Modal open={showSignModal} onClose={() => setShowSignModal(false)} title="Sign & Submit Document" footer={<><Button variant="secondary" onClick={() => setShowSignModal(false)}>Cancel</Button><Button variant="success" loading={uploadSignedMutation.isPending} onClick={() => { if (signaturePadRef.current?.isEmpty()) return; const sig = signaturePadRef.current?.toDataURL(); if (sig) uploadSignedMutation.mutate(sig) }}>Confirm &amp; Sign</Button></>}>
        <div className="space-y-5">
          <p className="text-sm text-slate-500 dark:text-slate-400">Your signature will be applied to the latest version of the document.</p>
          <div>
            <div className="flex items-center justify-between mb-1.5">
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">Draw your signature</label>
              <button type="button" onClick={() => signaturePadRef.current?.clear()} className="text-xs text-blue-600 dark:text-blue-400 hover:underline">Clear</button>
            </div>
            <SignaturePad ref={signaturePadRef} height={180} />
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">Your electronic signature will be attached to this document as proof of approval.</p>
          </div>
        </div>
      </Modal>

    </div>
  )
}

function DetailItem({ label, value, children, className = '' }: { label: string; value?: string | null; children?: React.ReactNode; className?: string }) {
  return (
    <div className={className}>
      <dt className="text-[11px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-0.5">{label}</dt>
      <dd className="text-sm text-slate-800 dark:text-slate-200 font-medium">{children ?? value ?? '—'}</dd>
    </div>
  )
}
