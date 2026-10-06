import { useEffect, useState } from 'react'
import { getHealth } from './api/client'

type ConnectionState = 'loading' | 'ok' | 'error'

export default function App() {
  const [connection, setConnection] = useState<ConnectionState>('loading')

  useEffect(() => {
    const controller = new AbortController()

    getHealth(controller.signal)
      .then(() => {
        if (!controller.signal.aborted) {
          setConnection('ok')
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setConnection('error')
        }
      })

    return () => controller.abort()
  }, [])

  return (
    <main className="sheet">
      <p className="eyebrow">Cobrança</p>
      <h1>Gerador de relatórios</h1>
      <p className="lede">
        O faturamento fica disponível quando a API responde.
      </p>
      <p className="status" role="status">
        {connection === 'loading' && 'Consultando a API.'}
        {connection === 'ok' && 'API disponível.'}
        {connection === 'error' &&
          'A API não respondeu. Inicie o backend e atualize a página.'}
      </p>
      <footer className="slip">
        <span>Via de conferência</span>
        <span>{connection === 'ok' ? 'Em operação' : 'Aguardando API'}</span>
      </footer>
    </main>
  )
}
