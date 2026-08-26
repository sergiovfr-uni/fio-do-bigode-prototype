# Fio do Bigode Mobile

Aplicativo Android/iOS em React Native + Expo.

## Primeira build integrada (v0.5.0)

- Usa a jornada web homologada dentro de um contêiner nativo.
- Login, cadastro, KYC Didit, negociações e pagamentos usam o backend real.
- Upload de fotos e arquivos usa o seletor nativo do aparelho.
- O botão Voltar do Android respeita o histórico interno do aplicativo.
- Erros de conexão apresentam uma tela de recuperação.

## Executar

```bash
cd mobile
npm install
npx expo start
```

## Gerar APK de homologação

```bash
npx eas-cli build --platform android --profile preview
```

O perfil `preview` em `eas.json` gera um APK instalável. A versão de produção gera AAB para a Play Store.
