<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Functional\Validator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCard;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\GiftCard\GiftCardConfiguration;

/**
 * Proves the constraints behind "every gift card expires" actually run in a booted container.
 *
 * The unit tests construct validators by hand, so they say nothing about wiring. Two things here are
 * implicit and both fail silently: FrameworkBundle finds `config/validation/*.xml` only because the
 * bundle's getPath() returns the repository root, and a constraint is only evaluated when its
 * **group** matches the one the form validates with - a constraint declared in only one group is
 * skipped outright by the other path, with every unit test still green and the form quietly
 * accepting a card that never expires.
 *
 * Both groups are still asked here even though the plugin's forms now validate with both of them
 * (issue #44). The two are separate paths: the form's, and a host validating the entity on its own.
 *
 * See docs/adr-log/0015-every-gift-card-expires.md and
 * docs/adr-log/0017-resource-forms-validate-with-default-too.md.
 */
final class GiftCardExpiryConstraintWiringTest extends KernelTestCase
{
    /** The group AbstractResourceType is given in config/services/forms.xml. */
    private const string RESOURCE_GROUP = 'madcoders_sylius_gift_card';

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();

        $validator = self::getContainer()->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;
    }

    /**
     * Deliberately not called `groups()` - `PHPUnit\Framework\TestCase::groups()` is final, and
     * overriding it is a fatal error rather than a test failure.
     *
     * @return iterable<string, array{string}>
     */
    public static function validationGroups(): iterable
    {
        yield 'the group the admin form validates with' => [self::RESOURCE_GROUP];
        yield 'the group a host validating the entity uses' => ['Default'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validationGroups')]
    public function testAGiftCardWithNoExpiryDateIsRefused(string $group): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GIFT-WIRING-NO-EXPIRY');
        $giftCard->setInitialAmount(5_000);

        $violations = $this->validator->validate($giftCard, null, [$group]);

        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card.expires_at.not_blank'],
            $this->pluginMessagesOf($violations),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validationGroups')]
    public function testAGiftCardWithAnExpiryDateIsAccepted(string $group): void
    {
        // The mirror image. Without it a constraint that refused unconditionally would pass the
        // test above.
        $giftCard = new GiftCard();
        $giftCard->setCode('GIFT-WIRING-WITH-EXPIRY');
        $giftCard->setInitialAmount(5_000);
        $giftCard->setExpiresAt(new \DateTime('+1 year'));

        $violations = $this->validator->validate($giftCard, null, [$group]);

        self::assertSame([], $this->pluginMessagesOf($violations));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validationGroups')]
    public function testAGiftCardIssuedAlreadyExpiredIsRefused(string $group): void
    {
        // The rule added in docs/adr-log/0018-an-expiry-date-cannot-be-moved-into-the-past.md. This
        // card has never been stored, so there is no previous date to compare against and the
        // submitted one is judged on its own - which is what a creation is.
        $giftCard = new GiftCard();
        $giftCard->setCode('GIFT-WIRING-PAST-EXPIRY');
        $giftCard->setInitialAmount(5_000);
        $giftCard->setExpiresAt(new \DateTime('-1 day'));

        $violations = $this->validator->validate($giftCard, null, [$group]);

        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card.expires_at.not_in_the_past'],
            $this->pluginMessagesOf($violations),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validationGroups')]
    public function testAConfigurationWithNoValidityPeriodIsRefused(string $group): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod(null);

        $violations = $this->validator->validate($configuration, null, [$group]);

        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card_configuration.validity_period.not_blank'],
            $this->pluginMessagesOf($violations),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validationGroups')]
    public function testAConfigurationWithAnUnparseableValidityPeriodIsRefused(string $group): void
    {
        // The silent failure this exists for: "1 yaer" used to save cleanly and quietly issue cards
        // that never expired.
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod('1 yaer');

        $violations = $this->validator->validate($configuration, null, [$group]);

        self::assertSame(
            ['madcoders_sylius_gift_card.gift_card_configuration.validity_period.unparseable'],
            $this->pluginMessagesOf($violations),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validationGroups')]
    public function testAConfigurationWithAUsableValidityPeriodIsAccepted(string $group): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setValidityPeriod('18 months');

        $violations = $this->validator->validate($configuration, null, [$group]);

        self::assertSame([], $this->pluginMessagesOf($violations));
    }

    /**
     * The plugin's own violations, in order. Sylius and Doctrine raise their own on these objects,
     * and asserting a total count would make this test a hostage to those.
     *
     * @return list<string>
     */
    private function pluginMessagesOf(ConstraintViolationListInterface $violations): array
    {
        $messages = [];

        foreach ($violations as $violation) {
            $message = (string) $violation->getMessageTemplate();

            if (str_starts_with($message, 'madcoders_sylius_gift_card.')) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
