import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError } from '../api/client'
import { getCustomer, statusLabel, type Customer } from '../api/customers'
import { clearNotice, readNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

export function CustomerDetailPage() {
  const params = useParams()
  const customerId = Number(params.id)
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [notice] = useState(readNotice)

  useEffect(() => {
    clearNotice()
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    getCustomer(customerId, controller.signal)
      .then((next) => {
        if (!controller.signal.aborted) {
          setCustomer(next)
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
  }, [customerId])

  return (
    <Sheet
      eyebrow="Clientes"
      title={customer?.name ?? 'Cliente'}
      footerStart="Ficha cadastral"
      footerEnd={customer ? statusLabel[customer.status] : 'Consultando'}
    >
      {notice ? (
        <p className="status" role="status">
          {notice}
        </p>
      ) : null}
      {loading ? <p className="status">Carregando cliente.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {customer ? (
        <dl className="details">
          <div>
            <dt>Documento</dt>
            <dd>{customer.document}</dd>
          </div>
          <div>
            <dt>E-mail</dt>
            <dd>{customer.email}</dd>
          </div>
          <div>
            <dt>Status</dt>
            <dd>{statusLabel[customer.status]}</dd>
          </div>
        </dl>
      ) : null}
      <div className="form-actions actions">
        {customer ? (
          <Link className="button" to={`/clientes/${customer.id}/editar`}>
            Editar
          </Link>
        ) : null}
        <Link className="button button-secondary" to="/clientes">
          Voltar
        </Link>
      </div>
    </Sheet>
  )
}
