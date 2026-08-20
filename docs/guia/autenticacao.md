# Autenticação

```bash
ca auth login
```

O comando imprime uma URL, sobe um listener local na porta **9876** e aguarda o redirect. Abra a URL no navegador, complete o login, e os tokens são gravados em `~/.config/conta-azul-cli/tokens.json` com permissão `0600`.

```bash
ca auth logout    # remove as credenciais locais
```

**Refresh é automático e invisível.** O access token é renovado preventivamente quando restam menos de 60 s de validade, e reativamente uma única vez em caso de `401`. Refresh tokens rotacionam a cada uso e o novo valor é sempre persistido. Invocações concorrentes coordenam via `flock()` sobre o arquivo de tokens, de modo que apenas uma delas faz o refresh e as demais leem o resultado.

## Ambientes headless (CI)

Rode `ca auth login` uma vez numa máquina com navegador, copie o `refresh_token` do `tokens.json` e exponha no CI:

```bash
export CA_BOOTSTRAP_REFRESH_TOKEN=<token>
```

Na primeira invocação o CLI detecta a variável, faz o refresh, persiste o resultado em arquivo e segue. Depois desse primeiro arranque a variável pode ser removida.
