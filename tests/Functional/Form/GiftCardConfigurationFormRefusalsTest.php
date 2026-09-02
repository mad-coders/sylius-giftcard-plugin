<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Functional\Form;

use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCardConfiguration;

/**
 * What the channel configuration form refuses, asked of the form the container actually builds.
 *
 * This form had the same validation-group mismatch as the gift card one and nothing covered it at
 * all - not Behat, not PHPUnit - so the constraint it carries could be inert, doubled or missing
 * without anything going red. See docs/adr-log/0017-resource-forms-validate-with-default-too.md.
 *
 * The assertions are on the exact list of messages, not on "an error appeared". A duplicate is a
 * real failure mode here: the operator is shown the same sentence twice and concludes the form is
 * broken, and every assertion phrased as "contains" passes while they do.
 */
final class GiftCardConfigurationFormRefusalsTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();

        $formFactory = self::getContainer()->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);
        $this->formFactory = $formFactory;
    }

    public function testACodeLengthBelowTheMinimumIsRefusedOnceAndOnlyOnce(): void
    {
        // Once. The model raises a short code length to the minimum as a backstop, which was long
        // believed to make the field constraint inert - it does not, because a field constraint
        // validates the child form's own reverse-transformed value and never sees the model. A
        // hand-made FormError alongside it therefore showed the operator the same sentence twice.
        $form = $this->submitConfigurationForm(['codeLength' => '4']);

        self::assertFalse($form->isValid());
        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card_configuration.code_length.too_short'],
            $this->errorTemplatesOn($form, 'codeLength'),
        );
    }

    public function testTheMinimumCodeLengthItselfIsAccepted(): void
    {
        // The boundary, because `GreaterThanOrEqual` and `GreaterThan` differ by exactly this case
        // and a shop set to the documented minimum must not be told it is too short.
        $form = $this->submitConfigurationForm(['codeLength' => '12']);

        self::assertTrue($form->isValid(), 'The form refused a configuration there is nothing wrong with: ' . $this->allErrorTemplates($form));
    }

    public function testAValidityPeriodTheShopCannotActOnIsRefusedOnce(): void
    {
        // Declared on the model rather than the field, so this is the other half of the group
        // reconciliation: it proves the resource group and `Default` together raise it once, not
        // twice, even though the mapping names both.
        $form = $this->submitConfigurationForm(['validityPeriod' => '1 yaer']);

        self::assertFalse($form->isValid());
        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card_configuration.validity_period.unparseable'],
            $this->errorTemplatesOn($form, 'validityPeriod'),
        );
    }

    public function testABlankValidityPeriodIsRefusedOnce(): void
    {
        $form = $this->submitConfigurationForm(['validityPeriod' => '']);

        self::assertFalse($form->isValid());
        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card_configuration.validity_period.not_blank'],
            $this->errorTemplatesOn($form, 'validityPeriod'),
        );
    }

    /**
     * Submits the configuration form, with anything the test did not name filled in acceptably.
     *
     * @param array<string, string> $overrides
     */
    private function submitConfigurationForm(array $overrides): FormInterface
    {
        $form = $this->formFactory->create(
            GiftCardConfigurationType::class,
            new GiftCardConfiguration(),
            // Off because there is no session here to hold a token. It is the browser's protection,
            // not a rule about gift cards.
            ['csrf_protection' => false],
        );

        $form->submit(array_merge([
            'channel' => '',
            'codePrefix' => '',
            'codeLength' => '12',
            'validityPeriod' => '1 year',
            'tenderMode' => 'goods_only',
            'saleMode' => 'sellable',
            'amountMode' => GiftCardAmountMode::Fixed->value,
            'amountPresets' => '',
            'minimumAmount' => '',
            'maximumAmount' => '',
            'enabled' => '1',
        ], $overrides));

        return $form;
    }

    /** @return list<string> */
    private function errorTemplatesOn(FormInterface $form, string $field): array
    {
        $templates = [];

        foreach ($form->get($field)->getErrors() as $error) {
            $templates[] = $error->getMessageTemplate();
        }

        return $templates;
    }

    private function allErrorTemplates(FormInterface $form): string
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $errors[] = sprintf('%s: %s', $error->getOrigin()?->getName() ?? 'form', $error->getMessageTemplate());
        }

        return [] === $errors ? 'no errors at all' : implode('; ', $errors);
    }
}
