import { ApiError, apiRequest } from './client'
import type { Billing, BillingPage } from './billings'

export type ReportPeriod = 'issued' | 'due' | 'paid'
export type ReportStatus = '' | 'pending' | 'overdue' | 'paid'
export type ReportDirection = 'asc' | 'desc'

export type ReportTotals = {
  count: number
  original_amount: string
  interest_amount: string
  updated_amount: string
  amount_paid: string
  pending_amount: string
}

export type BillingReport = BillingPage & {
  totals: ReportTotals
}

export type ReportQuery = {
  period: ReportPeriod
  from: string
  to: string
  customerId: string
  status: ReportStatus
  sort: string
  direction: ReportDirection
  page: number
}

export async function getBillingReport(query: ReportQuery, signal?: AbortSignal): Promise<BillingReport> {
  const params = new URLSearchParams({
    period: query.period,
    from: query.from,
    to: query.to,
    sort: query.sort,
    direction: query.direction,
    page: String(query.page),
  })

  if (query.customerId) {
    params.set('customer_id', query.customerId)
  }

  if (query.status) {
    params.set('status', query.status)
  }

  const result = await apiRequest<BillingReport & { data: Billing[] }>(`/api/reports/billings?${params.toString()}`, { signal })

  if (!result?.data || !result.meta || !result.totals) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result
}
