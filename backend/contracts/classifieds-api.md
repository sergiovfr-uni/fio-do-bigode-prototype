# Fio do Bigode — Classificados + Planos API Contract v0.1

## Objetivo
Vincular quantidade de anúncios ativos do classificado ao plano de assinatura do usuário, mantendo limites parametrizáveis no backend.

## GET /v1/plans
Retorna planos, preço, trial_days, active_ads_limit, negotiations_limit e featured_ads.

## GET /v1/classifieds
Lista anúncios ativos e públicos. Filtros: category, city, price_min, price_max, featured, owner_id.

## POST /v1/classifieds
Cria anúncio. Antes da criação, validar identidade/KYC e entitlement do plano. Se active_ads_count >= active_ads_limit retornar 422 PLAN_AD_LIMIT_REACHED com plano atual e upgrade_options.

Campos mínimos: title, category, description, price, media[], city, state, contact_mode.

## PATCH /v1/classifieds/{id}
Edita anúncio do proprietário.

## POST /v1/classifieds/{id}/activate
Ativa anúncio e consome uma vaga do plano.

## POST /v1/classifieds/{id}/deactivate
Desativa anúncio e libera uma vaga do plano.

## GET /v1/me/entitlements
Exemplo:
```json
{
  "plan":"bronze",
  "active_ads_limit":3,
  "active_ads_count":1,
  "active_ads_remaining":2,
  "featured_ads_limit":0,
  "featured_ads_used":0
}
```

## Admin
Planos e limites devem ser editáveis no Admin sem necessidade de nova publicação do app. Registrar alterações de plano e limites em auditoria.

## Relação com negociações
Um anúncio pode originar uma negociação Fio do Bigode por meio de `POST /v1/classifieds/{id}/start-negotiation`, preservando o anúncio original no dossiê da negociação.
