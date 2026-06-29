import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, useEffect } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { createPartner, fetchPartner, updatePartner, checkBinIin, checkAdataReliability } from '../api/partners'
import { useToast } from '../contexts/ToastContext'

export default function PartnerFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { addToast } = useToast()

  const [form, setForm] = useState({ name: '', bin_iin: '', email: '', bank_details: '' })
  const [binExists, setBinExists] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [adataResult, setAdataResult] = useState<Record<string, unknown> | null>(null)
  const [adataLoading, setAdataLoading] = useState(false)

  const { data: partner, isLoading } = useQuery({
    queryKey: ['partner', id],
    queryFn: () => fetchPartner(Number(id)),
    enabled: isEdit,
  })

  useEffect(() => {
    if (partner) {
      setForm({
        name: partner.name || '',
        bin_iin: partner.bin_iin || '',
        email: partner.email || '',
        bank_details: partner.bank_details || '',
      })
    }
  }, [partner])

  const saveMutation = useMutation({
    mutationFn: () => isEdit ? updatePartner(Number(id), form) : createPartner(form),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['partners'] })
      addToast(isEdit ? 'Partner updated' : 'Partner created')
      navigate(`/partners/${result.id}`, { replace: true })
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

  async function handleBinCheck() {
    if (form.bin_iin.length !== 12) return
    try {
      const { exists } = await checkBinIin(form.bin_iin)
      const alreadyExists = exists && (!isEdit || partner?.bin_iin !== form.bin_iin)
      setBinExists(alreadyExists)
      if (!alreadyExists) {
        setAdataLoading(true)
        try {
          const data = await checkAdataReliability(form.bin_iin)
          setAdataResult(data)
        } catch {
          setAdataResult(null)
        } finally {
          setAdataLoading(false)
        }
      }
    } catch {
      // ignore
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
      {/* Breadcrumb */}
      <Link to="/partners" className="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors mb-4">
        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Partners
      </Link>

      <h1 className="text-xl font-bold text-slate-900 dark:text-white mb-5">
        {isEdit ? 'Edit Partner' : 'New Partner'}
      </h1>

      <form onSubmit={handleSubmit}>
        <div className="rounded-xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800/80 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/50">
            <h2 className="text-sm font-semibold text-slate-800 dark:text-white">Partner Information</h2>
          </div>
          <div className="p-5 space-y-5">
            <FormField label="Company Name" required error={fieldErrors.name}>
              <input
                type="text"
                required
                value={form.name}
                onChange={(e) => setForm(f => ({ ...f, name: e.target.value }))}
                placeholder="Enter company name"
                className="form-input"
              />
            </FormField>

            <FormField label="BIN / IIN" required error={binExists ? 'This BIN/IIN already exists in the system.' : fieldErrors.bin_iin}>
              <div className="relative">
                <input
                  type="text"
                  required
                  value={form.bin_iin}
                  onChange={(e) => { setForm(f => ({ ...f, bin_iin: e.target.value.replace(/\D/g, '').slice(0, 12) })); setBinExists(false); setAdataResult(null) }}
                  onBlur={handleBinCheck}
                  placeholder="e.g. 123456789012"
                  className="form-input font-mono pr-8"
                />
                {form.bin_iin && (
                  <span className="absolute right-2.5 top-1/2 -translate-y-1/2">
                    {form.bin_iin.length === 12 ? (
                      <svg className="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    ) : (
                      <svg className="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    )}
                  </span>
                )}
              </div>
              {form.bin_iin && form.bin_iin.length !== 12 && (
                <p className="text-xs text-red-500 mt-1">BIN/IIN must be exactly 12 digits</p>
              )}
            </FormField>

            {adataLoading && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-800/30">
                <svg className="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                <span className="text-xs text-blue-700 dark:text-blue-300">Checking ADATA reliability...</span>
              </div>
            )}

            {adataResult && !adataLoading && adataResult.status === 'error' && (
              <div className="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40">
                <p className="text-xs text-red-600 dark:text-red-400">{String(adataResult.message)}</p>
              </div>
            )}

            {adataResult && !adataLoading && adataResult.status === 'live' && (() => {
              const r = adataResult as Record<string, string | number | boolean | null>
              const score = Number(r.reliability_score ?? 0)
              return (
              <div className="rounded-lg border border-slate-200/60 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-800/50 p-3.5">
                <div className="flex items-center justify-between mb-2.5">
                  <div className="flex items-center gap-2">
                    <svg className="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    <span className="text-xs font-semibold text-slate-700 dark:text-slate-300">ADATA Verification</span>
                  </div>
                  <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${score >= 70 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : score >= 40 ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'}`}>
                    {score}/100
                  </span>
                </div>
                <div className="space-y-1.5 text-xs">
                  {r.company_name && (
                    <div><span className="text-slate-400 dark:text-slate-500">Company: </span><span className="font-medium text-slate-800 dark:text-slate-100">{String(r.short_name || r.company_name)}</span></div>
                  )}
                  {r.director && (
                    <div><span className="text-slate-400 dark:text-slate-500">Director: </span><span className="font-medium text-slate-800 dark:text-slate-100">{String(r.director)}</span></div>
                  )}
                  <div className="flex gap-4">
                    {r.is_active != null && (
                      <div><span className="text-slate-400 dark:text-slate-500">Active: </span><span className={`font-semibold ${r.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>{r.is_active ? 'Yes' : 'No'}</span></div>
                    )}
                    {r.is_nds_payer != null && (
                      <div><span className="text-slate-400 dark:text-slate-500">VAT: </span><span className={`font-semibold ${r.is_nds_payer ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'}`}>{r.is_nds_payer ? 'Yes' : 'No'}</span></div>
                    )}
                  </div>
                  {(r.company_problems || r.financial_problems || r.unreliable_zakup || r.head_problems) && (
                    <div className="flex flex-wrap gap-1.5 pt-1">
                      {r.company_problems && <span className="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Company issues</span>}
                      {r.financial_problems && <span className="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Financial issues</span>}
                      {r.unreliable_zakup && <span className="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Unreliable supplier</span>}
                      {r.head_problems && <span className="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Director issues</span>}
                    </div>
                  )}
                </div>
              </div>)
            })()}

            <FormField label="Email" required error={fieldErrors.email}>
              <input
                type="email"
                required
                value={form.email}
                onChange={(e) => setForm(f => ({ ...f, email: e.target.value }))}
                placeholder="partner@example.com"
                className="form-input"
              />
            </FormField>

            <FormField label="Bank Details" required error={fieldErrors.bank_details}>
              <textarea
                required
                value={form.bank_details}
                onChange={(e) => setForm(f => ({ ...f, bank_details: e.target.value }))}
                rows={4}
                placeholder="Account number, bank name, BIC/SWIFT..."
                className="form-input resize-none"
              />
            </FormField>
          </div>
        </div>

        <div className="flex items-center gap-3 mt-5">
          <button
            type="submit"
            disabled={saveMutation.isPending || binExists || (form.bin_iin.length > 0 && form.bin_iin.length !== 12)}
            className="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors disabled:opacity-50 disabled:pointer-events-none shadow-sm"
          >
            {saveMutation.isPending && (
              <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            )}
            {isEdit ? 'Update Partner' : 'Create Partner'}
          </button>
          <button
            type="button"
            onClick={() => navigate('/partners')}
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
        @media (prefers-color-scheme: dark) {
          .form-input {
            border-color: rgb(51 65 85 / 0.7);
            background: rgb(30 41 59 / 0.8);
            color: rgb(241 245 249);
          }
          .form-input:focus {
            border-color: rgb(96 165 250);
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.15);
          }
          .form-input::placeholder {
            color: rgb(100 116 139);
          }
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
