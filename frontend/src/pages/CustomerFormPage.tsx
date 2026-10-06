import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../api/client'
import {
  createCustomer,
  getCustomer,
  statusLabel,
  updateCustomer,
  type CustomerInput,
  type CustomerStatus,
} from '../api/customers'
import { writeNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

const emptyForm: CustomerInput = {
  name: '',
  document: '',
  email: '',
  status: 'active',
}

export function CustomerFormPage() {
  const params = useParams()
  const customerId = params.id ? Number(params.id) : null
  const isEdit = customerId !== null
  const navigate = useNavigate()
  const [form, setForm] = useState<CustomerInput>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (customerId === null) {
      return
    }

    const controller = new AbortController()

    getCustomer(customerId, controller.signal)
      .then((customer) => {
        if (controller.signal.aborted) {
          return
        }

        setForm({
          name: customer.name,
          document: customer.document,
          email: customer.email,
          status: customer.status,
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
  }, [customerId])

  function updateField<K extends keyof CustomerInput>(field: K, value: CustomerInput[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPending(true)
    setError(null)

    try {
      const customer = isEdit && customerId !== null ? await updateCustomer(customerId, form) : await createCustomer(form)
      writeNotice(isEdit ? 'Cliente atualizado.' : 'Cliente cadastrado.')
      navigate(`/clientes/${customer.id}`, { replace: true })
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'A API não respondeu.')
    } finally {
      setPending(false)
    }
  }

  return (
    <Sheet
      eyebrow="Clientes"
      title={isEdit ? 'Editar cliente' : 'Novo cliente'}
      lede="Informe nome, documento, e-mail e status."
      footerStart="Ficha cadastral"
      footerEnd={pending ? 'Salvando' : 'Rascunho'}
    >
      {loading ? <p className="status">Carregando cliente.</p> : null}
      {error ? (
        <p className="alert" role="alert">
          {error}
        </p>
      ) : null}
      {!loading ? (
        <form className="form" onSubmit={handleSubmit}>
          <label className="field" htmlFor="name">
            Nome
            <input id="name" name="name" value={form.name} onChange={(event) => updateField('name', event.target.value)} required />
          </label>
          <label className="field" htmlFor="document">
            Documento
            <input
              id="document"
              name="document"
              value={form.document}
              onChange={(event) => updateField('document', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="email">
            E-mail
            <input
              id="email"
              name="email"
              type="email"
              value={form.email}
              onChange={(event) => updateField('email', event.target.value)}
              required
            />
          </label>
          <label className="field" htmlFor="status">
            Status
            <select
              id="status"
              name="status"
              value={form.status}
              onChange={(event) => updateField('status', event.target.value as CustomerStatus)}
            >
              {(Object.keys(statusLabel) as CustomerStatus[]).map((status) => (
                <option key={status} value={status}>
                  {statusLabel[status]}
                </option>
              ))}
            </select>
          </label>
          <div className="form-actions actions">
            <button className="button" type="submit" disabled={pending}>
              {pending ? 'Salvando.' : 'Salvar'}
            </button>
            <Link className="button button-secondary" to={isEdit && customerId !== null ? `/clientes/${customerId}` : '/clientes'}>
              Cancelar
            </Link>
          </div>
        </form>
      ) : null}
    </Sheet>
  )
}
