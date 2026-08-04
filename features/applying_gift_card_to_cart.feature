@madcoders_gift_card_cart
Feature: Paying with a gift card
    In order to spend a gift card I was given
    As a Customer
    I want to apply it to my cart and see what it takes off the total

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And I have product "PHP T-Shirt" added to the cart

    @ui
    Scenario: Applying a gift card reduces the cart total
        Given the store has a gift card "GIFT-40" worth "$40.00"
        When I apply the gift card "GIFT-40"
        Then I should be notified that the gift card has been applied
        And the gift card "GIFT-40" should be applied to my cart
        And my cart total should be "$60.00"

    @ui
    Scenario: Applying two gift cards stacks them
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And the store has a gift card "GIFT-25" worth "$25.00"
        When I apply the gift card "GIFT-40"
        And I apply the gift card "GIFT-25"
        Then the gift card "GIFT-40" should be applied to my cart
        And the gift card "GIFT-25" should be applied to my cart
        And my cart total should be "$35.00"

    @ui
    Scenario: A gift card worth more than the cart only covers what is owed
        Given the store has a gift card "GIFT-500" worth "$500.00"
        When I apply the gift card "GIFT-500"
        Then my cart total should be "$0.00"
        And the gift cards should reduce my cart by "-$100.00"

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
        And my cart total should be "$85.00"
