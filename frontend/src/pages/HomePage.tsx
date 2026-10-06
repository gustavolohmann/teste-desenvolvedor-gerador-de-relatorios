import { useEffect, useState } from 'react'
import { useAuth } from '../auth/auth-context'
import { clearNotice, readNotice } from '../auth/session'
import { Sheet } from '../components/Sheet'

export function HomePage() {
  const { user } = useAuth()
  const [notice] = useState(readNotice)

  useEffect(() => {
    clearNotice()
  }, [])

  return (
    <Sheet
      eyebrow="Cobrança"
      title="Gerador de relatórios"
      lede={user ? `Olá, ${user.name}.` : undefined}
      footerStart="Via de conferência"
      footerEnd="Em operação"
    >
      {notice ? (
        <p className="status" role="status">
          {notice}
        </p>
      ) : null}
      <p className="lede">Clientes, cobranças e o relatório ficam no menu acima.</p>
    </Sheet>
  )
}