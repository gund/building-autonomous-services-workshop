<?php

declare(strict_types=1);

use Common\EventType;
use Common\Persistence\Database;
use Common\Persistence\KeyValueStore;
use Common\Stream\Stream;
use Common\Web\HttpApi;
use PhpParser\Node\Stmt\Break_;
use Purchase\PurchaseOrderId;
use Sales\OrderStatus;
use Sales\SalesOrder;
use Symfony\Component\ErrorHandler\Debug;

require __DIR__ . '/../../vendor/autoload.php';

Debug::enable();

/*
 * This is a demo projector which consumes every message from the stream,
 * starting *at a given index*. It uses the `KeyValueStore` to remember the
 * index at which to start consuming when the consumer has to be restarted
 * (possibly after a crash). This effectively makes this process manager
 * consume every message *only once*. The usual reason for this is that a
 * process manager produces side-effects; it may send a request to some other
 * service, or produce new events, which shouldn't happen again during a
 * restart of the service.
 *
 * If you want to visually keep track of the stream, run:
 *
 *     bin/logs
 */

/*
 * Every process manager should store its current index under a unique key
 * in the `KeyValueStore`, otherwise it would inherit the "start at index"
 * value from another consumer.
 */
$startAtIndexKey = $startAtIndexKey = basename(__DIR__) . '_start_at_index';

$startAtIndex = KeyValueStore::get($startAtIndexKey) ?: 0;
echo 'Start consuming at index: ' . (string)$startAtIndex;

// start consuming at the given index, and keep consuming incoming messages
Stream::consume(
    function (string $messageType, $data) use ($startAtIndexKey) {
        // do something with the message, or decide to ignore it based on its type
        echo $messageType . ': ' . json_encode($data) . "\n";

        switch ($messageType) {
            case EventType::SalesOrderCreated:
                $salesOrderId = $data['id'];
                $productId = $data['productId'];
                $quantity = (int)$data['quantity'];

                $orderStatus = new OrderStatus($salesOrderId);
                Database::persist($orderStatus);

                HttpApi::postFormData(
                    'http://stock_web/makeStockReservation',
                    [
                        'reservationId' => $salesOrderId,
                        'productId' => $productId,
                        'quantity' => $quantity,
                    ]
                );
                break;
            case EventType::StockReservationCreated:
                $reservationId = $data['reservationId'];

                /** @var SalesOrder */
                $salesOrder = Database::retrieve(SalesOrder::class, $reservationId);
                $salesOrder->markAsDeliverable();

                Database::persist($salesOrder);
                break;
            case EventType::StockReservationRejected:
                $reservationId = $data['reservationId'];
                $productId = $data['productId'];
                $quantity = (int)$data['quantity'];
                $purchaseId = PurchaseOrderId::create()->asString();

                /** @var OrderStatus */
                $orderStatus = Database::retrieve(OrderStatus::class, $reservationId);
                $orderStatus->setPurchaseOrderId($purchaseId);

                HttpApi::postFormData(
                    "http://purchase_web/createPurchaseOrder",
                    [
                        'purchaseOrderId' => $purchaseId,
                        'productId' => $productId,
                        'quantity' => $quantity,
                    ]
                );

                Database::persist($orderStatus);
                break;
        }

        /*
         * After processing the message successfully, we need to increase the
         * "start at index value". If an exception occurs, the process manager
         * will die, and when restarted will try to process the same message
         * again.
         */
        KeyValueStore::incr($startAtIndexKey);
    },
    $startAtIndex
);
