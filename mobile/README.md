# Fio do Bigode Mobile — v0.6.1

Aplicativo Android/iOS em React Native + Expo. Nesta fase do MVP o app usa um contêiner nativo (`react-native-webview`) para executar a jornada homologada em `live.html`, integrada à API real.

## Baseline v0.6.1

- Login, cadastro, recuperação de senha e 2FA por e-mail.
- KYC integrado à jornada Didit (`/v1/kyc/didit/*`).
- Classificados, anúncios patrocinados e propostas.
- Negociação direta, contraproposta, aceite e testemunhas opcionais.
- Assinatura eletrônica interna por código de e-mail + consentimento + assinatura desenhada.
- Entrada, comprovantes, parcelas, inadimplência e quitação.
- Compartilhamento/download de documentos pelo contêiner nativo.
- Ícone, adaptive icon e splash oficiais.

## Arquitetura atual

`Expo/React Native -> WebView -> live.html -> https://api.nofiodobigode.app.br/api/v1`

A WebView é uma decisão de MVP/homologação. Funcionalidades que exigirem integração nativa mais profunda (push, biometria, deep links avançados e publicação madura nas lojas) devem migrar gradualmente para componentes React Native sem alterar os contratos da API.

A URL da jornada pode ser sobrescrita no build com `EXPO_PUBLIC_APP_URL`. Sem essa variável, o fallback homologado continua sendo o GitHub Pages.

## Executar localmente

```bash
cd mobile
npm ci
npm run typecheck
npx expo start
```

## Gerar APK de homologação

O workflow oficial é `.github/workflows/build-android-apk.yml`. Ele executa instalação reproduzível, validação TypeScript, `expo prebuild` limpo e `assembleRelease`.

Também é possível usar EAS:

```bash
npx eas-cli build --platform android --profile preview
```

O perfil `preview` gera APK instalável; produção deve gerar AAB para a Play Store.
