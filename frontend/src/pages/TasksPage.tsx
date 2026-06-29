import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchTasks, fetchWorkflowRoutes } from '../api/tasks'
import { fetchDocumentCategories } from '../api/documentCategories'
import { fetchPartners } from '../api/partners'
import { fetchUsers } from '../api/users'
import Button from '../components/ui/Button'
import { Card } from '../components/ui/Card'
import { StatusBadge } from '../components/ui/Badge'
import Badge from '../components/ui/Badge'
import { Table, Thead, Th, Tbody, Tr, Td, EmptyRow } from '../components/ui/Table'
import Pagination from '../components/ui/Pagination'
import { SkeletonTable } from '../components/ui/Skeleton'

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'draft', label: 'Draft' },
  { value: 'pending_manager', label: 'Pending Manager' },
  { value: 'pending_lawyer', label: 'Pending Lawyer' },
  { value: 'pending_initiator', label: 'Pending Initiator' },
  { value: 'pending_final_lawyer', label: 'Final Lawyer Review' },
  { value: 'pending_final_manager', label: 'Final Manager Approval' },
  { value: 'pending_partner', label: 'Pending Partner' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

const ROUTE_TYPE_OPTIONS = [
  { value: '', label: 'All routes' },
  { value: 'standard', label: 'Standard' },
  { value: 'simplified', label: 'Simplified' },
]

const SORT_OPTIONS: { value: string; label: string }[] = [
  { value: 'updated_at', label: 'Recently updated' },
  { value: 'created_at', label: 'Recently created' },
  { value: 'deadline', label: 'Deadline' },
  { value: 'id', label: 'Task ID' },
  { value: 'amount', label: 'Amount' },
]

function formatTaskAmount(amount: number | null | undefined): string {
  if (amount == null || Number.isNaN(Number(amount))) return '—'
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(Number(amount))
}

export default function TasksPage() {
  const [status, setStatus] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [search, setSearch] = useState('')
  const [partnerId, setPartnerId] = useState('')
  const [initiatorId, setInitiatorId] = useState('')
  const [lawyerId, setLawyerId] = useState('')
  const [routeType, setRouteType] = useState('')
  const [workflowRouteId, setWorkflowRouteId] = useState('')
  const [fastTracked, setFastTracked] = useState('')
  const [deadlineFrom, setDeadlineFrom] = useState('')
  const [deadlineTo, setDeadlineTo] = useState('')
  const [createdFrom, setCreatedFrom] = useState('')
  const [createdTo, setCreatedTo] = useState('')
  const [minAmount, setMinAmount] = useState('')
  const [maxAmount, setMaxAmount] = useState('')
  const [overdue, setOverdue] = useState(false)
  const [sortField, setSortField] = useState('updated_at')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc')
  const [showAdvanced, setShowAdvanced] = useState(false)
  const [page, setPage] = useState(1)

  const { data: categories } = useQuery({
    queryKey: ['document-categories'],
    queryFn: fetchDocumentCategories,
  })

  const { data: partners } = useQuery({
    queryKey: ['partners-all'],
    queryFn: () => fetchPartners({ page: 1 }),
  })

  const { data: users } = useQuery({
    queryKey: ['users-all'],
    queryFn: () => fetchUsers(),
  })

  const { data: workflowRoutes } = useQuery({
    queryKey: ['workflow-routes'],
    queryFn: fetchWorkflowRoutes,
  })

  const { data, isLoading } = useQuery({
    queryKey: [
      'tasks', status, categoryId, search, partnerId, initiatorId, lawyerId,
      routeType, workflowRouteId, fastTracked, deadlineFrom, deadlineTo,
      createdFrom, createdTo, minAmount, maxAmount, overdue, sortField, sortDir, page,
    ],
    queryFn: () => fetchTasks({
      status: status || undefined,
      document_category_id: categoryId ? Number(categoryId) : undefined,
      search: search || undefined,
      partner_id: partnerId ? Number(partnerId) : undefined,
      initiator_id: initiatorId ? Number(initiatorId) : undefined,
      assigned_lawyer_id: lawyerId ? Number(lawyerId) : undefined,
      route_type: routeType || undefined,
      workflow_route_id: workflowRouteId ? Number(workflowRouteId) : undefined,
      fast_tracked: fastTracked === '' ? undefined : fastTracked === 'yes',
      deadline_from: deadlineFrom || undefined,
      deadline_to: deadlineTo || undefined,
      created_from: createdFrom || undefined,
      created_to: createdTo || undefined,
      min_amount: minAmount ? Number(minAmount) : undefined,
      max_amount: maxAmount ? Number(maxAmount) : undefined,
      overdue: overdue || undefined,
      sort: sortField,
      dir: sortDir,
      page,
    }),
  })

  const initiators = users?.filter(u => u.role === 'initiator')
  const lawyers = users?.filter(u => u.role === 'lawyer')

  const partnerName = partners?.data?.find(p => p.id === Number(partnerId))?.name
  const initiatorName = initiators?.find(u => u.id === Number(initiatorId))?.name
  const lawyerName = lawyers?.find(u => u.id === Number(lawyerId))?.name
  const categoryName = categories?.find(c => c.id === Number(categoryId))?.name
  const statusLabel = STATUS_OPTIONS.find(o => o.value === status)?.label
  const routeTypeLabel = ROUTE_TYPE_OPTIONS.find(o => o.value === routeType)?.label
  const workflowRouteName = workflowRoutes?.find(r => r.id === Number(workflowRouteId))?.name

  type Chip = { label: string; clear: () => void }
  const chips: Chip[] = [
    search ? { label: `Search: "${search}"`, clear: () => { setSearch(''); setPage(1) } } : null,
    status ? { label: `Status: ${statusLabel}`, clear: () => { setStatus(''); setPage(1) } } : null,
    categoryId ? { label: `Category: ${categoryName || '—'}`, clear: () => { setCategoryId(''); setPage(1) } } : null,
    partnerId ? { label: `Partner: ${partnerName || '—'}`, clear: () => { setPartnerId(''); setPage(1) } } : null,
    initiatorId ? { label: `Initiator: ${initiatorName || '—'}`, clear: () => { setInitiatorId(''); setPage(1) } } : null,
    lawyerId ? { label: `Lawyer: ${lawyerName || '—'}`, clear: () => { setLawyerId(''); setPage(1) } } : null,
    routeType ? { label: `Route: ${routeTypeLabel}`, clear: () => { setRouteType(''); setPage(1) } } : null,
    workflowRouteId ? { label: `Flow: ${workflowRouteName || '—'}`, clear: () => { setWorkflowRouteId(''); setPage(1) } } : null,
    fastTracked ? { label: fastTracked === 'yes' ? 'Fast-tracked' : 'Not fast-tracked', clear: () => { setFastTracked(''); setPage(1) } } : null,
    overdue ? { label: 'Overdue only', clear: () => { setOverdue(false); setPage(1) } } : null,
    deadlineFrom ? { label: `Deadline ≥ ${deadlineFrom}`, clear: () => { setDeadlineFrom(''); setPage(1) } } : null,
    deadlineTo ? { label: `Deadline ≤ ${deadlineTo}`, clear: () => { setDeadlineTo(''); setPage(1) } } : null,
    createdFrom ? { label: `Created ≥ ${createdFrom}`, clear: () => { setCreatedFrom(''); setPage(1) } } : null,
    createdTo ? { label: `Created ≤ ${createdTo}`, clear: () => { setCreatedTo(''); setPage(1) } } : null,
    minAmount ? { label: `Amount ≥ ${minAmount}`, clear: () => { setMinAmount(''); setPage(1) } } : null,
    maxAmount ? { label: `Amount ≤ ${maxAmount}`, clear: () => { setMaxAmount(''); setPage(1) } } : null,
  ].filter((c): c is Chip => c !== null)

  const advancedCount = [
    partnerId, initiatorId, lawyerId, routeType, workflowRouteId, fastTracked,
    deadlineFrom, deadlineTo, createdFrom, createdTo, minAmount, maxAmount,
  ].filter(Boolean).length + (overdue ? 1 : 0)

  const clearAll = () => {
    setStatus(''); setCategoryId(''); setSearch(''); setPartnerId(''); setInitiatorId('');
    setLawyerId(''); setRouteType(''); setWorkflowRouteId(''); setFastTracked('');
    setDeadlineFrom(''); setDeadlineTo(''); setCreatedFrom(''); setCreatedTo('');
    setMinAmount(''); setMaxAmount(''); setOverdue(false); setPage(1)
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900 dark:text-white">Tasks</h1>
          {data && <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{data.total} result{data.total !== 1 ? 's' : ''}</p>}
        </div>
        <Link to="/tasks/new">
          <Button>New Task</Button>
        </Link>
      </div>

      {isLoading ? (
        <SkeletonTable rows={6} cols={7} />
      ) : (
        <Card>
          <div className="p-4 border-b border-slate-200 dark:border-slate-700 space-y-3">
            {/* Row 1: search + common filters */}
            <div className="flex flex-wrap items-center gap-2.5">
              <div className="relative flex-1 min-w-[220px] max-w-sm">
                <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input
                  type="search"
                  placeholder="Search by ID, partner, initiator..."
                  value={search}
                  onChange={(e) => { setSearch(e.target.value); setPage(1) }}
                  className="w-full pl-9 pr-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all"
                />
              </div>

              <select
                value={status}
                onChange={(e) => { setStatus(e.target.value); setPage(1) }}
                className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
              >
                {STATUS_OPTIONS.map((o) => (<option key={o.value} value={o.value}>{o.label}</option>))}
              </select>

              <select
                value={categoryId}
                onChange={(e) => { setCategoryId(e.target.value); setPage(1) }}
                className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
              >
                <option value="">All categories</option>
                {categories?.map((c) => (<option key={c.id} value={c.id}>{c.name}</option>))}
              </select>

              <label className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium cursor-pointer transition-all ${
                overdue
                  ? 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300'
                  : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/40'
              }`}>
                <input type="checkbox" className="sr-only" checked={overdue} onChange={(e) => { setOverdue(e.target.checked); setPage(1) }} />
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                Overdue only
              </label>

              <button
                onClick={() => setShowAdvanced(s => !s)}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all ${
                  showAdvanced || advancedCount > 0
                    ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/40'
                }`}
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
                Advanced
                {advancedCount > 0 && (
                  <span className="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-blue-500 text-[9px] font-bold text-white">{advancedCount}</span>
                )}
              </button>

              {/* Sort */}
              <div className="flex items-center gap-1 ml-auto">
                <select
                  value={sortField}
                  onChange={(e) => setSortField(e.target.value)}
                  className="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-600 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                >
                  {SORT_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
                <button
                  onClick={() => setSortDir(d => d === 'asc' ? 'desc' : 'asc')}
                  className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all"
                  title={sortDir === 'asc' ? 'Ascending' : 'Descending'}
                >
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                    {sortDir === 'asc'
                      ? <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                      : <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />}
                  </svg>
                </button>
              </div>

              {chips.length > 0 && (
                <button
                  onClick={clearAll}
                  className="text-xs text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors inline-flex items-center gap-1"
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
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Partner</label>
                  <select
                    value={partnerId}
                    onChange={(e) => { setPartnerId(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  >
                    <option value="">All partners</option>
                    {partners?.data?.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Initiator</label>
                  <select
                    value={initiatorId}
                    onChange={(e) => { setInitiatorId(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  >
                    <option value="">All initiators</option>
                    {initiators?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Assigned lawyer</label>
                  <select
                    value={lawyerId}
                    onChange={(e) => { setLawyerId(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  >
                    <option value="">All lawyers</option>
                    {lawyers?.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Route type</label>
                  <select
                    value={routeType}
                    onChange={(e) => { setRouteType(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  >
                    {ROUTE_TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Workflow route</label>
                  <select
                    value={workflowRouteId}
                    onChange={(e) => { setWorkflowRouteId(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  >
                    <option value="">All workflows</option>
                    {workflowRoutes?.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Fast-tracked</label>
                  <select
                    value={fastTracked}
                    onChange={(e) => { setFastTracked(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  >
                    <option value="">Any</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                  </select>
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Deadline from</label>
                  <input
                    type="date"
                    value={deadlineFrom}
                    onChange={(e) => { setDeadlineFrom(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  />
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Deadline to</label>
                  <input
                    type="date"
                    value={deadlineTo}
                    min={deadlineFrom || undefined}
                    onChange={(e) => { setDeadlineTo(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  />
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Created from</label>
                  <input
                    type="date"
                    value={createdFrom}
                    onChange={(e) => { setCreatedFrom(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  />
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Created to</label>
                  <input
                    type="date"
                    value={createdTo}
                    min={createdFrom || undefined}
                    onChange={(e) => { setCreatedTo(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  />
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Min amount</label>
                  <input
                    type="number"
                    inputMode="decimal"
                    min={0}
                    placeholder="0"
                    value={minAmount}
                    onChange={(e) => { setMinAmount(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  />
                </div>
                <div>
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Max amount</label>
                  <input
                    type="number"
                    inputMode="decimal"
                    min={0}
                    placeholder="∞"
                    value={maxAmount}
                    onChange={(e) => { setMaxAmount(e.target.value); setPage(1) }}
                    className="w-full px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
                  />
                </div>
              </div>
            )}

            {/* Active filter chips */}
            {chips.length > 0 && (
              <div className="flex items-center gap-1.5 flex-wrap">
                <span className="text-[11px] text-slate-400 dark:text-slate-500 mr-1">Active:</span>
                {chips.map((chip, i) => (
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

          <Table>
            <Thead>
              <Th>ID</Th>
              <Th>Category</Th>
              <Th>Partner</Th>
              <Th align="right">Amount</Th>
              <Th>Status</Th>
              <Th>Deadline</Th>
              <Th align="right">Actions</Th>
            </Thead>
            <Tbody>
              {!data?.data.length ? (
                <EmptyRow colSpan={7} message={chips.length > 0 ? 'No tasks match your filters.' : 'No tasks found.'} />
              ) : (
                data.data.map((task) => {
                  const isOverdue = task.deadline && new Date(task.deadline) < new Date() && !['approved', 'archived', 'rejected', 'draft'].includes(task.status)
                  return (
                    <Tr key={task.id}>
                      <Td className="font-mono text-sm text-slate-900 dark:text-white">#{task.id}</Td>
                      <Td className="text-slate-600 dark:text-slate-300">{task.category?.name ?? '---'}</Td>
                      <Td className="text-slate-600 dark:text-slate-300">{task.partner?.name ?? '---'}</Td>
                      <Td align="right" className="text-sm tabular-nums text-slate-700 dark:text-slate-200">
                        {formatTaskAmount(task.amount)}
                      </Td>
                      <Td><StatusBadge status={task.status} /></Td>
                      <Td>
                        {task.deadline ? (
                          <span className={isOverdue ? 'text-red-600 font-medium text-sm' : 'text-slate-500 dark:text-slate-400 text-sm'}>
                            {new Date(task.deadline).toLocaleDateString()}
                            {isOverdue && <Badge color="red" className="ml-1">Overdue</Badge>}
                          </span>
                        ) : (
                          <span className="text-slate-400 text-sm">---</span>
                        )}
                      </Td>
                      <Td align="right">
                        <Link to={`/tasks/${task.id}`} className="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                          View
                        </Link>
                      </Td>
                    </Tr>
                  )
                })
              )}
            </Tbody>
          </Table>

          {data && <Pagination currentPage={data.current_page} lastPage={data.last_page} onPageChange={setPage} />}
        </Card>
      )}
    </div>
  )
}
