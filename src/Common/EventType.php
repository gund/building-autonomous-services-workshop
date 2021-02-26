<?php

declare(strict_types=1);

namespace Common;

final class EventType
{
  public const ProductCreated = 'product:created';

  public const PurchaseGoodsReceived = 'purchase:goods-received';

  public const SalesOrderDelivered = 'sales:order-delivered';
  public const SalesOrderCreated = 'sales:order-created';

  public const StockLevelChanged = 'stock:level-changed';
  public const StockReservationCreated = 'stock:reservation-created';
  public const StockReservationRejected = 'stock:reservation-rejected';
}
