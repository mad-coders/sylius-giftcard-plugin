<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Marks existing products as gift card products.
 *
 * Separate from a product fixture on purpose: Sylius' own `product` fixture is what a demo store
 * uses, and reimplementing it here to add one boolean would mean maintaining a copy of it. This
 * flags products that already exist, so a demo can say "and these two are gift cards" without
 * giving up anything Sylius' fixture does.
 *
 * Without it there is no way to demonstrate *selling* a gift card, which is half the plugin.
 *
 * Products can be named explicitly, or - for a demo, where the catalogue's codes depend on which
 * Sylius version generated it - simply counted.
 */
final class GiftCardProductFixture extends AbstractFixture
{
    /** @param ProductRepositoryInterface<ProductInterface> $productRepository */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ObjectManager $objectManager,
    ) {
    }

    public function getName(): string
    {
        return 'madcoders_gift_card_product';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($this->productsToFlag($options) as $product) {
            $product->setGiftCard(true);
        }

        $this->objectManager->flush();
    }

    /**
     * @param array<mixed> $options
     *
     * @return list<ProductInterface>
     */
    private function productsToFlag(array $options): array
    {
        /** @var list<string> $codes */
        $codes = $options['products'] ?? [];

        if ([] !== $codes) {
            return array_map(fn (string $code): ProductInterface => $this->productByCode($code), $codes);
        }

        /** @var int $count */
        $count = $options['count'] ?? 0;

        if ($count < 1) {
            return [];
        }

        // Ordered by code so the same products are picked on every run - a demo where the gift card
        // products move around between reloads is worse than no demo.
        /** @var list<ProductInterface> $products */
        $products = $this->productRepository->findBy([], ['code' => 'ASC'], $count);

        if (count($products) < $count) {
            throw new \InvalidArgumentException(sprintf(
                'Only %d products exist, so %d cannot be marked as gift card products. Load the product '
                . 'fixtures first (this fixture runs at a negative priority for that reason).',
                count($products),
                $count,
            ));
        }

        return $products;
    }

    private function productByCode(string $code): ProductInterface
    {
        $product = $this->productRepository->findOneByCode($code);

        if (!$product instanceof ProductInterface) {
            // Loud rather than silent: a typo here produces a demo store where buying a gift card
            // mysteriously does nothing, which is a miserable thing to debug.
            throw new \InvalidArgumentException(sprintf(
                'Cannot mark product "%s" as a gift card product: no such product. Load the product '
                . 'fixtures first (this fixture runs at a negative priority for that reason).',
                $code,
            ));
        }

        return $product;
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('products')
                    ->info('Product codes to mark as gift card products.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->integerNode('count')
                    ->info('Mark this many products instead, lowest code first. Ignored when `products` is given.')
                    ->min(0)
                ->end()
            ->end()
        ;
    }
}
