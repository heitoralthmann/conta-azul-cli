# Solução de problemas

**`403` com `"status_conta":"END_TRIAL"`**

```json
{"kind":"client_error","http_status":403,"message":"A conta não está elegível para uso da API devido ao status atual do plano."}
```

Não é um erro do CLI: a autenticação funcionou e a chamada chegou à API. A conta Conta Azul está fora do período de trial e precisa de um plano que habilite acesso à API. Resolva pelo painel da Conta Azul ou com o suporte deles.

**`kind: "auth_failed"` com `invalid_grant`** — o refresh token foi invalidado (consumido por uma rotação concorrente perdida, ou expirado). Rode `ca auth login` novamente.

**Erro ao subir o servidor de callback** — a porta 9876 está ocupada. Libere-a; ela é fixa porque precisa bater com o `redirect_uri` registrado no app.

**Navegador acusa certificado inválido no callback** — rode `mkcert -install` para instalar a CA local no trust store do sistema.
