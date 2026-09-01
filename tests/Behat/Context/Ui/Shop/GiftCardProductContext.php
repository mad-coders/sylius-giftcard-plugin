<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Behat\Page\Shop\Product\ShowPageInterface;
use Webmozart\Assert\Assert;

/**
 * What a customer is actually offered on a gift card product's page.
 *
 * The page is rendered without JavaScript, so what these scenarios see is the plain HTML the shop
 * serves - which is the point: the choice has to be a set of controls a browser understands on its
 * own, not something a script assembles.
 */
final readonly class GiftCardProductContext implements Context
{
    public function __construct(private ShowPageInterface $productShowPage)
    {
    }

    /**
     * @When I look at the :product product page
     */
    public function iLookAtTheProductPage(ProductInterface $product): void
    {
        $this->openProductPage($product);
    }

    private function openProductPage(ProductInterface $product): void
    {
        $this->productShowPage->open(['slug' => $product->getSlug(), '_locale' => 'en_US']);
    }

    /**
     * @Then I should be offered the amounts :amounts to choose from
     */
    public function iShouldBeOfferedTheAmountsToChooseFrom(string $amounts): void
    {
        Assert::true($this->productShowPage->hasAmountChoice(), 'The page offers no choice of amount at all.');

        $parts = preg_split('/\s*(?:,|and)\s*/', trim($amounts));
        Assert::isArray($parts);

        $expected = array_map(static fn (string $amount): string => trim($amount), $parts);

        Assert::same($this->productShowPage->getAmountOptions(), $expected);
    }

    /**
     * @Then each amount should be a selectable option rather than an entry in a list
     */
    public function eachAmountShouldBeASelectableOption(): void
    {
        Assert::true(
            $this->productShowPage->amountOptionsAreSelectable(),
            'The amounts are not radio options, so they do not read as a set of choices.',
        );
    }

    /**
     * @Then I should be able to type my own amount
     */
    public function iShouldBeAbleToTypeMyOwnAmount(): void
    {
        Assert::true($this->productShowPage->hasFreeAmountField(), 'There is nowhere to type an amount.');
    }

    /**
     * @Then I should not be able to type my own amount
     */
    public function iShouldNotBeAbleToTypeMyOwnAmount(): void
    {
        Assert::false($this->productShowPage->hasFreeAmountField(), 'The page lets me type an amount it should not.');
    }

    /**
     * @Then the form should tell me I can type anything between :minimum and :maximum
     */
    public function theFormShouldTellMeICanTypeAnythingBetween(string $minimum, string $maximum): void
    {
        $help = $this->productShowPage->getFreeAmountHelp();

        Assert::contains($help, $minimum, sprintf('The form does not name the smallest amount: "%s".', $help));
        Assert::contains($help, $maximum, sprintf('The form does not name the largest amount: "%s".', $help));
    }

    /**
     * @Then I should not be asked to choose an amount
     */
    public function iShouldNotBeAskedToChooseAnAmount(): void
    {
        Assert::false(
            $this->productShowPage->hasAmountChoice(),
            'The page asks for an amount in a channel that sells gift cards at the product\'s price.',
        );
    }

    /**
     * @When I add a :product of :amount saying :message to my cart
     */
    public function iAddAGiftCardOfSayingToMyCart(ProductInterface $product, string $amount, string $message): void
    {
        $this->openProductPage($product);
        $this->productShowPage->chooseAmount($amount);
        $this->productShowPage->specifyMessage($message);
        $this->productShowPage->addToCart();
        $this->assertItWasAdded();
    }

    /**
     * @When I add a :product of :amount to my cart
     */
    public function iAddAGiftCardOfToMyCart(ProductInterface $product, string $amount): void
    {
        $this->openProductPage($product);
        $this->productShowPage->chooseAmount($amount);
        $this->productShowPage->addToCart();
        $this->assertItWasAdded();
    }

    /**
     * A silently-refused add would otherwise read as a passing scenario: the cart simply keeps
     * whatever it already had, and an assertion about line counts can still come out right.
     */
    private function assertItWasAdded(): void
    {
        $messages = $this->productShowPage->getValidationMessages();

        Assert::same($messages, '', sprintf('The shop refused to add the gift card: "%s".', $messages));
    }

    /**
     * @When I try to add a :product of my own amount :amount to my cart
     */
    public function iTryToAddAGiftCardOfMyOwnAmount(ProductInterface $product, string $amount): void
    {
        $this->openProductPage($product);
        $this->productShowPage->specifyCustomAmount($amount);
        $this->productShowPage->addToCart();
    }

    /**
     * @When I try to add a :product with a message of :length characters to my cart
     */
    public function iTryToAddAGiftCardWithAMessageOfCharacters(ProductInterface $product, int $length): void
    {
        $this->openProductPage($product);
        $this->productShowPage->specifyMessageIgnoringTheBrowserLimit(str_repeat('a', $length));
        $this->productShowPage->addToCart();
    }

    /**
     * @Then I should be told the amount must be one of the offered ones
     */
    public function iShouldBeToldTheAmountMustBeOneOfTheOfferedOnes(): void
    {
        Assert::contains(
            $this->productShowPage->getValidationMessages(),
            'one of the available amounts',
            'The form accepted an amount the channel does not offer.',
        );
    }

    /**
     * @Then I should be told the amount must be between :minimum and :maximum
     */
    public function iShouldBeToldTheAmountMustBeBetweenAnd(string $minimum, string $maximum): void
    {
        // Naming both bounds is the point of the criterion: "invalid amount" leaves the customer
        // guessing what to type instead.
        $messages = $this->productShowPage->getValidationMessages();

        Assert::contains($messages, $minimum, sprintf('The refusal does not name the smallest amount: "%s".', $messages));
        Assert::contains($messages, $maximum, sprintf('The refusal does not name the largest amount: "%s".', $messages));
    }

    /**
     * @Then I should be told my message is too long
     */
    public function iShouldBeToldMyMessageIsTooLong(): void
    {
        Assert::contains(
            $this->productShowPage->getValidationMessages(),
            'too long',
            'The form accepted a message longer than the limit.',
        );
    }

    /**
     * @Then I should be able to write a message
     */
    public function iShouldBeAbleToWriteAMessage(): void
    {
        Assert::true($this->productShowPage->hasMessageField(), 'There is nowhere to write a message.');
    }

    /**
     * @Then the message field should tell me how long it may be
     */
    public function theMessageFieldShouldTellMeHowLongItMayBe(): void
    {
        $limit = GiftCardInterface::CUSTOM_MESSAGE_MAX_LENGTH;

        Assert::same(
            $this->productShowPage->getMessageMaxLength(),
            $limit,
            'The message field does not stop the browser at the limit.',
        );

        Assert::contains(
            $this->productShowPage->getMessageHelp(),
            (string) $limit,
            'The form does not say how long the message may be, so the limit is a surprise.',
        );
    }
}
