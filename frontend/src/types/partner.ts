export interface PartnerDocument {
  id: number
  partner_id: number
  name: string
  path: string
  mime_type: string
  size: number
  type?: string
  created_at: string
  updated_at: string
}

export interface Partner {
  id: number
  name: string
  bin_iin: string
  bank_details: string | null
  email: string | null
  reliability_data: Record<string, unknown> | null
  blacklisted_at: string | null
  blacklist_reason: string | null
  blacklisted_by: number | null
  created_at: string
  updated_at: string
  documents?: PartnerDocument[]
}

export interface PartnerFormData {
  name: string
  bin_iin: string
  email?: string
  bank_details?: string
}
