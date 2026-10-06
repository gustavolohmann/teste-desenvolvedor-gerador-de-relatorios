<?php

/*
| A estratégia ativa é BILLING_INTEREST: compound (padrão) ou simple.
| As duas implementações ficam em App\Billing e obedecem o mesmo contrato.
| Cobrança pendente e vencida calcula os juros na leitura, com a data da
| aplicação como referência. Cobrança paga usa o valor gravado no pagamento.
|
| Juros compostos:
| valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
|
| Juros simples:
| valor_atualizado = valor_original × (1 + taxa_mensal × dias_em_atraso / 30)
|
| O valor atualizado é arredondado para centavos. Os juros são a diferença
| para o valor original. Sem atraso, os juros são zero.
*/

return [
    'interest' => env('BILLING_INTEREST', 'compound'),
];
