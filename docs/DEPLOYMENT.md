# Guía de Despliegue — Sumeria Collection Agent

## Requisitos

- PHP 8.2+
- Composer
- MySQL 8.0+
- (Producción) AWS SQS para la cola de jobs
- (Producción) WhatsApp Business API verificada (Meta)
- (Producción) Anthropic API key (Claude Sonnet)
- (Producción) Credenciales de MercadoPago / pasarelas de pago del piloto

---

## Instalación local (desarrollo)

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Crear archivo de entorno
cp .env.example .env
php artisan key:generate

# 3. Configurar base de datos en .env
#    DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 4. Correr migraciones
php artisan migrate

# 5. Crear lender piloto y obtener su token
php artisan db:seed --class=PilotLenderSeeder

# 6. Levantar el servidor
php artisan serve
```

El servicio queda disponible en `http://localhost:8000`.
En local, todas las integraciones externas (WhatsApp, MercadoPago, Anthropic, scoring) están en modo fake — ninguna llamada HTTP real se realiza.

---

## Variables de entorno por ambiente

### Desarrollo (`.env`)

```env
APP_ENV=local
QUEUE_CONNECTION=sync        # Jobs corren sincrónicamente, sin worker
DB_CONNECTION=mysql
DB_DATABASE=sumeria_collection
```

### Staging / Producción

```env
APP_ENV=production
QUEUE_CONNECTION=sqs
SQS_KEY=...
SQS_SECRET=...
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/ACCOUNT
SQS_QUEUE=sumeria-collection-jobs

SUMERIA_SCORING_API_URL=https://api-interna.sumeria.io
SUMERIA_SCORING_WEBHOOK_SECRET=...

MERCADOPAGO_ACCESS_TOKEN=...

WA_API_URL=https://graph.facebook.com/v19.0
WA_API_TOKEN=...
WA_PHONE_NUMBER_ID=...

ANTHROPIC_API_KEY=...
```

---

## Onboarding de un nuevo lender

El flujo completo para dar de alta una institución financiera es self-service desde el Sprint 9:

```bash
# 1. Crear el lender (Sumeria admin)
curl -X POST https://collection.sumeria.io/api/v1/admin/lenders \
  -H "Authorization: Bearer {SUMERIA_ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"name":"Banco XYZ","slug":"banco-xyz","jurisdiction":"UY","contact_email":"tech@xyz.uy"}'

# 2. Emitir token para el lender (Sumeria admin)
curl -X POST https://collection.sumeria.io/api/v1/admin/lenders/{ID}/tokens \
  -H "Authorization: Bearer {SUMERIA_ADMIN_TOKEN}" \
  -d '{"token_name":"produccion-v1"}'
# → guardar el token, solo se muestra una vez

# 3. Configurar webhook (Sumeria admin)
curl -X PUT https://collection.sumeria.io/api/v1/admin/lenders/{ID}/webhook \
  -H "Authorization: Bearer {SUMERIA_ADMIN_TOKEN}" \
  -d '{"escalation_webhook_url":"https://crm.xyz.uy/webhook","webhook_secret":"..."}'

# 4. El lender configura su política de holdback (self-service)
curl -X POST .../api/v1/lenders/{ID}/negotiation-policy \
  -H "Authorization: Bearer {LENDER_TOKEN}" \
  -d '{
    "min_holdback_pct": 0.05,
    "max_holdback_pct": 0.20,
    "max_default_rate": 0.015,
    "max_recovery_extension_days": 90,
    "max_exception_requests": 3,
    "min_recovery_threshold": 0.40,
    "contact_hours_start": "09:00",
    "contact_hours_end": "20:00",
    "jurisdiction": "UY",
    "shadow_mode": true
  }'

# 5. Al originar cada préstamo, el lender registra el mandato del comercio
curl -X POST .../api/v1/lenders/{ID}/holdback-mandates \
  -H "Authorization: Bearer {LENDER_TOKEN}" \
  -d '{
    "merchant_id": 4521,
    "loan_id": 7710,
    "authorized_max_holdback_pct": 0.20,
    "payment_channels": ["mercadopago"],
    "contract_clause_ref": "anexo-3.2",
    "signed_at": "2026-01-15T10:00:00Z"
  }'
```

---

## Checklist pre-producción (por país)

### Técnico
- [ ] `shadow_mode=true` en la política del lender piloto — nunca activar holdback real sin revisión humana en la primera semana
- [ ] Verificar que la integración con la pasarela piloto (MercadoPago u otra) devuelve un `gateway_holdback_id` válido en staging
- [ ] Confirmar que el webhook del lender recibe y procesa correctamente el payload de escalación
- [ ] Correr `php artisan queue:work` (o configurar SQS worker) antes del primer caso real
- [ ] Confirmar que `ANTHROPIC_API_KEY` está seteado — si no, el Exception Agent cae al fake
- [ ] Verificar que la cuenta de WhatsApp Business API del lender está verificada por Meta

### Legal (ver `agente-cobranza-autonomo-legal.md`)
- [ ] Mandato de holdback validado por asesoría legal local antes de incluirlo en contratos reales
- [ ] Marco regulatorio de retención automática sobre ingresos confirmado en el país del piloto
- [ ] Cláusulas contractuales Sumeria ↔ lender firmadas

### Operativo
- [ ] El equipo del lender sabe usar los endpoints de shadow review (`/shadow-reviews/approve` y `/reject`)
- [ ] Hay un humano del lender monitoreando el audit log en la primera semana
- [ ] Proceso de escalación documentado internamente en el lender: quién recibe el webhook, cómo actúa

---

## Correr el worker de colas (producción)

```bash
# Procesa jobs de la cola continuamente
php artisan queue:work sqs --queue=sumeria-collection-jobs --tries=3 --backoff=60

# Con Supervisor (recomendado para producción)
# /etc/supervisor/conf.d/sumeria-collection-worker.conf
[program:sumeria-collection-worker]
command=php /var/www/artisan queue:work sqs --queue=sumeria-collection-jobs --tries=3 --backoff=60
autostart=true
autorestart=true
user=www-data
numprocs=2
```

---

## Correr tests

```bash
# Todos los tests (requiere PHP 8.2+ y Composer instalado)
php artisan test

# Solo tests unitarios
php artisan test --testsuite=Unit

# Solo tests de feature (API end-to-end)
php artisan test --testsuite=Feature

# Con cobertura
php artisan test --coverage
```
