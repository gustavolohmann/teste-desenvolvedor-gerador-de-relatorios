# Gerador de relatórios

Aplicação de faturamento com autenticação e relatório por período. O backend é um monólito Laravel. O frontend React conversa só pela API. O enunciado original do teste está no final deste arquivo.

## Executar

Na raiz do repositório:

```text
docker compose up --build
```

- Interface: http://localhost:5173
- API: http://localhost:8000
- Documentação OpenAPI: http://localhost:8000/api/documentation
- Acesso: `admin@example.com` / `password`

O Compose sobe MySQL 8.4, Redis, a API, o worker da fila e o frontend na rede `billing`. O banco `billing` é criado no container. Os dados ficam no volume `billing_mysql`. Os arquivos de relatório ficam no volume `billing_reports`, compartilhado pela API e pelo worker. Na primeira subida a API migra e grava o usuário de acesso. Não é preciso instalar PHP, Node ou MySQL na máquina.

As portas publicadas são 8000 (API), 5173 (interface) e `127.0.0.1:3306` (MySQL). Se 8000 ou 5173 já estiverem em uso, encerre o outro processo antes de subir o Compose. Se 3306 já estiver em uso, suba com outra porta:

```text
MYSQL_PUBLISH_PORT=3307 docker compose up --build
```

No PowerShell: `$env:MYSQL_PUBLISH_PORT='3307'; docker compose up --build`.

Para parar sem apagar os dados:

```text
docker compose down
```

`docker compose down -v` remove também o volume do MySQL.

Na primeira criação do volume, `backend/docker/mysql/init.sql` cria o banco `billing_test` e concede acesso ao usuário `billing`. Esse banco serve só à suíte MySQL e não recebe o seed da aplicação. Um volume que já existia antes desse script não o executa de novo; nesse caso o mesmo SQL precisa ser aplicado uma vez, como root do container.

A imagem da API instala só dependências de produção. O seeder de volume não usa Faker, então ele roda no container. Ele não entra no seed padrão. Rodar de novo acrescenta outro lote.

Volume pequeno, para conferir o fluxo:

```text
docker compose exec -e BILLING_VOLUME=100 -e BILLING_VOLUME_CUSTOMERS=10 backend php artisan db:seed --class=BillingVolumeSeeder
```

Volume significativo, o padrão da configuração (10.000 cobranças e 200 clientes):

```text
docker compose exec backend php artisan db:seed --class=BillingVolumeSeeder
```

Volume grande, para exercitar filtro, índice, CSV e PDF:

```text
docker compose exec -e BILLING_VOLUME=1000000 -e BILLING_VOLUME_CUSTOMERS=1000 backend php artisan db:seed --class=BillingVolumeSeeder
```

`BILLING_VOLUME` e `BILLING_VOLUME_CUSTOMERS` também podem ir no ambiente do serviço. No checkout local, com PHP e o `.env` apontando para o MySQL, o mesmo comando é `php artisan db:seed --class=BillingVolumeSeeder`.

A interface local usa `frontend/.env` com `VITE_API_URL=http://localhost:8000` e `npm run dev`.

## Testes

Os testes da API em SQLite usam memória e não dependem do Docker. Eles cobrem autenticação, juros, pagamento, filtros, totalizadores, PDF e CSV:

```text
cd backend
composer test
```

A suíte MySQL repete os mesmos testes contra o banco `billing_test`, inclusive `DATEDIFF`, `GREATEST`, `POW`, `CURDATE` e o bloqueio de pagamento concorrente. O Compose precisa estar no ar e a porta publicada precisa ser a do processo. O banco da aplicação (`billing`) não é apagado:

```text
cd backend
composer test:mysql
```

Se o MySQL do Compose não estiver em 3306, defina `DB_PORT` antes do comando. No PowerShell: `$env:DB_PORT='3307'; composer test:mysql`.

Os testes de interface usam Playwright e Chromium. Eles sobem uma API isolada em `127.0.0.1:8123` e o Vite em `127.0.0.1:5174`, então podem conviver com o ambiente em 8000 e 5173. É preciso PHP e Node na máquina:

```text
cd frontend
npm ci
npx playwright install chromium
npm run test:e2e
```

## Juros

A regra ativa é `BILLING_INTEREST` em `backend/config/billing.php`. O padrão é juros compostos. `simple` troca a estratégia. As duas implementam `App\Billing\InterestStrategy`. O cálculo fica só no backend. A listagem, os totalizadores, o CSV, o PDF, o detalhe da cobrança e o pagamento usam a mesma expressão SQL da estratégia ativa. Não há uma segunda fórmula em PHP. O arredondamento para centavos é o `ROUND(..., 2)` do banco. A diferença entre o valor atualizado e o original usa bcmath, sem float.

Juros compostos:

```text
valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
```

Juros simples:

```text
valor_atualizado = valor_original × (1 + taxa_mensal × dias_em_atraso / 30)
```

A taxa é uma fração: `0,02` representa 2% ao mês. O valor atualizado é arredondado para centavos. Os juros são a diferença para o valor original. Sem atraso, os juros são zero.

O status gravado é `pending` ou `paid`. “Vencida” é derivada: pendente com vencimento anterior a hoje. Enquanto a cobrança está pendente, os juros são calculados na leitura com a data da aplicação. O pagamento grava a data, o valor pago e os juros daquele momento. Uma cobrança paga não volta a acumular juros. Pagamento antecipado ou no vencimento grava juros zero. Pagamento atrasado grava os juros até a data informada.

O registro do pagamento trava a linha com `SELECT FOR UPDATE` dentro de uma transação. A primeira requisição conclui. Uma segunda tentativa simultânea espera o lock e, depois, recebe 422 porque a cobrança já está paga. Se a espera estourar o tempo do InnoDB, a API responde 422 pedindo para tentar de novo. Um segundo pagamento de uma cobrança já paga também responde 422.

## Decisões

- Autenticação por token Bearer do Laravel Sanctum. Não há cadastro público.
- Clientes e cobranças não têm exclusão. Documento do cliente é único.
- A regra de juros, o pagamento e a consulta do relatório ficam em `App\Billing`. Os controllers só recebem a requisição e devolvem o recurso.
- A API está descrita em classes OpenAPI dedicadas. O JSON versionado fica em `backend/storage/api-docs/api-docs.json`.
- A tela formata valores em real. A API, o CSV e o PDF usam decimais com ponto.
- O CSV usa `;` como separador e inclui BOM UTF-8. O PDF é A4 paisagem, gerado com Dompdf.
- A busca de cliente no formulário e no relatório consulta a API por nome ou documento. A lista não percorre todas as páginas.

## Índices

A migração `2026_10_06_050000_add_billing_report_indexes` cria três índices em `billings`:

| Índice | Colunas |
| --- | --- |
| `billings_issued_report_index` | `issued_on`, `customer_id`, `status` |
| `billings_due_report_index` | `due_on`, `customer_id`, `status` |
| `billings_paid_report_index` | `paid_on`, `customer_id`, `status` |

O relatório sempre recorta um intervalo de emissão, vencimento ou pagamento e pode restringir cliente e status. A data lidera o índice para o intervalo. Cliente e status completam os filtros de igualdade sem varrer a tabela. Há um índice para cada data porque cada execução usa só uma delas.

## Performance

A página do relatório tem 15 linhas por padrão. O cliente pode pedir `per_page` de 1 a 100; um valor fora disso é rejeitado. Filtro, ordenação e totalizadores rodam no MySQL. A página não soma os registros em PHP. O cliente é carregado junto com a cobrança, sem uma consulta por linha. A ordenação aceita só colunas da lista permitida. Ordenar por data e `id` pode usar `filesort` depois do recorte do índice: o intervalo já está indexado e a página devolve poucas linhas, então não há um segundo índice só para evitar esse `filesort`.

Com milhões de registros, o recorte por data usa o índice correspondente e a paginação devolve só a página pedida. Os totalizadores agregam o filtro inteiro numa consulta, sem materializar as linhas. Páginas muito profundas ficam mais caras com `OFFSET`. Em produção, a troca natural é paginação por cursor.

## Exportação

### Estratégia de geração

```text
Request → ReportJob → fila Redis → worker Laravel → lotes no MySQL → arquivo → Storage
```

O botão de exportar chama `POST /api/reports/export` e recebe `202` com o `job_id`. A requisição não monta o arquivo. Um worker executa `php artisan queue:work redis`, lê os filtros gravados no `report_jobs` e gera o CSV ou o PDF. A tela consulta `GET /api/reports/export/{id}` até o status `done` e então baixa o arquivo por um endpoint autenticado.

No checkout local, sem o Compose, o worker é o mesmo comando, com `QUEUE_CONNECTION=redis` e o Redis publicado em `127.0.0.1:6379`. Se essa porta já estiver em uso, suba com `REDIS_PUBLISH_PORT=6380` e aponte `REDIS_PORT` para ela. Os testes da API usam a fila `sync` e não dependem do Redis.

### Grandes volumes

A consulta do relatório continua no MySQL, com filtro, ordenação e os índices de data. A página da tela devolve 15 linhas. A exportação ignora essa página e percorre o filtro com `cursor()`, uma cobrança por vez. O resultado não é carregado com `get()`.

O CSV escreve cada linha com `fputcsv` enquanto o `cursor()` avança. O arquivo usa BOM UTF-8 e separador `;`, no formato que o Excel em português abre. O PDF também lê com `cursor()`, uma cobrança por vez, mas o Dompdf não transmite o documento página a página: ele monta o HTML e a árvore da tabela na memória do worker antes de gravar o arquivo. Não há teto artificial de linhas. O CSV é o formato para um volume realmente grande.

O worker do Compose usa a mesma imagem da API e só muda o processo. O PHP do worker tem 512 MB. Depois de cada job, se o processo passou de 480 MB, ele encerra e o Compose sobe outro. Assim um PDF pesado não deixa a fila parada. Uma geração medida de cerca de 51 mil linhas em CSV levou por volta de 9 segundos, e um PDF de 637 linhas levou por volta de 5 segundos dentro desses 512 MB. O timeout do job é 120 segundos, mais de dez vezes o pior caso medido. O `retry_after` do Redis é 150 segundos, acima desse timeout, para um segundo worker não pegar o mesmo job enquanto o primeiro ainda está gerando. São 3 tentativas, com 5 segundos entre elas. Se o processo morrer antes da última tentativa, o Redis devolve o job e o registro segue `processing` até o reprocessamento. Na última tentativa, um erro fatal grava `failed` mesmo quando o PHP não chega ao `catch`.

### Storage

O teste grava em `storage/app/reports/{id}.csv` ou `.pdf`, no disco `reports` do `Storage` do Laravel. API e worker compartilham esse diretório. A geração só conhece o disco configurado em `REPORTS_DISK`. Trocar esse disco para S3 depois não muda o job, o CSV nem o PDF.

### Limitações

Não há suporte infinito e o PDF não faz streaming ilimitado. O CSV cresce com o tempo de leitura do banco e o tamanho do arquivo, não com uma lista de registros na memória. O PDF depende do Dompdf: a partir de algumas centenas de linhas a tabela pode estourar os 512 MB do worker, e nesse caso o processamento termina como `failed` em vez de ficar preso. A aplicação não recusa um filtro por quantidade de linhas. Cada usuário só consulta e baixa os próprios processamentos. Um arquivo que sumiu do disco responde 404.

Outras melhorias deixadas de fora porque o teste não as pede: paginação por cursor no lugar de `OFFSET`, coluna gerada para o juros, réplica de leitura, cache de totalizadores, análise de `EXPLAIN` no ambiente de staging e desligar a regeneração do Swagger a cada requisição.

## Fora do escopo

Não há perfis de acesso, cache de relatório, pipeline de integração contínua nem observabilidade. A documentação da API e os testes de interface com Playwright estão incluídos.

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
