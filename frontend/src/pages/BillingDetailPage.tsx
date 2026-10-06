import { useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError } from '../api/client'
import { billingSituation, formatDate, formatMoney, getBilling, payBilling, type Billing } from '../api/billings'
import { clearNotice, readNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

function todayInput(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

export function BillingDetailPage() {
  const params = useParams()
  const billingId = Number(params.id)
  const [billing, setBilling] = useState<Billing | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [notice, setNotice] = useState(readNotice)
  const [paidOn, setPaidOn] = useState(todayInput)
  const [paying, setPaying] = useState(false)

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

  async function handlePayment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPaying(true)
    setError(null)

    try {
      const next = await payBilling(billingId, paidOn)
      setBilling(next)
      setNotice('Pagamento registrado.')
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'A API não respondeu.')
    } finally {
      setPaying(false)
    }
  }

  return (
    <Sheet
      eyebrow="Cobranças"
      title={billing?.description ?? 'Cobrança'}
      footerStart="Ficha de cobrança"
      footerEnd={billing ? billingSituation(billing) : 'Consultando'}
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
            <dt>Juros</dt>
            <dd>{formatMoney(billing.interest_amount)}</dd>
          </div>
          <div>
            <dt>Valor atualizado</dt>
            <dd>{formatMoney(billing.updated_amount)}</dd>
          </div>
          <div>
            <dt>Valor pago</dt>
            <dd>{billing.amount_paid ? formatMoney(billing.amount_paid) : 'Ainda não pago'}</dd>
          </div>
          <div>
            <dt>Dias em atraso</dt>
            <dd>{billing.days_overdue}</dd>
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
            <dd>{billingSituation(billing)}</dd>
          </div>
        </dl>
      ) : null}
      {billing && billing.status === 'pending' ? (
        <form className="form" onSubmit={handlePayment}>
          <label className="field" htmlFor="payment_paid_on">
            Data do pagamento
            <input
              id="payment_paid_on"
              name="paid_on"
              type="date"
              value={paidOn}
              onChange={(event) => setPaidOn(event.target.value)}
              required
            />
          </label>
          <div className="form-actions actions">
            <button className="button" type="submit" disabled={paying}>
              {paying ? 'Registrando.' : 'Registrar pagamento'}
            </button>
          </div>
        </form>
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
