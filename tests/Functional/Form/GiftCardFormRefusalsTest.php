<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Functional\Form;

use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCard;

/**
 * What the admin gift card form refuses, asked of the form the container actually builds.
 *
 * Every one of these was accepted before issue #44, and no unit test could have said so. A
 * constraint is only evaluated when its groups intersect the ones the form validates with, and the
 * form's groups come from `config/services/forms.xml` - a fact that exists nowhere in the form
 * class, nowhere in the constraint, and nowhere a test that builds either of them by hand can see.
 * See docs/adr-log/0017-resource-forms-validate-with-default-too.md.
 *
 * Behat covers the same ground through the rendered admin screen. This sits underneath it and fails
 * with a shorter explanation: the constraint, not the page.
 */
final class GiftCardFormRefusalsTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();

        $formFactory = self::getContainer()->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);
        $this->formFactory = $formFactory;
    }

    public function testABlankInitialAmountIsRefusedOnTheFieldRatherThanThrown(): void
    {
        // The model refuses a non-positive amount by throwing, and Symfony writes the submitted
        // value onto the object before it validates. So this is two failures away from a 500: the
        // constraint has to run at all, and the write has to be declined so the model is never
        // asked.
        $form = $this->submitCreateForm(['initialAmount' => '']);

        self::assertFalse($form->isValid());
        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card.initial_amount.not_blank'],
            $this->errorTemplatesOn($form, 'initialAmount'),
        );
    }

    public function testACardWorthNothingIsRefusedOnTheField(): void
    {
        $form = $this->submitCreateForm(['initialAmount' => '0']);

        self::assertFalse($form->isValid());
        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card.initial_amount.positive'],
            $this->errorTemplatesOn($form, 'initialAmount'),
        );
    }

    public function testAnExpiryDateInThePastIsRefusedOnTheField(): void
    {
        $form = $this->submitCreateForm(['expiresAt' => '2020-01-01 12:00']);

        self::assertFalse($form->isValid());
        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card.expires_at.not_in_the_past'],
            $this->errorTemplatesOn($form, 'expiresAt'),
        );
    }

    public function testACardWithAFutureExpiryAndAPositiveAmountIsAccepted(): void
    {
        // The mirror image. Without it, a form that refused everything would pass every test above.
        $form = $this->submitCreateForm([]);

        self::assertTrue(
            $form->isValid(),
            'The form refused a gift card there is nothing wrong with: ' . $this->allErrorTemplates($form),
        );
    }

    /**
     * Submits the create form, with anything the test did not name filled in acceptably.
     *
     * @param array<string, string> $overrides
     */
    private function submitCreateForm(array $overrides): FormInterface
    {
        // Off because there is no session here to hold a token. It is the browser's protection, not
        // a rule about gift cards, and the Behat scenarios post through the real form with it on.
        $form = $this->formFactory->create(GiftCardType::class, new GiftCard(), ['csrf_protection' => false]);

        $form->submit(array_merge([
            // Blank rather than a generated one: the code is filled in after validation, by
            // PrepareGiftCardOnCreateListener, so this is what the form really receives.
            'code' => '',
            'channel' => '',
            'expiresAt' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d H:i'),
            'initialAmount' => '75.00',
            'customMessage' => '',
            'enabled' => '1',
        ], $overrides));

        return $form;
    }

    /** Every error on the form, named by the field it landed on, for a failure message worth reading. */
    private function allErrorTemplates(FormInterface $form): string
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $errors[] = sprintf('%s: %s', $error->getOrigin()?->getName() ?? 'form', $error->getMessageTemplate());
        }

        return [] === $errors ? 'no errors at all' : implode('; ', $errors);
    }

    /**
     * The message *templates* of the errors on one field, not the rendered sentences.
     *
     * Which key was raised is what this test is about; whether the key has a translation is
     * ConstraintMessagesLiveInTheValidatorsDomainTest's job, and asserting English here would put
     * the two tests in each other's way.
     *
     * @return list<string>
     */
    private function errorTemplatesOn(FormInterface $form, string $field): array
    {
        $templates = [];

        foreach ($form->get($field)->getErrors() as $error) {
            $templates[] = $error->getMessageTemplate();
        }

        return $templates;
    }
}
