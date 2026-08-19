# Fio do Bigode — Advertising API Contract

## Objetivo
Separar campanhas/mídias do aplicativo para que o Admin controle publicidade sem nova publicação do app.

## Endpoints previstos

### GET /api/v1/ads/placements/home_primary
Retorna campanhas ativas para o slot principal da Home, já filtradas por período, status e prioridade.

### POST /api/v1/ads/impressions
Payload mínimo: `campaign_id`, `placement`, `user_id` opcional, `session_id`, `occurred_at`.
Uma impressão só deve ser registrada quando pelo menos 50% da mídia estiver visível.

### POST /api/v1/ads/clicks
Payload mínimo: `campaign_id`, `placement`, `user_id` opcional, `session_id`, `occurred_at`.

### Admin
- CRUD de anunciantes
- CRUD de campanhas
- upload/gestão de mídia
- período inicial/final
- status ativo/inativo
- prioridade
- placements
- limite de impressões
- métricas de impressões, alcance, cliques e CTR

## Entidades
`advertisers`, `campaigns`, `campaign_media`, `ad_placements`, `ad_impressions`, `ad_clicks`.

## Protótipo
A versão v0.4.5 utiliza `data/campaigns.json` como feed estático e `localStorage` apenas para demonstrar contagem de impressões/cliques. Isso deve ser substituído pelos endpoints reais no MVP integrado.
