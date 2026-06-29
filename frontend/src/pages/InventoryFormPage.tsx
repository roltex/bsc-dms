import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { createInventoryItem, fetchInventoryItem, updateInventoryItem } from '../api/inventoryItems'
import { useToast } from '../contexts/ToastContext'
import { INVENTORY_CATEGORIES, INVENTORY_STATUSES } from '../types/inventoryItem'

function getImageUrl(path: string | null) {
  if (!path) return null
  const base = (import.meta.env.VITE_API_URL || '').replace(/\/api\/?$/, '')
  return `${base}/storage/${path}`
}

export default function InventoryFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { addToast } = useToast()
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [form, setForm] = useState({
    title: '',
    description: '',
    category: '',
    price: '',
    currency: 'EUR',
    serial_number: '',
    model_number: '',
    status: 'available',
  })
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const { data: item, isLoading } = useQuery({
    queryKey: ['inventory-item', id],
    queryFn: () => fetchInventoryItem(Number(id)),
    enabled: isEdit,
  })

  useEffect(() => {
    if (item) {
      setForm({
        title: item.title || '',
        description: item.description || '',
        category: item.category || '',
        price: item.price || '',
        currency: item.currency || 'EUR',
        serial_number: item.serial_number || '',
        model_number: item.model_number || '',
        status: item.status || 'available',
      })
      if (item.image_path) {
        setImagePreview(getImageUrl(item.image_path))
      }
    }
  }, [item])

  const saveMutation = useMutation({
    mutationFn: () => {
      const fd = new FormData()
      fd.append('title', form.title)
      if (form.description) fd.append('description', form.description)
      fd.append('category', form.category)
      if (form.price) fd.append('price', form.price)
      fd.append('currency', form.currency)
      if (form.serial_number) fd.append('serial_number', form.serial_number)
      if (form.model_number) fd.append('model_number', form.model_number)
      fd.append('status', form.status)
      if (imageFile) fd.append('image', imageFile)

      return isEdit ? updateInventoryItem(Number(id), fd) : createInventoryItem(fd)
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['inventory-items'] })
      if (isEdit) queryClient.invalidateQueries({ queryKey: ['inventory-item', id] })
      addToast(isEdit ? 'Item updated' : 'Item created')
      navigate(`/inventory/${result.id}`, { replace: true })
    },
    onError: (err: { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }) => {
      if (err.response?.data?.errors) {
        const flat: Record<string, string> = {}
        for (const [key, msgs] of Object.entries(err.response.data.errors)) {
          flat[key] = msgs[0]
        }
        setFieldErrors(flat)
      }
      addToast(err.response?.data?.message || 'Failed to save', 'error')
    },
  })

  function handleImageChange(e: React.ChangeEvent<HTMLInputElement>) {
    const f = e.target.files?.[0]
    if (f) {
      setImageFile(f)
      const reader = new FileReader()
      reader.onload = () => setImagePreview(reader.result as string)
      reader.readAsDataURL(f)
    }
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    saveMutation.mutate()
  }

  if (isEdit && isLoading) {
    return (
      <div className="max-w-2xl mx-auto space-y-4 animate-pulse">
        <div className="h-6 w-32 bg-slate-200 dark:bg-slate-700 rounded" />
        <div className="h-80 bg-slate-200 dark:bg-slate-700 rounded-xl" />
      </div>
    )
  }

  return (
    <div className="max-w-2xl mx-auto">
      <Link to="/inventory" className="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors mb-4">
        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Inventory
      </Link>

      <h1 className="text-xl font-bold text-slate-900 dark:text-white mb-5">
        {isEdit ? 'Edit Item' : 'New Item'}
      </h1>

      <form onSubmit={handleSubmit}>
        <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
            <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Item Information</h2>
          </div>
          <div className="p-5 space-y-5">
            <FormField label="Title" required error={fieldErrors.title}>
              <input
                type="text"
                required
                value={form.title}
                onChange={(e) => setForm(f => ({ ...f, title: e.target.value }))}
                placeholder="e.g. MacBook Pro 16"
                className="form-input"
              />
            </FormField>

            <div className="grid gap-5 sm:grid-cols-2">
              <FormField label="Category" required error={fieldErrors.category}>
                <select
                  required
                  value={form.category}
                  onChange={(e) => setForm(f => ({ ...f, category: e.target.value }))}
                  className="form-input"
                >
                  <option value="">Select category...</option>
                  {INVENTORY_CATEGORIES.map(cat => (
                    <option key={cat} value={cat}>{cat}</option>
                  ))}
                </select>
              </FormField>

              <FormField label="Status" error={fieldErrors.status}>
                <select
                  value={form.status}
                  onChange={(e) => setForm(f => ({ ...f, status: e.target.value }))}
                  className="form-input"
                >
                  {INVENTORY_STATUSES.map(s => (
                    <option key={s.value} value={s.value}>{s.label}</option>
                  ))}
                </select>
              </FormField>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
              <FormField label="Price" error={fieldErrors.price}>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">€</span>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.price}
                    onChange={(e) => setForm(f => ({ ...f, price: e.target.value }))}
                    placeholder="0.00"
                    className="form-input pl-7"
                  />
                </div>
              </FormField>

              <FormField label="Currency" error={fieldErrors.currency}>
                <select
                  value={form.currency}
                  onChange={(e) => setForm(f => ({ ...f, currency: e.target.value }))}
                  className="form-input"
                >
                  <option value="EUR">EUR</option>
                  <option value="USD">USD</option>
                  <option value="GBP">GBP</option>
                  <option value="GEL">GEL</option>
                </select>
              </FormField>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
              <FormField label="Serial Number" error={fieldErrors.serial_number}>
                <input
                  type="text"
                  value={form.serial_number}
                  onChange={(e) => setForm(f => ({ ...f, serial_number: e.target.value }))}
                  placeholder="e.g. SN-12345678"
                  className="form-input font-mono"
                />
              </FormField>

              <FormField label="Model Number" error={fieldErrors.model_number}>
                <input
                  type="text"
                  value={form.model_number}
                  onChange={(e) => setForm(f => ({ ...f, model_number: e.target.value }))}
                  placeholder="e.g. A2485"
                  className="form-input"
                />
              </FormField>
            </div>

            <FormField label="Description" error={fieldErrors.description}>
              <textarea
                value={form.description}
                onChange={(e) => setForm(f => ({ ...f, description: e.target.value }))}
                rows={3}
                placeholder="Optional item description..."
                className="form-input resize-none"
              />
            </FormField>

            <FormField label="Image" error={fieldErrors.image}>
              <div
                onClick={() => fileInputRef.current?.click()}
                className="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
              >
                {imagePreview ? (
                  <div className="space-y-3">
                    <img src={imagePreview} alt="Preview" className="max-h-40 mx-auto rounded-lg object-contain" />
                    <p className="text-xs text-slate-500 dark:text-slate-400">Click to change image</p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    <svg className="w-8 h-8 text-slate-400 mx-auto" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                    </svg>
                    <p className="text-sm text-slate-500 dark:text-slate-400">Click to upload an image</p>
                    <p className="text-xs text-slate-400 dark:text-slate-500">JPEG, PNG, or WebP up to 2MB</p>
                  </div>
                )}
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={handleImageChange}
                />
              </div>
            </FormField>
          </div>
        </div>

        <div className="flex items-center gap-3 mt-5">
          <button
            type="submit"
            disabled={saveMutation.isPending || !form.title || !form.category}
            className="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors disabled:opacity-50 disabled:pointer-events-none shadow-sm"
          >
            {saveMutation.isPending && (
              <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            )}
            {isEdit ? 'Update Item' : 'Create Item'}
          </button>
          <button
            type="button"
            onClick={() => navigate('/inventory')}
            className="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
          >
            Cancel
          </button>
        </div>
      </form>

      <style>{`
        .form-input {
          width: 100%;
          padding: 0.5rem 0.75rem;
          border-radius: 0.5rem;
          border: 1px solid rgb(226 232 240 / 0.7);
          background: white;
          color: rgb(15 23 42);
          font-size: 0.875rem;
          outline: none;
          transition: all 0.15s;
        }
        .form-input:focus {
          border-color: rgb(96 165 250);
          box-shadow: 0 0 0 3px rgb(59 130 246 / 0.15);
        }
        .form-input::placeholder {
          color: rgb(148 163 184);
        }
        .dark .form-input {
          border-color: rgb(51 65 85 / 0.7);
          background: rgb(30 41 59 / 0.8);
          color: rgb(241 245 249);
        }
        .dark .form-input:focus {
          border-color: rgb(96 165 250);
        }
        .dark .form-input::placeholder {
          color: rgb(100 116 139);
        }
        select.form-input {
          appearance: auto;
        }
      `}</style>
    </div>
  )
}

function FormField({ label, required, error, children }: {
  label: string; required?: boolean; error?: string; children: React.ReactNode
}) {
  return (
    <div>
      <label className="block text-[13px] font-medium text-slate-700 dark:text-slate-300 mb-1.5">
        {label}
        {required && <span className="text-red-500 ml-0.5">*</span>}
      </label>
      {children}
      {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
    </div>
  )
}
