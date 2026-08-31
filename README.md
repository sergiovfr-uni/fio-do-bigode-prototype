# Fio do Bigode — baseline operacional v0.6.1

O Fio do Bigode é uma plataforma para formalização de negociações entre pessoas, com proposta, contraproposta, contrato, assinatura eletrônica, acompanhamento de pagamentos, classificados e reputação.

## Componentes

- `live.html` — jornada web/mobile atual do MVP.
- `mobile/` — shell React Native + Expo para Android/iOS.
- `admin/` — painel administrativo.
- `backend/` — contratos e documentação técnica das APIs.
- `website/` — site institucional público.

## API

A jornada operacional usa a API do Fio do Bigode hospedada em ambiente externo. Credenciais, chaves e segredos não devem ser persistidos no repositório.

## Jornada principal homologada

1. Cadastro/login + 2FA.
2. KYC.
3. Criação ou recebimento de negociação.
4. Proposta / contraproposta / aceite.
5. Qualificação contratual.
6. Testemunhas opcionais.
7. Geração do documento.
8. Assinatura eletrônica do vendedor.
9. Assinatura eletrônica do comprador.
10. Comprovante da entrada.
11. Confirmação da entrada.
12. Parcelas e comprovantes.
13. Quitação.
14. Avaliação das partes.
15. Histórico documental.

O fluxo acima foi executado ponta a ponta em homologação em **28/08/2026** e foi **funcionalmente aprovado**.

## Ajustes pendentes/estabilizados

Durante o E2E foram encontrados três ajustes de UX, sem impacto na regra de negócio:

- modal de confirmação fora do centro da tela em alguns dispositivos;
- canvas de assinatura aparecendo antes do preenchimento do código de seis dígitos;
- formulário de dados contratuais permanecendo aberto após salvar.

A estabilização v0.6.1 aplica esses ajustes no shell mobile enquanto a limpeza física do `live.html` é feita de forma incremental.

## Build Android

Workflow oficial:

`.github/workflows/build-android-apk.yml`

O artifact esperado é:

`fio-do-bigode-v0.6.1-apk`

## Regra de release

Nenhum merge no `main` deve ocorrer sem:

- E2E principal verde;
- build Android verde;
- confirmação de que os ajustes de UX não introduziram regressão;
- revisão do PR da baseline.

## Limpeza estrutural

O `live.html` contém camadas históricas e implementações sobrescritas. A limpeza deve ser gradual e orientada por E2E, evitando substituição integral enquanto o MVP estiver em estabilização.

Consulte `docs/BASELINE-v0.6.1.md` para o checklist completo.
