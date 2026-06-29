import type { ReactNode } from 'react'

const colorMap = {
  gray: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300',
  blue: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
  green: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
  red: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
  yellow: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
  purple: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
  orange: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
} as const

interface BadgeProps {
  color?: keyof typeof colorMap
  children: ReactNode
  className?: string
  dot?: boolean
}

export default function Badge({ color = 'gray', children, className = '', dot }: BadgeProps) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${colorMap[color]} ${className}`}>
      {dot && <span className={`h-1.5 w-1.5 rounded-full bg-current`} />}
      {children}
    </span>
  )
}

export function StatusBadge({ status }: { status: string }) {
  const config: Record<string, { color: keyof typeof colorMap; label: string }> = {
    draft: { color: 'gray', label: 'Draft' },
    pending_manager: { color: 'yellow', label: 'Pending Manager' },
    pending_lawyer: { color: 'blue', label: 'Pending Lawyer' },
    pending_initiator: { color: 'purple', label: 'Pending Initiator' },
    pending_final_lawyer: { color: 'blue', label: 'Final Lawyer Review' },
    pending_final_manager: { color: 'yellow', label: 'Final Manager Approval' },
    pending_partner: { color: 'purple', label: 'Pending Partner' },
    needs_revision: { color: 'orange', label: 'Needs Revision' },
    approved: { color: 'green', label: 'Approved' },
    archived: { color: 'gray', label: 'Archived' },
    rejected: { color: 'red', label: 'Rejected' },
  }

  const c = config[status] ?? { color: 'gray' as const, label: status }

  return <Badge color={c.color} dot>{c.label}</Badge>
}
