export interface InventoryItem {
  id: number
  title: string
  description: string | null
  category: string
  price: string | null
  currency: string
  serial_number: string | null
  model_number: string | null
  image_path: string | null
  status: 'available' | 'in_use' | 'damaged' | 'retired'
  created_at: string
  updated_at: string
}

export interface InventoryItemFormData {
  title: string
  description?: string
  category: string
  price?: string
  currency?: string
  serial_number?: string
  model_number?: string
  status?: string
}

export const INVENTORY_CATEGORIES = [
  'Laptop',
  'Desktop',
  'Monitor',
  'Keyboard',
  'Mouse',
  'Headset',
  'Phone',
  'Tablet',
  'Printer',
  'Scanner',
  'Server',
  'Network Equipment',
  'Furniture',
  'Office Supplies',
  'Other',
] as const

export const INVENTORY_STATUSES = [
  { value: 'available', label: 'Available' },
  { value: 'in_use', label: 'In Use' },
  { value: 'damaged', label: 'Damaged' },
  { value: 'retired', label: 'Retired' },
] as const
