# Instruções para agentes

Leia este arquivo antes de alterar o projeto. O enunciado e o modo de executar estão no `README.md` da raiz.

## Forma do sistema

Há um monólito. A API Laravel fica em `backend`. A interface React, Vite e TypeScript fica em `frontend` e fala com a API por HTTP. Não separe o sistema em microsserviços e não acople a tela ao banco.

## Onde fica cada regra

- Juros, pagamento e relatório ficam em `backend/app/Billing`.
- A estratégia ativa vem de `BILLING_INTEREST` e do contrato `InterestStrategy`. Juros compostos e simples compartilham esse contrato. Não duplique a fórmula em controller, resource ou tela.
- Cobrança pendente calcula juros na leitura. Cobrança paga usa `interest_amount` e `amount_paid` gravados e não volta a acumular.
- “Vencida” não é um status gravado. É cobrança `pending` com vencimento anterior a hoje.
- Filtro, ordenação, página e totalizadores do relatório acontecem no banco. A exportação usa `cursor()` e ignora a página da tela.
- A expressão SQL dos juros mora na estratégia. Listagem, totais, CSV e PDF usam a mesma expressão.

## API e interface

- Todo endpoint novo ou alterado atualiza as anotações OpenAPI e o JSON em `backend/storage/api-docs/api-docs.json`.
- Controllers permanecem finos. Validação fica nos form requests. Respostas de erro continuam em português.
- A tela formata moeda. A API devolve decimais com ponto.
- Não adicione cadastro público, exclusão de cliente ou cobrança, papéis, cache, fila de exportação, CI ou observabilidade sem um pedido explícito.

## Verificação

- Testes da API: `composer test` dentro de `backend`. Eles usam SQLite em memória.
- Testes da interface: `npm run test:e2e` dentro de `frontend`.
- Uma mudança visível na tela precisa ser exercitada no navegador.
- Docker sobe com `docker compose up --build` na raiz. Não misture essa alteração com documentação nem com regra de negócio no mesmo commit.
