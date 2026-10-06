import { apiRequest, ApiError } from './client'

export type CustomerStatus = 'active' | 'inactive'

export type Customer = {
  id: number
  name: string
  document: string
  email: string
  status: CustomerStatus
}

export type CustomerInput = {
  name: string
  document: string
  email: string
  status: CustomerStatus
}

type CustomerResponse = {
  data: Customer
}

export type CustomerPage = {
  data: Customer[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const statusLabel: Record<CustomerStatus, string> = {
  active: 'Ativo',
  inactive: 'Inativo',
}

export async function listCustomers(page: number, signal?: AbortSignal): Promise<CustomerPage> {
  const result = await apiRequest<CustomerPage>(`/api/customers?page=${page}`, { signal })

  if (!result) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result
}

export async function getCustomer(id: number, signal?: AbortSignal): Promise<Customer> {
  const result = await apiRequest<CustomerResponse>(`/api/customers/${id}`, { signal })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}

export async function createCustomer(input: CustomerInput): Promise<Customer> {
  const result = await apiRequest<CustomerResponse>('/api/customers', {
    method: 'POST',
    body: input,
  })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}

export async function updateCustomer(id: number, input: CustomerInput): Promise<Customer> {
  const result = await apiRequest<CustomerResponse>(`/api/customers/${id}`, {
    method: 'PUT',
    body: input,
  })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}
