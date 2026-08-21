<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

/**
 * Starting contents for a user-level `.env`, embedded in the binary.
 *
 * This is a class constant on purpose, and must stay one. `.env.example` lives
 * in the repository, not inside a PHAR, so reading it from disk would work in a
 * checkout and fail everywhere else — reproducing the exact class of bug that
 * the environment-file search path exists to fix.
 *
 * It is a curated subset of `.env.example`: the two required variables, plus
 * the optional ones an operator most often needs. The full reference lives in
 * `docs/guia/configuracao.md`, which the header points at rather than
 * duplicating it here.
 */
final class EnvFileTemplate
{
  /** Contents written by `ca config init`. */
  public const string CONTENTS = <<<'ENV'
    # Configuração do conta-azul-cli.
    # Referência completa das variáveis: https://github.com/heitoralthmann/conta-azul-cli
    #
    # Use "ca config set <chave> <valor>" para editar sem abrir o arquivo,
    # "ca config show" para conferir o resultado e "ca config path" para
    # descobrir qual arquivo está valendo.

    # Credenciais obrigatórias do app Conta Azul.
    CA_CLIENT_ID=
    CA_CLIENT_SECRET=

    # Variáveis opcionais: descomente e ajuste conforme necessário.

    # Scope OAuth2. Sandbox: não defina (qualquer valor devolve invalid_scope).
    # Produção: exige scope explícito.
    # CA_SCOPE='openid profile aws.cognito.signin.user.admin'

    # URI de redirecionamento OAuth2, igual à cadastrada no portal.
    # CA_REDIRECT_URI=http://localhost:9876/callback

    # Certificado TLS local do servidor de callback, quando o portal exigir HTTPS.
    # CA_CALLBACK_CERT=/caminho/para/cert.pem
    # CA_CALLBACK_KEY=/caminho/para/key.pem

    # Janela para concluir o login no navegador, em segundos.
    # CA_CALLBACK_TIMEOUT=300

    # Endpoints. Os defaults servem produção e sandbox; normalmente não se mexe.
    # CA_API_BASE_URL=https://api-v2.contaazul.com
    # CA_AUTH_BASE_URL=https://auth.contaazul.com
    # CA_AUTHORIZE_URL=https://auth.contaazul.com/oauth2/authorize
    # CA_TOKEN_URL=https://auth.contaazul.com/oauth2/token

    # Caminho do arquivo de tokens.
    # CA_CLI_TOKEN_PATH=~/.config/conta-azul-cli/tokens.json

    # Bootstrap token para ambientes headless/CI (apenas no primeiro arranque).
    # CA_BOOTSTRAP_REFRESH_TOKEN=

    ENV;
}
