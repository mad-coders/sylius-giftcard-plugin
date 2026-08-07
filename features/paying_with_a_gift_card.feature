@madcoders_gift_card_checkout
Feature: Paying for an order with a gift card
    In order to actually spend what is on my card
    As a Customer
    I want the balance to move when the order is placed, and to come back if it is cancelled

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And the store ships everywhere for free
        And the store allows paying offline

    @ui
    Scenario: Placing the order takes the money off the card and charges only the rest
        Given there is a customer "holder@example.com" that placed an order "#00001"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        # The order is still worth what the goods are worth - a gift card is money, not a discount.
        Then the total of this order should be "$100.00"
        # ...and it is the payment that drops. This is the load-bearing assertion: if the card ever
        # stops settling the payment, the customer is charged the full $100 with the card debited.
        And the payment for this order should be "$60.00"
        And the gift card "GIFT-40" should be worth "$0.00"
        And the gift card "GIFT-40" should have 1 entry in its balance history

    @ui
    Scenario: Spending a card records me as the one using it
        Given there is a customer "holder@example.com" that placed an order "#00002"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then the gift card "GIFT-40" should be used by "holder@example.com"

    @ui
    Scenario: A card larger than the order is only charged what was owed
        Given there is a customer "holder@example.com" that placed an order "#00003"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-500" worth "$500.00" is applied to this order
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then this order should have nothing left to pay
        And the total of this order should be "$100.00"
        And the gift card "GIFT-500" should be worth "$400.00"

    @ui
    Scenario: A card that exactly covers the order leaves nothing to pay
        Given there is a customer "holder@example.com" that placed an order "#00004"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-100" worth "$100.00" is applied to this order
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then this order should have nothing left to pay
        And the gift card "GIFT-100" should be worth "$0.00"

    @ui
    Scenario: Two cards on one order are each charged only what they covered
        Given there is a customer "holder@example.com" that placed an order "#00005"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-70" worth "$70.00" is applied to this order
        And the gift card "GIFT-50" worth "$50.00" is applied to this order
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then this order should have nothing left to pay
        # The second card is only charged the $30 still owed - the first covered the rest.
        And the gift card "GIFT-70" should be worth "$0.00"
        And the gift card "GIFT-50" should be worth "$20.00"

    @ui
    Scenario: Cancelling the order puts the money back on the card
        Given there is a customer "holder@example.com" that placed an order "#00006"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00006" was cancelled
        Then the gift card "GIFT-40" should be worth "$40.00"
        And the gift card "GIFT-40" should have 2 entries in its balance history

    @ui
    Scenario: Cancelling gives back only what was actually charged
        Given there is a customer "holder@example.com" that placed an order "#00007"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-500" worth "$500.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00007" was cancelled
        Then the gift card "GIFT-500" should be worth "$500.00"

    @ui
    Scenario: An order part-paid by a gift card still counts as fully paid
        # Sylius decides the payment state by comparing completed payments against the order total,
        # which a gift card deliberately does not reduce. Without a resolver that understands the
        # split, the order sticks at partially_paid forever: it can never be fulfilled, and the
        # `pay` transition that issues purchased gift cards and emails their codes never fires.
        Given there is a customer "holder@example.com" that placed an order "#00008"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00008" is already paid
        Then this order should be fully paid

    @ui
    Scenario: An order covered entirely by a gift card still counts as fully paid
        Given there is a customer "holder@example.com" that placed an order "#00009"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-100" worth "$100.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00009" is already paid
        Then this order should be fully paid

    @ui
    Scenario: Retrying a failed payment does not charge me for the gift card money again
        # When a payment fails, Sylius replaces it with a fresh one sized from the order total. The
        # card was debited when the order was placed, so without a correction the customer is asked
        # to hand over the same $40 a second time - and nothing anywhere throws.
        Given there is a customer "holder@example.com" that placed an order "#00010"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the payment for this order fails
        Then the customer should be asked to pay "$60.00" to retry
        And the gift card "GIFT-40" should be worth "$0.00"
        And the gift card "GIFT-40" should have 1 entry in its balance history

    @ui
    Scenario: Retrying a failed payment on a fully covered order asks for nothing
        Given there is a customer "holder@example.com" that placed an order "#00011"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-100" worth "$100.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the payment for this order fails
        Then the customer should be asked to pay "$0.00" to retry
