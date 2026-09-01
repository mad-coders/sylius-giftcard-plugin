<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Sylius\Behat\Service\SharedStorageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account\GiftCardIndexPageInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Account\GiftCardShowPageInterface;
use Webmozart\Assert\Assert;

final readonly class AccountGiftCardContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private GiftCardIndexPageInterface $indexPage,
        private GiftCardShowPageInterface $showPage,
        private Session $session,
    ) {
    }

    /**
     * @When I browse my gift cards
     */
    public function iBrowseMyGiftCards(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I open the balance history of :code
     */
    public function iOpenTheBalanceHistoryOf(string $code): void
    {
        if (!$this->indexPage->isOpen()) {
            $this->indexPage->open();
        }

        $this->indexPage->openBalanceHistoryOf($code);
    }

    /**
     * @When I try to open the gift card :code belonging to somebody else
     */
    public function iTryToOpenTheGiftCardBelongingToSomebodyElse(string $code): void
    {
        /** @var array<string, int> $ids */
        $ids = $this->sharedStorage->get('gift_card_ids');
        Assert::keyExists($ids, $code);

        // tryToOpen(), not session->visit() with a hand-built path: the page object generates the
        // URL against the configured base URL. A relative visit() resolves against localhost, a
        // different host, so the session cookie would not be sent and every request would look
        // logged out - which reads exactly like a passing authorization test while proving nothing.
        $this->showPage->tryToOpen(['id' => $ids[$code]]);
    }

    /**
     * @Then I should see :code among the gift cards I use
     */
    public function iShouldSeeAmongTheGiftCardsIUse(string $code): void
    {
        Assert::inArray($code, $this->indexPage->getRedeemedGiftCardCodes());
    }

    /**
     * @Then I should see :code among the gift cards I bought
     */
    public function iShouldSeeAmongTheGiftCardsIBought(string $code): void
    {
        Assert::inArray($code, $this->indexPage->getPurchasedGiftCardCodes());
    }

    /**
     * @Then I should not see :code among the gift cards I use
     */
    public function iShouldNotSeeAmongTheGiftCardsIUse(string $code): void
    {
        Assert::false(in_array($code, $this->indexPage->getRedeemedGiftCardCodes(), true));
    }

    /**
     * @Then :code should show a remaining balance of :balance
     */
    public function shouldShowARemainingBalanceOf(string $code, string $balance): void
    {
        Assert::same($this->indexPage->getBalanceOf($code), $balance);
    }

    /**
     * @Then I should see a remaining balance of :balance
     */
    public function iShouldSeeARemainingBalanceOf(string $balance): void
    {
        Assert::same($this->showPage->getBalance(), $balance);
    }

    /**
     * @Then I should see :count entry/entries in the balance history
     */
    public function iShouldSeeEntriesInTheBalanceHistory(int $count): void
    {
        Assert::same($this->showPage->countTransactions(), $count);
    }

    /**
     * @Then I should see the message :message on the card
     */
    public function iShouldSeeTheMessageOnTheCard(string $message): void
    {
        Assert::same($this->showPage->getCustomMessage(), $message);
    }

    /**
     * The message is written by whoever bought the card, so it reaches this page as untrusted text.
     *
     * Asserted against the page's HTML rather than its rendered text: read as text, injected markup
     * and escaped markup look identical, so an assertion made there would pass either way.
     *
     * @Then the card's page should show :raw as text rather than as markup
     */
    public function theCardsPageShouldShowAsTextRatherThanAsMarkup(string $raw): void
    {
        $html = $this->session->getPage()->getContent();

        Assert::false(
            str_contains($html, $raw),
            sprintf('The page carries "%s" as markup, so a customer message can inject HTML.', $raw),
        );

        Assert::contains(
            $html,
            htmlspecialchars($raw, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            sprintf('The page does not carry "%s" escaped, so the message is missing.', $raw),
        );
    }

    /**
     * @Then I should be refused access
     */
    public function iShouldBeRefusedAccess(): void
    {
        // 403 specifically. Accepting 404 as well would let this pass if the page simply stopped
        // existing - a routing mistake would then read as a working authorization check.
        Assert::same(
            $this->session->getStatusCode(),
            403,
            sprintf('Expected the page to be forbidden, got status %d.', $this->session->getStatusCode()),
        );
    }
}
