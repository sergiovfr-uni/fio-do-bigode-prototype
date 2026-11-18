# Rede Jurídica — operação por convite

## Fluxo implementado

1. Admin emite convite individual por e-mail (1 a 30 dias). Só o hash do token é armazenado. Reemissão troca o token; uso, revogação ou expiração impedem cadastro.
2. Escritório aceita a política e cadastra os dados usando o e-mail convidado. Estado inicial: `pending_review`. Não cria usuário comum e não libera acesso.
3. Admin confere cadastro e libera plano `legal_partner` ativo até uma data definida. `payment_confirmed` exige referência e `payment_reviewed=true`; `manual` registra liberação sem afirmar pagamento. Ambas exigem justificativa.
4. Escritório recebe código no e-mail para entrar no portal `/parceiro-juridico/acesso/`. Código de uso único, dez minutos, cinco tentativas; sessão jurídica de até oito horas.
5. Admin encaminha uma solicitação com consentimento registrado a um escritório ativo e com vigência válida. Não há distribuição automática.
6. Parceiro consulta dossiê e documentos, registra notas privadas e status do atendimento. Não altera contratos, parcelas ou pagamentos.
7. Reatribuição remove acesso anterior; notas privadas não passam ao novo escritório. Cancelamento pelo solicitante remove o acesso pela plataforma. Downloads já realizados não são apagados.

## Rotas principais — prefixo `/api/v1`

- Admin: GET/POST `/admin/legal-partner-invitations`; POST `/{id}/resend` e `/{id}/revoke`.
- Cadastro: GET `/legal-partner-invitations/{token}`; POST `/legal-partner-applications` com `invitation_token`, dados cadastrais e `consent=true`.
- Admin: GET `/admin/legal-partners`; PUT `/admin/legal-partners/{id}` para correções cadastrais (sem trocar e-mail de acesso); POST `/admin/legal-partners/{id}/activate` com `plan_id`, `access_until`, `activation_method`, `reason` e confirmação/referência quando pago; POST `/{id}/status` para `suspended`, `blocked`, `rejected`.
- Admin: GET `/admin/legal-requests`; POST `/admin/legal-requests/{action}/assign` com `legal_partner_id` e `reason`.
- Login: POST `/legal/auth/code` com `email`; POST `/legal/auth/verify` com `challenge_id` e `code`.
- Parceiro autenticado: GET `/legal/me`, GET `/legal/cases`, GET `/legal/cases/{id}`; POST `/{id}/status`, POST `/{id}/notes`; GET `/{id}/documents/{document}`; POST `/legal/logout`.
- Solicitante autenticado: POST `/deals/{deal}/legal-support/{action}/cancel`.

## Segurança e operação

- Identidade e token do escritório são separados do usuário do app. Token jurídico não acessa APIs de consumidor/admin; token comum não acessa o portal jurídico.
- Status, vigência e plano são conferidos em cada acesso. Suspensão revoga sessões e desafios de login.
- Dossiê e downloads exigem atribuição atual e consentimento não cancelado. Respostas privadas usam `Cache-Control: private, no-store`.
- Documentos são baixados por rotas autenticadas, sem token na URL. O portal não usa dados fictícios nem armazena dossiês em localStorage.
- Aprovação não verifica automaticamente OAB/CNPJ. A conferência humana é responsabilidade do admin.
- Falha de envio de e-mail é informada ao admin. Acesso e encaminhamento registrados permanecem válidos e podem ser consultados no painel.
- Sem gateway ou cobrança automática; o registro manual de pagamento não executa transação financeira.
- Demonstração permanece separada em `/parceiro-juridico/painel/`.
