import { type TextareaHTMLAttributes, forwardRef } from 'react'

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
  hint?: string
}

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ label, error, hint, className = '', id, ...props }, ref) => {
    const textareaId = id || label?.toLowerCase().replace(/\s+/g, '-')

    return (
      <div>
        {label && (
          <label htmlFor={textareaId} className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            {label}
            {props.required && <span className="text-red-500 ml-0.5">*</span>}
          </label>
        )}
        <textarea
          ref={ref}
          id={textareaId}
          className={`w-full rounded-lg border bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2.5 text-sm transition-colors focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none resize-y ${
            error
              ? 'border-red-400 dark:border-red-500'
              : 'border-slate-300 dark:border-slate-600'
          } ${className}`}
          aria-invalid={!!error}
          {...props}
        />
        {error && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>}
        {hint && !error && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{hint}</p>}
      </div>
    )
  }
)

Textarea.displayName = 'Textarea'
export default Textarea
