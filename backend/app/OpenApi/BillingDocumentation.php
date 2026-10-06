<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Cobranças', description: 'Cadastro de cobranças')]
#[OA\Schema(
    schema: 'BillingCustomer',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Billing',
    required: ['id', 'customer_id', 'customer', 'description', 'original_amount', 'issued_on', 'due_on', 'paid_on', 'monthly_interest_rate', 'status', 'interest_amount', 'updated_amount', 'amount_paid', 'days_overdue', 'overdue'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'customer_id', type: 'integer', example: 1),
        new OA\Property(property: 'customer', ref: '#/components/schemas/BillingCustomer'),
        new OA\Property(property: 'description', type: 'string', example: 'Mensalidade de outubro'),
        new OA\Property(property: 'original_amount', type: 'string', example: '150.00'),
        new OA\Property(property: 'issued_on', type: 'string', format: 'date', example: '2026-10-01'),
        new OA\Property(property: 'due_on', type: 'string', format: 'date', example: '2026-10-10'),
        new OA\Property(property: 'paid_on', type: 'string', format: 'date', example: '2026-10-09', nullable: true),
        new OA\Property(property: 'monthly_interest_rate', type: 'string', example: '0.020000', description: 'Taxa decimal. 0.02 equivale a 2% ao mês.'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid'], example: 'pending'),
        new OA\Property(property: 'interest_amount', type: 'string', example: '2.00'),
        new OA\Property(property: 'updated_amount', type: 'string', example: '152.00'),
        new OA\Property(property: 'amount_paid', type: 'string', example: '152.00', nullable: true),
        new OA\Property(property: 'days_overdue', type: 'integer', example: 30),
        new OA\Property(property: 'overdue', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BillingPaymentInput',
    required: ['paid_on'],
    properties: [
        new OA\Property(property: 'paid_on', type: 'string', format: 'date', example: '2026-11-09'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BillingInput',
    required: ['customer_id', 'description', 'original_amount', 'issued_on', 'due_on', 'monthly_interest_rate', 'status'],
    properties: [
        new OA\Property(property: 'customer_id', type: 'integer', example: 1),
        new OA\Property(property: 'description', type: 'string', maxLength: 255, example: 'Mensalidade de outubro'),
        new OA\Property(property: 'original_amount', type: 'number', minimum: 0.01, example: 150),
        new OA\Property(property: 'issued_on', type: 'string', format: 'date', example: '2026-10-01'),
        new OA\Property(property: 'due_on', type: 'string', format: 'date', example: '2026-10-10'),
        new OA\Property(property: 'paid_on', type: 'string', format: 'date', nullable: true, example: null),
        new OA\Property(property: 'monthly_interest_rate', type: 'number', minimum: 0, maximum: 1, example: 0.02),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid'], example: 'pending'),
    ],
    type: 'object',
)]
#[OA\Get(
    path: '/api/billings',
    operationId: 'listBillings',
    summary: 'Lista cobranças',
    security: [['bearerAuth' => []]],
    tags: ['Cobranças'],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Página de cobranças',
            content: new OA\JsonContent(
                required: ['data', 'meta'],
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Billing')),
                    new OA\Property(
                        property: 'meta',
                        required: ['current_page', 'last_page', 'per_page', 'total'],
                        properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 1),
                        ],
                        type: 'object',
                    ),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(response: 401, description: 'Não autenticado'),
    ],
)]
#[OA\Post(
    path: '/api/billings',
    operationId: 'createBilling',
    summary: 'Cadastra uma cobrança',
    security: [['bearerAuth' => []]],
    tags: ['Cobranças'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BillingInput')),
    responses: [
        new OA\Response(response: 201, description: 'Cobrança cadastrada'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Dados inválidos'),
    ],
)]
#[OA\Get(
    path: '/api/billings/{billing}',
    operationId: 'showBilling',
    summary: 'Visualiza uma cobrança',
    security: [['bearerAuth' => []]],
    tags: ['Cobranças'],
    parameters: [
        new OA\Parameter(name: 'billing', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Cobrança encontrada'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 404, description: 'Registro não encontrado'),
    ],
)]
#[OA\Put(
    path: '/api/billings/{billing}',
    operationId: 'updateBilling',
    summary: 'Edita uma cobrança',
    security: [['bearerAuth' => []]],
    tags: ['Cobranças'],
    parameters: [
        new OA\Parameter(name: 'billing', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BillingInput')),
    responses: [
        new OA\Response(response: 200, description: 'Cobrança atualizada'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 404, description: 'Registro não encontrado'),
        new OA\Response(response: 422, description: 'Dados inválidos'),
    ],
)]
#[OA\Post(
    path: '/api/billings/{billing}/payment',
    operationId: 'payBilling',
    summary: 'Registra o pagamento e congela os juros',
    security: [['bearerAuth' => []]],
    tags: ['Cobranças'],
    parameters: [
        new OA\Parameter(name: 'billing', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BillingPaymentInput')),
    responses: [
        new OA\Response(response: 200, description: 'Pagamento registrado'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 404, description: 'Registro não encontrado'),
        new OA\Response(response: 422, description: 'Dados inválidos ou cobrança já paga'),
    ],
)]
class BillingDocumentation {}
