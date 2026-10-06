import type { ReactNode } from 'react'

type SheetProps = {
  eyebrow: string
  title: string
  lede?: string
  children?: ReactNode
  footerStart: string
  footerEnd: string
}

export function Sheet({ eyebrow, title, lede, children, footerStart, footerEnd }: SheetProps) {
  return (
    <main className="sheet">
      <p className="eyebrow">{eyebrow}</p>
      <h1>{title}</h1>
      {lede ? <p className="lede">{lede}</p> : null}
      {children}
      <footer className="slip">
        <span>{footerStart}</span>
        <span>{footerEnd}</span>
      </footer>
    </main>
  )
}
