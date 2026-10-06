import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { ApiError } from '../api/client'
import { listCustomers, statusLabel, type CustomerPage } from '../api/customers'
import { Sheet } from '../components/Sheet'

export function CustomerListPage() {
  const [params, setParams] = useSearchParams()
  const page = Math.max(1, Number(params.get('page') ?? '1') || 1)
  const [result, setResult] = useState<CustomerPage | null>(null)
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

    listCustomers(page, controller.signal)
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
      eyebrow="Clientes"
      title="Cadastro"
      lede="Nome, documento, e-mail e status de cada cliente."
      footerStart="Ficha cadastral"
      footerEnd={loading ? 'Consultando' : `${total} ${total === 1 ? 'cliente' : 'clientes'}`}
    >
      <div className="toolbar">
        <Link className="button" to="/clientes/novo">
          Novo cliente
        </Link>
      </div>
      {loading ? <p className="status">Carregando clientes.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {!loading && !error && result?.data.length === 0 ? (
        <p className="status">Nenhum cliente cadastrado.</p>
      ) : null}
      {!loading && !error && result && result.data.length > 0 ? (
        <ul className="records">
          {result.data.map((customer) => (
            <li key={customer.id}>
              <Link to={`/clientes/${customer.id}`}>{customer.name}</Link>
              <span>{customer.document}</span>
              <span className={customer.status === 'active' ? 'badge' : 'badge badge-inactive'}>
                {statusLabel[customer.status]}
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
