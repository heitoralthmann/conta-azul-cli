<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

/** Bulk person operations supported by {@see BatchCommand}. */
enum BatchOperation: string
{
  case Activate   = 'activate';
  case Deactivate = 'deactivate';
  case Delete     = 'delete';
}
