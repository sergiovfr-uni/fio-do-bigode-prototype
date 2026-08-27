# Fio do Bigode Admin

Painel administrativo conectado à API real do Fio do Bigode.

## Segurança

- conta marcada como administradora no backend;
- senha e código 2FA enviado por e-mail;
- token Sanctum exclusivo do painel;
- token mantido somente durante a sessão da aba;
- nenhuma credencial ou token manual é armazenado no código.

## Áreas disponíveis nesta fase

Dashboard, usuários e KYC, negociações, classificados, parcelas, wallets e campanhas em modo de consulta.

O painel não possui fallback demonstrativo: se a API estiver indisponível, apresenta o erro sem substituir dados reais por dados fictícios.
