import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { ApiError } from '../api/client'
import { billingSituation, formatDate, formatMoney, listCustomerOptions, type Billing } from '../api/billings'
import type { Customer } from '../api/customers'
import { getBillingReport, type BillingReport, type ReportDirection, type ReportPeriod, type ReportStatus } from '../api/reports'
import { Sheet } from '../components/Sheet'

const sorts = [
  ['customer', 'Cliente'],
  ['description', 'Descrição'],
  ['issued_on', 'Emissão'],
  ['due_on', 'Vencimento'],
  ['status', 'Status'],
  ['original_amount', 'Original'],
  ['interest_amount', 'Juros'],
  ['updated_amount', 'Atualizado'],
  ['amount_paid', 'Pago'],
] as const

function localDate(day = new Date().getDate()): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${String(day).padStart(2, '0')}`
}

export function BillingReportPage() {
  const [params, setParams] = useSearchParams()
  const period = (params.get('period') ?? 'due') as ReportPeriod
  const from = params.get('from') ?? localDate(1)
  const to = params.get('to') ?? localDate()
  const customerId = params.get('customer_id') ?? ''
  const status = (params.get('status') ?? '') as ReportStatus
  const sort = params.get('sort') ?? 'due_on'
  const direction = (params.get('direction') === 'desc' ? 'desc' : 'asc') as ReportDirection
  const page = Math.max(1, Number(params.get('page') ?? '1') || 1)
  const queryKey = `${period}|${from}|${to}|${customerId}|${status}|${sort}|${direction}|${page}`
  const [customers, setCustomers] = useState<Customer[]>([])
  const [report, setReport] = useState<BillingReport | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [shownKey, setShownKey] = useState(queryKey)

  if (shownKey !== queryKey) {
    setShownKey(queryKey)
    setLoading(true)
    setError(null)
  }

  useEffect(() => {
    const controller = new AbortController()

    listCustomerOptions(controller.signal)
      .then((options) => {
        if (!controller.signal.aborted) {
          setCustomers(options)
        }
      })
      .catch(() => undefined)

    return () => controller.abort()
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    getBillingReport(
      { period, from, to, customerId, status, sort, direction, page },
      controller.signal,
    )
      .then((next) => {
        if (!controller.signal.aborted) {
          setReport(next)
          setLoading(false)
        }
      })
      .catch((reason: unknown) => {
        if (controller.signal.aborted) {
          return
        }

        setError(reason instanceof ApiError ? reason.message : 'A API não respondeu.')
        setLoading(false)
      })

    return () => controller.abort()
  }, [period, from, to, customerId, status, sort, direction, page])

  function update(next: Record<string, string | null>, keepPage = false) {
    const updated = new URLSearchParams(params)

    for (const [key, value] of Object.entries(next)) {
      if (value === null || value === '') {
        updated.delete(key)
      } else {
        updated.set(key, value)
      }
    }

    if (!keepPage) {
      updated.delete('page')
    }

    setParams(updated)
  }

  function toggleSort(column: string) {
    if (sort === column) {
      update({ sort: column, direction: direction === 'asc' ? 'desc' : 'asc' })
      return
    }

    update({ sort: column, direction: 'asc' })
  }

  const total = report?.meta.total ?? 0

  return (
    <Sheet
      wide
      eyebrow="Relatório"
      title="Faturamento"
      lede="Período, cliente e status. Os totais consideram o filtro inteiro."
      footerStart="Relatório de faturamento"
      footerEnd={loading ? 'Consultando' : `${total} ${total === 1 ? 'cobrança' : 'cobranças'}`}
    >
      <form className="filters" onSubmit={(event) => event.preventDefault()}>
        <label className="field" htmlFor="period">
          Período por
          <select id="period" value={period} onChange={(event) => update({ period: event.target.value })}>
            <option value="issued">Emissão</option>
            <option value="due">Vencimento</option>
            <option value="paid">Pagamento</option>
          </select>
        </label>
        <label className="field" htmlFor="from">
          De
          <input id="from" type="date" value={from} onChange={(event) => update({ from: event.target.value })} required />
        </label>
        <label className="field" htmlFor="to">
          Até
          <input id="to" type="date" value={to} onChange={(event) => update({ to: event.target.value })} required />
        </label>
        <label className="field" htmlFor="customer_id">
          Cliente
          <select id="customer_id" value={customerId} onChange={(event) => update({ customer_id: event.target.value })}>
            <option value="">Todos</option>
            {customers.map((customer) => (
              <option key={customer.id} value={customer.id}>
                {customer.name}
              </option>
            ))}
          </select>
        </label>
        <label className="field" htmlFor="status">
          Status
          <select id="status" value={status} onChange={(event) => update({ status: event.target.value })}>
            <option value="">Todos</option>
            <option value="pending">Pendente</option>
            <option value="overdue">Vencida</option>
            <option value="paid">Paga</option>
          </select>
        </label>
      </form>
      {loading ? <p className="status">Carregando relatório.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {report ? (
        <dl className="totals">
          <div>
            <dt>Cobranças</dt>
            <dd>{report.totals.count}</dd>
          </div>
          <div>
            <dt>Original</dt>
            <dd>{formatMoney(report.totals.original_amount)}</dd>
          </div>
          <div>
            <dt>Juros</dt>
            <dd>{formatMoney(report.totals.interest_amount)}</dd>
          </div>
          <div>
            <dt>Atualizado</dt>
            <dd>{formatMoney(report.totals.updated_amount)}</dd>
          </div>
          <div>
            <dt>Recebido</dt>
            <dd>{formatMoney(report.totals.amount_paid)}</dd>
          </div>
          <div>
            <dt>Pendente</dt>
            <dd>{formatMoney(report.totals.pending_amount)}</dd>
          </div>
        </dl>
      ) : null}
      {!loading && !error && report?.data.length === 0 ? <p className="status">Nenhuma cobrança no filtro.</p> : null}
      {!loading && !error && report && report.data.length > 0 ? (
        <div className="report-wrap">
          <table className="report">
            <thead>
              <tr>
                {sorts.map(([column, label]) => (
                  <th key={column} aria-sort={sort === column ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}>
                    <button type="button" onClick={() => toggleSort(column)}>
                      {label}
                      {sort === column ? (direction === 'asc' ? ' ↑' : ' ↓') : ''}
                    </button>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {report.data.map((billing) => (
                <ReportRow key={billing.id} billing={billing} />
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      {report && report.meta.last_page > 1 ? (
        <div className="pager">
          <button className="button button-secondary" type="button" disabled={page <= 1} onClick={() => update({ page: String(page - 1) }, true)}>
            Anterior
          </button>
          <span>
            Página {report.meta.current_page} de {report.meta.last_page}
          </span>
          <button
            className="button button-secondary"
            type="button"
            disabled={page >= report.meta.last_page}
            onClick={() => update({ page: String(page + 1) }, true)}
          >
            Próxima
          </button>
        </div>
      ) : null}
    </Sheet>
  )
}

function ReportRow({ billing }: { billing: Billing }) {
  return (
    <tr>
      <td>{billing.customer.name}</td>
      <td>
        <Link to={`/cobrancas/${billing.id}`}>{billing.description}</Link>
      </td>
      <td>{formatDate(billing.issued_on)}</td>
      <td>{formatDate(billing.due_on)}</td>
      <td>{billingSituation(billing)}</td>
      <td className="num">{formatMoney(billing.original_amount)}</td>
      <td className="num">{formatMoney(billing.interest_amount)}</td>
      <td className="num">{formatMoney(billing.updated_amount)}</td>
      <td className="num">{billing.amount_paid ? formatMoney(billing.amount_paid) : '—'}</td>
    </tr>
  )
}
