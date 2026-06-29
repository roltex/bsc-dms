import { useCallback, useState, type DragEvent } from 'react'

interface FileUploadProps {
  onFileSelect: (file: File) => void
  accept?: string
  label?: string
  hint?: string
  currentFile?: File | null
}

export default function FileUpload({ onFileSelect, accept, label, hint, currentFile }: FileUploadProps) {
  const [dragOver, setDragOver] = useState(false)

  const handleDrop = useCallback(
    (e: DragEvent) => {
      e.preventDefault()
      setDragOver(false)
      const file = e.dataTransfer.files?.[0]
      if (file) onFileSelect(file)
    },
    [onFileSelect]
  )

  return (
    <div>
      {label && <p className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{label}</p>}
      <label
        onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        className={`flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 cursor-pointer transition-colors ${
          dragOver
            ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/10'
            : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500'
        }`}
      >
        <svg className="h-8 w-8 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        {currentFile ? (
          <p className="text-sm text-slate-700 dark:text-slate-300 font-medium">{currentFile.name}</p>
        ) : (
          <p className="text-sm text-slate-500 dark:text-slate-400">
            <span className="text-blue-600 dark:text-blue-400 font-medium">Click to upload</span> or drag and drop
          </p>
        )}
        {hint && <p className="text-xs text-slate-400 mt-1">{hint}</p>}
        <input
          type="file"
          accept={accept}
          className="hidden"
          onChange={(e) => {
            const file = e.target.files?.[0]
            if (file) onFileSelect(file)
          }}
        />
      </label>
    </div>
  )
}
