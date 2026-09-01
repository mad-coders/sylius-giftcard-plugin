<?php

declare(strict_types=1);

/**
 * Applies step 6 of docs/INSTALLATION.md to a host application's existing entities.
 *
 * Step 6 is a *modification*, not a file to create: Sylius Standard already ships `Order`,
 * `OrderItemUnit` and `Product`, and they usually already carry other plugins' interfaces and
 * traits. So this adds the three things the guide asks for - the imports, the interface, the trait
 * (plus the constructor call on Order) - and leaves everything else alone.
 *
 * If this script cannot make an edit it fails loudly rather than writing a half-modified class,
 * because a silently skipped trait shows up much later as a confusing mapping error.
 *
 * Usage: php apply_entity_traits.php <path-to-target-app>
 */
final class EntityTraitApplier
{
    private const array ENTITIES = [
        'src/Entity/Order/Order.php' => [
            'interface' => ['Madcoders\SyliusGiftCardPlugin\Model\OrderInterface', 'GiftCardOrderInterface'],
            'trait' => ['Madcoders\SyliusGiftCardPlugin\Model\OrderTrait', 'GiftCardOrderTrait'],
            'class' => 'Order',
            // The trait cannot initialise its own collection, because Order already has a constructor.
            'constructor_call' => 'initializeGiftCards',
        ],
        'src/Entity/Order/OrderItem.php' => [
            'interface' => ['Madcoders\SyliusGiftCardPlugin\Model\OrderItemInterface', 'GiftCardOrderItemInterface'],
            'trait' => ['Madcoders\SyliusGiftCardPlugin\Model\OrderItemTrait', 'GiftCardOrderItemTrait'],
            'class' => 'OrderItem',
            'constructor_call' => null,
        ],
        'src/Entity/Order/OrderItemUnit.php' => [
            'interface' => ['Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitInterface', 'GiftCardOrderItemUnitInterface'],
            'trait' => ['Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitTrait', 'GiftCardOrderItemUnitTrait'],
            'class' => 'OrderItemUnit',
            'constructor_call' => null,
        ],
        'src/Entity/Product/Product.php' => [
            'interface' => ['Madcoders\SyliusGiftCardPlugin\Model\ProductInterface', 'GiftCardProductInterface'],
            'trait' => ['Madcoders\SyliusGiftCardPlugin\Model\ProductTrait', 'GiftCardProductTrait'],
            'class' => 'Product',
            'constructor_call' => null,
        ],
    ];

    public function applyTo(string $applicationDirectory): void
    {
        foreach (self::ENTITIES as $path => $entity) {
            $absolute = rtrim($applicationDirectory, '/') . '/' . $path;

            if (!is_file($absolute)) {
                throw new RuntimeException(sprintf(
                    '%s does not exist. A Sylius application is expected to already have it - if this one '
                    . 'does not, the guide needs a step telling the host to create it.',
                    $path,
                ));
            }

            $source = file_get_contents($absolute);

            if (false === $source) {
                throw new RuntimeException(sprintf('Cannot read %s.', $path));
            }

            $modified = $this->apply($source, $entity);

            file_put_contents($absolute, $modified);
            echo 'extended ', $path, "\n";
        }
    }

    /** @param array{interface: array{string, string}, trait: array{string, string}, class: string, constructor_call: ?string} $entity */
    private function apply(string $source, array $entity): string
    {
        [$interfaceFqcn, $interfaceAlias] = $entity['interface'];
        [$traitFqcn, $traitAlias] = $entity['trait'];
        $class = $entity['class'];

        if (str_contains($source, $traitFqcn)) {
            return $source;
        }

        // Imports, after the last existing `use` at file level.
        $source = $this->addImports($source, [
            sprintf('use %s as %s;', $interfaceFqcn, $interfaceAlias),
            sprintf('use %s as %s;', $traitFqcn, $traitAlias),
        ]);

        // The interface and the trait in one pass: the class declaration line gains the interface,
        // and the trait joins whatever traits the class already uses, at the top of the body.
        $body = sprintf("\n{\n    use %s;\n", $traitAlias);

        $pattern = sprintf('/^(class %s extends \S+)( implements (.+?))?\n\{\n/m', preg_quote($class, '/'));

        $source = preg_replace_callback($pattern, static function (array $matches) use ($interfaceAlias, $body): string {
            $implements = isset($matches[3]) && '' !== $matches[3]
                ? $matches[3] . ', ' . $interfaceAlias
                : $interfaceAlias;

            return $matches[1] . ' implements ' . $implements . $body;
        }, $source, 1, $replacements);

        if (1 !== $replacements) {
            throw new RuntimeException(sprintf(
                'Could not extend class %s - its declaration is not in a shape this script understands.',
                $class,
            ));
        }

        if (null === $entity['constructor_call']) {
            return $source;
        }

        // Appended at the end of the class rather than next to the trait, so it reads after the
        // properties and traits like a constructor normally would.
        $constructor = sprintf(
            "\n    public function __construct()\n    {\n        parent::__construct();\n\n        \$this->%s();\n    }\n",
            $entity['constructor_call'],
        );

        $closingBrace = strrpos($source, '}');

        if (false === $closingBrace) {
            throw new RuntimeException(sprintf('Class %s has no closing brace.', $class));
        }

        return substr_replace($source, $constructor, $closingBrace, 0);
    }

    /** @param list<string> $imports */
    private function addImports(string $source, array $imports): string
    {
        $offset = strrpos($source, "\nuse ");

        if (false === $offset) {
            throw new RuntimeException('The class has no imports to append to.');
        }

        $endOfLine = strpos($source, "\n", $offset + 1);

        if (false === $endOfLine) {
            throw new RuntimeException('The class has a malformed import block.');
        }

        return substr_replace($source, "\n" . implode("\n", $imports), $endOfLine, 0);
    }
}

$applicationDirectory = $argv[1] ?? null;

if (null === $applicationDirectory) {
    fwrite(STDERR, "Usage: php apply_entity_traits.php <target-app>\n");

    exit(1);
}

(new EntityTraitApplier())->applyTo($applicationDirectory);
