# Test Results

**Fecha:** 2026-06-17  
**Framework:** Laravel 12 + Pest v3  
**PHP:** 8.5.7  
**Total:** 25 passed · 65 assertions · 0.32s

---

## Unit Tests

### AuditLogTest — PASS
| Test | Estado |
|------|--------|
| AuditLog lanza LogicException al intentar modificar un registro existente | ✓ |
| AuditLog lanza LogicException al intentar eliminar | ✓ |
| AuditLog nuevo (no existente) puede guardarse sin excepción | ✓ |

### DecisionEngineTest — PASS
| Test | Estado |
|------|--------|
| Comercio con score alto recibe recomendación de activar holdback | ✓ |
| Comercio con score muy bajo escala por high_risk_score | ✓ |
| holdback_pct nunca supera el máximo de la política | ✓ |
| holdback_pct nunca supera el máximo autorizado en el mandato | ✓ |
| holdback_pct siempre es mayor o igual al mínimo de la política | ✓ |
| recovery_probability está entre 0 y 1 | ✓ |

### PolicyEngineTest — PASS
| Test | Estado |
|------|--------|
| Holdback dentro del rango no lanza excepción | ✓ |
| Holdback exactamente en el mínimo es válido | ✓ |
| Holdback exactamente en el máximo de la política es válido | ✓ |
| Holdback por debajo del mínimo lanza DomainException | ✓ |
| Holdback por encima del máximo de la política lanza DomainException | ✓ |
| Holdback respeta el máximo del mandato cuando es más restrictivo que la política | ✓ |
| Holdback válido cuando el mandato es más restrictivo y se respeta | ✓ |

---

## Feature Tests

### CollectionCaseApiTest — PASS
| Test | Estado |
|------|--------|
| POST negotiation-policy crea política correctamente | ✓ |
| POST negotiation-policy rechaza max menor que min | ✓ |
| POST holdback-mandates registra un mandato | ✓ |
| POST collection-cases crea caso cuando hay mandato y política activos | ✓ |
| POST collection-cases falla sin mandato activo | ✓ |
| GET collection-cases devuelve estado del caso | ✓ |
| POST escalate transiciona el caso a escalated | ✓ |
| Shadow approve transiciona el caso según la recomendación del Decision Engine | ✓ |
| Sin autenticación los endpoints retornan 401 | ✓ |
