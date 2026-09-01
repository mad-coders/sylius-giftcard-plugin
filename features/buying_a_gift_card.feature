@madcoders_gift_card_buying
Feature: Buying a gift card
    In order to give someone a gift card
    As a Customer
    I want to buy one and have it issued when I pay

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product
        And the store ships everywhere for free
        And the store allows paying offline

    @ui
    Scenario: A gift card is issued once the order is paid
        Given there is a customer "buyer@example.com" that placed an order "#00001"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00001" is already paid
        Then 1 gift card should have been issued to "buyer@example.com"
        And the gift card issued to "buyer@example.com" should be worth "$50.00"
        And the gift card issued to "buyer@example.com" should be usable
        And "buyer@example.com" should have been emailed the code of their gift card

    @ui
    Scenario: No gift card is issued before the order is paid
        Given there is a customer "buyer@example.com" that placed an order "#00002"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then 0 gift cards should have been issued to "buyer@example.com"

    @ui
    Scenario: Buying two gift cards issues two separate cards
        Given there is a customer "buyer@example.com" that placed an order "#00003"
        And the customer bought 2 "Gift Card" products
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00003" is already paid
        Then 2 gift cards should have been issued to "buyer@example.com"

    @ui
    Scenario: Buying an ordinary product issues nothing
        Given the store has a product "Mug" priced at "$20.00"
        And there is a customer "buyer@example.com" that placed an order "#00004"
        And the customer bought a single "Mug"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00004" is already paid
        Then 0 gift cards should have been issued to "buyer@example.com"

    @ui
    Scenario: A channel that sells gift cards issues one when the order is paid
        # The paired half of the scenario below. Stated explicitly rather than left to the default,
        # so the two modes are compared on the same setup and the refusal cannot be read as a
        # configured channel simply issuing nothing.
        Given the channel sells gift cards in the shop
        And there is a customer "buyer@example.com" that placed an order "#00006"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00006" is already paid
        Then 1 gift card should have been issued to "buyer@example.com"

    @ui
    Scenario: A channel that issues gift cards by an administrator only issues none when an order is paid
        # A shop that hands out cards as goodwill and never sells them. The cart refuses a gift card
        # product in this mode, but a cart filled before the mode changed is already sitting there -
        # so paying it must not hand out a card either, or a mode change would leak free money for
        # as long as the oldest unpaid order lives.
        Given the channel issues gift cards by an administrator only
        And there is a customer "buyer@example.com" that placed an order "#00007"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00007" is already paid
        Then 0 gift cards should have been issued to "buyer@example.com"

    @ui
    Scenario: Cancelling the order takes the issued gift card out of circulation
        Given there is a customer "buyer@example.com" that placed an order "#00005"
        And the customer bought a single "Gift Card"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        And the order "#00005" is already paid
        When the order "#00005" was cancelled
        Then 1 gift card should have been issued to "buyer@example.com"
        And the gift card issued to "buyer@example.com" should no longer be usable
