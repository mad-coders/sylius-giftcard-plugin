<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Webmozart\Assert\Assert;

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

    /**
     * @Then the product :product should still be a gift card product
     */
    public function theProductShouldStillBeAGiftCardProduct(ProductInterface $product): void
    {
        // Re-read rather than trust the in-memory object: the bug this guards against is a form
        // submit writing false, which only shows up once the entity comes back from the database.
        $this->productManager->clear();

        $reloaded = $this->productManager->getRepository($product::class)->find($product->getId());
        Assert::isInstanceOf($reloaded, GiftCardProductInterface::class);
        Assert::true(
            $reloaded->isGiftCard(),
            'The product stopped being a gift card product - most likely a form submit cleared the flag.',
        );
    }
}
