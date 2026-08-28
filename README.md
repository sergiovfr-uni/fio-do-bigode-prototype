# Fio do Bigode

Baseline consolidado do MVP: **v0.6.1**.

## Componentes

- `website/` — site institucional público. Não deve oferecer login/cadastro da aplicação.
- `live.html` — jornada operacional web usada pelo MVP mobile em homologação.
- `mobile/` — shell React Native/Expo que executa a jornada homologada e integra recursos nativos necessários.
- `admin/` — painel administrativo conectado à API real.
- `backend/contracts/` — contratos e referências de integração; não contém o backend de produção.
- `data/` — dados estáticos auxiliares usados pelo site/protótipo.

## API

Produção/homologação operacional utiliza:

`https://api.nofiodobigode.app.br/api/v1`

O backend de produção é externo a este repositório. Portanto, uma alteração de interface que dependa de endpoint novo só deve ser considerada concluída após validação contra a API real.

## Jornada vigente

1. cadastro + LGPD;
2. 2FA por e-mail;
3. KYC Didit;
4. classificados ou negociação direta;
5. proposta/contraproposta e aceite;
6. testemunhas opcionais;
7. assinatura eletrônica do vendedor;
8. assinatura eletrônica do comprador;
9. comprovante e confirmação da entrada;
10. parcelas / inadimplência;
11. quitação e reputação.

## Assinatura

O fluxo oficial para novas negociações é a assinatura eletrônica interna com código enviado por e-mail, consentimento explícito e assinatura desenhada. O fluxo externo Gov.br/ICP-Brasil é legado e só deve existir para compatibilidade de negociações antigas.

## Mobile

Versão do app: `0.6.1`, Android `versionCode 13`, iOS `buildNumber 13`.

A arquitetura de MVP é `React Native/Expo -> WebView -> live.html -> API`. A URL da jornada pode ser definida por `EXPO_PUBLIC_APP_URL`; sem variável, existe fallback para o GitHub Pages homologado.

## Build Android

Pipeline oficial:

`.github/workflows/build-android-apk.yml`

Ele executa:

- `npm ci`;
- `npm run typecheck`;
- `expo prebuild --clean`;
- `gradlew assembleRelease`;
- publicação do APK como artefato `fio-do-bigode-v0.6.1-apk`.

O workflow `android-apk.yml` está mantido apenas como legado/manual e não deve ser usado para releases.

## Regra de release

Nenhuma versão deve ser promovida para `main` sem validar:

- login/2FA;
- KYC;
- criação e aceite de negociação;
- assinatura das duas partes;
- entrada;
- parcelas/quitação;
- classificados;
- painel administrativo;
- build Android.

Veja `docs/BASELINE-v0.6.1.md` para o checklist completo.
