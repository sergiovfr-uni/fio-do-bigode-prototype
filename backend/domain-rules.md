# Regras de domínio — MVP Fio do Bigode

## 1. Identidade e segurança

1. Usuário não pode formalizar negociação sem identidade verificada.
2. Login operacional exige 2FA após credenciais primárias.
3. Recuperação de senha usa token temporário, uso único e expiração curta.
4. KYC e Risk Score são conceitos independentes.
5. Índice Bigode mede reputação; Risk Score mede risco/fraude.
6. Usuário legítimo pode ter operação bloqueada por risco alto.

## 2. LGPD e responsabilidade

1. Termos de Uso, Política de Privacidade e Ciência de Responsabilidade possuem versões independentes.
2. O backend registra evidência do aceite: usuário, documento, versão, data/hora, IP, dispositivo e hash da versão aceita.
3. Negociações possuem aceite de responsabilidade específico, vinculado ao negócio.
4. O Fio do Bigode atua como plataforma de intermediação, registro e acompanhamento; não é comprador, vendedor, fiador, avalista ou garantidor da obrigação entre usuários.
5. Textos jurídicos finais devem ser validados pelo jurídico antes de produção.

## 3. Classificados e planos

1. Cada plano define `active_listing_limit`.
2. Publicação deve ser rejeitada se o usuário já atingiu o limite de anúncios ativos.
3. Limites são configurados no backend/Admin; nunca hardcoded no app.
4. Free Trial inicial: 1 anúncio ativo; Bronze: 3; Prata: 10; Ouro: 30.
5. Um anúncio pode originar negociação, mantendo `listing_id` na negociação.
6. Preço do anúncio é referência e pode receber proposta diferente.

## 4. Negociações

Fluxo-base:

`draft -> proposal_sent -> under_review -> counter_offer -> accepted -> management_defined -> awaiting_signatures -> active -> settled`

Exceções possíveis: `overdue` e `canceled`.

1. Toda contraproposta gera nova versão imutável da proposta.
2. Contraproposta nunca libera contrato automaticamente.
3. Contrato só pode ser gerado após aceite explícito da parte contrária na versão atual.
4. Após aceite, condições aceitas ficam congeladas; alteração exige nova proposta e novo aceite.
5. Documento assinado é importado após aceite da proposta e geração do contrato.
6. Importação do PDF assinado por todas as partes pode ativar a negociação.
7. Todo evento relevante entra na timeline/auditoria.

## 5. Parcelas e pagamentos

1. Parcelas nascem das condições aceitas.
2. Pagamento manual exige comprovante/registro.
3. Pagamento pela Wallet/Bigode Bank depende de integração BaaS futura.
4. Status financeiro não pode ser alterado apenas pelo frontend.

## 6. Wallet / Bigode Bank

1. No MVP inicial pode existir em modo `demo` até contratação/integracão BaaS.
2. Modelo deve suportar saldo, PIX, receber, pagar, extrato e conciliação.
3. Nenhuma tela demonstrativa pode induzir a entender que houve movimentação financeira real.

## 7. Publicidade

1. Campanhas são administradas no backend.
2. Aplicativo recebe apenas campanhas elegíveis para placement, período e prioridade.
3. Impressão só é contabilizada quando a mídia efetivamente entra em viewport conforme regra definida pelo cliente.
4. Clique e impressão são eventos separados.
5. App não deve precisar ser republicado para mudança de campanha/mídia.

## 8. Antifraude

Sinais podem envolver identidade, documento, dispositivo, rede, comportamento, pagamentos e relações entre contas.

Decisões possíveis:

- `allow`
- `review`
- `block`

A decisão deve ser persistida com os motivos utilizados no momento da avaliação.
