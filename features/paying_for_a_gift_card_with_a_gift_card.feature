@madcoders_gift_card_cart
Feature: A gift card does not buy a gift card
    In order for a gift card's expiry date to mean something
    As a Shop Owner
    I want a gift card to pay for goods and never for another gift card

    # Without this, a holder with $412.37 left and a week to run buys a new card for exactly
    # $412.37, pays nothing, and walks away with a fresh code and a fresh expiry - repeatable
    # forever. The size of the shop's liability never changes; its duration becomes unbounded, which
    # is precisely what the mandatory expiry exists to stop. See
    # docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product
        And the store has a gift card "GIFT-500" worth "$500.00"

    @ui
    Scenario: A basket of nothing but gift cards refuses gift card payment
        Given I have product "Gift Card" added to the cart
        When I try to apply the gift card "GIFT-500"
        Then I should be told a gift card cannot pay for a gift card
        And no gift card should be applied to my cart
        # The cart total, not "left to pay": the panel only prints what is left to pay once a card
        # is actually covering something, so its absence here is itself the proof that nothing was.
        And my cart total should be "$50.00"

    @ui
    Scenario: The refusal says nothing about whether the code exists
        # The message is specific, which is only safe because the basket is judged before the code
        # is looked up. An unknown code has to be refused in exactly the same words, or the specific
        # message becomes an oracle for which codes are real.
        Given I have product "Gift Card" added to the cart
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be told a gift card cannot pay for a gift card

    @ui
    Scenario: A mixed basket pays for the goods with the card and the gift card in cash
        # The decision recorded in ADR 0016 criterion 5. Refusing the whole basket would punish a
        # customer who did nothing wrong, and would close nothing the cap does not already close.
        Given I have product "PHP T-Shirt" added to the cart
        And I have product "Gift Card" added to the cart
        When I apply the gift card "GIFT-500"
        Then I should be notified that the gift card has been applied
        And my cart total should be "$150.00"
        # $100 of shirt covered by the card; the $50 gift card is still payable in cash.
        And my gift cards should cover "-$100.00" of my cart
        And I should have "$50.00" left to pay

    @ui
    Scenario: An ordinary basket redeems exactly as it always did
        Given I have product "PHP T-Shirt" added to the cart
        When I apply the gift card "GIFT-500"
        Then I should be notified that the gift card has been applied
        And my cart total should be "$100.00"
        And I should have "$0.00" left to pay

    @ui
    Scenario: A channel may allow it, and then a gift card buys a gift card
        # A shop that wants this can have it, per channel, and the admin help text says what it
        # costs: expiry dates in that channel stop meaning anything.
        Given the channel lets a gift card pay for another gift card
        And I have product "Gift Card" added to the cart
        When I apply the gift card "GIFT-500"
        Then I should be notified that the gift card has been applied
        And my cart total should be "$50.00"
        And I should have "$0.00" left to pay

    @ui
    Scenario: A cart filled before the rule changed cannot slip through checkout
        # A cart outlives the setting. The customer fills it and applies a card while the channel
        # still allows this, and pays after it stops. The order processor caps the coverage
        # regardless, so the shop cannot lose money - what this adds is the customer being told,
        # instead of reaching the pay button believing their card covered something.
        Given the store ships everywhere for free
        And the store allows paying offline
        And the channel lets a gift card pay for another gift card
        And I have product "Gift Card" added to the cart
        And I apply the gift card "GIFT-500"
        And the channel stops letting a gift card pay for another gift card
        And I have proceeded through checkout process
        When I look at the checkout summary
        And I try to place my order
        # The violation is the whole assertion, as it is in Sylius' own checkout scenarios: where a
        # refused confirmation leaves the customer is Sylius' business, and pinning it here would
        # make this scenario a hostage to that.
        Then I should be told at the checkout that a gift card cannot pay for a gift card

    @ui
    Scenario: A mixed basket reaches the pay button with the right split
        # The other half of the rule, and the one that would take the whole shop down if it were
        # wrong: the constraint runs on every checkout, gift card or not.
        #
        # Deliberately stops at the summary rather than pressing confirm. Confirming here would
        # complete a *guest* order, and Sylius' own SaveCustomerAddressesListener fatals on one
        # under Symfony 6.4 - a crash in Sylius' completion path that has nothing to say about gift
        # cards. That the constraint lets this order through is asserted where it can be asserted
        # cleanly on every database: GiftCardTenderConstraintWiringTest validates a real mixed
        # basket in the real `sylius_checkout_complete` group.
        Given the store ships everywhere for free
        And the store allows paying offline
        And I have product "PHP T-Shirt" added to the cart
        And I have product "Gift Card" added to the cart
        And I apply the gift card "GIFT-500"
        And I have proceeded through checkout process
        When I look at the checkout summary
        Then the summary should show "-$100.00" covered by my gift cards
        And the summary should tell me I will be charged "$50.00"
        And the checkout should not have objected to my gift card
