import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../api/client'
import {
  billingStatusLabel,
  createBilling,
  getBilling,
  listCustomerOptions,
  updateBilling,
  type BillingInput,
  type BillingStatus,
} from '../api/billings'
import type { Customer } from '../api/customers'
import { writeNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

const emptyForm: BillingInput = {
  customer_id: 0,
  description: '',
  original_amount: '',
  issued_on: '',
  due_on: '',
  paid_on: '',
  monthly_interest_rate: '0.02',
  status: 'pending',
}

export function BillingFormPage() {
  const params = useParams()
  const billingId = params.id ? Number(params.id) : null
  const isEdit = billingId !== null
  const navigate = useNavigate()
  const [form, setForm] = useState<BillingInput>(emptyForm)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [pinnedCustomer, setPinnedCustomer] = useState<Customer | null>(null)
  const [customerQuery, setCustomerQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    listCustomerOptions(controller.signal, customerQuery)
      .then((options) => {
        if (controller.signal.aborted) {
          return
        }

        setCustomers(options)

        if (billingId === null) {
          setForm((current) => ({
            ...current,
            customer_id: current.customer_id || options[0]?.id || 0,
          }))
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
  }, [billingId, customerQuery])

  useEffect(() => {
    if (billingId === null) {
      return
    }

    const controller = new AbortController()

    getBilling(billingId, controller.signal)
      .then((billing) => {
        if (controller.signal.aborted) {
          return
        }

        setPinnedCustomer({
          id: billing.customer.id,
          name: billing.customer.name,
          document: '',
          email: '',
          status: 'active',
        })
        setForm({
          customer_id: billing.customer_id,
          description: billing.description,
          original_amount: billing.original_amount,
          issued_on: billing.issued_on,
          due_on: billing.due_on,
          paid_on: billing.paid_on ?? '',
          monthly_interest_rate: billing.monthly_interest_rate,
          status: billing.status,
        })
        setLoading(false)
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

  const visibleCustomers =
    pinnedCustomer && !customers.some((customer) => customer.id === pinnedCustomer.id)
      ? [pinnedCustomer, ...customers]
      : customers

  function updateField<K extends keyof BillingInput>(field: K, value: BillingInput[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPending(true)
    setError(null)

    try {
      const billing = isEdit && billingId !== null ? await updateBilling(billingId, form) : await createBilling(form)
      writeNotice(isEdit ? 'Cobrança atualizada.' : 'Cobrança cadastrada.')
      navigate(`/cobrancas/${billing.id}`, { replace: true })
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'A API não respondeu.')
    } finally {
      setPending(false)
    }
  }

  return (
    <Sheet
      eyebrow="Cobranças"
      title={isEdit ? 'Editar cobrança' : 'Nova cobrança'}
      lede="A taxa mensal é decimal: 0,02 equivale a 2% ao mês."
      footerStart="Ficha de cobrança"
      footerEnd={pending ? 'Salvando' : 'Rascunho'}
    >
      {loading ? <p className="status">Carregando cobrança.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {!loading && visibleCustomers.length === 0 && customerQuery.trim() === '' ? (
        <p className="status">Cadastre um cliente antes de lançar a cobrança.</p>
      ) : null}
      {!loading && (visibleCustomers.length > 0 || customerQuery.trim() !== '') ? (
        <form className="form" onSubmit={handleSubmit}>
          <label className="field" htmlFor="customer_query">
            Buscar cliente
            <input
              id="customer_query"
              value={customerQuery}
              onChange={(event) => setCustomerQuery(event.target.value)}
              placeholder="Nome ou documento"
            />
          </label>
          <label className="field" htmlFor="customer_id">
            Cliente
            <select
              id="customer_id"
              name="customer_id"
              value={form.customer_id || ''}
              onChange={(event) => updateField('customer_id', Number(event.target.value))}
              required
            >
              {visibleCustomers.map((customer) => (
                <option key={customer.id} value={customer.id}>
                  {customer.name}
                </option>
              ))}
            </select>
          </label>
          <label className="field" htmlFor="description">
            Descrição
            <input
              id="description"
              name="description"
              value={form.description}
              onChange={(event) => updateField('description', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="original_amount">
            Valor original
            <input
              id="original_amount"
              name="original_amount"
              type="number"
              min="0.01"
              step="0.01"
              value={form.original_amount}
              onChange={(event) => updateField('original_amount', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="issued_on">
            Emissão
            <input
              id="issued_on"
              name="issued_on"
              type="date"
              value={form.issued_on}
              onChange={(event) => updateField('issued_on', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="due_on">
            Vencimento
            <input
              id="due_on"
              name="due_on"
              type="date"
              value={form.due_on}
              onChange={(event) => updateField('due_on', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="monthly_interest_rate">
            Taxa mensal
            <input
              id="monthly_interest_rate"
              name="monthly_interest_rate"
              type="number"
              min="0"
              max="1"
              step="0.000001"
              value={form.monthly_interest_rate}
              onChange={(event) => updateField('monthly_interest_rate', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="status">
            Status
            <select
              id="status"
              name="status"
              value={form.status}
              onChange={(event) => updateField('status', event.target.value as BillingStatus)}
            >
              {(Object.keys(billingStatusLabel) as BillingStatus[]).map((status) => (
                <option key={status} value={status}>
                  {billingStatusLabel[status]}
                </option>
              ))}
            </select>
          </label>
          {form.status === 'paid' ? (
            <label className="field" htmlFor="paid_on">
              Pagamento
              <input
                id="paid_on"
                name="paid_on"
                type="date"
                value={form.paid_on ?? ''}
                onChange={(event) => updateField('paid_on', event.target.value)}
                required
              />
            </label>
          ) : null}
          <div className="form-actions actions">
            <button className="button" type="submit" disabled={pending}>
              {pending ? 'Salvando.' : 'Salvar'}
            </button>
            <Link className="button button-secondary" to={isEdit && billingId !== null ? `/cobrancas/${billingId}` : '/cobrancas'}>
              Cancelar
            </Link>
          </div>
        </form>
      ) : null}
    </Sheet>
  )
}
