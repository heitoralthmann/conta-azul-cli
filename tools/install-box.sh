#!/usr/bin/env bash
#
# Baixa o Box (empacotador de PHAR) numa versão fixa e confere o SHA-256 antes de usar.
#
# O Box não pode entrar em require-dev: o PHAR precisa de um vendor/ construído com
# `composer install --no-dev`, e nesse vendor/ o Box não estaria presente. Então ele é
# baixado como binário — e, como todo o resto da cadeia de suprimentos deste repo (as
# GitHub Actions são pinadas por SHA de commit), a versão e o hash ficam cravados aqui.
#
# CI e máquina local rodam este mesmo script, então o build é reproduzível.
#
# Uso: tools/install-box.sh   (idempotente; não rebaixa nada se o hash já bater)

set -euo pipefail

# Box 4.7.0, publicado em 2026-03-18.
# SHA-256 do asset box.phar, conforme o digest da própria API do GitHub:
#   gh api repos/box-project/box/releases/tags/4.7.0 --jq '.assets[] | select(.name == "box.phar") | .digest'
BOX_VERSION='4.7.0'
BOX_SHA256='3d390eeaec33288098fe83f8a54c60cc575cb6be295f38ff4482b4b4f26f8d52'
BOX_URL="https://github.com/box-project/box/releases/download/${BOX_VERSION}/box.phar"

REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${REPO_ROOT}/build"
BOX_PHAR="${BUILD_DIR}/box.phar"

# shasum existe no macOS; sha256sum é o padrão no Linux/CI.
checksum_of() {
  if command -v shasum > /dev/null 2>&1; then
    shasum -a 256 "$1" | cut -d' ' -f1
  elif command -v sha256sum > /dev/null 2>&1; then
    sha256sum "$1" | cut -d' ' -f1
  else
    echo "ERRO: nem shasum nem sha256sum estão disponíveis." >&2
    exit 1
  fi
}

if [ -f "${BOX_PHAR}" ] && [ "$(checksum_of "${BOX_PHAR}")" = "${BOX_SHA256}" ]; then
  echo "Box ${BOX_VERSION} já presente em ${BOX_PHAR} (checksum confere)."
  exit 0
fi

mkdir -p "${BUILD_DIR}"

TMP_PHAR="$(mktemp "${BUILD_DIR}/box.phar.XXXXXX")"
trap 'rm -f "${TMP_PHAR}"' EXIT

echo "Baixando Box ${BOX_VERSION}..."
curl --fail --location --silent --show-error --output "${TMP_PHAR}" "${BOX_URL}"

ACTUAL_SHA256="$(checksum_of "${TMP_PHAR}")"
if [ "${ACTUAL_SHA256}" != "${BOX_SHA256}" ]; then
  echo "ERRO: checksum do box.phar não confere." >&2
  echo "  esperado: ${BOX_SHA256}" >&2
  echo "  obtido:   ${ACTUAL_SHA256}" >&2
  exit 1
fi

chmod 0755 "${TMP_PHAR}"
mv "${TMP_PHAR}" "${BOX_PHAR}"
trap - EXIT

echo "Box ${BOX_VERSION} instalado em ${BOX_PHAR} (SHA-256 verificado)."
