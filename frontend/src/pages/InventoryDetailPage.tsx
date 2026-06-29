import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { fetchInventoryItem, deleteInventoryItem } from '../api/inventoryItems'
import { useToast } from '../contexts/ToastContext'
import { useState } from 'react'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'

const STATUS_CONFIG: Record<string, { label: string; bg: string; dot: string }> = {
  available: { label: 'Available', bg: 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400', dot: 'bg-emerald-500' },
  in_use: { label: 'In Use', bg: 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400', dot: 'bg-blue-500' },
  damaged: { label: 'Damaged', bg: 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400', dot: 'bg-amber-500' },
  retired: { label: 'Retired', bg: 'bg-slate-100 dark:bg-slate-600/30 text-slate-600 dark:text-slate-400', dot: 'bg-slate-400' },
}

function getImageUrl(path: string | null) {
  if (!path) return null
  const base = (import.meta.env.VITE_API_URL || '').replace(/\/api\/?$/, '')
  return `${base}/storage/${path}`
}

function formatPrice(price: string | null, currency: string) {
  if (!price) return '—'
  const symbol = currency === 'EUR' ? '€' : currency === 'USD' ? '$' : currency === 'GBP' ? '£' : currency + ' '
  return `${symbol}${Number(price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

export default function InventoryDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { addToast } = useToast()
  const queryClient = useQueryClient()
  const [showDeleteModal, setShowDeleteModal] = useState(false)

  const { data: item, isLoading } = useQuery({
    queryKey: ['inventory-item', id],
    queryFn: () => fetchInventoryItem(Number(id)),
    enabled: Boolean(id),
  })

  const deleteMutation = useMutation({
    mutationFn: () => deleteInventoryItem(Number(id)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-items'] })
      addToast('Item deleted')
      navigate('/inventory', { replace: true })
    },
    onError: () => addToast('Failed to delete item', 'error'),
  })

  if (isLoading || !item) {
    return (
      <div className="space-y-4 animate-pulse">
        <div className="h-6 w-32 bg-slate-200 dark:bg-slate-700 rounded" />
        <div className="h-48 bg-slate-200 dark:bg-slate-700 rounded-xl" />
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="lg:col-span-2 h-48 bg-slate-200 dark:bg-slate-700 rounded-xl" />
          <div className="h-48 bg-slate-200 dark:bg-slate-700 rounded-xl" />
        </div>
      </div>
    )
  }

  const sc = STATUS_CONFIG[item.status] || STATUS_CONFIG.available
  const imgUrl = getImageUrl(item.image_path)

  return (
    <div className="space-y-5">
      <Link to="/inventory" className="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Inventory
      </Link>

      <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 p-5">
        <div className="flex items-start gap-4">
          {imgUrl ? (
            <img src={imgUrl} alt={item.title} className="flex-shrink-0 w-16 h-16 rounded-xl object-cover" />
          ) : (
            <div className="flex-shrink-0 w-16 h-16 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
              <svg className="w-7 h-7 text-slate-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
              </svg>
            </div>
          )}
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2.5 flex-wrap">
              <h1 className="text-lg font-bold text-slate-900 dark:text-white">{item.title}</h1>
              <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ${sc.bg}`}>
                <span className={`w-1.5 h-1.5 rounded-full ${sc.dot}`} />
                {sc.label}
              </span>
            </div>
            <div className="flex items-center gap-4 mt-1 flex-wrap">
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{item.category}</span>
              <span className="text-xs text-slate-400 dark:text-slate-500">
                Added {new Date(item.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
              </span>
            </div>
          </div>
          <div className="flex-shrink-0 flex items-center gap-2">
            <Link
              to={`/inventory/${item.id}/edit`}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
              Edit
            </Link>
            <button
              onClick={() => setShowDeleteModal(true)}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-500/10 text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors"
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
              Delete
            </button>
          </div>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-4">
          <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
              <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Details</h2>
            </div>
            <div className="p-5">
              <dl className="grid gap-4 sm:grid-cols-2">
                <DetailItem label="Category" value={item.category} />
                <DetailItem label="Status" value={sc.label} />
                <DetailItem label="Price" value={formatPrice(item.price, item.currency)} />
                <DetailItem label="Currency" value={item.currency} />
                <DetailItem label="Serial Number" value={item.serial_number || '—'} mono />
                <DetailItem label="Model Number" value={item.model_number || '—'} />
                {item.description && (
                  <div className="sm:col-span-2">
                    <DetailItem label="Description" value={item.description} pre />
                  </div>
                )}
              </dl>
            </div>
          </div>

          {imgUrl && (
            <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
              <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
                <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Image</h2>
              </div>
              <div className="p-5">
                <img src={imgUrl} alt={item.title} className="max-w-full max-h-96 rounded-lg object-contain mx-auto" />
              </div>
            </div>
          )}
        </div>

        <div className="space-y-4">
          <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
            <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
              <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Quick Info</h2>
            </div>
            <div className="p-5 space-y-3">
              <div className="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                <span className="text-xs text-slate-500 dark:text-slate-400">ID</span>
                <span className="text-xs font-mono font-medium text-slate-700 dark:text-slate-300">#{item.id}</span>
              </div>
              <div className="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                <span className="text-xs text-slate-500 dark:text-slate-400">Created</span>
                <span className="text-xs text-slate-700 dark:text-slate-300">{new Date(item.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
              </div>
              <div className="flex items-center justify-between py-1.5">
                <span className="text-xs text-slate-500 dark:text-slate-400">Updated</span>
                <span className="text-xs text-slate-700 dark:text-slate-300">{new Date(item.updated_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Modal
        open={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        title="Delete Item"
        footer={
          <>
            <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>Cancel</Button>
            <Button variant="danger" loading={deleteMutation.isPending} onClick={() => deleteMutation.mutate()}>Delete</Button>
          </>
        }
      >
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Are you sure you want to delete <strong>{item.title}</strong>? This action cannot be undone.
        </p>
      </Modal>
    </div>
  )
}

function DetailItem({ label, value, mono, pre }: {
  label: string; value: string; mono?: boolean; pre?: boolean
}) {
  return (
    <div>
      <dt className="text-[11px] font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-1">{label}</dt>
      <dd className={`text-sm text-slate-800 dark:text-slate-100 ${mono ? 'font-mono' : ''} ${pre ? 'whitespace-pre-wrap' : ''}`}>
        {value}
      </dd>
    </div>
  )
}
