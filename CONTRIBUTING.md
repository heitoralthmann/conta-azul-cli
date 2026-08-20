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
atualize [`docs/desenvolvimento/cobertura-da-api.md`](docs/desenvolvimento/cobertura-da-api.md)
**no mesmo commit**. Esse arquivo é o livro-razão de cobertura da API — se ele
divergir do código, deixa de servir ao propósito.

### A referência de comandos é gerada

`docs/referencia/` **não se edita à mão.** A metade mecânica da referência —
nomes, argumentos, opções, defaults — é lida de volta do próprio CLI, e a
metade que nenhuma introspecção conhece — o endpoint que cada comando chama, a
marca de verificação e as armadilhas descobertas exercitando — mora em
`docs/_data/commands/<grupo>.yaml`.

Ao adicionar ou alterar um comando:

```bash
composer docs:generate    # regenera referência, commands.json e llms*.txt
composer docs:check       # falha se o que está commitado está desatualizado
```

`docs:check` roda no CI e falha quando uma nota cita um comando ou parâmetro
que o CLI não tem mais, quando um comando existente não está documentado em
fragmento nenhum, ou quando um grupo novo não aparece no `nav` do
`mkdocs.yml`. Ou seja: a referência não pode divergir do código sem quebrar o
build.

Para ver o site localmente:

```bash
pip install -r docs/requirements.txt
mkdocs serve
```

### Nunca confie na documentação da API sem exercitar

As listagens da Conta Azul respondem `200` e **descartam em silêncio**
parâmetros de query que não reconhecem. Um filtro com o nome errado não
falha: devolve a coleção inteira, que parece resultado legítimo. Foi assim
que `produto list --codigo` e três dos quatro filtros de `servico list`
ficaram quebrados sem ninguém notar.

Antes de marcar um endpoint como verificado (`status: verified` no fragmento
do grupo), siga a receita em [Notas para quem for
estender](docs/guia/estendendo.md): baseline, parâmetro de
controle inexistente, um valor discriminante por filtro, varredura de nomes
alternativos, **dois valores** em todo filtro que aceita vários (a
codificação erra tanto quanto o nome) e uma execução **sem argumento nenhum**
(um default que o endpoint recusa não aparece de outro jeito). O mesmo vale
para nome de campo de payload, tipo de id e formato de resposta: os três já
divergiram da documentação neste projeto.

A campanha de verificação de 2026-08-15 a 2026-08-19 exercitou os nove grupos
de comandos contra a produção, e **nenhum saiu ileso**. Se você achar que um
endpoint novo é a exceção, é mais provável que o teste esteja olhando para o
lugar errado.

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
- Que stdout contém **só** o payload de sucesso (TOON por padrão; use
  `CommandTestCase::decodePayload()`).
- Que stderr contém **só** o envelope de erro, com o `kind` esperado
  (`CommandTestCase::decodeEnvelope()`).

Os testes de comando **não** passam por `ContaAzulApplication`, então `--raw`
e `--format` não mudam o formatter aí — o mesmo vale para `--debug`. Cubra
essas flags em `tests/Unit/Output/OutputFormatResolverTest.php`.

### Adicionando um formato de resposta

A saída do CLI é composta, não herdada. Para um formato novo:

1. Implemente `ContaAzulCli\Output\ResponseFormatterInterface` (`name()` +
   `format()`).
2. Registre a classe em `FormatterRegistry::withDefaults()`.
3. `--format=<name>` passa a funcionar sem mudar comandos. Atualize a
   descrição de `--format` em `ContaAzulApplication` e este contrato nos
   docs.

### Filtros de listagem exigem um teste a mais

Os testes de cliente (`tests/Unit/Api/*ClientTest.php`) **não pegam nome de
filtro errado**: o cliente repassa qualquer chave que recebe, então o teste
passa mandando `codigo` mesmo quando a API só entende `sku`.

O mapeamento *opção da CLI → parâmetro de query* mora no `$filters` de cada
`src/Command/Module/*CommandModule.php`, e é lá que os bugs de 2026-08-19
estavam. Ao adicionar ou alterar um filtro, escreva também um teste em
`tests/Integration/Command/Module/` que rode o comando com a opção e
inspecione a URL de saída — veja
`ProdutoCommandModuleTest::testListMapsCodigoOptionToTheSkuQueryParameter`
e o equivalente em `ServicoCommandModuleTest`. Filtro removido por não
existir na API merece asserção negativa, para não voltar por engano.

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
