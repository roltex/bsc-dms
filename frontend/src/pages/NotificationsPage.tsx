import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  fetchNotifications,
  markNotificationRead,
  markAllNotificationsRead,
  deleteNotification,
  deleteAllNotifications,
  type AppNotification,
} from '../api/notifications'
import { useToast } from '../contexts/ToastContext'

const EVENT_META: Record<string, { label: string; color: string; bg: string; icon: React.ReactNode }> = {
  approved:              { label: 'Approved',           color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-100 dark:bg-emerald-500/20', icon: <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /> },
  rejected:              { label: 'Rejected',           color: 'text-red-600 dark:text-red-400',         bg: 'bg-red-100 dark:bg-red-500/20',         icon: <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /> },
  needs_revision:        { label: 'Revision',           color: 'text-amber-600 dark:text-amber-400',     bg: 'bg-amber-100 dark:bg-amber-500/20',     icon: <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /> },
  overdue:               { label: 'Overdue',            color: 'text-red-600 dark:text-red-400',         bg: 'bg-red-100 dark:bg-red-500/20',         icon: <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /> },
  deadline_approaching:  { label: 'Deadline',           color: 'text-orange-600 dark:text-orange-400',   bg: 'bg-orange-100 dark:bg-orange-500/20',   icon: <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /> },
  delegated:             { label: 'Delegated',          color: 'text-violet-600 dark:text-violet-400',   bg: 'bg-violet-100 dark:bg-violet-500/20',   icon: <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /> },
  reviewer_added:        { label: 'Review',             color: 'text-indigo-600 dark:text-indigo-400',   bg: 'bg-indigo-100 dark:bg-indigo-500/20',   icon: <path strokeLinecap="round" strokeLinejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /> },
  pending:               { label: 'Pending',            color: 'text-blue-600 dark:text-blue-400',       bg: 'bg-blue-100 dark:bg-blue-500/20',       icon: <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /> },
  pending_initiator:     { label: 'Action Needed',      color: 'text-blue-600 dark:text-blue-400',       bg: 'bg-blue-100 dark:bg-blue-500/20',       icon: <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /> },
  status_change:         { label: 'Update',             color: 'text-slate-600 dark:text-slate-400',     bg: 'bg-slate-100 dark:bg-slate-500/20',     icon: <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /> },
}

const DEFAULT_META = {
  label: 'Update',
  color: 'text-slate-600 dark:text-slate-400',
  bg: 'bg-slate-100 dark:bg-slate-500/20',
  icon: <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />,
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d ago`
  if (days < 30) return `${Math.floor(days / 7)}w ago`
  return new Date(dateStr).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

export default function NotificationsPage() {
  const { addToast } = useToast()
  const queryClient = useQueryClient()
  const [filter, setFilter] = useState<'all' | 'unread'>('all')
  const [page, setPage] = useState(1)
  const [confirmClearAll, setConfirmClearAll] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['notifications', page],
    queryFn: () => fetchNotifications(page, 30),
  })

  const notifications = data?.data ?? []
  const unreadCount = data?.unread_count ?? 0
  const meta = data?.meta

  const markReadMut = useMutation({
    mutationFn: markNotificationRead,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  })

  const markAllMut = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      addToast('All notifications marked as read')
    },
  })

  const deleteMut = useMutation({
    mutationFn: deleteNotification,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      addToast('Notification deleted')
    },
  })

  const deleteAllMut = useMutation({
    mutationFn: deleteAllNotifications,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      setConfirmClearAll(false)
      addToast('All notifications cleared')
    },
  })

  const filtered = notifications.filter(n => {
    if (filter === 'unread') return !n.read_at
    return true
  })

  function getEventMeta(n: AppNotification) {
    return EVENT_META[n.data.event_type ?? ''] ?? DEFAULT_META
  }

  return (
    <div className="max-w-3xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white">Notifications</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {unreadCount > 0 ? `${unreadCount} unread` : 'All caught up'}
            {meta?.total ? ` · ${meta.total} total` : ''}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {/* Filter */}
          <div className="flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <button
              onClick={() => setFilter('all')}
              className={`px-3 py-1.5 text-xs font-medium transition-colors ${filter === 'all' ? 'bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'}`}
            >
              All
            </button>
            <button
              onClick={() => setFilter('unread')}
              className={`px-3 py-1.5 text-xs font-medium border-l border-slate-200 dark:border-slate-700 transition-colors ${filter === 'unread' ? 'bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'}`}
            >
              Unread{unreadCount > 0 ? ` (${unreadCount})` : ''}
            </button>
          </div>

          {unreadCount > 0 && (
            <button
              onClick={() => markAllMut.mutate()}
              disabled={markAllMut.isPending}
              className="px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition-colors disabled:opacity-50"
            >
              Mark all read
            </button>
          )}

          {notifications.length > 0 && (
            confirmClearAll ? (
              <div className="flex items-center gap-1">
                <button
                  onClick={() => deleteAllMut.mutate()}
                  disabled={deleteAllMut.isPending}
                  className="px-2.5 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 rounded-lg transition-colors disabled:opacity-50"
                >
                  Confirm
                </button>
                <button
                  onClick={() => setConfirmClearAll(false)}
                  className="px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg transition-colors"
                >
                  Cancel
                </button>
              </div>
            ) : (
              <button
                onClick={() => setConfirmClearAll(true)}
                className="px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors"
              >
                Clear all
              </button>
            )
          )}
        </div>
      </div>

      {/* Loading */}
      {isLoading ? (
        <div className="space-y-2">
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="animate-pulse rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 p-4">
              <div className="flex gap-3">
                <div className="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700" />
                <div className="flex-1 space-y-2">
                  <div className="h-3.5 bg-slate-200 dark:bg-slate-700 rounded w-3/4" />
                  <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : !filtered.length ? (
        /* Empty state */
        <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 p-12 text-center">
          <div className="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
            <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
          </div>
          <p className="text-sm font-medium text-slate-600 dark:text-slate-300">
            {filter === 'unread' ? 'No unread notifications' : 'No notifications yet'}
          </p>
          <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
            {filter === 'unread' ? 'Switch to "All" to see read notifications' : "You'll see task updates and alerts here"}
          </p>
        </div>
      ) : (
        /* Notification list */
        <div className="space-y-1.5">
          {filtered.map(n => {
            const em = getEventMeta(n)
            return (
              <div
                key={n.id}
                className={`group rounded-xl border bg-white dark:bg-slate-800/80 transition-all ${
                  !n.read_at
                    ? 'border-blue-200/80 dark:border-blue-800/40 shadow-sm shadow-blue-100/50 dark:shadow-none'
                    : 'border-slate-200/60 dark:border-slate-700/60'
                }`}
              >
                <div className="flex items-start gap-3 p-4">
                  {/* Icon */}
                  <div className={`flex-shrink-0 w-9 h-9 rounded-full ${em.bg} flex items-center justify-center mt-0.5`}>
                    <svg className={`w-[18px] h-[18px] ${em.color}`} fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                      {em.icon}
                    </svg>
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2 mb-0.5">
                          <span className={`text-[10px] font-semibold uppercase tracking-wide ${em.color}`}>{em.label}</span>
                          {!n.read_at && <span className="w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0" />}
                        </div>
                        <p className={`text-[13px] leading-relaxed ${!n.read_at ? 'text-slate-900 dark:text-white font-medium' : 'text-slate-600 dark:text-slate-300'}`}>
                          {n.data.message ?? 'Notification'}
                        </p>
                      </div>
                      <span className="text-[11px] text-slate-400 dark:text-slate-500 whitespace-nowrap flex-shrink-0 mt-0.5">
                        {timeAgo(n.created_at)}
                      </span>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-3 mt-2">
                      {n.data.task_id && (
                        <Link
                          to={`/tasks/${n.data.task_id}`}
                          className="text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
                        >
                          View task #{n.data.task_id}
                        </Link>
                      )}
                      {!n.read_at && (
                        <button
                          onClick={() => markReadMut.mutate(n.id)}
                          disabled={markReadMut.isPending}
                          className="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                        >
                          Mark read
                        </button>
                      )}
                      <button
                        onClick={() => deleteMut.mutate(n.id)}
                        disabled={deleteMut.isPending}
                        className="text-xs text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100"
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-5 px-1">
          <p className="text-xs text-slate-400 dark:text-slate-500">
            Page {meta.current_page} of {meta.last_page}
          </p>
          <div className="flex gap-1.5">
            <button
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-40 disabled:pointer-events-none transition-colors"
            >
              Previous
            </button>
            <button
              onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
              disabled={page >= meta.last_page}
              className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-40 disabled:pointer-events-none transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
