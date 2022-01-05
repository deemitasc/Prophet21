<?php

declare(strict_types=1);

namespace Ripen\Prophet21\Model;

use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;

/**
 * @inheritdoc
 */
class AppendReservations implements AppendReservationsInterface
{
    /**
     * @inheritdoc
     */
    public function execute(array $reservations): void
    {
        /**
         * Don't update reservations. Inventory sync handles MSI stock qty updates.
         * If we update reservations, we might run into a problem with double reservations
         * after an order sync from ERP.
         *
         */
    }
}
