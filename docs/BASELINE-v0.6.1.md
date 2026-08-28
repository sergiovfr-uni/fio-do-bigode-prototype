# Baseline v0.6.1 — Fio do Bigode

Data: 28/08/2026

## Objetivo

Consolidar uma referência estável para homologação do MVP antes da publicação e antes da limpeza estrutural do `live.html`.

## Estado dos módulos

- Autenticação + 2FA por e-mail: operacional.
- Recuperação de senha: operacional.
- Cadastro + consentimentos LGPD: operacional.
- KYC Didit: integrado no fluxo atual; validar sessão/webhook/status em produção controlada.
- Classificados: operacional para publicação, consulta e propostas.
- Negociação direta: operacional.
- Busca de usuário cadastrado: operacional, incluindo busca por nome com seleção explícita.
- Aceite e contraproposta: operacional.
- Testemunhas opcionais: operacional.
- Assinatura eletrônica interna: operacional no fluxo atual.
- Comprovante de entrada + confirmação: operacional.
- Parcelas + comprovantes + confirmação: operacional.
- Quitação + avaliação: operacional.
- Admin: módulos principais presentes; autenticação e ações críticas devem permanecer protegidas.
- APK Android: pipeline oficial consolidado.
- iOS/TestFlight: ainda não homologado nesta baseline.

## E2E realizado em 28/08/2026

Fluxo completo aprovado em homologação com dois usuários controlados:

1. Login no APK Android.
2. Recuperação do estado KYC já verificado após reinstalação.
3. Busca nominal da contraparte cadastrada.
4. Criação de negociação direta.
5. Recebimento e aceite da proposta pela contraparte.
6. Formalização sem testemunhas.
7. Assinatura eletrônica do vendedor.
8. Assinatura eletrônica do comprador.
9. Transição para `Negócio Feito`.
10. Envio do comprovante de entrada pelo comprador.
11. Confirmação da entrada pelo vendedor.
12. Geração correta das parcelas.
13. Envio e confirmação de comprovantes de parcelas.
14. Quitação da negociação.
15. Recebimento final e avaliação das partes.

Resultado: **E2E funcionalmente aprovado**.

## Ajustes de UX identificados no E2E

- Modal de confirmação aparecia deslocado para o rodapé em algumas telas móveis; corrigir centralização e respeito às safe areas.
- Campo de assinatura desenhada deve permanecer oculto até o usuário preencher o código de seis dígitos recebido por e-mail.
- Após salvar os dados contratuais no perfil, o formulário deve fechar automaticamente.

Esses itens não bloquearam o fluxo de negócio, mas fazem parte da estabilização da v0.6.1 antes do merge.

## Critérios bloqueadores antes do merge no `main`

- Login e 2FA sem regressão.
- KYC persistente e acesso restrito quando pendente.
- Convite/negociação sem duplicidade.
- Contraproposta deve invalidar aceites anteriores quando aplicável.
- Termos acordados congelados antes da formalização.
- Ordem de assinatura: vendedor primeiro, comprador depois.
- Código de assinatura associado ao usuário e negociação corretos.
- Documento final preservado com evidências.
- Entrada e comprovantes persistidos corretamente.
- Parcelas e saldo coerentes com a negociação.
- `paid_off` não pode ser revertido por operação comum.
- Termo de quitação disponível ao final da jornada.
- Admin sem exposição pública indevida.
- Download/compartilhamento de documentos funcionando no APK.
- Site institucional público sem login/cadastro operacional.

## Regra de limpeza

O `live.html` ainda contém implementações históricas e sobreposições sucessivas. A limpeza física deve ser incremental, sempre após E2E verde, evitando substituição integral ou remoção agressiva de trechos que ainda possam sustentar o fluxo homologado.

## Direção arquitetural

Manter o shell React Native + WebView durante a estabilização do MVP. Migrar gradualmente jornadas críticas para componentes nativos somente após a baseline funcional permanecer estável e coberta por testes reproduzíveis.
