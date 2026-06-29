export type TaskStatus =
  | 'draft'
  | 'pending_manager'
  | 'pending_lawyer'
  | 'pending_initiator'
  | 'pending_final_lawyer'
  | 'pending_final_manager'
  | 'pending_partner'
  | 'needs_revision'
  | 'approved'
  | 'archived'
  | 'rejected'

export interface TaskDocument {
  id: number
  task_id: number
  path: string
  mime_type: string
  version: number
  is_attachment: boolean
  original_name: string | null
  approved_at: string | null
  registration_number: string | null
  is_signed: boolean
  signature_path: string | null
  signed_by: number | null
  signer: { id: number; name: string } | null
  created_at: string
  updated_at: string
}

export interface TaskActivity {
  id: number
  task_id: number
  user_id: number
  action: string
  comment: string | null
  meta: Record<string, unknown> | null
  created_at: string
  user: { id: number; name: string } | null
}

export interface TaskReviewer {
  id: number
  name: string
  email: string
  role: string
  pivot?: {
    task_id: number
    user_id: number
    deadline: string | null
    created_at?: string | null
    updated_at?: string | null
  }
}

export interface WorkflowRouteStep {
  id: number
  sort_order: number
  name: string
  role: string
  action_type: string
  duration_days: number
}

export interface WorkflowRoute {
  id: number
  name: string
  slug: string
  description: string | null
  is_default: boolean
  category_ids: number[]
  steps: WorkflowRouteStep[]
}

export interface ActiveStep {
  step_id: number
  step_name: string | null
  role: string | null
  action_type: string | null
  status: string
  started_at?: string | null
}

export interface Task {
  id: number
  document_category_id: number
  partner_id: number
  initiator_id: number
  assigned_lawyer_id: number | null
  route_type: 'standard' | 'simplified' | string
  workflow_route_id: number | null
  current_workflow_step_id: number | null
  status: TaskStatus
  current_step: number
  deadline: string | null
  commercial_terms: string | null
  amount: number | null
  validity_from: string | null
  validity_to: string | null
  registration_number: string | null
  fast_tracked: boolean
  created_at: string
  updated_at: string
  category: { id: number; name: string } | null
  partner: { id: number; name: string; bin_iin: string } | null
  initiator: { id: number; name: string; email: string } | null
  assigned_lawyer: { id: number; name: string } | null
  documents: TaskDocument[]
  activities: TaskActivity[]
  reviewers: TaskReviewer[]
  workflow_route: { id: number; name: string; slug: string; steps: WorkflowRouteStep[] } | null
  partner_access?: {
    url: string
    email: string
    expires_at: string
    step_name: string | null
  } | null
  comments?: {
    id: number
    body: string
    resolved: boolean
    user: { id: number; name: string }
    replies: { id: number; body: string; user: { id: number; name: string }; created_at: string }[]
    created_at: string
  }[]
  available_actions?: string[]
  active_steps?: ActiveStep[]
  current_step_action_type?: string
  current_step_name?: string
  current_step_role?: string
  can_edit_attachments?: boolean
  table_data?: Record<string, Record<string, string>[]> | null
  step_durations?: Record<string, number> | null
}
