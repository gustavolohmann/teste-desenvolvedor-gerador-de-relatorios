<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Gerador de Relatórios',
    description: 'API de faturamento. Os registros exigem o token retornado no login.',
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Servidor local')]
#[OA\Tag(name: 'Sistema', description: 'Disponibilidade da API')]
#[OA\Tag(name: 'Autenticação', description: 'Login e sessão')]
#[OA\Get(
    path: '/api/health',
    operationId: 'health',
    summary: 'Consulta a disponibilidade da API',
    tags: ['Sistema'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'API disponível',
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'ok'),
                ],
                type: 'object',
            ),
        ),
    ],
)]
#[OA\Post(
    path: '/api/login',
    operationId: 'login',
    summary: 'Autentica um usuário',
    tags: ['Autenticação'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
            ],
            type: 'object',
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Sessão iniciada',
            content: new OA\JsonContent(
                required: ['token', 'data'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(
                        property: 'data',
                        required: ['id', 'name', 'email'],
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Administrador'),
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                        ],
                        type: 'object',
                    ),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(
            response: 401,
            description: 'Credenciais inválidas',
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Credenciais inválidas.'),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(
            response: 422,
            description: 'Dados inválidos',
            content: new OA\JsonContent(
                required: ['message', 'errors'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Informe o e-mail.'),
                    new OA\Property(
                        property: 'errors',
                        type: 'object',
                        example: ['email' => ['Informe o e-mail.']],
                    ),
                ],
                type: 'object',
            ),
        ),
    ],
)]
#[OA\Get(
    path: '/api/user',
    operationId: 'currentUser',
    summary: 'Retorna o usuário autenticado',
    security: [['bearerAuth' => []]],
    tags: ['Autenticação'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Usuário autenticado',
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        required: ['id', 'name', 'email'],
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Administrador'),
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                        ],
                        type: 'object',
                    ),
                ],
                type: 'object',
            ),
        ),
        new OA\Response(
            response: 401,
            description: 'Não autenticado',
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Não autenticado.'),
                ],
                type: 'object',
            ),
        ),
    ],
)]
#[OA\Post(
    path: '/api/logout',
    operationId: 'logout',
    summary: 'Encerra a sessão atual',
    security: [['bearerAuth' => []]],
    tags: ['Autenticação'],
    responses: [
        new OA\Response(response: 204, description: 'Sessão encerrada'),
        new OA\Response(
            response: 401,
            description: 'Não autenticado',
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Não autenticado.'),
                ],
                type: 'object',
            ),
        ),
    ],
)]
class ApiDocumentation {}
