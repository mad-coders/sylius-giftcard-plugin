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
    Scenario: Re-applying a card the cart already has does not buy back the allowance
        # Applying a card does not debit it, and a card the cart already holds is added again as a
        # silent no-op that still succeeds, still flushes and still flashes exactly like the first
        # time. If that counted as a redemption, one $5 card would buy unlimited guessing: nine wrong
        # codes, then your own, for ever. The card is put on the cart directly rather than through the
        # redeem field so that the re-submission below is the first success of the window - otherwise
        # the cap on forgiveness would hide the missing guard instead of the guard being tested.
        Given the gift card "GIFT-40" worth "$40.00" is already on my cart
        And I try to apply 2 wrong gift card codes
        When I apply the gift card "GIFT-40"
        Then I should be notified that the gift card has been applied
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be notified that the gift card cannot be used
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be told I have tried too many gift card codes

    @ui
    Scenario: A second real card in the same window does not forgive a second run of guesses
        # Even a genuinely new redemption is repeatable - remove the card, apply it again - so it is
        # not on its own evidence that the client is not guessing. One forgiveness per window bounds
        # what a real card is worth to an attacker while still covering the customer who fumbled.
        Given the store has a gift card "GIFT-40" worth "$40.00"
        And the store has a gift card "GIFT-25" worth "$25.00"
        And I try to apply 2 wrong gift card codes
        And I apply the gift card "GIFT-40"
        And I try to apply 2 wrong gift card codes
        # The second real card still applies - the customer is not punished for holding two cards.
        # What it no longer does is wipe the two failures that came before it.
        When I apply the gift card "GIFT-25"
        Then I should be notified that the gift card has been applied
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be notified that the gift card cannot be used
        When I try to apply the gift card "GIFT-NOPE"
        Then I should be told I have tried too many gift card codes

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
