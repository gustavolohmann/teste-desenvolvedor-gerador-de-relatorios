<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Clientes', description: 'Cadastro de clientes')]
#[OA\Schema(
    schema: 'Customer',
    required: ['id', 'name', 'document', 'email', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
        new OA\Property(property: 'document', type: 'string', example: '12345678901'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria@example.com'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomerInput',
    required: ['name', 'document', 'email', 'status'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Maria Silva'),
        new OA\Property(property: 'document', type: 'string', maxLength: 32, example: '12345678901'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'maria@example.com'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
    ],
    type: 'object',
)]
#[OA\Get(
    path: '/api/customers',
    operationId: 'listCustomers',
    summary: 'Lista clientes',
    security: [['bearerAuth' => []]],
    tags: ['Clientes'],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        new OA\Parameter(name: 'q', in: 'query', required: false, description: 'Trecho do nome ou do documento', schema: new OA\Schema(type: 'string', maxLength: 100)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Página de clientes',
            content: new OA\JsonContent(
                required: ['data', 'meta'],
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Customer')),
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
    path: '/api/customers',
    operationId: 'createCustomer',
    summary: 'Cadastra um cliente',
    security: [['bearerAuth' => []]],
    tags: ['Clientes'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CustomerInput')),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Cliente cadastrado',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Dados inválidos'),
    ],
)]
#[OA\Get(
    path: '/api/customers/{customer}',
    operationId: 'showCustomer',
    summary: 'Visualiza um cliente',
    security: [['bearerAuth' => []]],
    tags: ['Clientes'],
    parameters: [
        new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Cliente encontrado',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 404, description: 'Registro não encontrado'),
    ],
)]
#[OA\Put(
    path: '/api/customers/{customer}',
    operationId: 'updateCustomer',
    summary: 'Edita um cliente',
    security: [['bearerAuth' => []]],
    tags: ['Clientes'],
    parameters: [
        new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CustomerInput')),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Cliente atualizado',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Customer'),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 404, description: 'Registro não encontrado'),
        new OA\Response(response: 422, description: 'Dados inválidos'),
    ],
)]
class CustomerDocumentation {}
