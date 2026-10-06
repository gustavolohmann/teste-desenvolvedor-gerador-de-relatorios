<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Relatórios', description: 'Relatório de faturamento')]
#[OA\Schema(
    schema: 'BillingReportTotals',
    required: ['count', 'original_amount', 'interest_amount', 'updated_amount', 'amount_paid', 'pending_amount'],
    properties: [
        new OA\Property(property: 'count', type: 'integer', example: 2),
        new OA\Property(property: 'original_amount', type: 'string', example: '300.00'),
        new OA\Property(property: 'interest_amount', type: 'string', example: '2.99'),
        new OA\Property(property: 'updated_amount', type: 'string', example: '302.99'),
        new OA\Property(property: 'amount_paid', type: 'string', example: '302.99'),
        new OA\Property(property: 'pending_amount', type: 'string', example: '0.00'),
    ],
    type: 'object',
)]
#[OA\Get(
    path: '/api/reports/billings',
    operationId: 'billingReport',
    summary: 'Relatório de cobranças por período',
    security: [['bearerAuth' => []]],
    tags: ['Relatórios'],
    parameters: [
        new OA\Parameter(name: 'period', in: 'query', required: true, description: 'Base do período', schema: new OA\Schema(type: 'string', enum: ['issued', 'due', 'paid'])),
        new OA\Parameter(name: 'from', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'to', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Pendente é a cobrança em aberto dentro do prazo. Vencida deriva de pendente com vencimento anterior a hoje.', schema: new OA\Schema(type: 'string', enum: ['pending', 'overdue', 'paid'])),
        new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['customer', 'description', 'issued_on', 'due_on', 'paid_on', 'status', 'original_amount', 'interest_amount', 'updated_amount', 'amount_paid'])),
        new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Página do relatório e totalizadores do filtro inteiro',
            content: new OA\JsonContent(
                required: ['data', 'meta', 'totals'],
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Billing')),
                    new OA\Property(
                        property: 'meta',
                        required: ['current_page', 'last_page', 'per_page', 'total'],
                        properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 2),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(property: 'totals', ref: '#/components/schemas/BillingReportTotals'),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Filtros inválidos'),
    ],
)]
#[OA\Get(
    path: '/api/reports/billings.csv',
    operationId: 'exportBillingReportCsv',
    summary: 'Exporta o relatório em CSV',
    description: 'Usa os mesmos filtros da consulta. O arquivo traz o período, os filtros, as cobranças e os totalizadores. A geração é em fluxo.',
    security: [['bearerAuth' => []]],
    tags: ['Relatórios'],
    parameters: [
        new OA\Parameter(name: 'period', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['issued', 'due', 'paid'])),
        new OA\Parameter(name: 'from', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'to', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'overdue', 'paid'])),
        new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['customer', 'description', 'issued_on', 'due_on', 'paid_on', 'status', 'original_amount', 'interest_amount', 'updated_amount', 'amount_paid'])),
        new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Arquivo CSV', content: new OA\MediaType(mediaType: 'text/csv')),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Filtros inválidos'),
    ],
)]
#[OA\Get(
    path: '/api/reports/billings.pdf',
    operationId: 'exportBillingReportPdf',
    summary: 'Exporta o relatório em PDF',
    description: 'Usa os mesmos filtros da consulta. O arquivo traz o período, os filtros, as cobranças e os totalizadores. As cobranças são lidas em cursor.',
    security: [['bearerAuth' => []]],
    tags: ['Relatórios'],
    parameters: [
        new OA\Parameter(name: 'period', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['issued', 'due', 'paid'])),
        new OA\Parameter(name: 'from', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'to', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'overdue', 'paid'])),
        new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['customer', 'description', 'issued_on', 'due_on', 'paid_on', 'status', 'original_amount', 'interest_amount', 'updated_amount', 'amount_paid'])),
        new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Arquivo PDF', content: new OA\MediaType(mediaType: 'application/pdf')),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Filtros inválidos'),
    ],
)]
class ReportDocumentation {}
