import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchInventoryItems } from '../api/inventoryItems'
import type { InventoryItem } from '../types/inventoryItem'

type StatusFilter = 'all' | 'available' | 'in_use' | 'damaged' | 'retired'

const STATUS_CONFIG: Record<string, { label: string; bg: string }> = {
  available: { label: 'Available', bg: 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' },
  in_use: { label: 'In Use', bg: 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400' },
  damaged: { label: 'Damaged', bg: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400' },
  retired: { label: 'Retired', bg: 'bg-slate-100 dark:bg-slate-600/30 text-slate-600 dark:text-slate-400' },
}

const CATEGORY_COLORS: Record<string, string> = {
  Laptop: 'bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-400',
  Desktop: 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400',
  Monitor: 'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-700 dark:text-cyan-400',
  Phone: 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-400',
  Tablet: 'bg-pink-100 dark:bg-pink-500/15 text-pink-700 dark:text-pink-400',
  Printer: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400',
  Server: 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-400',
}

function getCategoryColor(cat: string) {
  return CATEGORY_COLORS[cat] || 'bg-slate-100 dark:bg-slate-600/30 text-slate-600 dark:text-slate-400'
}

function getImageUrl(path: string | null) {
  if (!path) return null
  const base = (import.meta.env.VITE_API_URL || '').replace(/\/api\/?$/, '')
  return `${base}/storage/${path}`
}

export default function InventoryPage() {
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all')

  const { data, isLoading } = useQuery({
    queryKey: ['inventory-items', search, page, statusFilter],
    queryFn: () => fetchInventoryItems({
      search: search || undefined,
      page,
      status: statusFilter !== 'all' ? statusFilter : undefined,
    }),
  })

  const items = data?.data ?? []
  const total = data?.total ?? 0

  return (
    <div>
      <div className="flex items-center justify-between mb-5">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white">Inventory</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {total} item{total !== 1 ? 's' : ''} registered
          </p>
        </div>
        <Link
          to="/inventory/new"
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors shadow-sm"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Item
        </Link>
      </div>

      <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
        <div className="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 flex flex-wrap items-center gap-3">
          <div className="relative flex-1 min-w-[200px] max-w-sm">
            <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
              type="search"
              placeholder="Search title, serial, model..."
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              className="w-full pl-9 pr-4 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all"
            />
          </div>

          <div className="flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            {(['all', 'available', 'in_use', 'damaged', 'retired'] as StatusFilter[]).map(f => (
              <button
                key={f}
                onClick={() => { setStatusFilter(f); setPage(1) }}
                className={`px-3 py-1.5 text-xs font-medium border-r last:border-r-0 border-slate-200 dark:border-slate-700 transition-colors ${
                  statusFilter === f
                    ? 'bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white'
                    : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                }`}
              >
                {f === 'all' ? 'All' : f === 'in_use' ? 'In Use' : f.charAt(0).toUpperCase() + f.slice(1)}
              </button>
            ))}
          </div>
        </div>

        {isLoading ? (
          <div className="divide-y divide-slate-100 dark:divide-slate-700/40">
            {[1, 2, 3, 4, 5, 6].map(i => (
              <div key={i} className="px-4 py-3.5 animate-pulse">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 rounded-lg bg-slate-200 dark:bg-slate-700" />
                  <div className="flex-1 space-y-1.5">
                    <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/3" />
                    <div className="h-2.5 bg-slate-200 dark:bg-slate-700 rounded w-1/5" />
                  </div>
                  <div className="h-5 w-14 bg-slate-200 dark:bg-slate-700 rounded" />
                </div>
              </div>
            ))}
          </div>
        ) : !items.length ? (
          <div className="flex flex-col items-center justify-center py-16 px-6">
            <div className="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-3">
              <svg className="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
              </svg>
            </div>
            <p className="text-sm font-medium text-slate-600 dark:text-slate-300">No items found</p>
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
              {search ? 'Try a different search term' : 'Add your first inventory item to get started'}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-slate-100 dark:border-slate-700/50">
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 w-12"></th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Title</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Category</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 hidden md:table-cell">Price</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 hidden lg:table-cell">Serial #</th>
                  <th className="text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5">Status</th>
                  <th className="text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-4 py-2.5 w-24">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/40">
                {items.map((item: InventoryItem) => {
                  const sc = STATUS_CONFIG[item.status] || STATUS_CONFIG.available
                  const imgUrl = getImageUrl(item.image_path)
                  return (
                    <tr key={item.id} className="group hover:bg-slate-50/70 dark:hover:bg-slate-700/20 transition-colors">
                      <td className="px-4 py-3">
                        {imgUrl ? (
                          <img src={imgUrl} alt="" className="w-10 h-10 rounded-lg object-cover" />
                        ) : (
                          <div className="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <Link to={`/inventory/${item.id}`} className="text-sm font-medium text-slate-800 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                          {item.title}
                        </Link>
                        {item.model_number && (
                          <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{item.model_number}</p>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ${getCategoryColor(item.category)}`}>
                          {item.category}
                        </span>
                      </td>
                      <td className="px-4 py-3 hidden md:table-cell">
                        {item.price ? (
                          <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {item.currency === 'EUR' ? '€' : item.currency === 'USD' ? '$' : item.currency === 'GBP' ? '£' : ''}{Number(item.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                          </span>
                        ) : (
                          <span className="text-sm text-slate-400">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 hidden lg:table-cell">
                        <span className="text-sm font-mono text-slate-500 dark:text-slate-400">{item.serial_number || '—'}</span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ${sc.bg}`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${
                            item.status === 'available' ? 'bg-emerald-500' :
                            item.status === 'in_use' ? 'bg-blue-500' :
                            item.status === 'damaged' ? 'bg-amber-500' : 'bg-slate-400'
                          }`} />
                          {sc.label}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <Link
                            to={`/inventory/${item.id}`}
                            className="p-1.5 rounded-md text-slate-400 dark:text-slate-500 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors"
                            title="View"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                          </Link>
                          <Link
                            to={`/inventory/${item.id}/edit`}
                            className="p-1.5 rounded-md text-slate-400 dark:text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors"
                            title="Edit"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                          </Link>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {data && data.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-700/50">
            <p className="text-xs text-slate-400 dark:text-slate-500">
              Page {data.current_page} of {data.last_page} · {data.total} items
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
                onClick={() => setPage(p => Math.min(data.last_page, p + 1))}
                disabled={page >= data.last_page}
                className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-40 disabled:pointer-events-none transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
