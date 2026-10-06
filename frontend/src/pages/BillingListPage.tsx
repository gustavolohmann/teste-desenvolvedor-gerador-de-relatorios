import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { ApiError } from '../api/client'
import { billingStatusLabel, formatDate, formatMoney, listBillings, type BillingPage } from '../api/billings'
import { Sheet } from '../components/Sheet'

export function BillingListPage() {
  const [params, setParams] = useSearchParams()
  const page = Math.max(1, Number(params.get('page') ?? '1') || 1)
  const [result, setResult] = useState<BillingPage | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [shownPage, setShownPage] = useState(page)

  if (shownPage !== page) {
    setShownPage(page)
    setLoading(true)
    setError(null)
  }

  useEffect(() => {
    const controller = new AbortController()

    listBillings(page, controller.signal)
      .then((next) => {
        if (!controller.signal.aborted) {
          setResult(next)
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
  }, [page])

  function goToPage(nextPage: number) {
    setParams(nextPage <= 1 ? {} : { page: String(nextPage) })
  }

  const total = result?.meta.total ?? 0

  return (
    <Sheet
      wide
      eyebrow="Cobranças"
      title="Lançamentos"
      lede="Cliente, valor original, vencimento e status."
      footerStart="Ficha de cobrança"
      footerEnd={loading ? 'Consultando' : `${total} ${total === 1 ? 'cobrança' : 'cobranças'}`}
    >
      <div className="toolbar">
        <Link className="button" to="/cobrancas/nova">
          Nova cobrança
        </Link>
      </div>
      {loading ? <p className="status">Carregando cobranças.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {!loading && !error && result?.data.length === 0 ? <p className="status">Nenhuma cobrança cadastrada.</p> : null}
      {!loading && !error && result && result.data.length > 0 ? (
        <ul className="records records-billing">
          {result.data.map((billing) => (
            <li key={billing.id}>
              <Link to={`/cobrancas/${billing.id}`}>{billing.description}</Link>
              <span>{billing.customer.name}</span>
              <span>{formatDate(billing.due_on)}</span>
              <span>{formatMoney(billing.original_amount)}</span>
              <span className={billing.status === 'paid' ? 'badge' : 'badge badge-inactive'}>
                {billingStatusLabel[billing.status]}
              </span>
            </li>
          ))}
        </ul>
      ) : null}
      {result && result.meta.last_page > 1 ? (
        <div className="pager">
          <button className="button button-secondary" type="button" disabled={page <= 1} onClick={() => goToPage(page - 1)}>
            Anterior
          </button>
          <span>
            Página {result.meta.current_page} de {result.meta.last_page}
          </span>
          <button
            className="button button-secondary"
            type="button"
            disabled={page >= result.meta.last_page}
            onClick={() => goToPage(page + 1)}
          >
            Próxima
          </button>
        </div>
      ) : null}
    </Sheet>
  )
}
