@madcoders_gift_card_admin
Feature: Managing gift cards
    In order to issue and correct gift cards
    As an Administrator
    I want to manage them from the admin panel

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Browsing gift cards
        Given the store has a gift card "GIFT-BROWSE" worth "$50.00"
        When I browse gift cards
        Then the gift card "GIFT-BROWSE" should appear in the list

    @ui
    Scenario: Creating a gift card by hand
        When I want to create a new gift card
        And I specify its code as "GIFT-NEW001"
        And I specify its initial amount as "75.00"
        And I add this gift card
        Then the gift card "GIFT-NEW001" should appear in the list

    @ui
    Scenario: Seeing a gift card's balance and who uses it
        Given the store has a gift card "GIFT-SHOW01" worth "$50.00" with "$20.00" left
        When I view the gift card "GIFT-SHOW01"
        Then its remaining balance should be "$20.00"

    @ui
    Scenario: Topping up a gift card records it in the balance history
        # Two entries, not one: the card was already partly spent, and that spending has its own
        # ledger entry - a balance can never exist without the history explaining it.
        Given the store has a gift card "GIFT-TOPUP1" worth "$50.00" with "$20.00" left
        When I view the gift card "GIFT-TOPUP1"
        And I add "10.00" to its balance
        Then I should be notified that the balance has been adjusted
        And its remaining balance should be "$30.00"
        And its balance history should have 2 entries

    @ui
    Scenario: Taking money off a gift card records it in the balance history
        Given the store has a gift card "GIFT-CLAW01" worth "$50.00"
        When I view the gift card "GIFT-CLAW01"
        And I take "15.00" from its balance
        Then its remaining balance should be "$35.00"
        And its balance history should have 1 entries

    @ui
    Scenario: Editing a gift card product does not clear its gift card flag
        # Guards a silent failure: the flag is a mapped checkbox, and Sylius' product form renders
        # only what a hookable emits. If it is not rendered, every product save submits it as
        # absent and Symfony writes false - quietly turning a gift card product into a normal one.
        Given the store has a product "Gift Card"
        And the product "Gift Card" is a gift card product
        When I edit the product "Gift Card" and save it unchanged
        Then the product "Gift Card" should still be a gift card product

    @ui
    Scenario: An issued gift card's code and face value cannot be changed
        # Both are load-bearing. The code is bearer money the customer is already holding, and it is
        # the only link between an order and the card that paid for it - renaming an issued card
        # would strand every refund, silently. The face value is what those orders were priced
        # against. Use the balance adjustment action to correct a balance instead.
        Given the store has a gift card "GIFT-LOCKED" worth "$50.00"
        When I want to edit the gift card "GIFT-LOCKED"
        Then I should not be able to change its code
        And its code should still be "GIFT-LOCKED"
        And I should not be able to change its initial amount

    @ui
    Scenario: Taking more off a gift card than it holds is refused on the form
        # The model throws rather than let a balance go negative. That exception has to become a
        # form error: letting it out would be a 500, with the reason buried in a log and the
        # administrator none the wiser.
        Given the store has a gift card "GIFT-OVER01" worth "$50.00"
        When I view the gift card "GIFT-OVER01"
        And I take "80.00" from its balance
        Then I should be told the adjustment is not possible
        And the balance on the form should still be "$50.00"

    @ui
    Scenario: An adjustment of nothing is refused in words the administrator can read
        # Pins the translation domain, which nothing did for the gift card forms. Symfony resolves a
        # constraint violation in the `validators` catalogue, so the same key sitting in
        # messages.en.yaml renders as `madcoders_sylius_gift_card.gift_card.amount.positive` - a
        # string that tells an administrator nothing. This asserts the English sentence, so moving
        # the key back to `messages` turns it red.
        Given the store has a gift card "GIFT-ZERO1" worth "$50.00"
        When I view the gift card "GIFT-ZERO1"
        And I add "0.00" to its balance
        Then I should be told the amount must be greater than zero
        And the balance on the form should still be "$50.00"

    @ui
    Scenario: Topping a gift card above its face value is refused on the form
        # The cap is what stops a mistyped adjustment turning a $50 card into a $50,000 one.
        Given the store has a gift card "GIFT-OVER02" worth "$50.00" with "$40.00" left
        When I view the gift card "GIFT-OVER02"
        And I add "30.00" to its balance
        Then I should be told the adjustment is not possible
        And the balance on the form should still be "$40.00"

    @ui
    Scenario: A card created without a code takes its code from the channel's configuration
        # The per-channel configuration decides how guessable a code is and how long a card lasts.
        # Nothing exercised it end to end before: the provider returned null in every test, so the
        # generator always fell back to its defaults and the configuration was dead weight.
        Given the channel issues gift card codes 12 characters long prefixed with "XMAS-"
        When I want to create a new gift card
        And I specify its initial amount as "75.00"
        And I add this gift card
        Then the issued card's code should start with "XMAS-"
        And the issued card's code should have 12 characters after the prefix "XMAS-"

    @ui
    Scenario: A card created without an expiry takes the channel's validity period
        Given the channel's gift cards are valid for "30 days"
        When I want to create a new gift card
        And I specify its initial amount as "75.00"
        And I add this gift card
        Then the issued card should expire in about 30 days

    @ui
    Scenario: A validity period that cannot be parsed issues a card that never expires
        # Rather than one that is already expired. A card issued dead is worse than one that never
        # expires, because the customer only finds out at the till.
        Given the channel's gift cards are valid for the unparseable period "not a period"
        When I want to create a new gift card
        And I specify its initial amount as "75.00"
        And I add this gift card
        # The prefix proves the configuration was read at all - without it, "never expires" would
        # also be the answer if the configuration were ignored, and this would test nothing.
        Then the issued card's code should start with "BADCFG-"
        And the issued card should never expire

    @ui
    Scenario: Configuring how a channel issues gift card codes
        # The per-channel configuration is what decides how guessable a code is and how long a card
        # lasts. Until now nothing exercised its admin screens at all.
        When I want to configure gift cards for the "United States" channel
        And I set the code prefix to "XMAS-"
        And I set the code length to 20
        And I set the validity period to "18 months"
        And I save this configuration
        Then the "United States" channel should issue codes 20 characters long prefixed with "XMAS-"
        And the configuration for "United States" should appear in the list

    @ui
    Scenario: Setting a channel to issue gift cards by an administrator only
        # A shop that hands cards out as goodwill or compensation and never sells them. The mode is
        # shown in the list because an operator running several channels needs to see which of them
        # sell gift cards without opening each configuration in turn.
        When I want to configure gift cards for the "United States" channel
        And I set gift cards to be issued by an administrator only
        And I save this configuration
        Then the "United States" channel should issue gift cards by an administrator only
        And the list should show "United States" as issuing gift cards by an administrator only

    @ui
    Scenario: Offering customers a list of amounts to choose from
        When I want to configure gift cards for the "United States" channel
        And I let customers choose from preset amounts
        And I offer the amounts "25, 50, 100"
        And I save this configuration
        Then the "United States" channel should offer gift cards of "$25.00, $50.00 and $100.00"

    @ui
    Scenario: Letting customers name their own amount within a range
        When I want to configure gift cards for the "United States" channel
        And I let customers choose any amount within a range
        And I allow amounts between "10" and "500"
        And I save this configuration
        Then the "United States" channel should allow any amount between "$10.00" and "$500.00"

    @ui
    Scenario: A preset that is not an amount is refused
        # Also pins the translation domain: a constraint's message is looked up in `validators`, so
        # the same key sitting in messages.en.yaml renders as the raw key on the page.
        When I want to configure gift cards for the "United States" channel
        And I let customers choose from preset amounts
        And I offer the amounts "25, fifty, 100"
        And I save this configuration
        Then I should be told the preset amounts are not amounts
        And no gift card configuration should have been saved

    @ui
    Scenario: A worthless preset is refused rather than dropped
        When I want to configure gift cards for the "United States" channel
        And I let customers choose from preset amounts
        And I offer the amounts "25, 0, 100"
        And I save this configuration
        Then I should be told the preset amounts are not amounts
        And no gift card configuration should have been saved

    @ui
    Scenario: A range with a bound missing is refused
        # A channel that offers a free amount without knowing its bounds offers nothing at all at
        # runtime, which is the safe behaviour but a silent one. The operator has to be told.
        When I want to configure gift cards for the "United States" channel
        And I let customers choose any amount within a range
        And I save this configuration
        Then I should be told the range needs both bounds
        And no gift card configuration should have been saved

    @ui
    Scenario: A code length below the minimum is refused
        # Not a preference - a short code is guessable, and a guessable gift card code is money
        # anybody can spend. The model clamps it as a backstop, but an operator who asks for a
        # 4-character code has to be told, not quietly given a 12-character one.
        When I want to configure gift cards for the "United States" channel
        And I set the code length to 4
        And I save this configuration
        Then I should be told the code length is too short
        And no gift card configuration should have been saved
