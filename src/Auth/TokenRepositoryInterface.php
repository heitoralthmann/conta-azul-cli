<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/** Persists the single locally cached OAuth token. */
interface TokenRepositoryInterface
{


    /** Saves a token, replacing the previously cached value. */
    public function save(TokenData $token): void;


    /** Returns the cached token, or NULL when it is absent or invalid. */
    public function load(): ?TokenData;


    /** Removes the cached token when one exists. */
    public function delete(): void;


    /** Returns the path used for the token file. */
    public function getPath(): string;


}
