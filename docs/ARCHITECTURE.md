# Arquitectura — Sumeria Collection Agent

## Visión general

El agente es un **servicio Laravel independiente** que se integra con el stack existente de Sumeria (AWS, scoring engine, MySQL). No es un módulo embebido en el monolito — se despliega separado, con su propia base de datos y sus propias credenciales de API.

## Principio central

La cobranza opera sobre un **mandato pre-autorizado al originar el préstamo**, no sobre negociación caso por caso. El comercio autoriza en el contrato que Sumeria retenga un % de sus ventas si entra en mora. Cuando se detecta la mora, el agente ejecuta — no pide permiso.

## Capas del sistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                        API Layer (Laravel)                           │
│  NegotiationPolicyController  │  CollectionCaseController            │
│  HoldbackMandateController    │  ShadowReviewController              │
│  ExceptionRequestController   │  Admin/LenderController              │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────────────┐
│                      Orchestration Layer                              │
│                                                                       │
│   ProcessCollectionCase (Job)                                         │
│         │                                                             │
│         ▼                                                             │
│   HoldbackOrchestratorService  ←──── PolicyEngineService             │
│         │                                                             │
│         ├── DecisionEngineService  ←── ScoringApiInterface           │
│         │        (probabilidad de recuperación + % holdback óptimo)  │
│         │                                                             │
│         ├── HoldbackExecutionEngine  ←── PaymentGatewayInterface     │
│         │        (activa/ajusta/cancela retención en pasarela)        │
│         │                                                             │
│         ├── ExceptionAgentService  ←── Anthropic API (Claude)        │
│         │        (evalúa solicitudes de excepción del comercio)       │
│         │                                                             │
│         ├── NotificationService  ←── WhatsApp Business API           │
│         │        (informa al comercio — no negocia)                   │
│         │                                                             │
│         └── EscalationNotificationService  ←── Webhook del lender    │
│                  (notifica al CRM del lender cuando escala)           │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────────────┐
│                       Persistence Layer                               │
│                                                                       │
│   holdback_mandates       negotiation_policies    lenders             │
│   collection_cases        holdback_adjustments    lender_webhook_configs│
│   exception_requests      audit_log (append-only) escalations         │
└─────────────────────────────────────────────────────────────────────┘
```

## Flujo completo de un caso

### 1. Origen del préstamo
```
Lender origina préstamo → POST /holdback-mandates
                       → comercio ya autorizó retención en contrato
                       → mandato queda registrado y activo
```

### 2. Detección de mora
```
Scoring de Sumeria detecta atraso → POST /collection-cases
                                  → caso creado (status=detected)
                                  → Job despachado a la cola
```

### 3. Decision Engine (async)
```
ProcessCollectionCase Job ejecuta:
  ├── Lee score del comercio (ya está en el caso)
  ├── Llama ScoringApiInterface:
  │     getAverageDailySales()  → flujo de ventas promedio
  │     getPaymentHistory()     → historial de pagos (on_time/late/default)
  │     getSalesTrend()         → tendencia de ventas (-1 a 1)
  │
  ├── Calcula recovery_probability (score + historial)
  │
  ├── Si prob < min_recovery_threshold → ESCALAR (high_risk_score)
  │
  ├── Calcula holdback_pct óptimo (deuda / flujo / urgencia / tendencia)
  │
  ├── Si estimated_recovery_days > max_recovery_extension_days → ESCALAR
  │
  └── Si shadow_mode=true:
  │     guarda shadow_recommendation → espera revisión humana
  └── Si shadow_mode=false:
        activa holdback vía HoldbackExecutionEngine
        notifica comercio vía WhatsApp
```

### 4. Ejecución de holdback (shadow_mode=false o aprobación humana)
```
HoldbackExecutionEngine.activate(case, pct):
  ├── Lee payment_channels del mandato
  ├── Para cada canal → gateway.activateHoldback()
  │     mercadopago → API de split de pagos de MercadoPago
  │     pos_x       → API del proveedor de POS
  └── Guarda gateway_holdback_id en el caso
```

### 5. El comercio pide excepción (vía WhatsApp)
```
WhatsApp webhook → POST /exception-requests
                → valida excepciones disponibles
                → ExceptionAgentService (Claude):
                │    system prompt: solo ve el rango disponible para ESE caso
                │    evalúa el mensaje en lenguaje natural
                │    devuelve JSON estructurado: approved/escalated + resolution
                ├── Si approved: ajusta holdback en pasarela + notifica
                └── Si escalated: webhook al lender + notifica comercio
```

### 6. Cierre
```
Deuda recuperada → POST /close
               → ExecutionEngine.cancel() → cancela retención en pasarela
               → NotificationService → WhatsApp: "saldo regularizado"
               → caso queda en closed_recovered
```

## Máquina de estados

```
detected ──────────────────────────────────────────────► escalated
   │                                                         ▲
   │ (Decision Engine)                                       │
   ▼                                                         │
holdback_active ──── holdback_adjusted ──────────────────────┤
   │    ▲                  │    ▲                            │
   │    │                  │    │                            │
   ▼    │                  ▼    │                            │
exception_pending ──────────────────────────────────────────►│
   │                                                         │
   └──────────────────► closed_recovered                     │
                                                             │
                     closed_default ◄──────────────────────┘
                     (solo vía escalación humana)
```

## Aislamiento multi-tenant

Cada institución financiera (`lender_id`) está completamente aislada:
- Tiene su propio API token (Sanctum)
- Sus políticas, mandatos y casos no son visibles para otras instituciones
- Ninguna query cruza `lender_id` entre instituciones
- El audit log es accesible solo por el lender dueño del caso

## Seguridad

| Garantía | Implementación |
|---|---|
| Sin retención sin mandato | `HoldbackExecutionEngine` y `CollectionCaseController` validan mandato activo antes de cualquier acción |
| Sin retención fuera de límites | `PolicyEngineService.assertHoldbackWithinLimits()` bloquea antes de ejecutar |
| LLM no puede saltarse la política | El system prompt del LLM solo ve el rango disponible para el caso, no los límites absolutos de la política |
| Audit trail inmutable | `AuditLog.save()` lanza `LogicException` si el registro ya existe |
| Comunicación whitelabel | `NotificationService` siempre usa el nombre del lender, nunca "Sumeria" |
| Webhooks firmados | `EscalationNotificationService` usa HMAC-SHA256 con el `webhook_secret` del lender |

## Dependencias externas

| Servicio | Uso | Implementación en local |
|---|---|---|
| Sumeria Scoring API | Lee flujo de ventas, historial de pagos, tendencia | `ScoringApiFake` (datos deterministas por merchant_id) |
| MercadoPago API | Activa/ajusta/cancela split de pagos | `FakeGateway` (no-op) |
| Anthropic API (Claude) | Evalúa solicitudes de excepción en lenguaje natural | Respuesta fake hardcodeada |
| WhatsApp Business API | Notificaciones al comercio | `Log::info()` en local |
| SQS (AWS) | Cola para `ProcessCollectionCase` Job | `sync` driver en local |

## Agregar un nuevo proveedor de pago

1. Crear `app/Services/Gateways/NuevoProveedorGateway.php` implementando `PaymentGatewayInterface`
2. Registrarlo en `HoldbackExecutionEngine::resolveGateways()`
3. Los comercios pueden entonces usar ese canal en sus `holdback_mandates.payment_channels`
