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

type ExportFormat = 'csv' | 'pdf'

type ExportJob = {
  id: number
  status: 'pending' | 'processing' | 'done' | 'failed'
  download_url?: string
  error?: string
}

export async function downloadBillingReport(query: ReportQuery): Promise<void> {
  await exportAndDownload(query, 'csv')
}

export async function downloadBillingReportPdf(query: ReportQuery): Promise<void> {
  await exportAndDownload(query, 'pdf')
}

async function exportAndDownload(query: ReportQuery, format: ExportFormat): Promise<void> {
  const created = await apiRequest<{ job_id: number }>('/api/reports/export', {
    method: 'POST',
    body: {
      period: query.period,
      from: query.from,
      to: query.to,
      customer_id: query.customerId || null,
      status: query.status || null,
      sort: query.sort,
      direction: query.direction,
      format,
    },
  })

  if (!created?.job_id) {
    throw new ApiError('A API não respondeu.', 500)
  }

  const ready = await waitForExport(created.job_id)
  const accept = format === 'pdf' ? 'application/pdf' : 'text/csv'
  const fallback = format === 'pdf' ? 'faturamento.pdf' : 'faturamento.csv'
  await saveDownload(ready.download_url ?? `/api/reports/export/${created.job_id}/download`, accept, fallback)
}

async function waitForExport(jobId: number): Promise<ExportJob> {
  for (let attempt = 0; attempt < 120; attempt += 1) {
    const job = await apiRequest<ExportJob>(`/api/reports/export/${jobId}`)

    if (!job?.status) {
      throw new ApiError('A API não respondeu.', 500)
    }

    if (job.status === 'done') {
      return job
    }

    if (job.status === 'failed') {
      throw new ApiError(job.error || 'A geração do relatório falhou.', 422)
    }

    await new Promise((resolve) => setTimeout(resolve, 1000))
  }

  throw new ApiError('A geração do relatório demorou demais.', 504)
}
