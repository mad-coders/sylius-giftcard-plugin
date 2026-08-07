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
