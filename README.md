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

O Compose sobe MySQL 8.4, a API e o frontend na rede `billing`. O banco `billing` é criado no container. Os dados ficam no volume `billing_mysql`. Na primeira subida a API migra e grava o usuário de acesso. Não é preciso instalar PHP, Node ou MySQL na máquina.

As portas publicadas são 8000 e 5173. O MySQL não é publicado no host. Se 8000 ou 5173 já estiverem em uso, encerre o outro processo antes de subir o Compose.

Para parar sem apagar os dados:

```text
docker compose down
```

`docker compose down -v` remove também o volume do MySQL.

A imagem da API instala dependências de produção. O seeder de volume usa factories e precisa das dependências de desenvolvimento, então ele roda no checkout local:

```text
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=BillingVolumeSeeder
```

O padrão gera 10.000 cobranças e 200 clientes. `BILLING_VOLUME` e `BILLING_VOLUME_CUSTOMERS` alteram essas quantidades. Esse seeder não entra no seed padrão.

A interface local usa `frontend/.env` com `VITE_API_URL=http://localhost:8000` e `npm run dev`.

## Testes

Os testes da API usam SQLite em memória e não dependem do Docker:

```text
cd backend
composer test
```

Os testes de interface usam Playwright e Chromium. Eles sobem uma API isolada em `127.0.0.1:8123` e o Vite em `127.0.0.1:5174`, então podem conviver com o ambiente em 8000 e 5173. É preciso PHP e Node na máquina:

```text
cd frontend
npm ci
npx playwright install chromium
npm run test:e2e
```

## Juros

A regra ativa é `BILLING_INTEREST` em `backend/config/billing.php`. O padrão é juros compostos. `simple` troca a estratégia. As duas implementam `App\Billing\InterestStrategy`. O cálculo fica só no backend e a mesma expressão SQL alimenta a listagem, os totalizadores, o CSV e o PDF.

Juros compostos:

```text
valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
```

Juros simples:

```text
valor_atualizado = valor_original × (1 + taxa_mensal × dias_em_atraso / 30)
```

A taxa é uma fração: `0,02` representa 2% ao mês. O valor atualizado é arredondado para centavos. Os juros são a diferença para o valor original. Sem atraso, os juros são zero.

O status gravado é `pending` ou `paid`. “Vencida” é derivada: pendente com vencimento anterior a hoje. Enquanto a cobrança está pendente, os juros são calculados na leitura com a data da aplicação. O pagamento grava a data, o valor pago e os juros daquele momento. Uma cobrança paga não volta a acumular juros. Um segundo pagamento responde 422.

## Decisões

- Autenticação por token Bearer do Laravel Sanctum. Não há cadastro público.
- Clientes e cobranças não têm exclusão. Documento do cliente é único.
- A regra de juros, o pagamento e a consulta do relatório ficam em `App\Billing`. Os controllers só recebem a requisição e devolvem o recurso.
- A API está descrita em classes OpenAPI dedicadas. O JSON versionado fica em `backend/storage/api-docs/api-docs.json`.
- A tela formata valores em real. A API, o CSV e o PDF usam decimais com ponto.
- O CSV usa `;` como separador e inclui BOM UTF-8. O PDF é A4 paisagem, gerado com Dompdf.

## Índices

A migração `2026_10_06_050000_add_billing_report_indexes` cria três índices em `billings`:

| Índice | Colunas |
| --- | --- |
| `billings_issued_report_index` | `issued_on`, `customer_id`, `status` |
| `billings_due_report_index` | `due_on`, `customer_id`, `status` |
| `billings_paid_report_index` | `paid_on`, `customer_id`, `status` |

O relatório sempre recorta um intervalo de emissão, vencimento ou pagamento e pode restringir cliente e status. A data lidera o índice para o intervalo. Cliente e status completam os filtros de igualdade sem varrer a tabela. Há um índice para cada data porque cada execução usa só uma delas.

## Performance

A página do relatório tem 15 linhas. Filtro, ordenação e totalizadores rodam no MySQL. A página não soma os registros em PHP. O cliente é carregado junto com a cobrança, sem uma consulta por linha. A ordenação aceita só colunas da lista permitida.

Com milhões de registros, o recorte por data usa o índice correspondente e a paginação devolve só a página pedida. Os totalizadores agregam o filtro inteiro numa consulta, sem materializar as linhas. Páginas muito profundas ficam mais caras com `OFFSET`. Em produção, a troca natural é paginação por cursor.

O CSV percorre o resultado com `cursor()` e escreve cada linha na resposta. A página da tela é ignorada. O uso de memória no PHP acompanha uma linha, não o arquivo inteiro.

O PDF também lê com `cursor()`, mas o Dompdf monta o documento completo antes de enviá-lo. Ele serve para o período filtrado. Um arquivo com milhões de linhas não cabe nesse modelo. Em produção, a exportação grande iria para uma fila, em partes, ou o CSV seria o formato de volume.

Outras melhorias deixadas de fora porque o teste não as pede: coluna gerada para o juros, réplica de leitura, cache de totalizadores, análise de `EXPLAIN` no ambiente de staging e desligar a regeneração do Swagger a cada requisição.

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
