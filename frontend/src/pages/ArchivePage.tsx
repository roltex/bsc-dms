import { useQuery } from '@tanstack/react-query'
import { useState, useMemo } from 'react'
import { Link } from 'react-router-dom'
import { fetchArchive, getArchiveExportUrl } from '../api/archive'
import { fetchDocumentCategories } from '../api/documentCategories'

type SortField = 'updated_at' | 'id' | 'created_at'
type SortDir = 'asc' | 'desc'

export default function ArchivePage() {
  const [search, setSearch] = useState('')
  const [year, setYear] = useState<number | ''>('')
  const [categoryId, setCategoryId] = useState('')
  const [statusFilter, setStatusFilter] = useState<'all' | 'approved' | 'archived'>('all')
  const [sortField, setSortField] = useState<SortField>('updated_at')
  const [sortDir, setSortDir] = useState<SortDir>('desc')
  const [page, setPage] = useState(1)

  const { data: categories } = useQuery({
    queryKey: ['document-categories'],
    queryFn: fetchDocumentCategories,
  })

  const { data, isLoading } = useQuery({
    queryKey: ['archive', search, year, categoryId, statusFilter, sortField, sortDir, page],
    queryFn: () => fetchArchive({
      search: search || undefined,
      year: year || undefined,
      document_category_id: categoryId ? Number(categoryId) : undefined,
      status: statusFilter !== 'all' ? statusFilter : undefined,
      sort: sortField,
      dir: sortDir,
      page,
      per_page: 20,
    }),
  })

  const tasks = data?.data ?? []
  const total = data?.total ?? 0

  const yearOptions = useMemo(() => {
    const currentYear = new Date().getFullYear()
    return Array.from({ length: 5 }, (_, i) => currentYear - i)
  }, [])

  const exportUrl = getArchiveExportUrl({
    year: year || undefined,
    document_category_id: categoryId ? Number(categoryId) : undefined,
  })

  const activeFilters = [
    year ? `Year: ${year}` : null,
    categoryId ? `Category: ${categories?.find(c => String(c.id) === categoryId)?.name}` : null,
    statusFilter !== 'all' ? `Status: ${statusFilter}` : null,
    search ? `Search: "${search}"` : null,
  ].filter(Boolean)

  function toggleSort(field: SortField) {
    if (sortField === field) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc')
    } else {
      setSortField(field)
      setSortDir('desc')
    }
    setPage(1)
  }

  function clearFilters() {
    setSearch('')
    setYear('')
    setCategoryId('')
    setStatusFilter('all')
    setPage(1)
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white">Archive</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {total} approved document{total !== 1 ? 's' : ''}
          </p>
        </div>
        <a
          href={exportUrl}
          download
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition-colors shadow-sm"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Export Excel
        </a>
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
                placeholder="Search ID, reg no, partner, category..."
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1) }}
                className="w-full pl-9 pr-4 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all"
              />
            </div>

            {/* Year */}
            <select
              value={year}
              onChange={(e) => { setYear(e.target.value === '' ? '' : parseInt(e.target.value, 10)); setPage(1) }}
              className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all"
            >
              <option value="">All years</option>
              {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
            </select>

            {/* Category */}
            <select
              value={categoryId}
              onChange={(e) => { setCategoryId(e.target.value); setPage(1) }}
              className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all max-w-[200px]"
            >
              <option value="">All categories</option>
              {categories?.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>

            {/* Status */}
            <div className="flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
              {(['all', 'approved', 'archived'] as const).map(s => (
                <button
                  key={s}
                  onClick={() => { setStatusFilter(s); setPage(1) }}
                  className={`px-2.5 py-1.5 text-xs font-medium border-r last:border-r-0 border-slate-200 dark:border-slate-700 transition-colors ${
                    statusFilter === s
                      ? s === 'approved'
                        ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                        : s === 'archived'
                          ? 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400'
                          : 'bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white'
                      : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                  }`}
                >
                  {s === 'all' ? 'All' : s.charAt(0).toUpperCase() + s.slice(1)}
                </button>
              ))}
            </div>
          </div>

          {/* Active filters */}
          {activeFilters.length > 0 && (
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-[11px] text-slate-400 dark:text-slate-500">Filters:</span>
              {activeFilters.map((f, i) => (
                <span key={i} className="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-500/10 text-[11px] font-medium text-blue-700 dark:text-blue-300">
                  {f}
                </span>
              ))}
              <button onClick={clearFilters} className="text-[11px] text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors ml-1">
                Clear all
              </button>
            </div>
          )}
        </div>

        {/* Table */}
        {isLoading ? (
          <div className="divide-y divide-slate-100 dark:divide-slate-700/40">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="px-4 py-3.5 animate-pulse">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-5 bg-slate-200 dark:bg-slate-700 rounded" />
                  <div className="flex-1 space-y-1.5">
                    <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-2/5" />
                    <div className="h-2.5 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
                  </div>
                  <div className="h-5 w-16 bg-slate-200 dark:bg-slate-700 rounded" />
                </div>
              </div>
            ))}
          </div>
        ) : !tasks.length ? (
          <div className="flex flex-col items-center justify-center py-16 px-6">
            <div className="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-3">
              <svg className="w-7 h-7 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
              </svg>
            </div>
            <p className="text-sm font-medium text-slate-600 dark:text-slate-300">No archived documents found</p>
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
              {activeFilters.length > 0 ? 'Try adjusting your filters' : 'Approved tasks will appear here'}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-slate-100 dark:border-slate-700/50">
                  <SortTh active={sortField === 'id'} dir={sortDir} onClick={() => toggleSort('id')}>ID</SortTh>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Reg. No</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Category</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 hidden md:table-cell">Partner</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 hidden lg:table-cell">Initiator</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Status</th>
                  <SortTh active={sortField === 'updated_at'} dir={sortDir} onClick={() => toggleSort('updated_at')}>Date</SortTh>
                  <th className="text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 w-20">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/40">
                {tasks.map(task => (
                  <tr key={task.id} className="group hover:bg-slate-50/70 dark:hover:bg-slate-700/20 transition-colors">
                    <td className="px-4 py-3">
                      <span className="text-sm font-mono font-medium text-slate-600 dark:text-slate-300">#{task.id}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className="text-sm text-slate-700 dark:text-slate-200 font-mono">
                        {task.registration_number || <span className="text-slate-400 dark:text-slate-500">—</span>}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      {task.category ? (
                        <span className="inline-flex px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                          {task.category.name}
                        </span>
                      ) : (
                        <span className="text-sm text-slate-400 dark:text-slate-500">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3 hidden md:table-cell">
                      {task.partner ? (
                        <Link to={`/partners/${task.partner.id}`} className="text-sm text-slate-700 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                          {task.partner.name}
                        </Link>
                      ) : (
                        <span className="text-sm text-slate-400 dark:text-slate-500">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3 hidden lg:table-cell">
                      <span className="text-sm text-slate-500 dark:text-slate-400">{task.initiator?.name ?? '—'}</span>
                    </td>
                    <td className="px-4 py-3">
                      {task.status === 'approved' ? (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                          Approved
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400">
                          <span className="w-1.5 h-1.5 rounded-full bg-slate-400" />
                          Archived
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <span className="text-xs text-slate-400 dark:text-slate-500">
                        {new Date(task.updated_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Link
                        to={`/tasks/${task.id}`}
                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Footer: Stats + Pagination */}
        {data && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-700/50">
            <p className="text-xs text-slate-400 dark:text-slate-500">
              Showing {tasks.length} of {data.total} · Page {data.current_page} of {data.last_page}
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
