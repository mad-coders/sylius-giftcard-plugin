<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Admin\GiftCardConfiguration\CreatePageInterface;
use Webmozart\Assert\Assert;

/**
 * The per-channel gift card configuration screens.
 *
 * This is where a shop operator decides how guessable a gift card code is and how long a card
 * lasts - so the minimum code length is a security control, not a preference, and the form is the
 * only place it is enforced against a human.
 */
final readonly class GiftCardConfigurationContext implements Context
{
    public function __construct(
        private IndexPageInterface $indexPage,
        private CreatePageInterface $createPage,
        private GiftCardConfigurationRepositoryInterface $repository,
    ) {
    }

    /**
     * @When I want to configure gift cards for the :channel channel
     */
    public function iWantToConfigureGiftCardsForTheChannel(ChannelInterface $channel): void
    {
        $this->createPage->open();
        $this->createPage->chooseChannel((string) $channel->getName());
    }

    /**
     * @When I set the code prefix to :prefix
     */
    public function iSetTheCodePrefixTo(string $prefix): void
    {
        $this->createPage->specifyCodePrefix($prefix);
    }

    /**
     * @When I set the code length to :length
     */
    public function iSetTheCodeLengthTo(int $length): void
    {
        $this->createPage->specifyCodeLength($length);
    }

    /**
     * @When I set the validity period to :period
     */
    public function iSetTheValidityPeriodTo(string $period): void
    {
        $this->createPage->specifyValidityPeriod($period);
    }

    /**
     * @When I set gift cards to be issued by an administrator only
     */
    public function iSetGiftCardsToBeIssuedByAnAdministratorOnly(): void
    {
        $this->createPage->chooseSaleMode('Issued by an administrator only');
    }

    /**
     * @When I save this configuration
     */
    public function iSaveThisConfiguration(): void
    {
        $this->createPage->create();
    }

    /**
     * @Then the :channel channel should issue codes :length characters long prefixed with :prefix
     */
    public function theChannelShouldIssueCodesLongPrefixedWith(
        ChannelInterface $channel,
        int $length,
        string $prefix,
    ): void {
        $configuration = $this->repository->findOneByChannel($channel);
        Assert::isInstanceOf($configuration, GiftCardConfiguration::class, 'No configuration was saved for that channel.');

        Assert::same($configuration->getCodeLength(), $length);
        Assert::same($configuration->getCodePrefix(), $prefix);
    }

    /**
     * @Then the :channel channel should issue gift cards by an administrator only
     */
    public function theChannelShouldIssueGiftCardsByAnAdministratorOnly(ChannelInterface $channel): void
    {
        $configuration = $this->repository->findOneByChannel($channel);
        Assert::isInstanceOf($configuration, GiftCardConfiguration::class, 'No configuration was saved for that channel.');

        Assert::same($configuration->getSaleMode(), GiftCardSaleMode::AdminOnly);
    }

    /**
     * @Then the list should show :channel as issuing gift cards by an administrator only
     */
    public function theListShouldShowAsIssuingGiftCardsByAnAdministratorOnly(ChannelInterface $channel): void
    {
        // Read off the grid rather than the database: the point of this is that an operator can see
        // which channels sell gift cards without opening every configuration in turn.
        $this->indexPage->open();

        Assert::true(
            $this->indexPage->isSingleResourceOnPage([
                'channel' => (string) $channel->getName(),
                'saleMode' => 'Issued by an administrator only',
            ]),
            'The configuration list does not show the sale mode.',
        );
    }

    /**
     * @Then I should be told the code length is too short
     */
    public function iShouldBeToldTheCodeLengthIsTooShort(): void
    {
        // A short code is guessable, and a guessable gift card code is money anyone can spend. The
        // model clamps it as a backstop, but the operator has to be told rather than silently given
        // something other than what they asked for.
        Assert::contains(
            $this->createPage->getCodeLengthValidationMessage(),
            'at least 12 characters',
            'The form did not object to a code length below the minimum.',
        );
    }

    /**
     * @Then no gift card configuration should have been saved
     */
    public function noGiftCardConfigurationShouldHaveBeenSaved(): void
    {
        Assert::isEmpty($this->repository->findAll(), 'A configuration was saved despite the form being rejected.');
    }

    /**
     * @Then the configuration for :channel should appear in the list
     */
    public function theConfigurationForShouldAppearInTheList(ChannelInterface $channel): void
    {
        $this->indexPage->open();

        Assert::true(
            $this->indexPage->isSingleResourceOnPage(['channel' => (string) $channel->getName()]),
            'The configuration is not listed.',
        );
    }
}
