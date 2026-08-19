<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/**
 * How a paginated endpoint decides which page sizes it accepts.
 *
 * The API disagrees with itself here, so the shape of the rule — not just
 * its ceiling — has to be per-endpoint. Measured against production on
 * 2026-08-19.
 */
enum PageSizeRule
{
  /**
   * Only the discrete steps 10, 20, 50, 100, 200, 500 and 1000 are accepted.
   *
   * These endpoints answer `400` with "O tamanho da página deve ser um dos
   * seguintes valores: …" for anything in between, so `15` is as invalid
   * as `99999`.
   */
  case DiscreteSizes;

  /**
   * Any integer from 1 up to the endpoint's ceiling is accepted.
   *
   * `GET /v1/captura/documentos/status` takes 1 through 20 — `1`, `5` and
   * `15` all answer `200`, and only `21` and above answer `400` ("O valor
   * do atributo 'tamanho_pagina' deve ser no máximo 20").
   */
  case AnySizeUpToMax;
}
