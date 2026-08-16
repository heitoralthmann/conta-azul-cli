<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Api\PaginationValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/** Reads and validates the common pagination options exposed by list commands. */
final readonly class PaginationOptions
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PAGE_SIZE = 50;


    /**
     * @param int $page One-based page number
     * @param int $pageSize Number of records requested per page
     */
    public function __construct(
        private int $page,
        private int $pageSize,
    ) {
    }


    /** Adds the shared pagination options to a command definition. */
    public static function configure(Command $command): void {
        $command
            ->addOption('pagina', NULL, InputOption::VALUE_REQUIRED, 'Número da página', (string) self::DEFAULT_PAGE)
            ->addOption('tamanho-pagina', NULL, InputOption::VALUE_REQUIRED, 'Itens por página', (string) self::DEFAULT_PAGE_SIZE);
    }


    /**
     * Creates validated pagination options from a Symfony command input.
     *
     * @throws \ContaAzulCli\Error\CliException when the page size is unsupported
     */
    public static function fromInput(InputInterface $input, PaginationValidator $validator): self {
        $pageRaw = $input->hasOption('pagina') ? $input->getOption('pagina') : NULL;
        $pageSizeRaw = $input->hasOption('tamanho-pagina') ? $input->getOption('tamanho-pagina') : NULL;
        $page = is_numeric($pageRaw) ? (int) $pageRaw : self::DEFAULT_PAGE;
        $pageSize = is_numeric($pageSizeRaw) ? (int) $pageSizeRaw : self::DEFAULT_PAGE_SIZE;
        $validator->validatePageSize($pageSize);

        return new self($page, $pageSize);
    }


    /** Returns the requested one-based page number. */
    public function page(): int {
        return $this->page;
    }


    /** Returns the requested number of records per page. */
    public function pageSize(): int {
        return $this->pageSize;
    }


}
