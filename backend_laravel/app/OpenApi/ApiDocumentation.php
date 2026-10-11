<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// Describe the existing API contract; these methods document routes, not execute them.
#[OA\Info(title: 'MediCare API', version: '1.0.0', description: 'Employee, HR and Helpdesk APIs. Input amounts are whole TWD; record amounts ending in _cents are integer cents. Medication amounts are included in visit coverage.')]
#[OA\Server(url: '/api', description: 'Same-origin API through Vite or the production proxy')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Schema(schema: 'Error', type: 'object', properties: [
    new OA\Property(property: 'message', type: 'string'),
    new OA\Property(property: 'errors', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))),
])]
#[OA\Schema(schema: 'Login', type: 'object', required: ['email', 'password'], properties: [
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex@microprogram.employee'),
    new OA\Property(property: 'password', type: 'string', format: 'password'),
])]
#[OA\Schema(schema: 'Token', type: 'object', properties: [
    new OA\Property(property: 'access_token', type: 'string'),
    new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
])]
#[OA\Schema(schema: 'User', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'role', type: 'string', enum: ['employee', 'hr', 'helpdesk']),
    new OA\Property(property: 'company_id', type: 'integer', nullable: true),
])]
#[OA\Schema(schema: 'MedicationInput', type: 'object', required: ['name', 'covered_amount'], properties: [
    new OA\Property(property: 'name', type: 'string', maxLength: 255),
    new OA\Property(property: 'dosage', type: 'string', nullable: true, maxLength: 255),
    new OA\Property(property: 'instructions', type: 'string', nullable: true, maxLength: 2000),
    new OA\Property(property: 'covered_amount', type: 'integer', minimum: 0, maximum: 42949672, description: 'Whole TWD included in the visit total'),
])]
#[OA\Schema(schema: 'VisitInput', type: 'object', required: ['clinic', 'visited_on', 'covered_amount'], properties: [
    new OA\Property(property: 'clinic', type: 'string', maxLength: 255),
    new OA\Property(property: 'visited_on', type: 'string', format: 'date'),
    new OA\Property(property: 'covered_amount', type: 'integer', minimum: 0, maximum: 42949672, description: 'Whole TWD, including medications'),
    new OA\Property(property: 'diagnosis', type: 'string', nullable: true, maxLength: 255),
    new OA\Property(property: 'doctor_name', type: 'string', nullable: true, maxLength: 255),
    new OA\Property(property: 'doctor_title', type: 'string', nullable: true, maxLength: 255),
    new OA\Property(property: 'visited_time', type: 'string', nullable: true, pattern: '^([01][0-9]|2[0-3]):[0-5][0-9]$', example: '09:30'),
    new OA\Property(property: 'sick_leave_started_on', type: 'string', nullable: true, format: 'date', description: 'Provide both sick-leave dates; end must be on or after start'),
    new OA\Property(property: 'sick_leave_ended_on', type: 'string', nullable: true, format: 'date', description: 'Provide both sick-leave dates; end must be on or after start'),
    new OA\Property(property: 'medications', type: 'array', maxItems: 50, items: new OA\Items(ref: '#/components/schemas/MedicationInput')),
])]
#[OA\Schema(schema: 'Medication', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'visit_id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string', nullable: true),
    new OA\Property(property: 'dosage', type: 'string', nullable: true),
    new OA\Property(property: 'instructions', type: 'string', nullable: true),
    new OA\Property(property: 'covered_amount_cents', type: 'integer'),
])]
#[OA\Schema(schema: 'Visit', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'user_id', type: 'integer', nullable: true),
    new OA\Property(property: 'covered_amount_cents', type: 'integer'),
    new OA\Property(property: 'reviewed_by', type: 'integer', nullable: true),
    new OA\Property(property: 'clinic', type: 'string', nullable: true),
    new OA\Property(property: 'diagnosis', type: 'string', nullable: true),
    new OA\Property(property: 'doctor_name', type: 'string', nullable: true),
    new OA\Property(property: 'doctor_title', type: 'string', nullable: true),
    new OA\Property(property: 'visited_time', type: 'string', nullable: true),
    new OA\Property(property: 'reviewed_at', type: 'string', nullable: true),
    new OA\Property(property: 'sick_leave_started_on', type: 'string', nullable: true),
    new OA\Property(property: 'sick_leave_ended_on', type: 'string', nullable: true),
    new OA\Property(property: 'visited_on', type: 'string', format: 'date'),
    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected']),
    new OA\Property(property: 'medications', type: 'array', items: new OA\Items(ref: '#/components/schemas/Medication')),
    new OA\Property(property: 'user', type: 'object', nullable: true, description: 'Included for Helpdesk; employee and company details'),
])]
#[OA\Schema(schema: 'BenefitSummary', type: 'object', properties: [
    new OA\Property(property: 'year', type: 'integer'),
    new OA\Property(property: 'currency', type: 'string', example: 'TWD'),
    new OA\Property(property: 'plan_name', type: 'string'),
    new OA\Property(property: 'annual_limit', type: 'integer'),
    new OA\Property(property: 'used_amount', type: 'integer'),
    new OA\Property(property: 'remaining_balance', type: 'integer'),
])]
#[OA\Schema(schema: 'Employee', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'email', type: 'string', format: 'email'),
    new OA\Property(property: 'company_id', type: 'integer'),
])]
#[OA\Schema(schema: 'BenefitPlan', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'company_id', type: 'integer'),
    new OA\Property(property: 'annual_limit_cents', type: 'integer'),
])]
#[OA\Schema(schema: 'Assignment', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'user_id', type: 'integer'),
    new OA\Property(property: 'benefit_plan_id', type: 'integer'),
    new OA\Property(property: 'year', type: 'integer'),
])]
#[OA\Schema(schema: 'AssignmentInput', type: 'object', required: ['user_id', 'benefit_plan_id'], properties: [
    new OA\Property(property: 'user_id', type: 'integer'),
    new OA\Property(property: 'benefit_plan_id', type: 'integer'),
])]
#[OA\Schema(schema: 'ReviewInput', type: 'object', required: ['status'], properties: [
    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected']),
])]
class ApiDocumentation
{
    #[OA\Post(path: '/login', operationId: 'login', summary: 'Email/password login', tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Login')),
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Token')),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed or visit has no owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 429, description: 'Too many login attempts', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function login(): void {}

    #[OA\Get(path: '/me', operationId: 'me', summary: 'Current user', tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function me(): void {}

    #[OA\Post(path: '/logout', operationId: 'logout', summary: 'Revoke the current JWT', tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string')])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function logout(): void {}

    #[OA\Get(path: '/visits', operationId: 'visits', summary: 'List own visits', tags: ['Employee'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'visits', type: 'array', items: new OA\Items(ref: '#/components/schemas/Visit'))])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function visits(): void {}

    #[OA\Post(path: '/visits', operationId: 'submitVisit', summary: 'Submit own visit and medication breakdowns', tags: ['Employee'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VisitInput')),
        responses: [
            new OA\Response(response: 201, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'visit', ref: '#/components/schemas/Visit')])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed or visit has no owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function submitVisit(): void {}

    #[OA\Get(path: '/benefits', operationId: 'benefits', summary: 'Current-year benefits; only approved visits count', tags: ['Employee'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/BenefitSummary')),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Record or current-year assignment not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function benefits(): void {}

    #[OA\Get(path: '/hr/employees', operationId: 'employees', summary: 'List own-company employees', tags: ['HR'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'employees', type: 'array', items: new OA\Items(ref: '#/components/schemas/Employee'))])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function employees(): void {}

    #[OA\Get(path: '/hr/benefit-plans', operationId: 'plans', summary: 'List own-company plans', tags: ['HR'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'benefit_plans', type: 'array', items: new OA\Items(ref: '#/components/schemas/BenefitPlan'))])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function plans(): void {}

    #[OA\Post(path: '/hr/benefit-assignments', operationId: 'assign', summary: 'Assign or replace current-year own-company plan', tags: ['HR'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AssignmentInput')),
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'assignment', ref: '#/components/schemas/Assignment')])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Record or current-year assignment not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed or visit has no owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function assign(): void {}

    #[OA\Get(path: '/hr/employees/{employeeId}/visits', operationId: 'employeeVisits', summary: 'Read an own-company employee visit history', tags: ['HR'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'employeeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'employee', type: 'object', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')]), new OA\Property(property: 'visits', type: 'array', items: new OA\Items(ref: '#/components/schemas/Visit'))])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Record or current-year assignment not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function employeeVisits(): void {}

    #[OA\Get(path: '/helpdesk/visits', operationId: 'pending', summary: 'Pending visits across client companies', tags: ['Helpdesk'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'visits', type: 'array', items: new OA\Items(ref: '#/components/schemas/Visit'))])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function pending(): void {}

    #[OA\Patch(path: '/helpdesk/visits/{visitId}/status', operationId: 'review', summary: 'Review a pending visit and record reviewer/time', tags: ['Helpdesk'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'visitId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ReviewInput')),
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'visit', ref: '#/components/schemas/Visit')])),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Role or company access denied', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Record or current-year assignment not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Visit already processed', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed or visit has no owner', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function review(): void {}
}
