<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/** Provides authenticated access tokens and manages their local lifecycle. */
interface AccessTokenServiceInterface
{


  /** Returns a usable token, refreshing and persisting it when necessary. */
  public function getValidAccessToken(): string;


  /** Refreshes the cached token after an API request receives HTTP 401. */
  public function refreshAfter401(): string;


  /** Deletes the locally cached credentials. */
  public function logout(): void;


}
