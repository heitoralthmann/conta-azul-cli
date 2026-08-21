#!/usr/bin/env bash
#
# Smoke test do PHAR construído — 6 verificações.
#
# A suíte do PHPUnit instancia ContaAzulApplication in-process e nunca executa bin/ca,
# então nenhum teste atual enxerga o que muda dentro de um phar://: o VERSION que some
# do arquivo, o vendor/ que não foi empacotado, os Resources/ não-PHP do completion, o
# .env que dentro do PHAR aponta para um caminho que não existe. Foi assim que 16 PHARs
# quebrados foram publicados sem ninguém notar. Este script roda contra o artefato final
# e é o que impede a regressão de voltar.
#
#   1. --version bate com o arquivo VERSION e não é 'unknown'
#   2. sem credenciais: `list --format=json` degrada e ainda expõe os comandos de config
#   3. `config path` resolve para ~/.config/conta-azul-cli/.env
#   4. `completion bash` produz saída
#   5. credenciais via CA_CLI_ENV_FILE (candidato 1, o escape hatch explícito)
#   6. credenciais via ~/.config/conta-azul-cli/.env (candidato 3, o binário global)
#
# A ordem é significativa: veja o comentário do caso 6 antes de reorganizar.
#
# Uso: tools/smoke-test.sh build/conta-azul-cli.phar

set -euo pipefail

PHAR="${1:-}"

if [ -z "${PHAR}" ]; then
  echo "Uso: $(basename "${BASH_SOURCE[0]}") <caminho-do-phar>" >&2
  exit 64
fi

if [ ! -f "${PHAR}" ]; then
  echo "ERRO: PHAR não encontrado em '${PHAR}'." >&2
  exit 1
fi

# Caminho absoluto: os testes rodam a partir de um diretório temporário, de propósito.
PHAR="$(cd -- "$(dirname -- "${PHAR}")" && pwd)/$(basename -- "${PHAR}")"

REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
EXPECTED_VERSION="$(tr -d '[:space:]' < "${REPO_ROOT}/VERSION")"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "${WORKDIR}"' EXIT

# HOME isolado: numa máquina de desenvolvimento existe ~/.config/conta-azul-cli/.env com
# credenciais reais, e ele faria os testes de "modo degradado" passarem por engano — ou,
# pior, falharem só no CI. Com HOME apontando para um diretório vazio, o comportamento é
# idêntico aqui e lá.
FAKE_HOME="${WORKDIR}/home"
mkdir -p "${FAKE_HOME}"

FAILURES=0

pass() {
  printf 'ok    %s\n' "$1"
}

fail() {
  printf 'FALHA %s\n' "$1" >&2
  if [ -n "${2:-}" ]; then
    printf '      %s\n' "$2" >&2
  fi
  FAILURES=$((FAILURES + 1))
}

# Executa o PHAR sem nenhuma credencial no ambiente e com HOME isolado.
# CA_CLI_ENV_FILE também sai do ambiente para não contaminar os casos 1-4.
run_clean() {
  env -u CA_CLIENT_ID \
      -u CA_CLIENT_SECRET \
      -u CA_BOOTSTRAP_REFRESH_TOKEN \
      -u CA_CLI_ENV_FILE \
      -u CA_CLI_TOKEN_PATH \
      HOME="${FAKE_HOME}" \
      php "${PHAR}" "$@"
}

# Idem, mas com CA_CLI_ENV_FILE apontando para um arquivo de credenciais fora do PHAR.
run_with_env_file() {
  env -u CA_CLIENT_ID \
      -u CA_CLIENT_SECRET \
      -u CA_BOOTSTRAP_REFRESH_TOKEN \
      -u CA_CLI_TOKEN_PATH \
      HOME="${FAKE_HOME}" \
      CA_CLI_ENV_FILE="${WORKDIR}/.env" \
      php "${PHAR}" "$@"
}

# Conta os comandos de uma saída de `list --format=json`. Sai com 2 se o JSON for inválido.
json_command_count() {
  php -r '
    $raw = file_get_contents($argv[1]);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data["commands"]) || !is_array($data["commands"])) {
        exit(2);
    }
    echo count($data["commands"]);
  ' "$1"
}

json_has_command() {
  php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    $names = array_column($data["commands"] ?? [], "name");
    exit(in_array($argv[2], $names, true) ? 0 : 1);
  ' "$1" "$2"
}

echo "Smoke test de ${PHAR}"
echo

# ---------------------------------------------------------------------------
# 1. --version bate com o arquivo VERSION e não é 'unknown'.
#    ContaAzulApplication::version() lê __DIR__ . '/../VERSION' e cai em silêncio
#    para a string 'unknown' quando o arquivo não foi empacotado (regressão 93bfee7).
# ---------------------------------------------------------------------------
status=0
version_output="$(run_clean --version --no-ansi 2>&1)" || status=$?
reported_version="$(printf '%s' "${version_output}" | head -n1 | awk '{print $NF}')"

if [ "${status}" -ne 0 ]; then
  fail "1. --version" "saiu com status ${status}: ${version_output}"
elif [ "${reported_version}" = 'unknown' ]; then
  fail "1. --version" "VERSION não foi empacotado no PHAR (versão reportada: 'unknown')"
elif [ "${reported_version}" != "${EXPECTED_VERSION}" ]; then
  fail "1. --version" "esperado '${EXPECTED_VERSION}', obtido '${reported_version}'"
else
  pass "1. --version reporta ${reported_version}, igual ao arquivo VERSION"
fi

# ---------------------------------------------------------------------------
# 2. Sem credenciais: `list --format=json` produz JSON válido, em modo degradado,
#    e os comandos de config continuam registrados — são justamente eles que
#    permitem sair do modo degradado.
# ---------------------------------------------------------------------------
status=0
run_clean list --format=json --no-ansi --no-interaction > "${WORKDIR}/list-degraded.json" 2> "${WORKDIR}/list-degraded.err" || status=$?

if [ "${status}" -ne 0 ]; then
  fail "2. list --format=json sem credenciais" "saiu com status ${status}: $(head -n3 "${WORKDIR}/list-degraded.err")"
else
  count=0
  json_status=0
  count="$(json_command_count "${WORKDIR}/list-degraded.json")" || json_status=$?

  if [ "${json_status}" -ne 0 ]; then
    fail "2. list --format=json sem credenciais" "saída não é um JSON com a chave 'commands'"
  elif [ "${count}" -ge 30 ]; then
    fail "2. list --format=json sem credenciais" "esperado o modo degradado, mas ${count} comandos foram listados"
  else
    missing=''
    for command_name in 'config init' 'config path' 'config set' 'config show'; do
      json_has_command "${WORKDIR}/list-degraded.json" "${command_name}" || missing="${missing} '${command_name}'"
    done

    if [ -n "${missing}" ]; then
      fail "2. list --format=json sem credenciais" "comandos ausentes no modo degradado:${missing}"
    else
      pass "2. list --format=json sem credenciais: JSON válido, ${count} comandos, config presente"
    fi
  fi
fi

# ---------------------------------------------------------------------------
# 3. `config path` funciona sem credenciais e resolve para ~/.config/conta-azul-cli/.env.
#    Dentro do PHAR o candidato '<raiz do repo>/.env' vira phar://.../.env e é pulado,
#    então o caminho do HOME é o que sobra — e é o que torna o binário global viável.
# ---------------------------------------------------------------------------
status=0
config_path_output="$(run_clean config path --no-ansi --no-interaction 2>&1)" || status=$?

if [ "${status}" -ne 0 ]; then
  fail "3. config path" "saiu com status ${status}: ${config_path_output}"
elif ! printf '%s' "${config_path_output}" | grep -qF '/.config/conta-azul-cli/.env'; then
  fail "3. config path" "não aponta para ~/.config/conta-azul-cli/.env: ${config_path_output}"
else
  pass "3. config path resolve para ~/.config/conta-azul-cli/.env"
fi

# ---------------------------------------------------------------------------
# 4. `completion bash` produz saída.
#    DumpCompletionCommand lê vendor/symfony/console/Resources/completion.bash por
#    __DIR__; qualquer filtro de empacotamento que só aceite *.php quebra isso em silêncio.
# ---------------------------------------------------------------------------
status=0
completion_output="$(run_clean completion bash 2>&1)" || status=$?

if [ "${status}" -ne 0 ]; then
  fail "4. completion bash" "saiu com status ${status}: $(printf '%s' "${completion_output}" | head -n3)"
elif [ -z "${completion_output}" ]; then
  fail "4. completion bash" "saída vazia: os Resources/completion.* não foram empacotados"
elif ! printf '%s' "${completion_output}" | grep -qF 'complete'; then
  fail "4. completion bash" "a saída não parece um script de completion do bash"
else
  pass "4. completion bash produz o script de completion"
fi

# ---------------------------------------------------------------------------
# 5. A tese inteira: o PHAR enxerga credenciais de um arquivo fora dele.
#    Era exatamente isto que os PHARs publicados não faziam — e o único jeito de
#    provar é com um .env em disco, longe do repositório, e o CLI saindo do modo
#    degradado por causa dele.
# ---------------------------------------------------------------------------
cat > "${WORKDIR}/.env" <<'ENV'
CA_CLIENT_ID=smoke-test
CA_CLIENT_SECRET=smoke-test
ENV
chmod 0600 "${WORKDIR}/.env"

status=0
run_with_env_file list --format=json --no-ansi --no-interaction > "${WORKDIR}/list-full.json" 2> "${WORKDIR}/list-full.err" || status=$?

if [ "${status}" -ne 0 ]; then
  fail "5. CA_CLI_ENV_FILE" "saiu com status ${status}: $(head -n3 "${WORKDIR}/list-full.err")"
else
  count=0
  json_status=0
  count="$(json_command_count "${WORKDIR}/list-full.json")" || json_status=$?

  if [ "${json_status}" -ne 0 ]; then
    fail "5. CA_CLI_ENV_FILE" "saída não é um JSON com a chave 'commands'"
  elif [ "${count}" -le 80 ]; then
    fail "5. CA_CLI_ENV_FILE" "esperado mais de 80 comandos, obtido ${count}: o PHAR não leu o arquivo de credenciais"
  else
    pass "5. CA_CLI_ENV_FILE: ${count} comandos a partir de um .env fora do PHAR"
  fi
fi

# ---------------------------------------------------------------------------
# 6. O caso do binário global, que é o motivo de tudo isto existir.
#    O caso 5 prova o escape hatch (CA_CLI_ENV_FILE, candidato 1); este prova o
#    caminho que um usuário de verdade percorre: `ca config init` grava em
#    ~/.config/conta-azul-cli/.env e, a partir daí, `ca` funciona de qualquer
#    diretório sem nenhuma variável de ambiente. É o candidato 3 sozinho — se ele
#    regredir e este teste não existir, todos os outros continuam verdes e a gente
#    republica exatamente a classe de bug que este trabalho veio consertar.
#
#    ESTE TESTE PRECISA SER O ÚLTIMO. Os casos 2 e 3 exigem o modo degradado sob o
#    mesmo FAKE_HOME; criar o arquivo abaixo antes deles daria credenciais ao CLI
#    e faria os dois falharem. Não reordene.
# ---------------------------------------------------------------------------
mkdir -p "${FAKE_HOME}/.config/conta-azul-cli"
cat > "${FAKE_HOME}/.config/conta-azul-cli/.env" <<'ENV'
CA_CLIENT_ID=smoke-test
CA_CLIENT_SECRET=smoke-test
ENV
chmod 0600 "${FAKE_HOME}/.config/conta-azul-cli/.env"

status=0
# run_clean, e não run_with_env_file: com CA_CLI_ENV_FILE fora do ambiente, só o
# candidato 3 pode estar fornecendo as credenciais.
run_clean list --format=json --no-ansi --no-interaction > "${WORKDIR}/list-home.json" 2> "${WORKDIR}/list-home.err" || status=$?

if [ "${status}" -ne 0 ]; then
  fail "6. ~/.config/conta-azul-cli/.env" "saiu com status ${status}: $(head -n3 "${WORKDIR}/list-home.err")"
else
  count=0
  json_status=0
  count="$(json_command_count "${WORKDIR}/list-home.json")" || json_status=$?

  if [ "${json_status}" -ne 0 ]; then
    fail "6. ~/.config/conta-azul-cli/.env" "saída não é um JSON com a chave 'commands'"
  elif [ "${count}" -le 80 ]; then
    fail "6. ~/.config/conta-azul-cli/.env" "esperado mais de 80 comandos, obtido ${count}: o PHAR não leu o .env do diretório home"
  else
    pass "6. ~/.config/conta-azul-cli/.env: ${count} comandos sem nenhuma variável de ambiente"
  fi
fi

echo

if [ "${FAILURES}" -ne 0 ]; then
  echo "${FAILURES} verificação(ões) falharam." >&2
  exit 1
fi

echo 'Todas as verificações passaram.'
