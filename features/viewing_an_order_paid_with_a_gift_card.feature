@madcoders_gift_card_order_view
Feature: Seeing what a gift card paid for on an order
    In order to answer a customer asking why they were charged what they were
    As an Administrator
    I want an order to show what its gift cards covered and what was actually charged

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And the store ships everywhere for free
        And the store allows paying offline
        And I am logged in as an administrator

    @ui
    Scenario: An order part-paid by a gift card shows the split
        # The order total stays at the full value of the goods - a card changes who pays, not the
        # price - so without these lines there is nothing on the page explaining why the payment is
        # smaller than the total.
        Given there is a customer "holder@example.com" that placed an order "#00001"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When I view the order "#00001" in the admin panel
        Then it should show "-$40.00" covered by gift cards
        And it should show "$60.00" left to pay
        And it should name the gift card "GIFT-40" that paid for it

    @ui
    Scenario: An order covered entirely by a gift card shows nothing left to pay
        Given there is a customer "holder@example.com" that placed an order "#00002"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-100" worth "$100.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When I view the order "#00002" in the admin panel
        Then it should show "-$100.00" covered by gift cards
        And it should show "$0.00" left to pay

    @ui
    Scenario: An order paid for with two gift cards names both of them
        Given there is a customer "holder@example.com" that placed an order "#00003"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-30" worth "$30.00" is applied to this order
        And the gift card "GIFT-50" worth "$50.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When I view the order "#00003" in the admin panel
        Then it should show "-$80.00" covered by gift cards
        And it should show "$20.00" left to pay
        And it should name the gift card "GIFT-30" that paid for it
        And it should name the gift card "GIFT-50" that paid for it

    @ui
    Scenario: An ordinary order carries no gift card lines at all
        Given there is a customer "holder@example.com" that placed an order "#00004"
        And the customer bought a single "PHP T-Shirt"
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When I view the order "#00004" in the admin panel
        Then it should say nothing about gift cards
