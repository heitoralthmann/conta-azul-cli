# Instalação

- PHP **8.4+** com as extensões `mbstring`, `openssl` e `posix`
- Composer
- Uma aplicação registrada no portal de desenvolvedores da Conta Azul (`client_id` + `client_secret`)
- Uma conta Conta Azul com **plano elegível para uso da API** (veja [Solução de problemas](solucao-de-problemas.md))

# Instalação

```bash
git clone git@github.com:heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
./bin/ca list
```

> Distribuição via `composer global require` está prevista, mas o pacote ainda não foi publicado no Packagist.
