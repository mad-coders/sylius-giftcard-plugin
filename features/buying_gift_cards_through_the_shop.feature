@madcoders_gift_card_buying
Feature: Buying gift cards through the shop's own form
    In order to buy two different gifts in one go
    As a Customer
    I want the shop to keep each gift card's amount and message apart

    # These scenarios go through the real add-to-cart form: a browser, Sylius' Live Component, its
    # CART_ITEM_ADD listener and OrderModifier. That last one is why they exist. OrderModifier merges
    # a new line into an existing one when `equals()` says they match, and Sylius' answer for a core
    # order item is "the variants match" - so without the plugin's override, a $25 card and a $100
    # card of the same product become one line of two $25 cards, and the second message is thrown
    # away. Nothing that builds an OrderItem by hand can catch that, because Order::addItem() guards
    # on identity and never merges.

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product

    @javascript
    Scenario: Two gift cards of different amounts stay two lines
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        When I add a "Gift Card" of "$25.00" saying "For Ann" to my cart
        And I add a "Gift Card" of "$100.00" saying "For Bob" to my cart
        Then my cart should hold 2 separate lines
        And the lines should be priced "$25.00 and $100.00"
        And my cart should come to "$125.00"

    @javascript
    Scenario: Two identical gift cards are still one line of two
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        When I add a "Gift Card" of "$50.00" to my cart
        And I add a "Gift Card" of "$50.00" to my cart
        Then my cart should hold 1 separate line
        And my cart should come to "$100.00"

    @javascript
    Scenario: A chosen preset becomes what the cart charges
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        When I add a "Gift Card" of "$100.00" saying "Happy birthday!" to my cart
        Then my cart should come to "$100.00"

    @javascript
    Scenario: An amount outside the channel's range is refused, and both bounds are named
        Given the channel lets customers choose any gift card amount between "$10.00" and "$500.00"
        When I try to add a "Gift Card" of my own amount "5000" to my cart
        Then I should be told the amount must be between "$10.00" and "$500.00"
        And my cart should hold 0 separate lines

    # There is deliberately no browser scenario for "an amount that is not one of the presets".
    # In a presets-only channel the form offers radio buttons and nothing else, so a browser cannot
    # express such an amount at all - which is the point. That path is a forged request, and it is
    # covered where a forged request actually lands: ChosenGiftCardAmountValidatorTest for the
    # message, GiftCardChosenAmountProcessorTest and choosing_a_gift_card_amount.feature for the
    # refusal that binds.

    @javascript
    Scenario: A message longer than the limit is refused
        # Typed past the browser's maxlength on purpose: the limit has to hold for a request that
        # ignores the attribute, which is every request that did not come from this form.
        Given the channel sells gift cards at the product's price
        When I try to add a "Gift Card" with a message of 300 characters to my cart
        Then I should be told my message is too long
        And my cart should hold 0 separate lines
