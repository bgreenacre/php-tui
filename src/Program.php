<?php

declare(strict_types=1);

namespace PhpTui\PhpTui;

use Psr\Container\ContainerInterface;

class Program
{
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
