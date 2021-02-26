<?php

declare(strict_types=1);

use Common\EventType;
use Common\Persistence\Database;
use Common\Persistence\KeyValueStore;
use Common\Stream\Stream;
use Stock\Balance;
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
            case EventType::ProductCreated:
                /** @var Balance */
                $balance = new Balance($data['id']);
                Database::persist($balance);
                break;
            case EventType::PurchaseGoodsReceived:
                $productId = $data['productId'];
                $quantity = (int)$data['quantity'];

                /** @var Balance */
                $balance = Database::retrieve(Balance::class, $productId);

                $reservation = $balance->processReceivedGoodsAndRetryRejectedReservations($quantity);

                Database::persist($balance);

                Stream::produce(
                    EventType::StockLevelChanged,
                    [
                        'productId' => $productId,
                        'stock' => $balance->stockLevel(),
                    ]
                );

                if ($reservation) {
                    Stream::produce(EventType::StockReservationCreated, [
                        'productId' => $productId,
                        'reservationId' => $reservation->reservationId(),
                        'quantity' => $reservation->quantity(),
                    ]);
                }
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
