@madcoders_gift_card_cart
Feature: Applying a gift card to my cart
    In order to spend a gift card I was given
    As a Customer
    I want to apply it to my cart and see how much of it they cover

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And I have product "PHP T-Shirt" added to the cart

    @ui
    Scenario: Applying a gift card leaves less to pay, without discounting the order
        Given the store has a gift card "GIFT-40" worth "$40.00"
        When I apply the gift card "GIFT-40"
        Then I should be notified that the gift card has been applied
        And the gift card "GIFT-40" should be applied to my cart
        # The goods still cost what they cost - a gift card is money, not a discount.
        And my cart total should be "$100.00"
        And I should have "$60.00" left to pay

    @ui
    Scenario: Applying two gift cards stacks them
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And the store has a gift card "GIFT-25" worth "$25.00"
        When I apply the gift card "GIFT-40"
        And I apply the gift card "GIFT-25"
        Then the gift card "GIFT-40" should be applied to my cart
        And the gift card "GIFT-25" should be applied to my cart
        And my cart total should be "$100.00"
        And I should have "$35.00" left to pay

    @ui
    Scenario: A gift card worth more than the cart only covers what is owed
        Given the store has a gift card "GIFT-500" worth "$500.00"
        When I apply the gift card "GIFT-500"
        Then my cart total should be "$100.00"
        And I should have "$0.00" left to pay
        And my gift cards should cover "-$100.00" of my cart

    @ui
    Scenario: Removing a gift card restores the cart total
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And I apply the gift card "GIFT-40"
        When I remove the gift card "GIFT-40"
        Then the gift card "GIFT-40" should no longer be applied to my cart
        And my cart total should be "$100.00"

    @ui
    Scenario: An expired gift card is refused
        Given the store has an expired gift card "GIFT-OLD" worth "$40.00"
        When I try to apply the gift card "GIFT-OLD"
        Then I should be notified that the gift card cannot be used
        And no gift card should be applied to my cart
        And my cart total should be "$100.00"

    @ui
    Scenario: A disabled gift card is refused
        Given the store has a disabled gift card "GIFT-OFF" worth "$40.00"
        When I try to apply the gift card "GIFT-OFF"
        Then I should be notified that the gift card cannot be used
        And no gift card should be applied to my cart

    @ui
    Scenario: An unknown code is refused
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be notified that the gift card does not exist
        And no gift card should be applied to my cart

    @ui
    Scenario: A partly spent gift card only covers what is left on it
        Given the store has a gift card "GIFT-PART" worth "$50.00" with "$15.00" left
        When I apply the gift card "GIFT-PART"
        Then the gift card "GIFT-PART" should be applied to my cart
        And I should have "$85.00" left to pay

    @ui
    Scenario: Changing the cart after applying a card does not double the discount
        # Guards RegisterGiftCardAdjustmentClearingPass, which fails silently if Sylius renames the
        # clearing-types parameter: the previous run's coverage survives and compounds on every
        # reprocess. Nothing else touches the cart after a card is applied.
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And the store has a product "PHP Mug" priced at "$100.00"
        And I apply the gift card "GIFT-40"
        When I have product "PHP Mug" added to the cart
        And I refresh the cart
        Then my cart total should be "$200.00"
        And I should have "$160.00" left to pay

    @ui
    Scenario: Applying the same card twice does not count it twice
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And I apply the gift card "GIFT-40"
        When I apply the gift card "GIFT-40"
        Then I should have "$60.00" left to pay

    @ui
    Scenario: The checkout summary tells me what I will actually be charged
        # The last page before paying. Its "Order total" is the full price of the goods, so without
        # the gift card lines the customer is told to expect a charge far larger than the one that
        # will reach their card.
        Given the store ships everywhere for free
        And the store allows paying offline
        And the store has a gift card "GIFT-40" worth "$40.00"
        And I apply the gift card "GIFT-40"
        And I have proceeded through checkout process
        When I look at the checkout summary
        Then the summary should show "-$40.00" covered by my gift cards
        And the summary should tell me I will be charged "$60.00"

    @ui
    Scenario: The checkout summary of a cart with no gift card is untouched
        Given the store ships everywhere for free
        And the store allows paying offline
        And I have proceeded through checkout process
        When I look at the checkout summary
        Then the summary should say nothing about gift cards

    @ui
    Scenario: I can redeem a gift card from the checkout, next to what I am about to pay
        # The panel used to exist only on the cart, so a customer already in checkout had to go
        # back to find it - at exactly the moment they are looking at what they will be charged.
        Given the store ships everywhere for free
        And the store allows paying offline
        And the store has a gift card "GIFT-40" worth "$40.00"
        And I have proceeded through checkout process
        When I look at the checkout summary
        Then I should be able to redeem a gift card from the checkout
        When I apply the gift card "GIFT-40" from the checkout
        Then I should be told in the checkout that the gift card was applied
        And I should still be in the checkout
        And the gift card "GIFT-40" should be applied in the checkout
        And the summary should tell me I will be charged "$60.00"

    @ui
    Scenario: I can take a gift card back off from the checkout
        Given the store ships everywhere for free
        And the store allows paying offline
        And the store has a gift card "GIFT-40" worth "$40.00"
        And I have proceeded through checkout process
        And I look at the checkout summary
        And I apply the gift card "GIFT-40" from the checkout
        When I remove the gift card "GIFT-40" from the checkout
        Then I should still be in the checkout
        And no gift card should be applied in the checkout
        And the summary should say nothing about gift cards

    @ui
    Scenario: An unknown code is refused in the checkout
        Given the store ships everywhere for free
        And the store allows paying offline
        And I have proceeded through checkout process
        And I look at the checkout summary
        When I apply the gift card "GIFT-NOPE" from the checkout
        Then I should be told in the checkout that the code is not valid
        And I should still be in the checkout
        And no gift card should be applied in the checkout

    @ui
    Scenario: An expired card is refused in the checkout
        Given the store ships everywhere for free
        And the store allows paying offline
        And the store has an expired gift card "GIFT-OLD" worth "$40.00"
        And I have proceeded through checkout process
        And I look at the checkout summary
        When I apply the gift card "GIFT-OLD" from the checkout
        Then I should be told in the checkout that the card cannot be redeemed
        And I should still be in the checkout
        And no gift card should be applied in the checkout

    @ui
    Scenario: A disabled card is refused in the checkout
        Given the store ships everywhere for free
        And the store allows paying offline
        And the store has a disabled gift card "GIFT-OFF" worth "$40.00"
        And I have proceeded through checkout process
        And I look at the checkout summary
        When I apply the gift card "GIFT-OFF" from the checkout
        Then I should be told in the checkout that the card cannot be redeemed
        And I should still be in the checkout
        And no gift card should be applied in the checkout

    @ui
    Scenario: The redeem field is on the earlier checkout steps too, and sends me back to them
        # The summary step blanks Sylius' sidebar and gets the panel a different way, so the earlier
        # steps need their own coverage - and this is the scenario that proves applying a card from
        # a step returns the customer to that step instead of dumping them back in the cart.
        Given the store ships everywhere for free
        And the store allows paying offline
        And the store has a gift card "GIFT-40" worth "$40.00"
        And I have proceeded through checkout process
        When I go to the payment step
        Then the payment step should let me redeem a gift card too
        When I apply the gift card "GIFT-40" from the payment step
        Then I should still be on the payment step
        And the payment step should tell me I will be charged "$60.00"

    @ui
    Scenario: A refusal on the payment step is not silent
        # The checkout steps render no flashes of their own - only the cart and the summary step do -
        # so the panel renders its own messages. Without that the page came back byte-identical and
        # the customer was told nothing, while the unread message surfaced later on whichever page
        # did render flashes, attached to the wrong action.
        Given the store ships everywhere for free
        And the store allows paying offline
        And I have proceeded through checkout process
        When I go to the payment step
        And I apply the gift card "GIFT-NOPE" from the payment step
        Then I should be told on the payment step that the code is not valid
        And I should still be on the payment step
