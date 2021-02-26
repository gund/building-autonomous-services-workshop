<?php

declare(strict_types=1);

namespace Stock;

use Common\EventType;
use Common\Persistence\Database;
use Common\Render;
use Common\Stream\Stream;
use Common\Web\HttpApi;

final class StockApplication
{
    public function stockLevelsController(): void
    {
        $stockLevels = $this->calculateStockLevels();

        Render::jsonOrHtml($stockLevels);
    }

    private function calculateStockLevels(): array
    {
        $stockLevels = [];

        $balances = Database::retrieveAll(Balance::class);

        foreach ($balances as $balance) {
            $stockLevels[$balance->id()] = $balance->stockLevel();
        }

        // $purchaseOrders = HttpApi::fetchDecodedJsonResponse('http://purchase_web/listPurchaseOrders');
        // foreach ($purchaseOrders as $purchaseOrder) {
        //     if (!$purchaseOrder->received) {
        //         continue;
        //     }

        //     $stockLevels[$purchaseOrder->productId] = ($stockLevels[$purchaseOrder->productId] ?? 0) + $purchaseOrder->quantity;
        // }

        // $salesOrders = HttpApi::fetchDecodedJsonResponse('http://sales_web/listSalesOrders');
        // foreach ($salesOrders as $salesOrder) {
        //     if (!$salesOrder->wasDelivered) {
        //         continue;
        //     }

        //     $stockLevels[$salesOrder->productId] = ($stockLevels[$salesOrder->productId] ?? 0) - $salesOrder->quantity;
        // }

        return $stockLevels;
    }

    /**
     * Note: this controller will become useful in Assignment 5
     */
    public function makeStockReservationController(): void
    {
        $reservationId = $_POST['reservationId'];
        $productId = $_POST['productId'];
        $quantity = (int)$_POST['quantity'];

        /** @var Balance */
        $balance = Database::retrieve(Balance::class, $productId);

        $reservationWasAccepted = $balance->makeReservation($reservationId, $quantity);
        Database::persist($balance);

        if ($reservationWasAccepted) {
            Stream::produce(
                EventType::StockLevelChanged,
                [
                    'productId' => $balance->id(),
                    'stock' => $balance->stockLevel(),
                ]
            );
            Stream::produce(EventType::StockReservationCreated, [
                'reservationId' => $reservationId,
                'productId' => $productId,
                'quantity' => $quantity,
            ]);
        } else {
            Stream::produce(EventType::StockReservationRejected, [
                'reservationId' => $reservationId,
                'productId' => $productId,
                'quantity' => $quantity - $balance->stockLevel(),
            ]);
        }
    }
}
