import { ApiError, apiBlob, apiRequest } from './client'
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

export function reportSearch(query: ReportQuery, includePage = true): URLSearchParams {
  const params = new URLSearchParams({
    period: query.period,
    from: query.from,
    to: query.to,
    sort: query.sort,
    direction: query.direction,
  })

  if (includePage) {
    params.set('page', String(query.page))
  }

  if (query.customerId) {
    params.set('customer_id', query.customerId)
  }

  if (query.status) {
    params.set('status', query.status)
  }

  return params
}

export async function getBillingReport(query: ReportQuery, signal?: AbortSignal): Promise<BillingReport> {
  const result = await apiRequest<BillingReport & { data: Billing[] }>(`/api/reports/billings?${reportSearch(query).toString()}`, { signal })

  if (!result?.data || !result.meta || !result.totals) {
    throw new ApiError('A API não respondeu.', 500)
  }

  return result
}

async function saveDownload(path: string, accept: string, fallback: string): Promise<void> {
  const { blob, filename } = await apiBlob(path, accept, fallback)
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export async function downloadBillingReport(query: ReportQuery): Promise<void> {
  await saveDownload(`/api/reports/billings.csv?${reportSearch(query, false).toString()}`, 'text/csv', 'faturamento.csv')
}

export async function downloadBillingReportPdf(query: ReportQuery): Promise<void> {
  await saveDownload(`/api/reports/billings.pdf?${reportSearch(query, false).toString()}`, 'application/pdf', 'faturamento.pdf')
}
