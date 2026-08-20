<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Autenticação

## `auth login` ✅ { #auth-login }

Sem parâmetros. Imprime uma URL, sobe um listener HTTPS local na porta **9876** e aguarda o redirect. Tokens vão para `~/.config/conta-azul-cli/tokens.json` com permissão `0600`.

O refresh é automático e invisível — não existe comando para isso. Renovação preventiva a menos de 60 s da expiração, e reativa uma vez em caso de `401`.

## `auth logout` ✅ { #auth-logout }

Sem parâmetros. Remove as credenciais locais.
