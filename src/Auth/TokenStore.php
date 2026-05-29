<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;

final class TokenStore
{
    private string $tokenPath;

    public function __construct(Configuration $config)
    {
        $this->tokenPath = $config->getTokenPath();
    }

    public function save(TokenData $token): void
    {
        $dir = dirname($this->tokenPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $json = json_encode(
            $token->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        );
        file_put_contents($this->tokenPath, $json, LOCK_EX);
        chmod($this->tokenPath, 0600);
    }

    public function load(): ?TokenData
    {
        if (!file_exists($this->tokenPath)) {
            return null;
        }

        try {
            $content = file_get_contents($this->tokenPath);
            if ($content === false || $content === '') {
                return null;
            }

            return TokenData::fromArray(json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            return null;
        }
    }

    public function delete(): void
    {
        if (file_exists($this->tokenPath)) {
            unlink($this->tokenPath);
        }
    }

    public function getPath(): string
    {
        return $this->tokenPath;
    }
}
