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

    @ui
    Scenario: Cancelling still refunds a card an administrator topped up in the meantime
        # The refund would take the card above its face value, which the model refuses in order to
        # catch a mistyped admin adjustment. Applying that rule to a refund made cancelling the
        # order fail outright with a 500, and left the customer out of pocket.
        Given there is a customer "holder@example.com" that placed an order "#00012"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-100" worth "$100.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        And an administrator topped the gift card "GIFT-100" up by "$100.00"
        When the order "#00012" was cancelled
        Then the gift card "GIFT-100" should be worth "$200.00"
        And the gift card "GIFT-100" should have 3 entries in its balance history

    @ui
    Scenario: A card that expires between applying it and checking out is dropped
        # A long checkout or an abandoned cart makes this ordinary rather than exotic, and it is the
        # one case where the plugin could lose the shop real money: the payment was already reduced
        # by $40 when the card was applied, so if the card were quietly honoured anyway the shop
        # would hand over goods for money nobody paid, and if it were dropped without re-pricing the
        # payment the customer would be undercharged.
        #
        # Neither happens - the processor re-checks every card on each pass through checkout, drops
        # the ones that are no longer redeemable, and the payment goes back to the full amount.
        Given there is a customer "holder@example.com" that placed an order "#00013"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        And the gift card "GIFT-40" has expired meanwhile
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then the payment for this order should be "$100.00"
        And the gift card "GIFT-40" should be worth "$40.00"

    @ui
    Scenario: A card disabled between applying it and checking out is dropped
        # Same shape, different cause: an administrator pulling a card mid-checkout. The card keeps
        # its balance, so it can be re-enabled and spent later.
        Given there is a customer "holder@example.com" that placed an order "#00014"
        And the customer bought a single "PHP T-Shirt"
        And the gift card "GIFT-40" worth "$40.00" is applied to this order
        And the gift card "GIFT-40" has been disabled meanwhile
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        Then the payment for this order should be "$100.00"
        And the gift card "GIFT-40" should be worth "$40.00"

    @ui
    Scenario: A card on a mixed basket is only charged for the goods
        # The settlement cap, followed all the way to the ledger. The cap decides one number and
        # four things read it: the coverage adjustment, the payment, the balance debited when the
        # order is placed, and what is given back on cancellation. Asserting only the payment would
        # miss the case that costs real money - a card debited $150 for $100 of coverage - because
        # OrderGiftCardAmountModifier debits from the adjustments, not from the payment.
        #
        # See docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
        Given the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product
        And there is a customer "holder@example.com" that placed an order "#00015"
        And the customer bought a single "PHP T-Shirt"
        And the customer bought a single "Gift Card"
        And the gift card "GIFT-500" worth "$500.00" is applied to this order
        When the customer chose "Free" shipping method to "United States" with "Offline" payment
        # The order is worth the shirt plus the gift card, as it always was.
        Then the total of this order should be "$150.00"
        # The card covered the shirt only, so the gift card is still payable in cash...
        And the payment for this order should be "$50.00"
        # ...and exactly $100 left the card, not $150.
        And the gift card "GIFT-500" should be worth "$400.00"
        And the gift card "GIFT-500" should have 1 entry in its balance history

    @ui
    Scenario: Cancelling a mixed basket gives back only the capped amount
        # The fourth reader of that number. A refund computed from the order total rather than the
        # coverage would hand the customer back $150 of a $100 debit - free money, and the kind
        # that only shows up in a reconciliation months later.
        Given the store has a product "Gift Card" priced at "$50.00"
        And the product "Gift Card" is a gift card product
        And there is a customer "holder@example.com" that placed an order "#00016"
        And the customer bought a single "PHP T-Shirt"
        And the customer bought a single "Gift Card"
        And the gift card "GIFT-500" worth "$500.00" is applied to this order
        And the customer chose "Free" shipping method to "United States" with "Offline" payment
        When the order "#00016" was cancelled
        Then the gift card "GIFT-500" should be worth "$500.00"
        And the gift card "GIFT-500" should have 2 entries in its balance history
