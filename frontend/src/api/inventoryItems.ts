import api from '../lib/api'
import type { InventoryItem } from '../types/inventoryItem'

export interface InventoryItemsListResponse {
  data: InventoryItem[]
  current_page: number
  last_page: number
  total: number
}

export async function fetchInventoryItems(params?: {
  search?: string
  status?: string
  category?: string
  page?: number
  per_page?: number
}): Promise<InventoryItemsListResponse> {
  const { data } = await api.get('/inventory-items', { params })
  return data
}

export async function fetchInventoryItem(id: number): Promise<InventoryItem> {
  const { data } = await api.get(`/inventory-items/${id}`)
  return data
}

export async function createInventoryItem(payload: FormData): Promise<InventoryItem> {
  const { data } = await api.post('/inventory-items', payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function updateInventoryItem(id: number, payload: FormData): Promise<InventoryItem> {
  payload.append('_method', 'PUT')
  const { data } = await api.post(`/inventory-items/${id}`, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function deleteInventoryItem(id: number): Promise<void> {
  await api.delete(`/inventory-items/${id}`)
}
