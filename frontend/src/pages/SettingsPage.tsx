import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, useEffect } from 'react'
import { fetchGoogleSettings, saveGoogleSettings, fetchGoogleAuthUrl, googleDisconnect } from '../api/settings'
import { useToast } from '../contexts/ToastContext'
import Button from '../components/ui/Button'
import { Card, CardBody, CardHeader } from '../components/ui/Card'

export default function SettingsPage() {
  const { addToast } = useToast()
  const queryClient = useQueryClient()
  const [enabled, setEnabled] = useState(false)
  const [clientId, setClientId] = useState('')
  const [clientSecret, setClientSecret] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['google-settings'],
    queryFn: fetchGoogleSettings,
  })

  useEffect(() => {
    if (data) {
      setEnabled(data.google_drive_enabled)
      setClientId(data.google_client_id || '')
      setClientSecret(data.google_client_secret || '')
    }
  }, [data])

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const authResult = params.get('google_auth')
    if (authResult === 'success') {
      addToast('Google account authorized successfully!')
      queryClient.invalidateQueries({ queryKey: ['google-settings'] })
      queryClient.invalidateQueries({ queryKey: ['google-status'] })
      window.history.replaceState({}, '', window.location.pathname)
    } else if (authResult === 'error') {
      addToast('Google authorization failed: ' + (params.get('message') || 'Unknown error'), 'error')
      window.history.replaceState({}, '', window.location.pathname)
    }
  }, [])

  const saveMutation = useMutation({
    mutationFn: () => saveGoogleSettings({
      google_drive_enabled: enabled,
      google_client_id: clientId,
      google_client_secret: clientSecret,
    }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['google-settings'] })
      queryClient.invalidateQueries({ queryKey: ['google-status'] })
      addToast('Google settings saved')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      addToast(err?.response?.data?.message || 'Failed to save settings', 'error')
    },
  })

  const authMutation = useMutation({
    mutationFn: fetchGoogleAuthUrl,
    onSuccess: (url: string) => {
      window.location.href = url
    },
    onError: () => {
      addToast('Failed to get authorization URL. Save your Client ID and Secret first.', 'error')
    },
  })

  const disconnectMutation = useMutation({
    mutationFn: googleDisconnect,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['google-settings'] })
      queryClient.invalidateQueries({ queryKey: ['google-status'] })
      addToast('Google account disconnected')
    },
    onError: () => {
      addToast('Failed to disconnect', 'error')
    },
  })

  const hasCredentials = clientId.trim().length > 10 && clientSecret.trim().length > 5

  if (isLoading) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="h-10 bg-slate-200 dark:bg-slate-700 rounded-xl w-48" />
        <div className="h-64 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
      </div>
    )
  }

  return (
    <div>
      <h1 className="text-2xl font-semibold text-slate-900 dark:text-white mb-6">Settings</h1>

      <div className="max-w-2xl">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                <svg className="w-5 h-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                </svg>
              </div>
              <div>
                <h2 className="font-semibold text-sm text-slate-900 dark:text-white">Google Docs Integration</h2>
                <p className="text-xs text-slate-500 dark:text-slate-400">Edit documents online using Google Docs</p>
              </div>
            </div>
          </CardHeader>
          <CardBody className="space-y-5">
            {/* Enable toggle */}
            <div className="flex items-center justify-between p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
              <div>
                <p className="text-sm font-medium text-slate-900 dark:text-white">Enable Google Docs Editing</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  When enabled, users can edit documents in Google Docs (opens in a new tab)
                </p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={enabled}
                onClick={() => setEnabled(!enabled)}
                className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                  enabled ? 'bg-blue-600' : 'bg-slate-200 dark:bg-slate-600'
                }`}
              >
                <span
                  className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                    enabled ? 'translate-x-5' : 'translate-x-0'
                  }`}
                />
              </button>
            </div>

            {/* Client ID */}
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                OAuth Client ID
              </label>
              <input
                type="text"
                value={clientId}
                onChange={(e) => setClientId(e.target.value)}
                placeholder="xxxx.apps.googleusercontent.com"
                className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white px-4 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all"
              />
            </div>

            {/* Client Secret */}
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                OAuth Client Secret
              </label>
              <input
                type="password"
                value={clientSecret}
                onChange={(e) => setClientSecret(e.target.value)}
                placeholder="GOCSPX-..."
                className="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white px-4 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all"
              />
            </div>

            {/* Authorization status */}
            <div className={`flex items-center justify-between p-4 rounded-xl border ${
              data?.google_authorized
                ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/40'
                : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/40'
            }`}>
              <div>
                <p className={`text-sm font-medium ${
                  data?.google_authorized
                    ? 'text-emerald-800 dark:text-emerald-300'
                    : 'text-amber-800 dark:text-amber-300'
                }`}>
                  {data?.google_authorized ? 'Google Account Connected' : 'Google Account Not Connected'}
                </p>
                <p className={`text-xs mt-0.5 ${
                  data?.google_authorized
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-amber-600 dark:text-amber-400'
                }`}>
                  {data?.google_authorized
                    ? 'Your Google Drive is authorized for document editing'
                    : 'Save credentials first, then click Authorize to connect your Google account'
                  }
                </p>
              </div>
              {data?.google_authorized ? (
                <button
                  onClick={() => disconnectMutation.mutate()}
                  disabled={disconnectMutation.isPending}
                  className="px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800/40 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors"
                >
                  {disconnectMutation.isPending ? 'Disconnecting...' : 'Disconnect'}
                </button>
              ) : (
                <button
                  onClick={() => authMutation.mutate()}
                  disabled={!hasCredentials || authMutation.isPending}
                  className="px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800/40 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {authMutation.isPending ? 'Redirecting...' : 'Authorize with Google'}
                </button>
              )}
            </div>

            {/* Setup instructions */}
            <div className="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 p-4">
              <p className="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Setup Instructions</p>
              <ol className="text-xs text-slate-600 dark:text-slate-400 space-y-1.5 list-decimal ml-4">
                <li>Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer" className="underline text-blue-600 dark:text-blue-400">Google Cloud Console</a></li>
                <li>Create a project (or select an existing one)</li>
                <li>Enable the <strong>Google Drive API</strong></li>
                <li>Go to <strong>APIs & Services &rarr; Credentials</strong></li>
                <li>Click <strong>Create Credentials &rarr; OAuth client ID</strong></li>
                <li>Choose <strong>Web application</strong> as the type</li>
                <li>Add <code className="bg-slate-200 dark:bg-slate-600 px-1 rounded text-xs">https://efes.buildweb.dev/api/google/callback</code> as an <strong>Authorized redirect URI</strong></li>
                <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into the fields above</li>
                <li>Click <strong>Save Settings</strong>, then click <strong>Authorize with Google</strong></li>
              </ol>
            </div>

            <div className="flex justify-end pt-2">
              <Button
                onClick={() => saveMutation.mutate()}
                loading={saveMutation.isPending}
              >
                Save Settings
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
