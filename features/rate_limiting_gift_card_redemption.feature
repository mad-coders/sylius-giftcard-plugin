@madcoders_gift_card_cart
Feature: Stopping someone from guessing gift card codes
    In order to keep the money on the cards my shop has issued
    As a Store Owner
    I want a client that keeps trying codes to be stopped, without being told which codes are real

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And I have product "PHP T-Shirt" added to the cart

    @ui
    Scenario: An unknown code and an unusable card are refused in the same words
        # The endpoint must not be a code-existence oracle. If it answered "there is no such code"
        # for one and "this card is expired" for the other, a script would learn which codes are
        # real without ever needing one that can be spent - and a code is money to whoever holds it.
        Given the store has an expired gift card "GIFT-OLD" worth "$40.00"
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be notified that the gift card cannot be used
        When I try to apply the gift card "GIFT-OLD"
        Then I should be notified that the gift card cannot be used
        And no gift card should be applied to my cart

    @ui
    Scenario: Guessing is refused once the attempts run out
        # The test application allows three failed attempts; the shipped default is ten. Both are
        # host configuration - see docs/INSTALLATION.md.
        When I try to apply 3 wrong gift card codes
        And I try to apply the gift card "GIFT-NOPE"
        Then I should be told I have tried too many gift card codes
        And no gift card should be applied to my cart

    @ui
    Scenario: Codes that work never count against the limit
        # Four successful applies against an allowance of three. Only failures cost anything, so a
        # customer using the cards they hold can never talk themselves into the refusal.
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And I apply the gift card "GIFT-40"
        And I apply the gift card "GIFT-40"
        And I apply the gift card "GIFT-40"
        When I apply the gift card "GIFT-40"
        Then I should be notified that the gift card has been applied
        And I should have "$60.00" left to pay

    @ui
    Scenario: A card that works clears the failed attempts before it
        # Two wrong codes, then a real one, then two more wrong ones. Without the reset that is five
        # failures against an allowance of three and the last try would be refused for the limit;
        # with it, the last try is only the third since the tally was cleared and gets the ordinary
        # refusal. A customer redeeming their own cards should never meet the limiter.
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And I try to apply 2 wrong gift card codes
        And I apply the gift card "GIFT-40"
        When I try to apply 2 wrong gift card codes
        And I try to apply the gift card "GIFT-NOPE"
        Then I should be notified that the gift card cannot be used
        And the gift card "GIFT-40" should be applied to my cart
