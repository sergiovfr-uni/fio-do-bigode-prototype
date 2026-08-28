# Fio do Bigode Mobile — v0.6.1

Aplicativo Android/iOS baseado em React Native + Expo, usando um shell nativo para carregar a jornada web consolidada do MVP.

## Arquitetura atual

- React Native + Expo.
- `react-native-webview` como shell da jornada do MVP.
- API real do Fio do Bigode.
- Download/compartilhamento nativo para documentos recebidos pela WebView.
- URL da jornada configurável por `EXPO_PUBLIC_APP_URL`.
- Fallback atual: GitHub Pages do repositório.

## Estabilização v0.6.1

Após o E2E completo aprovado em 28/08/2026, o shell recebeu uma camada temporária de compatibilidade para três ajustes de UX identificados durante a homologação:

- centralização dos modais de confirmação em telas móveis;
- assinatura desenhada exibida somente após o preenchimento do código de seis dígitos;
- fechamento automático do formulário de dados contratuais após salvamento bem-sucedido.

A camada existe para preservar a jornada já homologada enquanto o `live.html` monolítico é limpo de forma incremental. A regra é não substituir ou remover agressivamente trechos do `live.html` antes de nova validação E2E.

## Rodar localmente

```bash
npm ci
npm run typecheck
npx expo start
```

Para apontar a WebView para outro ambiente:

```bash
EXPO_PUBLIC_APP_URL=https://seu-endereco/live.html npx expo start
```

## APK Android oficial

Workflow oficial:

`.github/workflows/build-android-apk.yml`

O pipeline executa:

1. `npm ci`
2. `npm run typecheck`
3. `npx expo prebuild --platform android --clean`
4. `./gradlew assembleRelease`
5. upload do artifact `fio-do-bigode-v0.6.1-apk`

## Release

A baseline v0.6.1 usa:

- versão Expo: 0.6.1
- iOS buildNumber: 13
- Android versionCode: 13
- package/bundle: `com.fiodobigode.app`

A publicação em loja deve ocorrer somente depois do E2E final e da aprovação do merge no `main`.
