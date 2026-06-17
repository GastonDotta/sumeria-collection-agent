# Sumeria Collection Agent

Servicio autónomo de cobranza por retención automática (holdback) sobre flujo transaccional. Actúa en nombre de instituciones financieras (lenders) sobre comercios morosos, ejecutando un mandato pre-autorizado al momento de originar el préstamo — no negocia consentimiento caso por caso.

## Documentación

| Documento | Descripción |
|-----------|-------------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Arquitectura, capas, flujo completo, máquina de estados, seguridad |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Instalación, variables de entorno, onboarding de lenders, checklist pre-producción |
| [docs/openapi.yaml](docs/openapi.yaml) | Especificación OpenAPI 3.1 de todos los endpoints |
| [../agente-cobranza-autonomo.md](../agente-cobranza-autonomo.md) | Producto y negocio |
| [../agente-cobranza-autonomo-tecnico.md](../agente-cobranza-autonomo-tecnico.md) | Especificación técnica completa |
| [../agente-cobranza-autonomo-legal.md](../agente-cobranza-autonomo-legal.md) | Marco legal y compliance |

## Inicio rápido (local)

```bash
composer install
cp .env.example .env && php artisan key:generate
# configurar DB_* en .env
php artisan migrate
php artisan db:seed --class=PilotLenderSeeder
php artisan serve
```

En local todas las integraciones externas están en modo fake (scoring, MercadoPago, WhatsApp, Claude). Ninguna llamada HTTP real se realiza.

## Tests

```bash
php artisan test
```

## Estructura del proyecto

```
app/
  Contracts/
    PaymentGatewayInterface.php   ← contrato de pasarelas de pago
    ScoringApiInterface.php       ← contrato de lectura del scoring de Sumeria

  Models/
    Lender.php                    ← institución financiera (con Sanctum tokens)
    LenderWebhookConfig.php       ← webhook de escalaciones por lender
    HoldbackMandate.php           ← mandato autorizado al originar el préstamo
    NegotiationPolicy.php         ← política de holdback por lender
    CollectionCase.php            ← caso de cobranza individual
    HoldbackAdjustment.php        ← historial de ajustes del % de retención
    ExceptionRequest.php          ← solicitudes de excepción del comercio
    AuditLog.php                  ← log inmutable (append-only)
    Escalation.php                ← casos derivados a humano

  Services/
    PolicyEngineService.php           ← valida límites de holdback
    HoldbackMandateService.php        ← registro y consulta de mandatos
    DecisionEngineService.php         ← calcula probabilidad + % holdback óptimo
    HoldbackOrchestratorService.php   ← máquina de estados del caso
    HoldbackExecutionEngine.php       ← activa/ajusta/cancela en la pasarela real
    ExceptionAgentService.php         ← LLM (Claude) evalúa excepciones del comercio
    NotificationService.php           ← WhatsApp: informa al comercio (no negocia)
    EscalationNotificationService.php ← webhook al CRM del lender al escalar
    AuditLogService.php               ← escritura al log inmutable
    ScoringApiClient.php              ← consumo del API interno de Sumeria
    ScoringApiFake.php                ← fake para local/tests
    Gateways/
      MercadoPagoGateway.php          ← split de pagos MercadoPago
      FakeGateway.php                 ← fake para local/tests

  Jobs/
    ProcessCollectionCase.php     ← despacha Decision Engine + Orchestrator (async)

  Http/Controllers/Api/V1/
    Admin/
      LenderController.php        ← CRUD lenders + tokens (Sumeria admin)
    NegotiationPolicyController.php
    HoldbackMandateController.php
    CollectionCaseController.php
    ShadowReviewController.php    ← revisión humana en modo piloto
    ExceptionRequestController.php

database/migrations/              ← 13 migraciones en orden de dependencias
database/seeders/
  PilotLenderSeeder.php           ← crea lender piloto con política y token

docs/
  openapi.yaml                    ← OpenAPI 3.1 spec completa
  ARCHITECTURE.md
  DEPLOYMENT.md
```

## Endpoints disponibles

### Admin (requiere token `sumeria-admin`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/admin/lenders` | Listar instituciones |
| POST | `/api/v1/admin/lenders` | Crear institución |
| GET | `/api/v1/admin/lenders/{id}` | Detalle de institución |
| POST | `/api/v1/admin/lenders/{id}/tokens` | Emitir API token |
| PUT | `/api/v1/admin/lenders/{id}/webhook` | Configurar webhook de escalaciones |
| DELETE | `/api/v1/admin/lenders/{id}` | Desactivar institución |

### Self-service del lender (requiere token de la institución)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/v1/lenders/{id}/negotiation-policy` | Configurar política de holdback |
| GET | `/api/v1/lenders/{id}/negotiation-policy` | Ver política activa |
| POST | `/api/v1/lenders/{id}/holdback-mandates` | Registrar mandato al originar préstamo |
| GET | `/api/v1/lenders/{id}/holdback-mandates/{mid}` | Ver mandato |
| POST | `/api/v1/collection-cases` | Webhook de detección de mora (desde scoring) |
| GET | `/api/v1/collection-cases/{id}` | Estado del caso |
| POST | `/api/v1/collection-cases/{id}/close` | Cerrar caso (recuperado) |
| POST | `/api/v1/collection-cases/{id}/escalate` | Escalar a humano |
| GET | `/api/v1/lenders/{id}/shadow-reviews` | Casos pendientes de revisión (modo piloto) |
| POST | `/api/v1/collection-cases/{id}/shadow-reviews/approve` | Aprobar recomendación |
| POST | `/api/v1/collection-cases/{id}/shadow-reviews/reject` | Rechazar recomendación |
| POST | `/api/v1/collection-cases/{id}/exception-requests` | Solicitud de excepción del comercio |
