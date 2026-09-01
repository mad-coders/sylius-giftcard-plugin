<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Functional\Translation;

use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardAmountType;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every message the plugin's constraints carry has to resolve in the `validators` catalogue.
 *
 * Symfony translates constraint violations in `validators`, never in `messages`. A message key put
 * in the wrong catalogue does not fail, warn or log: the translator hands the key straight back,
 * and the administrator is shown `madcoders_sylius_gift_card.gift_card.amount.positive` where a
 * sentence should be. Five keys sat like that until issue #37, because a form that answers with
 * *something* looks like a form that works - to a person skimming, and to any test that only
 * asserts an error appeared.
 *
 * So this walks the places a constraint can be declared and asserts none of their messages
 * translate to themselves. It is deliberately a walk rather than a list: the next constraint is
 * covered the day it is written, by whoever writes it, without them knowing this test exists.
 *
 * Known gap: form type *extensions*, which can only be reached by building the type they extend -
 * and Sylius' cart item type needs a product, a channel and a cart to build. The one the plugin has
 * carries `cart_item.message.too_long`, which the model's own attribute declares as well, so the
 * key is at least written down twice where a reviewer sees it.
 */
final class ConstraintMessagesLiveInTheValidatorsDomainTest extends KernelTestCase
{
    /** Only the plugin's own messages. Sylius' and Symfony's are their own projects' problem. */
    private const KEY_PREFIX = 'madcoders_sylius_gift_card.';

    private const VALIDATORS_DOMAIN = 'validators';

    private TranslatorInterface $translator;

    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();

        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        $this->translator = $translator;

        $formFactory = self::getContainer()->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);
        $this->formFactory = $formFactory;
    }

    public function testEveryConstraintMessageResolvesToSomethingOtherThanItsOwnKey(): void
    {
        $keys = array_unique(array_merge(
            $this->messagesOnConstraintClasses(),
            $this->messagesInValidationMappings(),
            $this->messagesOnFormTypes(),
        ));
        sort($keys);

        // A collector that silently found nothing would make every assertion below vacuous, which
        // is the one way this test could rot into decoration.
        self::assertGreaterThan(5, count($keys), 'The walk found almost no constraint messages, so it is no longer walking anything.');

        $untranslated = [];

        foreach ($keys as $key) {
            if ($this->translator->trans($key, [], self::VALIDATORS_DOMAIN) === $key) {
                $untranslated[] = $key;
            }
        }

        self::assertSame(
            [],
            $untranslated,
            "These constraint messages render as their own key. They belong in translations/validators.en.yaml:\n"
            . implode("\n", $untranslated),
        );
    }

    /**
     * The plugin's own Constraint classes, which declare their messages as public properties.
     *
     * @return list<string>
     */
    private function messagesOnConstraintClasses(): array
    {
        $keys = [];

        foreach ($this->classesIn('src/Validator/Constraints') as $class) {
            if (!is_a($class, Constraint::class, true)) {
                continue;
            }

            // Read the defaults rather than instantiating: a constraint may demand required
            // options, and its messages are on the class either way.
            foreach ((new \ReflectionClass($class))->getDefaultProperties() as $value) {
                $keys = array_merge($keys, self::pluginKeysIn($value));
            }
        }

        return $keys;
    }

    /**
     * Constraints attached through `config/validation/*.xml`, whose messages are `<option>` values.
     *
     * @return list<string>
     */
    private function messagesInValidationMappings(): array
    {
        $keys = [];

        foreach (glob($this->path('config/validation') . '/*.xml') ?: [] as $file) {
            $document = simplexml_load_file($file);
            self::assertNotFalse($document, sprintf('Could not read the validation mapping %s.', $file));

            // Every option value in the document, at any depth. Only messages are ever a plugin
            // translation key, so no filtering by option name is needed - and none is wanted, since
            // the next constraint may well name its message something other than "message".
            foreach ($document->xpath('//*') ?: [] as $node) {
                $keys = array_merge($keys, self::pluginKeysIn((string) $node));
            }
        }

        return $keys;
    }

    /**
     * Constraints declared inline on a form field, which is where four of the five keys in issue #37
     * lived. They exist only on a built form, so the forms are built.
     *
     * @return list<string>
     */
    private function messagesOnFormTypes(): array
    {
        $keys = [];

        foreach ($this->classesIn('src/Form/Type') as $formType) {
            $form = $this->formFactory->create($formType, null, $this->optionsFor($formType));
            $keys = array_merge($keys, self::messagesOnForm($form));
        }

        return $keys;
    }

    /** @return list<string> */
    private static function messagesOnForm(FormInterface $form): array
    {
        $keys = [];
        $config = $form->getConfig();

        foreach ((array) $config->getOption('constraints', []) as $constraint) {
            if (!$constraint instanceof Constraint) {
                continue;
            }

            foreach (get_object_vars($constraint) as $value) {
                $keys = array_merge($keys, self::pluginKeysIn($value));
            }
        }

        // A transformer that cannot read what was typed raises this one, and Symfony resolves it in
        // `validators` like any other violation.
        $keys = array_merge($keys, self::pluginKeysIn($config->getOption('invalid_message')));

        foreach ($form->all() as $child) {
            $keys = array_merge($keys, self::messagesOnForm($child));
        }

        return $keys;
    }

    /**
     * The options a form type cannot be built without.
     *
     * A new type that needs options and is not listed here makes this test throw rather than skip
     * it. That is deliberate: a constraint nobody walks is a constraint nobody notices is broken.
     *
     * @param class-string $formType
     *
     * @return array<string, mixed>
     */
    private function optionsFor(string $formType): array
    {
        if (GiftCardAmountType::class !== $formType) {
            return [];
        }

        // Presets *and* a range, so both halves of the field are built and every constraint the
        // type can attach is reached.
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::PresetsAndRange);
        $configuration->setAmountPresets([2500, 5000]);
        $configuration->setMinimumAmount(1000);
        $configuration->setMaximumAmount(50000);

        return [
            'configuration' => $configuration,
            'currency_code' => 'USD',
            'locale_code' => 'en_US',
        ];
    }

    /**
     * The classes declared by the PHP files in a directory of `src/`.
     *
     * @param string $directory a path below the repository root, starting with `src/`
     *
     * @return list<class-string>
     */
    private function classesIn(string $directory): array
    {
        $classes = [];
        $namespace = 'Madcoders\SyliusGiftCardPlugin\\' . str_replace('/', '\\', substr($directory, 4)) . '\\';

        foreach (glob($this->path($directory) . '/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = $namespace . basename($file, '.php');

            if (!class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        self::assertNotSame([], $classes, sprintf('No classes were found in %s.', $directory));

        return $classes;
    }

    /** @return list<string> */
    private static function pluginKeysIn(mixed $value): array
    {
        return is_string($value) && str_starts_with($value, self::KEY_PREFIX) ? [$value] : [];
    }

    private function path(string $relative): string
    {
        return \dirname(__DIR__, 3) . '/' . $relative;
    }
}
