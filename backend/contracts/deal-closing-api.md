# Fio do Bigode — Fechamento e Documentos API Contract v0.6.1

## Objetivo

Concluir a negociação com aceite explícito das duas partes, congelamento das condições, geração documental, assinatura eletrônica interna e acompanhamento do cumprimento financeiro até a quitação.

O fluxo externo via Gov.br/ICP-Brasil pertence à jornada legada e não é o padrão para novas negociações.

## Jornada vigente

`proposal_sent` / `counteroffer` → `witnesses_pending` → `signature_pending` → `counterparty_signature_pending` → `entry_receipt_pending` → `entry_confirmation_pending` → `active` / `overdue` → `paid_off`.

Toda transição deve ser idempotente, autorizada por papel, vinculada à versão das condições e registrada em timeline/auditoria.

## Aceite e contraproposta

### POST /v1/deals/{id}/accept

Registra o aceite da parte autenticada sobre a versão vigente das condições. Qualquer contraproposta posterior invalida aceites anteriores daquela versão.

O aceite bilateral congela as condições e inicia a formalização.

## Testemunhas

As testemunhas são opcionais no MVP.

O vendedor pode:

- continuar sem testemunhas; ou
- cadastrar exatamente duas testemunhas antes da geração do documento.

A decisão deve ser registrada no dossiê e na auditoria.

## Geração documental

O dossiê de formalização reúne, conforme configuração jurídica:

1. Acordo da Negociação;
2. Instrumento de Confissão de Dívida;
3. Nota(s) Promissória(s);
4. qualificação das partes;
5. versão congelada das condições;
6. identificador público, hashes e timestamps.

Documentos não devem ser sobrescritos. Uma alteração de condições exige nova versão documental.

## Assinatura eletrônica interna

### POST /v1/deals/{id}/electronic-signature/challenge

Cria desafio de assinatura para a parte correta da etapa e envia código de uso único por e-mail.

A resposta deve informar, no mínimo:

- `challenge_id`;
- e-mail mascarado;
- expiração;
- texto de consentimento;
- versão do consentimento.

### POST /v1/deals/{id}/electronic-signature/sign

Confirma a assinatura utilizando:

- `challenge_id`;
- código de seis dígitos;
- confirmação de consentimento;
- versão do consentimento;
- imagem da assinatura desenhada.

O backend deve registrar evidências suficientes para auditoria, incluindo usuário, papel, negociação, versão documental, timestamps, hash do documento, desafio consumido e metadados de sessão disponíveis.

### Ordem obrigatória

1. vendedor assina primeiro;
2. comprador assina o mesmo documento depois;
3. somente após as duas assinaturas a negociação avança ao cumprimento financeiro.

O código OTP é de uso único e deve expirar. Não deve ser possível assinar por outro papel ou fora da etapa correspondente.

## Fluxo legado

Endpoints de upload de PDF assinado externamente podem permanecer temporariamente para negociações antigas já iniciadas no fluxo Gov.br/ICP-Brasil, mas devem ser tratados como compatibilidade legada e não apresentados em novas negociações.

## Entrada

### POST /v1/deals/{id}/entry-receipt

O comprador envia comprovante de entrada em formato aceito pela plataforma.

### POST /v1/deals/{id}/entry-receipt/confirm

O vendedor confirma o recebimento. Somente depois da confirmação a negociação entra em `active` e o cronograma financeiro passa a ser acompanhado como negócio formalizado.

## Parcelas, atraso e quitação

A API deve fornecer `payment_schedule` com número, vencimento, valor e status de cada parcela.

Estados mínimos:

- pendente;
- paga;
- vencida/em atraso.

A negociação pode assumir `overdue` enquanto houver obrigação vencida e `paid_off` quando todas as obrigações forem concluídas.

A quitação deve produzir registro/documento final sem apagar os comprovantes e documentos anteriores.

## GET /v1/deals/{id}

Deve retornar, respeitando autorização:

- condições e versão;
- comprador e vendedor;
- status e fase da jornada;
- propostas/contrapropostas;
- aceites;
- testemunhas;
- documentos;
- evidências de assinatura;
- comprovante de entrada;
- cronograma de parcelas;
- timeline e pendências.

## Segurança jurídica e operacional

- KYC/documentos de identidade ficam separados do dossiê e são apenas referenciados quando necessário;
- nenhuma condição muda silenciosamente depois do aceite;
- documentos e evidências não são sobrescritos;
- OTP de assinatura é curto, expira e só pode ser consumido uma vez;
- endpoints validam autenticação, papel e estado da negociação;
- arquivos devem ter MIME/tamanho validados e passar por controles de segurança adequados;
- dados pessoais devem respeitar LGPD, minimização, retenção e exclusão lógica quando aplicável;
- operações administrativas sensíveis devem gerar auditoria.
