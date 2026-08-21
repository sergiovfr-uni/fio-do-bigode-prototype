# Fio do Bigode — Fechamento e Documentos API Contract v0.1

## Objetivo
Concluir a negociação com aceite explícito das duas partes, congelamento das condições, geração documental e importação do PDF assinado externamente via Gov.br.

## Estados mínimos
`pending` → `accepted` → `documents_ready` → `awaiting_signatures` → `active`.

Toda transição deve ser idempotente, autorizada por papel e registrada na timeline/auditoria.

## POST /v1/deals/{id}/accept
Registra o aceite da parte autenticada com versão das condições, timestamp, sessão e evidência. A negociação só passa para `accepted` quando as duas partes aceitarem a mesma versão. Qualquer contraproposta invalida os aceites anteriores.

## POST /v1/deals/{id}/generate-documents
Disponível somente após aceite bilateral. Gera e vincula ao dossiê:
1. Acordo da Negociação;
2. Instrumento de Confissão de Dívida;
3. Nota(s) Promissória(s), uma por parcela ou conforme configuração jurídica.

Os PDFs devem conter identificador da negociação, partes, condições congeladas, hashes e versão do modelo.

## POST /v1/deals/{id}/signed-document
Importa o PDF único ou pacote assinado externamente pelas duas partes via Gov.br. MVP aceita JSON/Base64 para homologação; produção deve usar upload multipart ou URL pré-assinada para storage.

Campos: `file_name`, `signature_provider=gov.br-external`, `signed_by_both=true`, arquivo/hash.

Antes de ativar, validar PDF, integridade, partes signatárias quando tecnicamente possível, tamanho/MIME e antivírus.

## GET /v1/deals/{id}
Deve retornar condições, partes, status, aceites, documentos, timeline e pendências.

## Segurança jurídica e operacional
- documento de identidade/KYC fica separado do dossiê, apenas referenciado;
- nenhuma condição muda depois do aceite sem gerar nova versão;
- documentos não são sobrescritos;
- registrar hashes, timestamps e usuário responsável;
- ativação só após documento assinado vinculado;
- exclusão lógica e retenção conforme obrigação legal/LGPD.
