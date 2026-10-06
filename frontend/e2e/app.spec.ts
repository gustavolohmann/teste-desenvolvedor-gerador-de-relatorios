import { expect, test, type Page } from '@playwright/test'

function iso(offsetDays: number): string {
  const date = new Date()
  date.setHours(12, 0, 0, 0)
  date.setDate(date.getDate() + offsetDays)
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

async function login(page: Page): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('E-mail').fill('admin@example.com')
  await page.getByLabel('Senha').fill('password')
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByRole('status')).toHaveText('Sessão iniciada.')
}

test('visitante é levado ao login', async ({ page }) => {
  await page.goto('/clientes')

  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByRole('heading', { name: 'Entrar' })).toBeVisible()
})

test('credencial inválida permanece na entrada', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('E-mail').fill('admin@example.com')
  await page.getByLabel('Senha').fill('errada')
  await page.getByRole('button', { name: 'Entrar' }).click()

  await expect(page.getByRole('alert')).toHaveText('Credenciais inválidas.')
  await expect(page).toHaveURL(/\/login$/)
})

test('login e saída mostram o resultado da sessão', async ({ page }) => {
  await login(page)

  await expect(page.getByRole('heading', { name: 'Gerador de relatórios' })).toBeVisible()
  await expect(page.getByText('Olá, Administrador.')).toBeVisible()

  await page.getByRole('button', { name: 'Sair' }).click()

  await expect(page.getByRole('status')).toHaveText('Sessão encerrada.')
  await expect(page).toHaveURL(/\/login$/)
})

test('cadastra cliente e cobrança, registra o pagamento e exporta o relatório', async ({ page }) => {
  const stamp = Date.now().toString()
  const name = `Cliente ${stamp}`
  const description = `Parcela ${stamp}`
  const due = iso(-30)

  await login(page)
  await page.getByRole('link', { name: 'Clientes' }).click()
  await page.getByRole('link', { name: 'Novo cliente' }).click()
  await page.getByLabel('Nome').fill(name)
  await page.getByLabel('Documento').fill(stamp.slice(-11))
  await page.getByLabel('E-mail').fill(`${stamp}@example.com`)
  await page.getByRole('button', { name: 'Salvar' }).click()

  await expect(page.getByRole('status')).toHaveText('Cliente cadastrado.')
  await expect(page.getByRole('heading', { name })).toBeVisible()

  await page.getByRole('link', { name: 'Clientes' }).click()
  await expect(page.getByRole('link', { name })).toBeVisible()

  await page.getByRole('link', { name: 'Cobranças' }).click()
  await page.getByRole('link', { name: 'Nova cobrança' }).click()
  await page.getByRole('combobox', { name: 'Cliente', exact: true }).selectOption({ label: name })
  await page.getByLabel('Descrição').fill(description)
  await page.getByLabel('Valor original').fill('100')
  await page.getByLabel('Emissão').fill(iso(-40))
  await page.getByLabel('Vencimento').fill(due)
  await page.getByRole('button', { name: 'Salvar' }).click()

  await expect(page.getByRole('status')).toHaveText('Cobrança cadastrada.')
  const juros = page.locator('.details div').filter({ has: page.locator('dt', { hasText: /^Juros$/ }) })
  const atualizado = page.locator('.details div').filter({ has: page.locator('dt', { hasText: 'Valor atualizado' }) })
  const situacao = page.locator('.details div').filter({ has: page.locator('dt', { hasText: /^Status$/ }) })
  await expect(juros.locator('dd')).toHaveText(/R\$\s*2,00/)
  await expect(atualizado.locator('dd')).toHaveText(/R\$\s*102,00/)
  await expect(situacao.locator('dd')).toHaveText('Vencida')

  await page.getByRole('button', { name: 'Registrar pagamento' }).click()

  await expect(page.getByRole('status')).toHaveText('Pagamento registrado.')
  const pago = page.locator('.details div').filter({ has: page.locator('dt', { hasText: 'Valor pago' }) })
  await expect(pago.locator('dd')).toHaveText(/R\$\s*102,00/)
  await expect(page.getByRole('button', { name: 'Registrar pagamento' })).toHaveCount(0)

  await page.getByRole('link', { name: 'Relatório' }).click()
  await page.getByRole('textbox', { name: 'De', exact: true }).fill(due)
  await page.getByRole('textbox', { name: 'Até', exact: true }).fill(due)
  await page.getByRole('combobox', { name: 'Cliente', exact: true }).selectOption({ label: name })
  await page.getByLabel('Status').selectOption({ label: 'Paga' })

  await expect(page.getByRole('link', { name: description })).toBeVisible()
  await expect(page.locator('.totals')).toContainText('102,00')

  const csv = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exportar CSV' }).click()
  expect((await csv).suggestedFilename()).toMatch(/\.csv$/)
  await expect(page.getByRole('button', { name: 'Exportar CSV' })).toBeEnabled()

  const pdf = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exportar PDF' }).click()
  expect((await pdf).suggestedFilename()).toMatch(/\.pdf$/)
})
