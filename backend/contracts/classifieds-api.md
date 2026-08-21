# Fio do Bigode — Classificados + Planos API Contract v0.1

## Objetivo
Vincular quantidade de anúncios ativos do classificado ao plano de assinatura do usuário, mantendo limites parametrizáveis no backend.

## GET /v1/plans
Retorna planos, preço, trial_days, active_ads_limit, negotiations_limit e featured_ads, sponsored_ads_included e sponsored_extra_price.

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

## Patrocínio e destaque na Home

### POST /v1/classifieds/{id}/sponsor
Ativa o destaque patrocinado do anúncio. Validar propriedade do anúncio, status ativo, cota incluída no plano ou cobrança adicional autorizada. Campos: `placement` (`home_featured`), `starts_at`, `ends_at` e `billing_source`.

### DELETE /v1/classifieds/{id}/sponsor
Encerra o destaque sem desativar o anúncio comum.

`GET /v1/listings` deve retornar `sponsored`, `sponsored_until` e mídia principal. Na Home, anúncios patrocinados ativos aparecem antes dos recentes orgânicos, sempre identificados com o selo **Patrocinado**.

## Mídia
Cada anúncio terá `cover_image` obrigatória e `media[]` opcional. O backend deve armazenar imagens em storage dedicado, gerar miniaturas, validar MIME/tamanho, remover metadados sensíveis e nunca depender de Base64 no banco em produção.

## Admin
Planos e limites devem ser editáveis no Admin sem necessidade de nova publicação do app. Registrar alterações de plano e limites em auditoria.

## Relação com negociações
Um anúncio pode originar uma negociação Fio do Bigode por meio de `POST /v1/classifieds/{id}/start-negotiation`, preservando o anúncio original no dossiê da negociação.
