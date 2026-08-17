# Contribuindo

Obrigado pelo interesse em contribuir com o conta-azul-cli. Este documento
registra o que hoje só existe como conhecimento tácito — a maior fonte de
atrito para um primeiro PR externo.

## Configuração

```bash
git clone git@github.com:heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
```

## Antes de abrir um PR

```bash
composer lint    # phpcs — inclui bin/ca via STDIN, veja composer.json
composer format  # phpcbf — corrige o que for automaticamente corrigível
vendor/bin/phpunit --no-coverage
vendor/bin/phpstan analyse src/ --level=max --memory-limit=1G
vendor/bin/composer-dependency-analyser
```

`composer format` é só para uso local — o CI não corrige nada, só valida.
Os workflows em `.github/workflows/` (`tests.yml`, `static-analysis.yml`,
`code-style.yml`, `security.yml`) rodam os outros quatro em cada PR. Nenhum
aceita regressão: PHPStan está em `level max` sem baseline, e o phpcs não
tem exceções.

## Idioma

Regra fixa (ver [README](README.md#idioma)):

- **Superfície humana** (README, `--help`, descrições de comando, campo
  `message` do envelope de erro): **pt-BR**.
- **Superfície de máquina** (valores de `kind`, nomes de campo do envelope,
  variáveis de ambiente, identificadores, mensagens de commit): **inglês**.

## Mudando a integração com a API

Se a mudança adiciona, remove ou altera um endpoint consumido pelo CLI,
atualize [`API_COVERAGE.md`](API_COVERAGE.md) **no mesmo commit**. Esse
arquivo é o livro-razão de cobertura da API — se ele divergir do código,
deixa de servir ao propósito.

Da mesma forma, ao adicionar ou alterar um comando, atualize
[`COMMANDS.md`](COMMANDS.md), a referência canônica de todos os comandos.

### Acompanhando mudanças na API da Conta Azul

`docs/financial-apis-openapi.yaml` é um **placeholder**: a Conta Azul ainda
não publica uma URL estável para a spec OpenAPI (veja
[Limitações conhecidas](README.md#limitações-conhecidas)). Não há automação
que detecte drift — checar manualmente de vez em quando contra o [Portal do
Desenvolvedor](https://developers.contaazul.com/aboutapis) é o processo até
que essa URL exista. Se a Conta Azul publicar uma, um workflow de
`schedule` comparando o YAML local contra o publicado volta a fazer sentido.

## Testando comandos

Comandos são testados com `Symfony\Component\Console\Tester\CommandTester`
em `tests/Integration/Command/`, espelhando a estrutura de `src/Command/`.
Os clientes de API (`FinanceiroClient`, `PessoasClient`, `ProdutosClient`,
`ServicosClient`) são classes `final`, então os testes constroem instâncias
reais desses clientes com `Symfony\Component\HttpClient\MockHttpClient` no
lugar do transporte HTTP real — veja os helpers em
`tests/Integration/Support/`. Cada teste de comando deve cobrir, no mínimo:

- Exit code (`Command::SUCCESS` ou `Command::FAILURE`).
- Que stdout contém **só** o payload JSON de sucesso.
- Que stderr contém **só** o envelope de erro, com o `kind` esperado.

## Mutation testing

`vendor/bin/infection` mede o quanto os testes realmente pegariam um bug,
mutando o código e vendo se algum teste quebra. Precisa de `pcov` ou
`xdebug` local. Não roda automaticamente no CI (mutação re-executa a suíte
por mutante, o que não escala pra "todo push") — dispare manualmente pela
aba Actions, workflow "Mutation Testing". Sem gate por enquanto:
`Auth/LoginCommand` é conhecidamente não coberto por teste de comando (veja
`tests/Integration/Command/Auth/LogoutCommandTest.php`, que documenta por
quê), então um `--min-msi` precisaria excluir esse caminho antes de fazer
sentido.

## Convenção de commits

O histórico segue [Conventional Commits](https://www.conventionalcommits.org/):
`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `style:`. Mensagens
de commit são superfície de máquina — em inglês.

## Licença

Ao contribuir, você concorda que sua contribuição será licenciada sob a
[Apache License 2.0](LICENSE) deste projeto.
