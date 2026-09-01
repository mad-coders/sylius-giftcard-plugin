@madcoders_gift_card_buying
Feature: Choosing what a gift card is worth
    In order to give someone the right amount
    As a Customer
    I want to choose what the gift card I buy is worth, and leave a message with it

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product
        And the store ships everywhere for free
        And the store allows paying offline

    @ui
    Scenario: A channel that sells gift cards at the product's price issues a card worth that price
        Given the channel sells gift cards at the product's price
        And there is a customer "buyer@example.com" that placed an order "#00001"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00001" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$50.00"

    @ui
    Scenario: A customer picks one of the channel's preset amounts
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        And there is a customer "buyer@example.com" that placed an order "#00002"
        And the customer bought a "Gift Card" for "$100.00"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00002" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$100.00"
        And the gift card issued to "buyer@example.com" should be usable

    @ui
    Scenario: A customer types an amount within the channel's range
        Given the channel lets customers choose any gift card amount between "$10.00" and "$500.00"
        And there is a customer "buyer@example.com" that placed an order "#00003"
        And the customer bought a "Gift Card" for "$123.45"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00003" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$123.45"

    @ui
    Scenario: A customer picks a preset in a channel that also allows a free amount
        Given the channel offers gift cards of "$25.00 and $50.00" or any amount between "$10.00" and "$500.00"
        And there is a customer "buyer@example.com" that placed an order "#00004"
        And the customer bought a "Gift Card" for "$25.00"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00004" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$25.00"

    @ui
    Scenario: A customer types a free amount in a channel that also offers presets
        Given the channel offers gift cards of "$25.00 and $50.00" or any amount between "$10.00" and "$500.00"
        And there is a customer "buyer@example.com" that placed an order "#00005"
        And the customer bought a "Gift Card" for "$77.00"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00005" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$77.00"

    @ui
    Scenario: An amount outside the channel's range is not honoured
        Given the channel lets customers choose any gift card amount between "$10.00" and "$500.00"
        And there is a customer "buyer@example.com" that placed an order "#00006"
        And the customer submitted an amount of "$5000.00" for a "Gift Card" without using the shop form
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00006" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$50.00"

    @ui
    Scenario: An amount that is not one of the channel's presets is refused even when the form is bypassed
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        And there is a customer "buyer@example.com" that placed an order "#00007"
        And the customer submitted an amount of "$0.01" for a "Gift Card" without using the shop form
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00007" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$50.00"

    @ui
    Scenario: A channel selling at the product's price ignores an amount submitted anyway
        Given the channel sells gift cards at the product's price
        And there is a customer "buyer@example.com" that placed an order "#00008"
        And the customer submitted an amount of "$1000.00" for a "Gift Card" without using the shop form
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00008" is already paid
        Then the gift card issued to "buyer@example.com" should be worth "$50.00"

    @ui
    Scenario: Two gift cards of different amounts in one order issue two cards worth those amounts
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        And there is a customer "buyer@example.com" that placed an order "#00009"
        And the customer bought a "Gift Card" for "$25.00"
        And the customer bought a "Gift Card" for "$100.00"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00009" is already paid
        Then 2 gift cards should have been issued to "buyer@example.com"
        And the gift cards issued to "buyer@example.com" should be worth "$25.00 and $100.00"

    @ui
    Scenario: Buying two of the same chosen amount issues two cards of that amount
        Given the channel offers gift cards of "$25.00, $50.00 and $100.00"
        And there is a customer "buyer@example.com" that placed an order "#00010"
        And the customer bought 2 "Gift Card" products for "$100.00" each
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00010" is already paid
        Then 2 gift cards should have been issued to "buyer@example.com"
        And the gift cards issued to "buyer@example.com" should be worth "$100.00 and $100.00"
