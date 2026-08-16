## O que muda e por quê

<!-- Descreva a mudança e a motivação. Se corrige um bug, referencie a issue. -->

## Checklist

- [ ] `composer lint` passa (cobre `bin/ca`)
- [ ] `vendor/bin/phpunit` passa
- [ ] `vendor/bin/phpstan analyse src/ --level=max` sem erros
- [ ] Se mudou a integração com a API: [`API_COVERAGE.md`](../API_COVERAGE.md) atualizado no mesmo commit
- [ ] Se adicionou/alterou um comando: [`COMMANDS.md`](../COMMANDS.md) atualizado
