# Fio do Bigode — Backend MVP

Esta pasta contém a fundação do backend real do Fio do Bigode, separada do protótipo aprovado v0.4.7.

## Objetivo

Centralizar dados e regras para que App Mobile e Admin Web consumam a mesma API.

## Módulos do MVP

- Auth: login, recuperação de senha, 2FA e sessões
- Usuários e KYC: CPF, documento, selfie/liveness, status de identidade
- Antifraude: Risk Score, sinais, revisão manual e auditoria
- Termos/LGPD: versionamento e evidência de aceite
- Planos/assinaturas: Free Trial, Bronze, Prata e Ouro
- Classificados: anúncios e limites por plano
- Negociações: proposta, contraproposta, aceite, contrato, documentos e timeline
- Parcelas e pagamentos
- Wallet/Bigode Bank: modelo preparado para integração BaaS
- Publicidade: anunciantes, campanhas, placements, impressões e cliques
- Reputação: Índice Bigode separado de Risk Score
- Notificações

## Estrutura

- `database/schema.sql`: modelo relacional inicial
- `openapi.yaml`: contrato REST inicial
- `domain-rules.md`: regras de negócio que App e Admin devem respeitar

## Princípio central

O protótipo não é fonte de verdade. No MVP integrado, toda informação operacional relevante deve existir no backend e ser apresentada de forma consistente no App e no Admin.

## Acesso administrativo

O painel usa rotas próprias em `/api/v1/admin`, protegidas por Sanctum, middleware de administrador e 2FA por e-mail.

Para promover uma conta existente ou criar uma conta administrativa dedicada no deploy do Railway, configure:

```env
ADMIN_EMAIL=email-da-conta-existente
ADMIN_PASSWORD=senha-temporaria-forte
ADMIN_NAME=Administração Fio do Bigode
```

O `AdminUserSeeder` é executado na inicialização do container. Se a conta já existir, apenas concede o papel administrativo e preserva sua senha. Se não existir, cria uma conta interna sem KYC, reputação ou participação nas negociações. Nenhuma senha padrão fica gravada no código.
