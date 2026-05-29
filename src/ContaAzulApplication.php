<?php

declare(strict_types=1);

namespace ContaAzulCli;

use Symfony\Component\Console\Application;

class ContaAzulApplication extends Application
{
    private const APP_NAME = 'ca';
    private const APP_VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);
    }
}
