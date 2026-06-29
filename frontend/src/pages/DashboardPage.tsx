import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { fetchDashboard } from '../api/dashboard'
import { StatusBadge } from '../components/ui/Badge'

const STATUS_META: Record<string, { label: string; color: string; bg: string }> = {
  draft:                 { label: 'Draft',           color: '#94a3b8', bg: 'bg-slate-100 dark:bg-slate-700' },
  pending_initiator:     { label: 'Pending Initiator', color: '#f59e0b', bg: 'bg-amber-100 dark:bg-amber-900/30' },
  pending_manager:       { label: 'Pending Manager', color: '#3b82f6', bg: 'bg-blue-100 dark:bg-blue-900/30' },
  pending_lawyer:        { label: 'Pending Lawyer',  color: '#8b5cf6', bg: 'bg-violet-100 dark:bg-violet-900/30' },
  pending_final_manager: { label: 'Final Manager',   color: '#0ea5e9', bg: 'bg-sky-100 dark:bg-sky-900/30' },
  pending_final_lawyer:  { label: 'Final Lawyer',    color: '#6366f1', bg: 'bg-indigo-100 dark:bg-indigo-900/30' },
  pending_partner:       { label: 'Partner Review',  color: '#f97316', bg: 'bg-orange-100 dark:bg-orange-900/30' },
  needs_revision:        { label: 'Needs Revision',  color: '#eab308', bg: 'bg-yellow-100 dark:bg-yellow-900/30' },
  approved:              { label: 'Approved',         color: '#10b981', bg: 'bg-emerald-100 dark:bg-emerald-900/30' },
  rejected:              { label: 'Rejected',         color: '#ef4444', bg: 'bg-red-100 dark:bg-red-900/30' },
  archived:              { label: 'Archived',         color: '#64748b', bg: 'bg-slate-100 dark:bg-slate-700' },
}

function MiniDonut({ segments, size = 120 }: { segments: { value: number; color: string }[]; size?: number }) {
  const total = segments.reduce((s, seg) => s + seg.value, 0)
  if (total === 0) return null
  const r = (size - 12) / 2
  const cx = size / 2
  const cy = size / 2
  const circumference = 2 * Math.PI * r
  let offset = 0
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="transform -rotate-90">
      {segments.filter(s => s.value > 0).map((seg, i) => {
        const pct = seg.value / total
        const dash = pct * circumference
        const gap = circumference - dash
        const el = (
          <circle
            key={i}
            cx={cx}
            cy={cy}
            r={r}
            fill="none"
            stroke={seg.color}
            strokeWidth={10}
            strokeDasharray={`${dash} ${gap}`}
            strokeDashoffset={-offset}
            strokeLinecap="round"
            className="transition-all duration-700"
          />
        )
        offset += dash
        return el
      })}
    </svg>
  )
}

export default function DashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: fetchDashboard,
    refetchInterval: 60_000,
  })

  const statusSegments = useMemo(() => {
    if (!data?.status_breakdown) return []
    return Object.entries(data.status_breakdown)
      .filter(([, count]) => count > 0)
      .map(([status, count]) => ({
        status,
        label: STATUS_META[status]?.label ?? status.replace(/_/g, ' '),
        color: STATUS_META[status]?.color ?? '#94a3b8',
        bg: STATUS_META[status]?.bg ?? 'bg-slate-100 dark:bg-slate-700',
        value: count,
      }))
      .sort((a, b) => b.value - a.value)
  }, [data?.status_breakdown])

  if (isLoading) {
    return (
      <div className="space-y-5 animate-pulse">
        <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map(i => <div key={i} className="h-[88px] bg-slate-200 dark:bg-slate-700 rounded-xl" />)}
        </div>
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="lg:col-span-2 h-80 bg-slate-200 dark:bg-slate-700 rounded-xl" />
          <div className="h-80 bg-slate-200 dark:bg-slate-700 rounded-xl" />
        </div>
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="lg:col-span-2 h-64 bg-slate-200 dark:bg-slate-700 rounded-xl" />
          <div className="h-64 bg-slate-200 dark:bg-slate-700 rounded-xl" />
        </div>
      </div>
    )
  }

  const pendingCount = data?.pending_count ?? 0
  const overdueCount = data?.overdue_count ?? 0
  const totalTasks = data?.total_tasks ?? 0

  return (
    <div className="space-y-5">
      {/* ── KPI Strip ── */}
      <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
        <KpiCard
          label="Your Tasks"
          value={pendingCount}
          sub={pendingCount === 1 ? 'awaiting action' : 'awaiting action'}
          accent="blue"
          icon={<path strokeLinecap="round" strokeLinejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />}
        />
        <KpiCard
          label="Overdue"
          value={overdueCount}
          sub={overdueCount > 0 ? 'need attention' : 'all on track'}
          accent={overdueCount > 0 ? 'red' : 'green'}
          pulse={overdueCount > 0}
          icon={<path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />}
        />
        <KpiCard
          label="Total Tasks"
          value={totalTasks}
          sub="in the system"
          accent="violet"
          icon={<path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />}
        />
        <KpiCard
          label="Today"
          value={data?.today_activity_count ?? 0}
          sub="actions today"
          accent="amber"
          icon={<path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />}
        />
      </div>

      {/* ── Main Row: Tasks + Status Breakdown ── */}
      <div className="grid gap-4 lg:grid-cols-3">
        {/* Tasks Requiring Action */}
        <div className="lg:col-span-2 flex flex-col rounded-xl border border-slate-200/70 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Tasks Requiring Action</h2>
              {pendingCount > 0 && (
                <span className="inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full bg-blue-600 text-[11px] font-bold text-white">{pendingCount}</span>
              )}
            </div>
            <Link to="/tasks" className="text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">
              View all
            </Link>
          </div>

          {!data?.pending_tasks?.length ? (
            <div className="flex-1 flex flex-col items-center justify-center py-14 px-6">
              <div className="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center mb-3">
                <svg className="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              <p className="text-sm font-medium text-slate-500 dark:text-slate-400">All clear</p>
              <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">No pending tasks right now</p>
            </div>
          ) : (
            <div className="flex-1 overflow-y-auto max-h-[400px] divide-y divide-slate-100 dark:divide-slate-700/40">
              {data.pending_tasks.map(task => {
                const isOverdue = task.deadline && new Date(task.deadline) < new Date()
                return (
                  <Link key={task.id} to={`/tasks/${task.id}`} className="flex items-center gap-3.5 px-5 py-3 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors group">
                    <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center">
                      <span className="text-[11px] font-bold text-slate-500 dark:text-slate-400">#{task.id}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="text-[13px] font-medium text-slate-800 dark:text-slate-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                        {task.category?.name}{task.partner?.name ? ` — ${task.partner.name}` : ''}
                      </p>
                      <div className="flex items-center gap-2.5 mt-0.5">
                        {task.deadline && (
                          <span className={`text-[11px] flex items-center gap-1 ${isOverdue ? 'text-red-500 font-semibold' : 'text-slate-400 dark:text-slate-500'}`}>
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            {new Date(task.deadline).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                          </span>
                        )}
                        <span className="text-[11px] text-slate-400 dark:text-slate-500">
                          updated {new Date(task.updated_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                        </span>
                      </div>
                    </div>
                    <StatusBadge status={task.status} />
                    <svg className="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-slate-400 dark:group-hover:text-slate-500 transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                  </Link>
                )
              })}
            </div>
          )}
        </div>

        {/* Status Breakdown */}
        <div className="flex flex-col rounded-xl border border-slate-200/70 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
            <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Status Overview</h2>
          </div>
          <div className="flex-1 flex flex-col items-center justify-center p-5 gap-4">
            {statusSegments.length === 0 ? (
              <p className="text-sm text-slate-400">No data yet</p>
            ) : (
              <>
                <div className="relative">
                  <MiniDonut segments={statusSegments} size={140} />
                  <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-2xl font-bold text-slate-800 dark:text-white">{totalTasks}</span>
                    <span className="text-[11px] text-slate-400">total</span>
                  </div>
                </div>
                <div className="w-full grid grid-cols-2 gap-x-4 gap-y-1.5">
                  {statusSegments.map(seg => (
                    <div key={seg.status} className="flex items-center gap-2 min-w-0">
                      <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: seg.color }} />
                      <span className="text-[11px] text-slate-500 dark:text-slate-400 truncate">{seg.label}</span>
                      <span className="ml-auto text-[11px] font-semibold text-slate-700 dark:text-slate-300">{seg.value}</span>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      </div>

      {/* ── Second Row: Activity + Quick Actions ── */}
      <div className="grid gap-4 lg:grid-cols-3">
        {/* Recent Activity */}
        <div className="lg:col-span-2 flex flex-col rounded-xl border border-slate-200/70 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
            <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Recent Activity</h2>
          </div>
          {!data?.recent_activities?.length ? (
            <div className="flex-1 flex flex-col items-center justify-center py-14 px-6">
              <div className="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center mb-3">
                <svg className="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No recent activity</p>
            </div>
          ) : (
            <div className="flex-1 overflow-y-auto max-h-[320px]">
              <div className="p-4 space-y-0">
                {data.recent_activities.map((activity, idx) => {
                  const isLast = idx === (data.recent_activities?.length ?? 0) - 1
                  const colorClass =
                    activity.action.includes('reject') || activity.action.includes('returned') ? 'bg-red-500' :
                    activity.action.includes('approved') || activity.action.includes('signed') ? 'bg-emerald-500' :
                    activity.action.includes('submitted') || activity.action.includes('created') ? 'bg-blue-500' :
                    activity.action.includes('comment') ? 'bg-purple-500' :
                    'bg-slate-300 dark:bg-slate-600'
                  return (
                    <div key={activity.id} className="flex gap-3 pb-3.5 last:pb-0">
                      <div className="flex flex-col items-center">
                        <div className={`w-2 h-2 rounded-full ${colorClass} ring-[3px] ring-white dark:ring-slate-800 flex-shrink-0 mt-1.5`} />
                        {!isLast && <div className="w-px flex-1 bg-slate-200 dark:bg-slate-700 mt-1" />}
                      </div>
                      <div className="flex-1 min-w-0 -mt-0.5">
                        <p className="text-[13px] text-slate-700 dark:text-slate-200 leading-snug">
                          <span className="font-semibold text-slate-800 dark:text-white">{activity.user?.name ?? 'System'}</span>
                          {' '}<span className="text-slate-500 dark:text-slate-400">{activity.action.replace(/_/g, ' ')}</span>
                          {activity.task_id && (
                            <Link to={`/tasks/${activity.task_id}`} className="text-blue-600 dark:text-blue-400 hover:underline ml-1 font-medium">#{activity.task_id}</Link>
                          )}
                        </p>
                        {activity.comment && (
                          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 bg-slate-50 dark:bg-slate-700/40 rounded-lg px-2.5 py-1.5 break-words line-clamp-2 italic">"{activity.comment}"</p>
                        )}
                        <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                          {new Date(activity.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                        </p>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          )}
        </div>

        {/* Quick Actions */}
        <div className="flex flex-col rounded-xl border border-slate-200/70 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
            <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Quick Actions</h2>
          </div>
          <div className="flex-1 p-3 flex flex-col gap-1.5">
            <QuickAction to="/tasks/new" label="New Task" desc="Start document workflow" accent="blue"
              icon={<path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />} />
            <QuickAction to="/partners/new" label="Add Partner" desc="Register a counterparty" accent="emerald"
              icon={<path strokeLinecap="round" strokeLinejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />} />
            <QuickAction to="/archive" label="Archive" desc="Browse completed docs" accent="violet"
              icon={<path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0V4.875c0-.621-.504-1.125-1.125-1.125H4.875c-.621 0-1.125.504-1.125 1.125V7.5m16.5 0H3.75" />} />
            <QuickAction to="/substitutions" label="Substitutions" desc="Manage acting roles" accent="amber"
              icon={<path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />} />
            <QuickAction to="/partners" label="Partners" desc="All counterparties" accent="cyan"
              icon={<path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />} />
            <QuickAction to="/tasks" label="All Tasks" desc="Full task list" accent="slate"
              icon={<path strokeLinecap="round" strokeLinejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />} />
          </div>
        </div>
      </div>
    </div>
  )
}

/* ── KPI Card ── */
const ACCENT_MAP: Record<string, { icon: string; bg: string; text: string; ring: string }> = {
  blue:   { icon: 'text-blue-600 dark:text-blue-400',   bg: 'bg-blue-50 dark:bg-blue-500/10',    text: 'text-blue-600 dark:text-blue-400',   ring: 'ring-blue-200/60 dark:ring-blue-800/30' },
  red:    { icon: 'text-red-600 dark:text-red-400',     bg: 'bg-red-50 dark:bg-red-500/10',      text: 'text-red-600 dark:text-red-400',     ring: 'ring-red-200/60 dark:ring-red-800/30' },
  green:  { icon: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-400', ring: 'ring-emerald-200/60 dark:ring-emerald-800/30' },
  violet: { icon: 'text-violet-600 dark:text-violet-400', bg: 'bg-violet-50 dark:bg-violet-500/10', text: 'text-violet-600 dark:text-violet-400', ring: 'ring-violet-200/60 dark:ring-violet-800/30' },
  amber:  { icon: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-500/10',  text: 'text-amber-600 dark:text-amber-400', ring: 'ring-amber-200/60 dark:ring-amber-800/30' },
  cyan:   { icon: 'text-cyan-600 dark:text-cyan-400',   bg: 'bg-cyan-50 dark:bg-cyan-500/10',    text: 'text-cyan-600 dark:text-cyan-400',   ring: 'ring-cyan-200/60 dark:ring-cyan-800/30' },
  slate:  { icon: 'text-slate-600 dark:text-slate-400', bg: 'bg-slate-100 dark:bg-slate-500/10',  text: 'text-slate-600 dark:text-slate-400', ring: '' },
}

function KpiCard({ label, value, sub, accent, pulse, icon }: {
  label: string; value: number; sub: string; accent: string; pulse?: boolean; icon: React.ReactNode
}) {
  const a = ACCENT_MAP[accent] ?? ACCENT_MAP.slate
  return (
    <div className={`relative rounded-xl border border-slate-200/70 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 p-4 ${value > 0 && accent !== 'slate' ? `ring-1 ${a.ring}` : ''} transition-all`}>
      <div className="flex items-start justify-between">
        <div className={`w-9 h-9 rounded-lg ${a.bg} flex items-center justify-center ${a.icon}`}>
          <svg className="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">{icon}</svg>
        </div>
        {pulse && (
          <span className="flex h-2 w-2">
            <span className="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-red-400 opacity-75" />
            <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
          </span>
        )}
      </div>
      <p className={`text-2xl font-bold tracking-tight mt-2.5 ${value > 0 && accent === 'red' ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'}`}>{value}</p>
      <div className="flex items-baseline gap-1.5">
        <p className="text-[13px] font-medium text-slate-600 dark:text-slate-300">{label}</p>
        <p className="text-[11px] text-slate-400 dark:text-slate-500">{sub}</p>
      </div>
    </div>
  )
}

/* ── Quick Action Row ── */
function QuickAction({ to, label, desc, accent, icon }: {
  to: string; label: string; desc: string; accent: string; icon: React.ReactNode
}) {
  const a = ACCENT_MAP[accent] ?? ACCENT_MAP.slate
  return (
    <Link to={to} className="group flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
      <div className={`flex-shrink-0 w-9 h-9 rounded-lg ${a.bg} flex items-center justify-center ${a.icon} group-hover:scale-105 transition-transform`}>
        <svg className="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">{icon}</svg>
      </div>
      <div className="min-w-0">
        <p className="text-[13px] font-medium text-slate-700 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{label}</p>
        <p className="text-[11px] text-slate-400 dark:text-slate-500">{desc}</p>
      </div>
      <svg className="w-3.5 h-3.5 ml-auto text-slate-300 dark:text-slate-600 group-hover:text-slate-400 dark:group-hover:text-slate-500 transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
    </Link>
  )
}
