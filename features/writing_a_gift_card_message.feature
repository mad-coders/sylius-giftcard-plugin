@madcoders_gift_card_buying
Feature: Writing a message on a gift card
    In order to give a gift rather than a code
    As a Customer
    I want to leave a short message with the gift card I buy

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product
        And the store ships everywhere for free
        And the store allows paying offline

    @ui
    Scenario: The message reaches the issued card and the email that delivers it
        Given there is a customer "buyer@example.com" that placed an order "#00001"
        And the customer bought a "Gift Card" saying "Happy birthday, spend it on something silly"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00001" is already paid
        Then the gift card issued to "buyer@example.com" should say "Happy birthday, spend it on something silly"
        And "buyer@example.com" should have been emailed a gift card saying "Happy birthday, spend it on something silly"

    @ui
    Scenario: Leaving the message blank issues a card with no message
        Given there is a customer "buyer@example.com" that placed an order "#00002"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00002" is already paid
        Then the gift card issued to "buyer@example.com" should carry no message
        And "buyer@example.com" should have been emailed the code of their gift card

    @ui
    Scenario: Two gift cards in one order keep their own messages
        Given there is a customer "buyer@example.com" that placed an order "#00003"
        And the customer bought a "Gift Card" saying "For Ann"
        And the customer bought a "Gift Card" saying "For Bob"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00003" is already paid
        Then 2 gift cards should have been issued to "buyer@example.com"
        And the gift cards issued to "buyer@example.com" should say "For Ann and For Bob"

    @ui
    Scenario: A message written in markup is delivered as text, not as markup
        Given there is a customer "buyer@example.com" that placed an order "#00004"
        And the customer bought a "Gift Card" saying "<script>alert(1)</script>"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00004" is already paid
        Then the email to "buyer@example.com" should show "<script>alert(1)</script>" as text rather than as markup
