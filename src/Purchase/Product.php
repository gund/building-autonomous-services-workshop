<?php

declare(strict_types=1);

namespace Purchase;

use Common\Persistence\IdentifiableObject;

/**
 * Before you can purchase or sell any product, you first need to register add
 * it to the catalog. It needs an ID and a name.
 */
final class Product implements IdentifiableObject
{
    private string $id;
    private string $name;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
