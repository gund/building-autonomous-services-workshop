<?php

declare(strict_types=1);

namespace Dashboard;

use Common\Persistence\IdentifiableObject;

/**
 * Before you can purchase or sell any product, you first need to register add
 * it to the catalog. It needs an ID and a name.
 */
final class Product implements IdentifiableObject
{
    private string $id;
    private string $name;
    private int $stock;

    public function __construct(string $id, string $name, int $stock = 0)
    {
        $this->id = $id;
        $this->name = $name;
        $this->stock = $stock;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    public function updateStock(int $newStock): void
    {
        $this->stock = $newStock;
    }
}
