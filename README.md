# Gerador de relatórios

Aplicação de faturamento com autenticação e relatório por período. O backend é um monólito Laravel. O frontend React conversa só pela API. O enunciado original do teste está no final deste arquivo.

Para usar o sistema basta Docker e Docker Compose. PHP, Composer, Node e MySQL na máquina só entram na seção de testes automatizados.

## Como executar

### 1. Pré-requisitos

- Docker
- Docker Compose

Portas usadas na máquina:

| Porta | Serviço |
| --- | --- |
| 5173 | Interface |
| 8000 | API |
| 3306 | MySQL, só em `127.0.0.1` |
| 6379 | Redis, só em `127.0.0.1` |

Se 3306 já estiver em uso, escolha outra porta publicada. No PowerShell:

```text
$env:MYSQL_PUBLISH_PORT='3307'
```

Se 6379 já estiver em uso:

```text
$env:REDIS_PUBLISH_PORT='6380'
```

Essas variáveis valem só para a publicação na máquina. Dentro da rede Docker o MySQL continua em `mysql:3306` e o Redis em `redis:6379`.

### 2. Clone

```text
git clone <url-do-repositorio>
cd <diretorio-do-repositorio>
```

Não é preciso copiar `.env` para subir o Compose. O `docker-compose.yml` já entrega as variáveis da API e do worker.

`backend/.env.example` e `frontend/.env.example` servem para quem for rodar PHP ou o Vite na máquina, apontando para a API em `http://localhost:8000` e para o MySQL publicado. O frontend do Compose recebe `VITE_API_URL=http://localhost:8000` no build.

### 3. Build e subida

Na raiz do repositório:

```text
docker compose up --build
```

O Compose sobe cinco serviços na rede `billing`:

| Serviço | Papel |
| --- | --- |
| `mysql` | MySQL 8.4. Cria o banco `billing`. Os dados ficam no volume `billing_mysql`. |
| `redis` | Fila da exportação. |
| `backend` | API Laravel na porta 8000. Na subida roda as migrations e o seed do usuário. |
| `queue` | Worker da mesma imagem da API. Gera CSV e PDF. |
| `frontend` | Interface na porta 5173. |

API e worker compartilham o volume `billing_reports` em `storage/app/reports`. A imagem da API instala só dependências de produção. O seeder de volume não usa Faker, então ele roda nesse container.

Na primeira criação do volume MySQL, `backend/docker/mysql/init.sql` cria o banco `billing_test` e concede acesso ao usuário `billing`. Esse banco serve só à suíte MySQL e não recebe o seed da aplicação. Um volume que já existia antes desse script não o executa de novo. Nesse caso, uma vez:

```text
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS billing_test; GRANT ALL PRIVILEGES ON billing_test.* TO 'billing'@'%'; FLUSH PRIVILEGES;"
```

### 4. Conferir que subiu

- Interface: http://localhost:5173
- API: http://localhost:8000/api/health deve responder `{"status":"ok"}`
- Documentação OpenAPI: http://localhost:8000/api/documentation

Para parar sem apagar os dados:

```text
docker compose down
```

`docker compose down -v` apaga também o volume do MySQL e os arquivos de relatório.

### 5. Credenciais

O seed da subida cria um único usuário, de forma idempotente. Entrar de novo não duplica.

- E-mail: `admin@example.com`
- Senha: `password`

Não há cadastro público. Sem token, a API responde 401 com a mensagem `Não autenticado.` A tela manda o visitante para `/login`.

### 6. Dados de demonstração

A subida deixa o banco com 1 usuário, 0 clientes e 0 cobranças. O volume fica num seeder separado, porque a subida roda o seed de novo a cada start do container e esse seeder acrescenta outro lote se for chamado outra vez.

Volume de demonstração, o padrão de `backend/config/billing.php`: 200 clientes e 10.000 cobranças.

```text
docker compose exec backend php artisan db:seed --class=BillingVolumeSeeder
```

A saída esperada é `Volume gerado: 200 clientes e 10000 cobranças.`

Conferência:

```text
docker compose exec mysql mysql -ubilling -psecret billing -e "SELECT COUNT(*) AS usuarios FROM users; SELECT COUNT(*) AS clientes FROM customers; SELECT status, COUNT(*) AS quantidade FROM billings GROUP BY status;"
```

Depois desse comando, numa base que ainda não tinha clientes `VOL`:

| Conjunto | Quantidade |
| --- | --- |
| Usuários | 1 |
| Clientes | 200 |
| Clientes ativos | 180 |
| Clientes inativos | 20 |
| Cobranças | 10.000 |
| Pendentes com vencimento futuro | 3.500 |
| Pendentes vencidas | 2.500 |
| Pagas no vencimento, juros zero | 2.500 |
| Pagas em atraso, juros gravados | 1.500 |

Os documentos começam em `VOL` seguido de 12 dígitos. Os nomes são `Cliente volume N`. Os e-mails são `volumeN@volume.test`. A taxa mensal alterna `0.01`, `0.02` e `0.03`. O valor original varia de `100.00` a `999.99`. As datas são relativas a hoje no container:

| Grupo | Emissão | Vencimento | Pagamento |
| --- | --- | --- | --- |
| Aberta | 10 dias atrás | 20 a 34 dias à frente | vazio |
| Vencida | 70 dias atrás | 30 a 49 dias atrás | vazio |
| Paga no vencimento | 8 dias antes do vencimento | 40 a 49 dias atrás | no vencimento |
| Paga em atraso | 12 dias antes do vencimento | 50 a 59 dias atrás | 5 dias atrás |

A inserção é em lotes de 500 linhas. O juros da cobrança paga em atraso é calculado no MySQL com a mesma expressão da estratégia ativa, entre o vencimento e a data de pagamento, e gravado em `interest_amount` e `amount_paid`. Rodar o comando de novo soma outro lote igual. Não apaga o anterior.

Volume pequeno, para repetir o fluxo sem esperar o lote de 10.000:

```text
docker compose exec -e BILLING_VOLUME=100 -e BILLING_VOLUME_CUSTOMERS=10 backend php artisan db:seed --class=BillingVolumeSeeder
```

Isso acrescenta 10 clientes e 100 cobranças: 35 abertas, 25 vencidas, 25 pagas no vencimento e 15 pagas em atraso.

Carga grande, para filtro, índice e CSV. Não rode isto só para abrir a tela. Um milhão de linhas leva tempo e disco:

```text
docker compose exec -e BILLING_VOLUME=1000000 -e BILLING_VOLUME_CUSTOMERS=1000 backend php artisan db:seed --class=BillingVolumeSeeder
```

São 1.000 clientes e 1.000.000 de cobranças, na mesma proporção: 350.000 abertas, 250.000 vencidas, 250.000 pagas no vencimento e 150.000 pagas em atraso. `BILLING_VOLUME` e `BILLING_VOLUME_CUSTOMERS` também podem ir no ambiente do serviço.

As factories em `backend/database/factories` alimentam os testes. O seeder de volume não as usa.

## Como usar

Entre em http://localhost:5173 com `admin@example.com` e `password`. O menu tem Início, Clientes, Cobranças e Relatório. Sair encerra o token e volta ao login com a mensagem `Sessão encerrada.`

### Clientes

Em Clientes, use Novo cliente. Os campos são nome, documento, e-mail e status (`active` ou `inactive` na API; Ativo ou Inativo na tela). Documento é único. Documento repetido responde 422: `Já existe um cliente com este documento.` A lista pagina 15 por página. Abrir um cliente mostra os dados. Editar fica na ficha do cliente. Não há exclusão.

### Cobranças

Em Cobranças, use Nova cobrança. Busque o cliente por nome ou documento: a lista pede no máximo 100 nomes à API pelo parâmetro `q`, em vez de percorrer todas as páginas. Informe descrição, valor original, emissão, vencimento, taxa mensal e status. A taxa é uma fração: `0.02` é 2% ao mês. O vencimento não pode ser anterior à emissão. Status pago exige data de pagamento. Status pendente não aceita data de pagamento. A lista pagina 15 por página. A ficha mostra juros, valor atualizado e, se estiver pendente, o formulário de pagamento.

### Pagamento e juros

A estratégia ativa é `BILLING_INTEREST`. O padrão do Compose é `compound`. `simple` troca a estratégia. As duas ficam em `App\Billing` e obedecem `InterestStrategy`.

Juros compostos, o padrão:

```text
valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
```

Juros simples:

```text
valor_atualizado = valor_original × (1 + taxa_mensal × dias_em_atraso / 30)
```

Os dias de atraso de uma cobrança pendente são a diferença entre hoje e o vencimento, nunca negativa. No MySQL isso é `GREATEST(DATEDIFF(CURDATE(), vencimento), 0)`. Sem atraso, os juros são zero. O valor atualizado é arredondado para centavos com `ROUND(..., 2)`. Os juros são a diferença para o valor original.

A listagem do relatório, os totalizadores, o CSV e o PDF usam essa expressão SQL. O detalhe da cobrança pendente e o registro do pagamento executam a mesma expressão no banco, para o centavo não divergir entre PHP e MySQL. A tela formata em real. A API, o CSV e o PDF usam ponto decimal.

Para ver o cálculo na interface, cadastre uma cobrança pendente de `100.00`, taxa `0.02`, emitida há 40 dias e vencida há 30 dias. O detalhe mostra juros `2.00` e valor atualizado `102.00`, porque 30/30 é um mês cheio em juros compostos.

Registrar o pagamento grava `paid_on`, `amount_paid` e `interest_amount` daquele momento. A partir daí o detalhe, o relatório e a exportação usam esses valores gravados. A cobrança não volta a acumular. Pagamento no vencimento ou antes grava juros zero. Pagamento atrasado grava os juros até a data informada.

O pagamento e a edição que marca a cobrança como paga travam a linha com `SELECT FOR UPDATE` dentro de uma transação. A primeira requisição conclui. Uma segunda tentativa simultânea espera o lock e, depois, recebe 422 porque a cobrança já está paga. Se a espera estourar o tempo do InnoDB, a API responde 422 pedindo para tentar de novo. Um segundo pagamento de uma cobrança já paga também responde 422: `A cobrança já está paga.`

O status gravado é `pending` ou `paid`. “Vencida” não é status gravado. É cobrança `pending` com vencimento anterior a hoje.

### Relatório

Abra Relatório. O filtro padrão é vencimento, do dia 1 do mês até hoje, 15 linhas, ordenado por vencimento crescente. As cobranças do seeder de volume ficam fora dessa janela curta. Para vê-las, use:

- Período por: Vencimento
- De: 70 dias antes de hoje
- Até: 40 dias depois de hoje

Essa janela cobre os quatro grupos do seeder. Status pode ser Todos, Pendente, Vencida ou Paga. Pendente na API é `pending` ainda não vencida. Vencida é `overdue` e continua `pending` no banco. Cliente: busque por nome ou documento e escolha um. `Cliente volume 1` isola um cliente do lote.

A ordenação é o clique no cabeçalho. As colunas aceitas são cliente, descrição, emissão, vencimento, status, original, juros, atualizado e pago. O primeiro clique ordena crescente. O segundo, decrescente. A API também aceita `paid_on`. A tela não oferece esse botão.

A página da tela tem 15 linhas. A API aceita `per_page` de 1 a 100. Fora disso responde 422. A tela não envia `per_page`, então o avaliador muda o tamanho pela API. Com o token do login:

```text
curl -s -H "Authorization: Bearer SEU_TOKEN" -H "Accept: application/json" "http://localhost:8000/api/reports/billings?period=due&from=2026-01-01&to=2026-12-31&per_page=2&page=1"
```

Troque as datas pela janela acima. `per_page=0` ou `per_page=101` deve falhar. A página 2 devolve as duas linhas seguintes e o mesmo `meta.total`.

Os totalizadores no topo são uma agregação do filtro inteiro, não da página visível. São seis números:

| Rótulo | Significado |
| --- | --- |
| Cobranças | Quantidade de linhas do filtro |
| Original | Soma de `original_amount` |
| Juros | Soma dos juros da estratégia, ou do valor gravado se a cobrança está paga |
| Atualizado | Soma de original mais juros |
| Recebido | Soma de `amount_paid`. Pendente entra como zero |
| Pendente | Soma do valor atualizado das cobranças que não estão pagas |

Mudar de página não muda esses totais. Restringir cliente ou status muda.

Filtro, ordenação, página e totais rodam no MySQL. A página carrega o cliente com `where id in (...)`, uma consulta para a página, não uma por linha. A exportação ignora a página.

## Exportação

O botão Exportar CSV ou Exportar PDF envia os filtros atuais, sem a página e sem `per_page`.

```text
Tela
 → POST /api/reports/export
 → 202 { job_id }
 → linha em report_jobs, status pending, payload só com filtros e formato
 → fila Redis
 → worker
 → cursor() no MySQL
 → arquivo em storage/app/reports/{id}.csv ou {id}.pdf
 → status done
 → GET /api/reports/export/{id}
 → GET /api/reports/export/{id}/download
```

A API responde 202 antes de gerar o arquivo. A tela consulta o status a cada 1 segundo, no máximo 120 vezes, e mostra `Gerando relatório...`. Com `done`, baixa o arquivo e mostra `Relatório pronto.` Com `failed`, mostra o erro gravado no job. Cada usuário só vê e baixa os próprios jobs. O job de outro usuário responde 404: `Registro não encontrado.`

O CSV percorre o filtro com `cursor()`, uma cobrança por vez, e grava cada linha com `fputcsv`. Não é `chunk()` e não é streaming HTTP. O arquivo leva BOM UTF-8 e separador `;`, para o Excel em português. Célula que começa com `=`, `+`, `-` ou `@` ganha um apóstrofo. No fim do arquivo estão os mesmos totalizadores do filtro. Uma geração medida de cerca de 51 mil linhas levou por volta de 9 segundos.

O PDF também lê com `cursor()`, mas o Dompdf monta o HTML e a tabela inteiros na memória do worker antes de gravar o arquivo. Não há teto artificial de linhas. Há um limite prático de memória. Um PDF de 637 linhas levou por volta de 5 segundos dentro de 512 MB. A partir de algumas centenas de linhas a tabela pode estourar essa memória. Nesse caso o job termina `failed`. O CSV é o formato para um volume realmente grande. Não descreva o PDF como streaming nem como ilimitado.

O worker do Compose é:

```text
php -d memory_limit=512M artisan queue:work redis --sleep=1 --tries=3 --backoff=5 --timeout=120 --memory=480
```

O timeout do job é 120 segundos. O `retry_after` do Redis é 150 segundos, acima do timeout, para outro worker não pegar o mesmo job enquanto o primeiro ainda gera. São 3 tentativas, com 5 segundos entre elas. Se o processo morrer antes da última tentativa, o Redis devolve o job e o registro segue `processing`. Na última tentativa, um erro fatal grava `failed` mesmo quando o PHP não chega ao `catch`. Depois de cada job, se o processo passou de 480 MB, o worker encerra e o Compose sobe outro, porque o serviço `queue` tem `restart: unless-stopped`.

O disco é o `reports` do Laravel, configurável por `REPORTS_DISK`. Um arquivo que sumiu do disco responde 404, não 500. Trocar o disco para S3 depois não muda o job, o CSV nem o PDF. Isso não está ligado neste teste: o arquivo fica no volume local `billing_reports`.

## Banco e índices

Migrations em `backend/database/migrations`. Tabelas de negócio:

- `users`, com token do Sanctum em `personal_access_tokens`
- `customers`: nome, documento único, e-mail, status
- `billings`: cliente, descrição, valor original, emissão, vencimento, pagamento, taxa, status, `interest_amount` e `amount_paid` preenchidos no pagamento
- `report_jobs`: usuário, tipo, status, payload JSON, caminho do arquivo, erro, tentativas

`billings.customer_id` referencia `customers` e impede apagar o cliente se houver cobrança. `report_jobs.user_id` referencia `users`.

A migração `2026_10_06_050000_add_billing_report_indexes` cria três índices em `billings`:

| Índice | Colunas | Consulta | Motivo |
| --- | --- | --- | --- |
| `billings_issued_report_index` | `issued_on`, `customer_id`, `status` | Relatório com período por emissão | A data lidera o intervalo. Cliente e status fecham a igualdade sem varrer a tabela. |
| `billings_due_report_index` | `due_on`, `customer_id`, `status` | Relatório com período por vencimento | Cada execução usa só uma data. Um índice por data evita escolher a coluna errada. |
| `billings_paid_report_index` | `paid_on`, `customer_id`, `status` | Relatório com período por pagamento | Mesma forma, para a terceira data. |

`report_jobs` tem índice em (`user_id`, `status`) para achar os jobs do usuário autenticado.

Ordenar a página por data e `id` pode usar `filesort` depois do recorte do índice. O intervalo já está indexado e a página devolve no máximo 100 linhas, então não há um segundo índice só para evitar esse `filesort`.

Com milhões de registros, o recorte por data usa o índice correspondente e a paginação devolve só a página pedida. Os totalizadores agregam o filtro inteiro numa consulta, sem materializar as linhas. A exportação percorre o filtro com `cursor()`. Páginas muito profundas ficam mais caras com `OFFSET`.

Para olhar o plano no MySQL do Compose, depois do volume de demonstração:

```text
docker compose exec mysql mysql -ubilling -psecret billing -e "EXPLAIN SELECT id FROM billings WHERE due_on BETWEEN CURDATE() - INTERVAL 70 DAY AND CURDATE() + INTERVAL 40 DAY ORDER BY due_on, id LIMIT 15;"
```

O esperado é o índice `billings_due_report_index` no recorte. `Using filesort` nessa ordenação é o comportamento deixado de propósito.

## Testes

Os testes da API em SQLite usam memória e não dependem do Docker. Eles cobrem autenticação, autorização do relatório e da exportação, juros, pagamento, filtros, paginação, `per_page`, totalizadores, PDF, CSV, job, arquivo ausente e o seeder de volume. É preciso PHP 8.3+ e Composer na máquina:

```text
cd backend
composer install
composer test
```

A suíte MySQL repete os testes contra o banco `billing_test`, inclusive `DATEDIFF`, `GREATEST`, `POW`, `CURDATE` e o bloqueio de pagamento concorrente. O Compose precisa estar no ar. O banco `billing` da aplicação não é apagado. Se o MySQL publicado não for 3306, defina `DB_PORT`. No PowerShell, com a porta 3307:

```text
cd backend
$env:DB_PORT='3307'
composer test:mysql
```

O arquivo é `backend/phpunit.mysql.xml`. Usuário `billing`, senha `secret`, banco `billing_test`.

Os testes de interface usam Playwright e Chromium. Eles sobem uma API isolada em `127.0.0.1:8123` e o Vite em `127.0.0.1:5174`, então podem conviver com o Compose em 8000 e 5173. É preciso Node na máquina:

```text
cd frontend
npm ci
npx playwright install chromium
npm run test:e2e
```

A suíte de interface cobre visitante levado ao login, credencial inválida, login e saída, e o fluxo de cadastrar cliente e cobrança, registrar pagamento e exportar o relatório. Essa exportação no teste usa fila `sync`, porque o script sobe a API sem Redis. No Compose a fila é Redis.

## Arquitetura

```text
Navegador → frontend (nginx) → API Laravel → MySQL
                                      ↘ Redis → worker → volume de relatórios
```

Não há microsserviço. Juros, pagamento e consulta do relatório ficam em `App\Billing`. Os controllers recebem a requisição validada e devolvem o recurso. A API está descrita em classes OpenAPI. O JSON versionado fica em `backend/storage/api-docs/api-docs.json`.

## Decisões

| Escolha | Motivo | Troca |
| --- | --- | --- |
| Sanctum com token Bearer | O enunciado pede login e API protegida, sem cadastro público | Não há sessão de browser na API |
| Juros compostos como padrão | É o exemplo do enunciado | `BILLING_INTEREST=simple` troca a estratégia sem duplicar a regra na tela |
| Juros pendentes calculados na leitura | O enunciado não exige gravar o valor enquanto a cobrança está aberta | Cada leitura recalcula com a data de hoje |
| Juros pagos gravados | Depois do pagamento o valor não pode continuar mudando | O histórico fica no registro, não numa tabela de lançamentos |
| “Vencida” derivada | Evita um status que ficaria desatualizado à meia-noite | O filtro `overdue` é `pending` com vencimento anterior a hoje |
| Página no MySQL, exportação com `cursor()` | A tela não pode carregar o filtro inteiro | `OFFSET` profundo continua caro |
| Fila Redis e worker separado | A geração não segura a requisição HTTP | Exige Redis e o serviço `queue` |
| CSV incremental e PDF com Dompdf | O enunciado pede os dois formatos | O PDF não escala como o CSV |
| Disco local `reports` | O teste não pede storage externo | Produção trocaria `REPORTS_DISK` |
| Uma imagem para API e worker | Os dois processos precisam do mesmo código | O worker só muda o comando de entrada |
| Seeder de volume sem Faker, em lotes de 500 | A imagem de produção não traz o Faker, e um milhão de models estouraria memória | Rodar de novo acrescenta outro lote |

## Limitações

- O PDF não faz streaming. Documentos grandes podem esgotar os 512 MB do worker e o job fica `failed`.
- Não há teto de linhas na aplicação. O limite prático do PDF é a memória do Dompdf.
- O arquivo do teste fica no volume local, não em storage externo.
- Há um worker. Não há escala horizontal nem fila extra.
- A paginação da tela usa `OFFSET`.
- A tela do relatório não tem controle de `per_page`. O padrão visível é 15. Outros tamanhos são pela API, de 1 a 100.
- Não há perfis de acesso, exclusão de cliente ou cobrança, cache de relatório, pipeline de integração contínua nem observabilidade.
- Os testes da API e do Playwright pedem PHP, Composer e Node na máquina. Subir e usar o sistema não pede.

## O que ficou para produção

Isto não está implementado:

- paginação por cursor no lugar de `OFFSET`
- coluna gerada para o juros
- réplica de leitura
- cache de totalizadores
- partição da tabela de cobranças
- storage externo para o arquivo
- outra engine de PDF que escreva página a página
- mais de um worker e filas separadas por formato
- observabilidade da fila
- desligar a regeneração do Swagger a cada requisição

## Agentes

O desenvolvimento usou o Cursor. As instruções para os agentes estão em `AGENTS.md`.

---

# Enunciado

# Teste Técnico — Desenvolvedor Fullstack

## Objetivo

Desenvolver uma aplicação simples de faturamento com autenticação e um módulo de relatórios preparado para trabalhar com grandes volumes de dados.

O objetivo do teste é avaliar organização de código, modelagem de banco de dados, performance, domínio de backend e frontend, aplicação de regras de negócio e capacidade de justificar decisões técnicas.

---

## Tecnologias obrigatórias

### Backend

* PHP
* Laravel

### Frontend

* React ou Next.js
* TypeScript

### Banco de dados

* MySQL

### Infraestrutura

* Docker
* Docker Compose

---

## Contexto do projeto

A aplicação será utilizada para registrar cobranças realizadas para clientes.

Cada cobrança deverá possuir informações como:

* Cliente
* Descrição
* Valor original
* Data de emissão
* Data de vencimento
* Data de pagamento
* Status
* Taxa de juros
* Valor final atualizado

O sistema deverá possuir autenticação. Apenas usuários autenticados poderão acessar os registros e os relatórios.

---

## Funcionalidades obrigatórias

### 1. Autenticação

O sistema deverá permitir:

* Login
* Logout
* Proteção das rotas do sistema
* Proteção dos endpoints da API

Não é necessário desenvolver cadastro público de usuários.

---

### 2. Gestão de clientes

O sistema deverá permitir:

* Cadastrar clientes
* Editar clientes
* Listar clientes
* Visualizar os dados de um cliente

Dados mínimos do cliente:

* Nome
* Documento
* E-mail
* Status

---

### 3. Gestão de cobranças

O sistema deverá permitir:

* Cadastrar cobranças
* Editar cobranças
* Listar cobranças
* Visualizar uma cobrança
* Registrar o pagamento de uma cobrança

Dados mínimos da cobrança:

* Cliente
* Descrição
* Valor original
* Data de emissão
* Data de vencimento
* Data de pagamento
* Taxa de juros mensal
* Status

---

## Regra de negócio — cálculo de juros

Quando uma cobrança estiver vencida e ainda não estiver paga, o sistema deverá calcular seu valor atualizado em tempo real.

O cálculo deverá considerar:

* Valor original
* Taxa de juros mensal da cobrança
* Quantidade de dias em atraso
* Data atual

O candidato poderá escolher entre juros simples ou juros compostos, desde que:

* A regra utilizada esteja documentada
* O cálculo seja realizado no backend
* O resultado seja consistente em todas as telas e relatórios
* O valor calculado não precise obrigatoriamente ser salvo no banco de dados

Exemplo utilizando juros compostos:

```text
valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
```

Ao registrar o pagamento, o sistema deverá armazenar:

* Data do pagamento
* Valor efetivamente pago
* Valor dos juros no momento do pagamento

---

## Módulo de relatórios

Desenvolver um relatório de faturamento por período.

O relatório deverá permitir filtros por:

* Data inicial
* Data final
* Cliente
* Status da cobrança

O usuário deverá conseguir escolher se o período será baseado em:

* Data de emissão
* Data de vencimento
* Data de pagamento

O relatório deverá exibir:

* Cliente
* Descrição da cobrança
* Data de emissão
* Data de vencimento
* Status
* Valor original
* Juros calculados
* Valor atualizado
* Valor pago

Também deverão ser exibidos totalizadores:

* Quantidade de cobranças
* Valor original total
* Total de juros
* Valor atualizado total
* Valor total recebido
* Valor total pendente

---

## Exportação dos relatórios

O relatório deverá poder ser exportado nos seguintes formatos:

* PDF
* CSV

As exportações deverão respeitar os filtros aplicados pelo usuário.

O arquivo exportado deverá conter:

* Período selecionado
* Filtros utilizados
* Dados do relatório
* Totalizadores

A solução adotada para geração dos relatórios deverá ser definida pelo candidato.

---

## Requisitos de performance

O módulo de relatórios deverá ser projetado considerando tabelas com milhões de registros.

A aplicação não precisa incluir milhões de registros no repositório, mas deverá possuir uma forma de gerar dados para testes.

Requisitos obrigatórios:

* Paginação realizada no backend
* Filtros realizados no banco de dados
* Ordenação realizada no backend
* Não carregar todos os registros em memória
* Evitar consultas N+1
* Criar índices adequados no banco de dados
* Utilizar migrations
* Disponibilizar factories ou seeders para gerar um volume significativo de dados
* Garantir que a exportação dos relatórios seja preparada para grandes volumes

O candidato deverá explicar no README:

* Quais índices foram criados
* Por que esses índices foram escolhidos
* Como o relatório se comportaria com milhões de registros
* Como a exportação em PDF e CSV se comportaria com grandes volumes
* Quais melhorias adicionais poderiam ser aplicadas em produção

---

## Frontend

O frontend deverá possuir, no mínimo:

* Tela de login
* Listagem de clientes
* Cadastro e edição de clientes
* Listagem de cobranças
* Cadastro e edição de cobranças
* Tela do relatório de faturamento
* Filtros do relatório
* Paginação
* Ordenação
* Exportação em PDF
* Exportação em CSV
* Estados de carregamento
* Tratamento de erros
* Feedback de operações realizadas com sucesso

A interface não precisa possuir um design avançado, mas deverá ser organizada, responsiva e componentizada.

---

## API

A comunicação entre frontend e backend deverá ocorrer por API.

A API deverá possuir:

* Validação das requisições
* Respostas HTTP adequadas
* Tratamento de erros
* Autenticação
* Paginação
* Filtros
* Ordenação
* Geração de relatórios em PDF
* Geração de relatórios em CSV

A estrutura e o padrão dos endpoints ficam a critério do candidato.

---

## Dockerização

O projeto deverá ser completamente executável por Docker.

A estrutura deverá incluir, no mínimo:

* Serviço do backend
* Serviço do frontend
* Serviço do MySQL
* Arquivo `docker-compose.yml`
* Configurações necessárias para comunicação entre os serviços
* Persistência dos dados do banco
* Instruções para subir o ambiente

O projeto deverá poder ser iniciado com poucos comandos, sem necessidade de configurar manualmente PHP, Node.js ou MySQL na máquina local.

---

## Testes automatizados

O projeto deverá possuir testes automatizados no backend.

Cenários mínimos:

* Usuário não autenticado não acessa o relatório
* Usuário não autenticado não exporta relatórios
* Cálculo de juros para cobrança vencida
* Cobrança paga não continua acumulando juros
* Filtros do relatório
* Totalizadores do relatório
* Registro de pagamento
* Exportação do relatório em PDF
* Exportação do relatório em CSV

Testes no frontend serão considerados um diferencial.

---

## Uso de inteligência artificial

O uso de ferramentas de inteligência artificial durante o desenvolvimento é permitido, mas não obrigatório.

Caso sejam utilizadas ferramentas de IA, as configurações, instruções ou arquivos utilizados para orientar os agentes deverão ser mantidos dentro do repositório do projeto.

Também será avaliada a forma como o candidato utiliza e configura agentes de IA no processo de desenvolvimento.

Boas práticas no uso serão consideradas de forma positiva.

---

## Organização dos commits

O desenvolvimento deverá ser realizado com commits pequenos, semânticos e separados por responsabilidade.

Evite concentrar toda a implementação em poucos commits grandes.

Exemplos:

```text
feat: add authentication structure
feat: create customers module
feat: create billing module
feat: add overdue interest calculation
feat: create billing report filters
feat: add csv report export
feat: add pdf report export
test: add billing interest tests
chore: add docker environment
docs: update project instructions
```

Os commits também serão considerados durante a avaliação.

---

## Entrega

O candidato deverá realizar a entrega seguindo obrigatoriamente este fluxo:

1. Criar um **fork** do repositório disponibilizado para o teste.
2. Criar uma nova branch dentro do fork utilizando o próprio nome.

Exemplo:

```text
joao-silva
```

3. Desenvolver toda a solução nessa branch.
4. Manter o histórico de commits pequenos, semânticos e separados por responsabilidade.
5. Ao finalizar, abrir um **Pull Request da branch criada no fork para o repositório original do teste**.

Exemplo do fluxo:

```text
fork-do-candidato:joao-silva
    ↓
repositorio-original:main
```

O Pull Request deverá conter:

* Título claro e objetivo
* Resumo da solução desenvolvida
* Instruções para executar o projeto
* Instruções para executar os testes
* Explicação das decisões técnicas
* Explicação da estratégia de performance
* Explicação da geração dos relatórios
* Pontos que não foram concluídos, caso existam

O repositório deverá conter:

* Código do backend
* Código do frontend
* Dockerfiles
* Arquivo `docker-compose.yml`
* Migrations
* Factories e seeders
* Testes automatizados
* Arquivo `.env.example`
* Instruções para executar o projeto
* Instruções para executar os testes
* Explicação das decisões técnicas
* Explicação da estratégia de performance

Não serão aceitas entregas por arquivo compactado, e-mail, link para outro repositório ou qualquer outro meio externo.

A entrega deverá ser realizada exclusivamente por meio do Pull Request aberto a partir do fork do candidato para o repositório original disponibilizado para o teste.

---

## Critérios de avaliação

Serão avaliados:

* Organização e legibilidade do código
* Arquitetura da aplicação
* Modelagem do banco de dados
* Qualidade da API
* Componentização do frontend
* Uso correto do TypeScript
* Aplicação da regra de negócio
* Performance das consultas
* Estratégia de geração dos relatórios
* Segurança e autenticação
* Dockerização do projeto
* Qualidade dos testes automatizados
* Tratamento de erros
* Documentação
* Histórico de commits
* Qualidade e organização do Pull Request
* Configuração e uso de agentes de IA, caso utilizados

---

## Diferenciais

Serão considerados diferenciais:

* Testes no frontend
* Controle de acesso por perfil
* Documentação da API
* Uso de ferramentas de análise de consultas
* Estratégia para geração de relatórios muito grandes
* Cache de relatórios ou totalizadores
* Pipeline de integração contínua
* Monitoramento ou observabilidade
* Cobertura de testes documentada

---

## Prazo sugerido

Prazo de entrega sugerido: até 5 dias corridos.

O teste foi planejado para exigir aproximadamente 8 a 12 horas de desenvolvimento.

Não é necessário implementar funcionalidades além das solicitadas. O foco deve estar na qualidade da solução, nas decisões técnicas e na clareza da implementação.
