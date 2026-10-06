import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError } from '../api/client'
import { billingStatusLabel, formatDate, formatMoney, getBilling, type Billing } from '../api/billings'
import { clearNotice, readNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

export function BillingDetailPage() {
  const params = useParams()
  const billingId = Number(params.id)
  const [billing, setBilling] = useState<Billing | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [notice] = useState(readNotice)

  useEffect(() => {
    clearNotice()
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    getBilling(billingId, controller.signal)
      .then((next) => {
        if (!controller.signal.aborted) {
          setBilling(next)
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
  }, [billingId])

  return (
    <Sheet
      eyebrow="Cobranças"
      title={billing?.description ?? 'Cobrança'}
      footerStart="Ficha de cobrança"
      footerEnd={billing ? billingStatusLabel[billing.status] : 'Consultando'}
    >
      {notice ? (
        <p className="status" role="status">
          {notice}
        </p>
      ) : null}
      {loading ? <p className="status">Carregando cobrança.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {billing ? (
        <dl className="details">
          <div>
            <dt>Cliente</dt>
            <dd>
              <Link to={`/clientes/${billing.customer.id}`}>{billing.customer.name}</Link>
            </dd>
          </div>
          <div>
            <dt>Valor original</dt>
            <dd>{formatMoney(billing.original_amount)}</dd>
          </div>
          <div>
            <dt>Emissão</dt>
            <dd>{formatDate(billing.issued_on)}</dd>
          </div>
          <div>
            <dt>Vencimento</dt>
            <dd>{formatDate(billing.due_on)}</dd>
          </div>
          <div>
            <dt>Pagamento</dt>
            <dd>{billing.paid_on ? formatDate(billing.paid_on) : 'Ainda não pago'}</dd>
          </div>
          <div>
            <dt>Taxa mensal</dt>
            <dd>{billing.monthly_interest_rate}</dd>
          </div>
          <div>
            <dt>Status</dt>
            <dd>{billingStatusLabel[billing.status]}</dd>
          </div>
        </dl>
      ) : null}
      <div className="form-actions actions">
        {billing ? (
          <Link className="button" to={`/cobrancas/${billing.id}/editar`}>
            Editar
          </Link>
        ) : null}
        <Link className="button button-secondary" to="/cobrancas">
          Voltar
        </Link>
      </div>
    </Sheet>
  )
}
