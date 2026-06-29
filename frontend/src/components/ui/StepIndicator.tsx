import { useState } from 'react'

interface Step {
  label: string
  status: 'completed' | 'current' | 'upcoming'
}

interface StepIndicatorProps {
  steps: Step[]
  className?: string
}

export default function StepIndicator({ steps, className = '' }: StepIndicatorProps) {
  const [expanded, setExpanded] = useState(false)
  const currentIdx = steps.findIndex(s => s.status === 'current')
  const completedCount = steps.filter(s => s.status === 'completed').length
  const progress = steps.length > 0
    ? ((completedCount + (currentIdx >= 0 ? 0.5 : 0)) / steps.length) * 100
    : 0

  return (
    <div className={className}>
      {/* Mobile: Compact summary */}
      <div className="sm:hidden">
        <button
          type="button"
          onClick={() => setExpanded(!expanded)}
          className="w-full text-left"
        >
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2 min-w-0">
              <span className="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-[10px] font-bold">
                {currentIdx >= 0 ? currentIdx + 1 : completedCount}
              </span>
              <span className="text-sm font-semibold text-slate-900 dark:text-white truncate">
                {currentIdx >= 0 ? steps[currentIdx].label : 'Completed'}
              </span>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <span className="text-[11px] text-slate-400 dark:text-slate-500">
                {completedCount}/{steps.length}
              </span>
              <svg
                className={`w-4 h-4 text-slate-400 transition-transform ${expanded ? 'rotate-180' : ''}`}
                fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </div>
          </div>
          <div className="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
            <div
              className="h-full rounded-full bg-gradient-to-r from-emerald-500 to-blue-500 transition-all duration-500"
              style={{ width: `${progress}%` }}
            />
          </div>
        </button>

        {expanded && (
          <div className="mt-3 space-y-1 max-h-60 overflow-y-auto">
            {steps.map((step, idx) => (
              <div
                key={idx}
                className={`flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition-colors ${
                  step.status === 'current'
                    ? 'bg-blue-50 dark:bg-blue-900/20'
                    : ''
                }`}
              >
                <StepDot status={step.status} number={idx + 1} size="sm" />
                <span className={`text-xs font-medium ${
                  step.status === 'current'
                    ? 'text-blue-700 dark:text-blue-300'
                    : step.status === 'completed'
                      ? 'text-slate-500 dark:text-slate-400'
                      : 'text-slate-400 dark:text-slate-500'
                }`}>
                  {step.label}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Desktop: Scrollable horizontal with all labels */}
      <div className="hidden sm:block overflow-x-auto pb-1 -mb-1 scrollbar-thin">
        <div className="relative min-w-max px-2">
          {/* Progress track */}
          <div className="absolute top-3.5 left-4 right-4 h-0.5 bg-slate-200 dark:bg-slate-700" />
          <div
            className="absolute top-3.5 left-4 h-0.5 bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500"
            style={{ width: `calc(${progress}% - 2rem)` }}
          />

          {/* Steps */}
          <div className="relative flex">
            {steps.map((step, idx) => (
              <div key={idx} className="flex flex-col items-center" style={{ flex: '1 1 0', minWidth: '72px' }}>
                <StepDot status={step.status} number={idx + 1} size="md" />
                <span className={`mt-2 text-[10px] leading-tight font-medium text-center px-1 line-clamp-2 ${
                  step.status === 'current'
                    ? 'text-blue-600 dark:text-blue-400 font-semibold'
                    : step.status === 'completed'
                      ? 'text-emerald-600 dark:text-emerald-400'
                      : 'text-slate-400 dark:text-slate-500'
                }`}>
                  {step.label}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

function StepDot({ status, number, size }: { status: Step['status']; number: number; size: 'sm' | 'md' }) {
  const dims = size === 'sm' ? 'w-5 h-5 text-[9px]' : 'w-7 h-7 text-[11px]'

  if (status === 'completed') {
    return (
      <span className={`${dims} flex items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm`}>
        <svg className={size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5'} fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={3}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </span>
    )
  }

  if (status === 'current') {
    return (
      <span className={`${dims} flex items-center justify-center rounded-full bg-blue-600 text-white font-bold shadow-md ring-[3px] ring-blue-200 dark:ring-blue-800/60`}>
        {number}
      </span>
    )
  }

  return (
    <span className={`${dims} flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 font-medium border border-slate-200 dark:border-slate-600`}>
      {number}
    </span>
  )
}

interface ActiveStepInfo {
  step_id: number
  step_name: string | null
  role: string | null
  status: string
}

interface TaskStepIndicatorProps {
  routeType: string
  currentStep: number
  status: string
  workflowSteps?: { sort_order: number; name: string; id?: number }[]
  activeSteps?: ActiveStepInfo[]
}

export function TaskStepIndicator({ routeType, currentStep, status, workflowSteps, activeSteps }: TaskStepIndicatorProps) {
  const isTerminal = status === 'approved' || status === 'archived' || status === 'rejected'
  const activeStepIds = new Set(activeSteps?.filter(s => s.status === 'active').map(s => s.step_id) ?? [])
  const isParallel = activeStepIds.size > 1

  let stepLabels: { label: string; id?: number }[]

  if (workflowSteps && workflowSteps.length > 0) {
    stepLabels = workflowSteps
      .sort((a, b) => a.sort_order - b.sort_order)
      .map((s) => ({ label: s.name, id: s.id }))
  } else {
    const isStandard = routeType === 'standard'
    const labels = isStandard
      ? ['Submit', 'Manager Review', 'Lawyer Review', 'Initiator Negotiation', 'Final Lawyer Review', 'Final Manager Approval']
      : ['Submit', 'Manager Approval']
    stepLabels = labels.map(l => ({ label: l }))
  }

  const steps: Step[] = stepLabels.map((item, idx) => {
    const stepNum = idx + 1
    const stepId = item.id
    let s: Step['status'] = 'upcoming'

    if (stepId && activeStepIds.has(stepId)) {
      s = 'current'
    } else if (isTerminal && status !== 'rejected') {
      s = 'completed'
    } else if (status === 'rejected') {
      s = stepNum < currentStep ? 'completed' : stepNum === currentStep ? 'current' : 'upcoming'
    } else if (stepNum < currentStep) {
      s = 'completed'
    } else if (stepNum === currentStep && !isParallel) {
      s = 'current'
    }
    return { label: item.label, status: s }
  })

  return (
    <div>
      <StepIndicator steps={steps} />
      {isParallel && (
        <div className="mt-3 flex items-center gap-2 text-xs flex-wrap">
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-medium">
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            Parallel steps active
          </span>
          {activeSteps?.filter(s => s.status === 'active').map((s, i) => (
            <span key={i} className="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium">
              {s.step_name || `Step ${s.step_id}`}
            </span>
          ))}
        </div>
      )}
    </div>
  )
}
