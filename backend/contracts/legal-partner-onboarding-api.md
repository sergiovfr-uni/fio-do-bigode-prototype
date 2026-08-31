# Fio do Bigode — Onboarding de Parceiro Jurídico por Convite

## Regra de produto

A Rede Jurídica é fechada. Não existe cadastro público de escritório.

Somente o Admin pode gerar convite individual. O convite leva à página exclusiva `/parceiro-juridico/?convite={token}`. O envio do formulário cria uma candidatura em `pending_review`; não libera acesso, assinatura nem recebimento de leads.

## Estados

Convite: `pending` → `used` ou `expired` / `revoked`.

Parceiro: `pending_review` → `active` ou `rejected`. Após aprovação: `active` ↔ `suspended` / `blocked`.

Somente `active`, com assinatura válida, pode receber lead.

## Admin — convites

### POST /api/v1/admin/legal-partner-invitations
Body mínimo:
```json
{"email":"contato@escritorio.com.br","office_name":"Escritório Exemplo","expires_in_days":7}
```
Resposta:
```json
{"id":12,"status":"pending","invite_url":"https://nofiodobigode.app.br/parceiro-juridico/?convite=TOKEN","expires_at":"2026-09-07T23:59:59-03:00"}
```

### GET /api/v1/admin/legal-partner-invitations
Lista convites e estados.

### POST /api/v1/admin/legal-partner-invitations/{id}/revoke
Revoga convite ainda não utilizado.

### POST /api/v1/admin/legal-partner-invitations/{id}/resend
Reenvia o convite e registra auditoria.

## Página exclusiva do escritório

### GET /api/v1/legal-partner-invitations/{token}
Valida token sem autenticação administrativa. Retorna apenas dados mínimos do convite.

### POST /api/v1/legal-partner-applications
Body inclui `invitation_token`, `office_name`, `document`, `contact_name`, `oab`, `email`, `phone`, `city`, `state`, `service_regions`, `specialties`, `notes`.

Regras:
- token válido, não expirado e não revogado;
- um token só conclui uma candidatura;
- CNPJ e e-mail normalizados e validados;
- candidatura nasce em `pending_review`;
- nenhuma credencial operacional é liberada nesse momento;
- registrar IP/data/hora e aceite de privacidade conforme política aplicável.

## Admin — parceiros

### GET /api/v1/admin/legal-partners
Lista parceiros e candidaturas, incluindo status de análise, assinatura, região, especialidades e leads.

### GET /api/v1/admin/legal-partners/{id}
Detalhe completo para visualização humana.

### PUT /api/v1/admin/legal-partners/{id}
Edição administrativa dos dados permitidos.

### POST /api/v1/admin/legal-partners/{id}/approve
Aprova candidatura. Pode definir plano, mensalidade, regiões e especialidades. Só depois da aprovação e das regras comerciais aplicáveis o acesso operacional é liberado.

### POST /api/v1/admin/legal-partners/{id}/reject
Reprova com justificativa auditável.

### POST /api/v1/admin/legal-partners/{id}/status
Permite `active`, `suspended` ou `blocked`, sempre com justificativa e auditoria.

## Segurança

Tokens de convite devem ser aleatórios, de alta entropia, armazenados preferencialmente como hash, expirar e ser de uso único. A página não deve revelar se determinado CNPJ/e-mail já pertence à rede antes de validar o convite.

Bloqueio ou suspensão deve impedir novo login operacional e novos leads, preservando histórico. Aprovação, rejeição, edição, bloqueio, desbloqueio, geração/reenvio/revogação de convite e visualização de dados sensíveis devem gerar log de auditoria.

## E-mail

A geração do convite envia e-mail ao endereço definido pelo Admin com o link seguro. O e-mail não contém dados sensíveis. Falha de envio não invalida o convite e deve permitir reenvio administrativo.
