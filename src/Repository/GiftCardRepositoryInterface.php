<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Repository;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface GiftCardRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?GiftCardInterface;

    public function findOneByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardInterface;

    /**
     * The cards this customer bought, newest first.
     *
     * @return array<array-key, GiftCardInterface>
     */
    public function findByPurchaser(CustomerInterface $customer): array;

    /**
     * The cards this customer redeems, newest first. This is the list that answers "what is left on
     * my gift cards" - see docs/adr-log/0005-two-customer-links-and-transaction-ledger.md.
     *
     * @return array<array-key, GiftCardInterface>
     */
    public function findByRedeemer(CustomerInterface $customer): array;

    public function codeExists(string $code): bool;
}
