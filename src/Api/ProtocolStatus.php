<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/** Terminal statuses reported by the Conta Azul asynchronous protocol endpoint. */
enum ProtocolStatus: string
{
  case Success = 'SUCCESS';
  case Error   = 'ERROR';
}
