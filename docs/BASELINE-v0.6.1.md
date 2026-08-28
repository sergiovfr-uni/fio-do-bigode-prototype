# Baseline de Homologação — Fio do Bigode v0.6.1

Data de consolidação: 28/08/2026.

## Objetivo

Transformar o estado atual do MVP em uma base controlada, executável e verificável antes da publicação comercial.

## Estado por módulo

| Módulo | Estado de código | Validação necessária |
| --- | --- | --- |
| Site institucional | implementado | conferir domínio oficial, CTAs das lojas e classificados públicos |
| Login / cadastro | implementado | smoke test com usuário novo |
| Recuperação de senha | implementado | envio + token + nova senha |
| 2FA por e-mail | implementado | login real e expiração do código |
| KYC Didit | integrado | sessão real, retorno e webhook |
| Negociação direta | implementado | vendedor e comprador distintos |
| Busca de usuário | implementado | nome/e-mail/telefone/CPF conforme API disponível |
| Proposta / contraproposta | implementado | alteração de condições e invalidação de aceite anterior |
| Aceite bilateral | implementado | conferir transição de estado |
| Testemunhas | implementado | com zero e com duas testemunhas |
| Assinatura eletrônica | implementado | vendedor primeiro, comprador depois, OTP e consentimento |
| Entrada | implementado | upload pelo comprador e confirmação do vendedor |
| Parcelas | implementado | geração de cronograma e confirmação de recebimento |
| Inadimplência | implementado | simular vencimento e estado `overdue` |
| Quitação | implementado | última parcela, `paid_off` e documento/registro final |
| Classificados | implementado | criar anúncio, imagem, categoria e proposta |
| Reputação | implementado | avaliação pós-fechamento e atualização do Bigode |
| Notificações internas | implementado | central, leitura e contador |
| Push nativo | pendente de comprovação | validar Android/iOS em dispositivo físico |
| Planos | implementado no Admin | atribuição, limites e trial |
| Parceiros | implementado no Admin | CRUD e links sociais |
| Anunciantes / campanhas | implementado no Admin | CRUD, período, mídia e métricas |
| Auditoria | implementado no Admin | operações sensíveis gerando log |
| APK Android | pipeline consolidado | CI verde + instalação em aparelho |
| iOS | configuração presente | build/TestFlight ainda precisa ser executado |

## Fluxo E2E obrigatório

Executar com base limpa e dois usuários reais de homologação.

### Usuário A — vendedor

1. criar conta;
2. validar 2FA;
3. concluir KYC;
4. criar negociação;
5. convidar usuário B;
6. aceitar contraproposta, se houver;
7. decidir testemunhas;
8. assinar eletronicamente primeiro;
9. confirmar entrada;
10. confirmar parcelas;
11. avaliar comprador ao encerrar.

### Usuário B — comprador

1. receber convite;
2. criar conta se necessário;
3. validar 2FA e KYC;
4. aceitar ou fazer contraproposta;
5. aguardar formalização;
6. assinar eletronicamente depois do vendedor;
7. enviar comprovante da entrada;
8. acompanhar parcelas;
9. avaliar vendedor ao encerrar.

## Critérios de bloqueio

Não promover para produção se ocorrer qualquer um dos itens abaixo:

- usuário consegue pular KYC quando ele é obrigatório;
- assinatura do comprador ocorre antes da do vendedor;
- OTP pode ser reutilizado ou usado por outro usuário;
- contraproposta mantém aceite de condições antigas;
- documento final não corresponde às condições congeladas;
- comprovante de entrada fica visível para usuário não autorizado;
- parcela paga reaparece como pendente após recarregar;
- negociação quitada ainda aceita alterações financeiras;
- Admin consegue operar sem 2FA/autorização;
- APK não reproduz download/compartilhamento de documentos;
- site público expõe login/cadastro da jornada operacional.

## Limpeza técnica desta baseline

- versão mobile alinhada para 0.6.1;
- build Android alinhado para versionCode 13;
- pipeline de APK único/oficial;
- workflow antigo neutralizado;
- `npm ci` e TypeScript obrigatórios no CI;
- documentação atualizada para assinatura eletrônica interna;
- fluxo Gov.br tratado somente como legado;
- arquitetura WebView registrada explicitamente;
- API de produção identificada como componente externo ao repositório.

## Próxima evolução técnica

Depois da homologação do MVP, migrar progressivamente da WebView para telas React Native nativas, priorizando:

1. autenticação e sessão segura;
2. push notifications;
3. KYC/câmera;
4. assinatura eletrônica;
5. uploads/downloads;
6. deep links de convites;
7. classificados e negociação.

A migração deve preservar contratos da API para não interromper o backend nem a homologação existente.
