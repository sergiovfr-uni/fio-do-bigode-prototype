# Fio do Bigode — Apoio Jurídico API Contract v0.6.1

## Objetivo

Transformar a ação **Solicitar apoio jurídico** da inadimplência em uma fila operacional rastreável, sem contratação automática de advogado e sem apagar o histórico da negociação.

## Entidades

### Parceiro jurídico

Campos mínimos:
- `id`;
- `name` / razão ou nome profissional;
- `contact_name`;
- `email`;
- `phone`;
- `oab` quando aplicável;
- `city` / `state`;
- `specialties`;
- `active`;
- timestamps.

### Solicitação de apoio

Campos mínimos:
- `id`;
- `deal_id`;
- `installment_id`;
- `requested_by`;
- `legal_partner_id` opcional até atribuição;
- `status`;
- `reason` / observação;
- `requested_at`;
- `assigned_at`;
- `closed_at`;
- histórico de mudanças.

Estados mínimos:
`new` → `assigned` → `in_progress` → `waiting_response` → `completed`.

Estados alternativos: `cancelled` e `declined`.

## Aplicativo

### POST /v1/deals/{dealId}/installments/{installmentId}/delinquency/legal-support

Registra o pedido feito pelo usuário autorizado.

Regras:
- a parcela deve pertencer à negociação;
- deve existir atraso ou contexto de inadimplência válido;
- pedido duplicado aberto para a mesma parcela deve ser idempotente;
- criar evento de timeline/auditoria;
- notificar administradores responsáveis;
- quando houver roteamento automático para parceiro ativo, atribuir o parceiro e disparar notificação/e-mail;
- nenhum advogado é contratado automaticamente pelo simples clique.

Resposta mínima:
```json
{
  "message": "Pedido de apoio jurídico registrado.",
  "request_id": 123,
  "status": "new"
}
```

## Admin — Parceiros jurídicos

- `GET /api/v1/admin/legal-partners`
- `POST /api/v1/admin/legal-partners`
- `PUT /api/v1/admin/legal-partners/{id}`
- `POST /api/v1/admin/legal-partners/{id}/status`

Somente parceiros ativos podem receber novas solicitações.

## Admin — Solicitações

- `GET /api/v1/admin/legal-support-requests`
- `GET /api/v1/admin/legal-support-requests/{id}`
- `POST /api/v1/admin/legal-support-requests/{id}/assign`
- `POST /api/v1/admin/legal-support-requests/{id}/status`

A listagem deve retornar negociação, parcela, solicitante, parceiro atribuído, status e datas.

## Notificações

Ao criar uma solicitação:
1. registrar notificação interna para o Admin;
2. se houver parceiro atribuído, criar notificação operacional para o parceiro;
3. enviar e-mail ao endereço cadastrado do parceiro com referência da solicitação e link seguro para atendimento;
4. registrar sucesso/falha do envio para auditoria e permitir reenvio administrativo.

Não enviar CPF, documento de identidade ou anexos sensíveis no corpo do e-mail. O parceiro deve consultar os dados autorizados dentro do ambiente autenticado.

## Admin — Tela Jurídico

O menu **Jurídico** deve possuir duas visões:
- **Solicitações:** Nova, Encaminhada, Em atendimento, Aguardando retorno, Concluída/Cancelada;
- **Parceiros:** cadastro, ativação/desativação, contato e especialidades.

A tela de detalhe da solicitação deve permitir acompanhar status, parceiro responsável e referência da negociação sem alterar as condições financeiras do acordo.

## Auditoria e LGPD

Toda atribuição, mudança de status, visualização sensível e reenvio de notificação deve registrar administrador/parceiro, data/hora e ação. O compartilhamento de dados deve seguir necessidade, minimização e autorização aplicáveis.
