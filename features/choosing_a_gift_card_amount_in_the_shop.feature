@madcoders_gift_card_buying
Feature: Being offered a gift card amount in the shop
    In order to pick what my gift is worth without guessing
    As a Customer
    I want the gift card product page to show me what the shop offers

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product

    @ui
    Scenario: The channel's preset amounts are shown as options to pick from
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        When I look at the "Gift Card" product page
        Then I should be offered the amounts "$25.00, $50.00 and $100.00" to choose from
        And each amount should be a selectable option rather than an entry in a list
        And I should not be able to type my own amount

    @ui
    Scenario: A free-amount channel lets me type an amount and tells me the bounds
        Given the channel lets customers choose any gift card amount between "$10.00" and "$500.00"
        When I look at the "Gift Card" product page
        Then I should be able to type my own amount
        And the form should tell me I can type anything between "$10.00" and "$500.00"

    @ui
    Scenario: A channel offering both shows the presets and an other-amount box
        Given the channel offers gift cards of "$25.00 and $50.00" or any amount between "$10.00" and "$500.00"
        When I look at the "Gift Card" product page
        Then I should be offered the amounts "$25.00, $50.00 and Other amount" to choose from
        And each amount should be a selectable option rather than an entry in a list
        And I should be able to type my own amount

    @ui
    Scenario: A channel selling at the product's price asks for no amount
        Given the channel sells gift cards at the product's price
        When I look at the "Gift Card" product page
        Then I should not be asked to choose an amount

    @ui
    Scenario: The message field is offered with its limit on show
        Given the channel sells gift cards at the product's price
        When I look at the "Gift Card" product page
        Then I should be able to write a message
        And the message field should tell me how long it may be
