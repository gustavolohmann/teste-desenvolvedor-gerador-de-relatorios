import { apiRequest, ApiError } from './client'
import { listCustomers, type Customer } from './customers'

export type BillingStatus = 'pending' | 'paid'

export type Billing = {
  id: number
  customer_id: number
  customer: {
    id: number
    name: string
  }
  description: string
  original_amount: string
  issued_on: string
  due_on: string
  paid_on: string | null
  monthly_interest_rate: string
  status: BillingStatus
  interest_amount: string
  updated_amount: string
  amount_paid: string | null
  days_overdue: number
  overdue: boolean
}

export type BillingInput = {
  customer_id: number
  description: string
  original_amount: string
  issued_on: string
  due_on: string
  paid_on: string | null
  monthly_interest_rate: string
  status: BillingStatus
}

type BillingResponse = {
  data: Billing
}

export type BillingPage = {
  data: Billing[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const billingStatusLabel: Record<BillingStatus, string> = {
  pending: 'Pendente',
  paid: 'Paga',
}

export function billingSituation(billing: Pick<Billing, 'status' | 'overdue'>): string {
  if (billing.status === 'paid') {
    return 'Paga'
  }

  return billing.overdue ? 'Vencida' : 'Pendente'
}

export function billingBadgeClass(billing: Pick<Billing, 'status' | 'overdue'>): string {
  if (billing.status === 'paid') {
    return 'badge'
  }

  return billing.overdue ? 'badge badge-overdue' : 'badge badge-inactive'
}

export function formatMoney(value: string): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value))
}

export function formatDate(value: string): string {
  const [year, month, day] = value.split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day}/${month}/${year}`
}

export async function listBillings(page: number, signal?: AbortSignal): Promise<BillingPage> {
  const result = await apiRequest<BillingPage>(`/api/billings?page=${page}`, { signal })

  if (!result) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result
}

export async function getBilling(id: number, signal?: AbortSignal): Promise<Billing> {
  const result = await apiRequest<BillingResponse>(`/api/billings/${id}`, { signal })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}

export async function createBilling(input: BillingInput): Promise<Billing> {
  const result = await apiRequest<BillingResponse>('/api/billings', {
    method: 'POST',
    body: payload(input),
  })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}

export async function payBilling(id: number, paidOn: string): Promise<Billing> {
  const result = await apiRequest<BillingResponse>(`/api/billings/${id}/payment`, {
    method: 'POST',
    body: { paid_on: paidOn },
  })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}

export async function updateBilling(id: number, input: BillingInput): Promise<Billing> {
  const result = await apiRequest<BillingResponse>(`/api/billings/${id}`, {
    method: 'PUT',
    body: payload(input),
  })

  if (!result?.data) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result.data
}

export async function listCustomerOptions(signal?: AbortSignal, query = ''): Promise<Customer[]> {
  const page = await listCustomers(1, signal, 100, query)

  return page.data
}

function payload(input: BillingInput): BillingInput {
  return {
    ...input,
    paid_on: input.status === 'paid' ? input.paid_on : null,
    original_amount: input.original_amount,
    monthly_interest_rate: input.monthly_interest_rate,
  }
}
