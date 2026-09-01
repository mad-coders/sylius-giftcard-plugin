<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Behat\Service\Checker\EmailCheckerInterface;
use Sylius\Behat\Service\Provider\EmailMessagesProviderInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Webmozart\Assert\Assert;

/**
 * Assertions about the gift cards issued *by* buying them, checked against the database rather than
 * a page: issuing happens on the payment transition, with no screen of its own until the customer
 * account view lands.
 */
final readonly class BoughtGiftCardContext implements Context
{
    public function __construct(
        private GiftCardRepositoryInterface $giftCardRepository,
        private ObjectManager $giftCardManager,
        private EmailCheckerInterface $emailChecker,
        private EmailMessagesProviderInterface $emailMessagesProvider,
    ) {
    }

    /**
     * @Then :customer should have been emailed the code of their gift card
     */
    public function shouldHaveBeenEmailedTheCodeOfTheirGiftCard(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $email = (string) $customer->getEmail();
        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);

        // Asserting on the code itself, not just that some mail arrived: an email that reaches the
        // customer without the code in it is the same as no email at all.
        Assert::true(
            $this->emailChecker->hasMessageTo((string) $giftCards[0]->getCode(), $email),
            sprintf('No email carrying the gift card code was sent to "%s".', $email),
        );
    }

    /**
     * @Then :customer should not have been emailed anything
     */
    public function shouldNotHaveBeenEmailedAnything(CustomerInterface $customer): void
    {
        Assert::same($this->emailChecker->countMessagesTo((string) $customer->getEmail()), 0);
    }

    /**
     * One counting step rather than a separate "no gift cards" wording: Behat treats two patterns
     * that both match a sentence as ambiguous even when they point at the same method.
     *
     * @Then :count gift card(s) should have been issued to :customer
     */
    public function giftCardsShouldHaveBeenIssuedTo(int $count, CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        Assert::count($this->giftCardRepository->findByPurchaser($customer), $count);
    }

    /**
     * @Then the gift card issued to :customer should be worth :amount
     */
    public function theGiftCardIssuedToShouldBeWorth(CustomerInterface $customer, string $amount): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);

        $giftCard = $giftCards[0];
        Assert::same($giftCard->getInitialAmount(), self::toMinorUnits($amount));
        Assert::same($giftCard->getAmount(), self::toMinorUnits($amount));
        Assert::same($giftCard->getOrigin(), GiftCardOrigin::Order);
        Assert::notNull($giftCard->getCode());
    }

    /**
     * @Then the gift card issued to :customer should be usable
     */
    public function theGiftCardIssuedToShouldBeUsable(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);
        Assert::true($giftCards[0]->isRedeemable());
    }

    /**
     * @Then the gift card issued to :customer should no longer be usable
     */
    public function theGiftCardIssuedToShouldNoLongerBeUsable(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);
        Assert::false($giftCards[0]->isRedeemable());
    }

    /**
     * @Then the gift cards issued to :customer should be worth :amounts
     */
    public function theGiftCardsIssuedToShouldBeWorth(CustomerInterface $customer, string $amounts): void
    {
        $this->giftCardManager->clear();

        $expected = array_map(self::toMinorUnits(...), self::split($amounts));
        $actual = array_map(
            static fn (GiftCardInterface $giftCard): ?int => $giftCard->getInitialAmount(),
            $this->giftCardRepository->findByPurchaser($customer),
        );

        // Sorted, because what matters is that each amount was issued once - not the order the
        // repository happened to return them in.
        sort($expected);
        sort($actual);

        Assert::same($actual, $expected);
    }

    /**
     * @Then the gift card issued to :customer should say :message
     */
    public function theGiftCardIssuedToShouldSay(CustomerInterface $customer, string $message): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);
        Assert::same($giftCards[0]->getCustomMessage(), $message);
    }

    /**
     * @Then the gift card issued to :customer should carry no message
     */
    public function theGiftCardIssuedToShouldCarryNoMessage(CustomerInterface $customer): void
    {
        $this->giftCardManager->clear();

        $giftCards = $this->giftCardRepository->findByPurchaser($customer);
        Assert::count($giftCards, 1);
        Assert::null($giftCards[0]->getCustomMessage());
    }

    /**
     * @Then the gift cards issued to :customer should say :messages
     */
    public function theGiftCardsIssuedToShouldSay(CustomerInterface $customer, string $messages): void
    {
        $this->giftCardManager->clear();

        $expected = self::split($messages);
        $actual = array_map(
            static fn (GiftCardInterface $giftCard): ?string => $giftCard->getCustomMessage(),
            $this->giftCardRepository->findByPurchaser($customer),
        );

        sort($expected);
        sort($actual);

        Assert::same($actual, $expected);
    }

    /**
     * @Then :customer should have been emailed a gift card saying :message
     */
    public function shouldHaveBeenEmailedAGiftCardSaying(CustomerInterface $customer, string $message): void
    {
        $email = (string) $customer->getEmail();

        Assert::true(
            $this->emailChecker->hasMessageTo($message, $email),
            sprintf('No email carrying the message "%s" was sent to "%s".', $message, $email),
        );
    }

    /**
     * Proves the escaping rather than assuming it.
     *
     * Reads the raw HTML body rather than going through the email checker, which strips tags before
     * matching: through that lens injected markup and escaped markup look alike, so an assertion
     * made there would pass whether or not the template escapes anything.
     *
     * @Then the email to :customer should show :raw as text rather than as markup
     */
    public function theEmailShouldShowAsTextRatherThanAsMarkup(CustomerInterface $customer, string $raw): void
    {
        $address = (string) $customer->getEmail();
        $body = $this->lastHtmlBodySentTo($address);

        Assert::notNull($body, sprintf('No email was sent to "%s" at all.', $address));

        $escaped = htmlspecialchars($raw, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        Assert::false(
            str_contains($body, $raw),
            sprintf('The email to "%s" carries "%s" as markup, so a customer message can inject HTML.', $address, $raw),
        );

        Assert::true(
            str_contains($body, $escaped),
            sprintf('The email to "%s" does not carry "%s" escaped, so the message is missing.', $address, $raw),
        );
    }

    /**
     * The most recent email to this address.
     *
     * The most recent, not all of them: the mailer cache outlives a scenario, so older messages to
     * the same customer are still in it and would be judged against this scenario's expectation.
     */
    private function lastHtmlBodySentTo(string $address): ?string
    {
        $body = null;

        foreach ($this->emailMessagesProvider->provide() as $email) {
            foreach ($email->getTo() as $recipient) {
                if ($recipient->getAddress() === $address) {
                    $body = (string) $email->getHtmlBody();

                    break;
                }
            }
        }

        return $body;
    }

    /** @return list<string> */
    private static function split(string $values): array
    {
        $parts = preg_split('/\s*(?:,|and)\s*/', trim($values));

        if (false === $parts) {
            return [];
        }

        return array_values(array_filter($parts, static fn (string $part): bool => '' !== trim($part)));
    }

    private static function toMinorUnits(string $amount): int
    {
        $normalised = preg_replace('/[^0-9.]/', '', $amount) ?? '';

        return (int) round(((float) $normalised) * 100);
    }
}
