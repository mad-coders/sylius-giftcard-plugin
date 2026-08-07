<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class GiftCardProductContext implements Context
{
    public function __construct(private ObjectManager $productManager)
    {
    }

    /**
     * @Given the product :product is a gift card product
     */
    public function theProductIsAGiftCardProduct(ProductInterface $product): void
    {
        if (!$product instanceof GiftCardProductInterface) {
            throw new \RuntimeException(sprintf(
                'The application\'s Product must implement "%s" for it to be sellable as a gift card.',
                GiftCardProductInterface::class,
            ));
        }

        $product->setGiftCard(true);

        $this->productManager->flush();
    }
}
