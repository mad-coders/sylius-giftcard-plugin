@madcoders_gift_card_account
Feature: Seeing my gift cards
    In order to know what I can still spend
    As a Customer
    I want to see the gift cards I use and the ones I bought

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "holder@example.com" identified by "password123"
        And I am logged in as "holder@example.com"

    @ui
    Scenario: A card I use shows what is left on it
        Given the store has a gift card "GIFT-MINE01" worth "$50.00" with "$20.00" left used by "holder@example.com"
        When I browse my gift cards
        Then I should see "GIFT-MINE01" among the gift cards I use
        And "GIFT-MINE01" should show a remaining balance of "$20.00"

    @ui
    Scenario: A card I bought for someone else is listed separately
        Given the store has a gift card "GIFT-GIFT01" worth "$50.00" bought by "holder@example.com"
        When I browse my gift cards
        Then I should see "GIFT-GIFT01" among the gift cards I bought
        And I should not see "GIFT-GIFT01" among the gift cards I use

    @ui
    Scenario: A card bought for me shows the message that came with it
        Given the store has a gift card "GIFT-MINE02" worth "$50.00" used by "holder@example.com" saying "Happy birthday!"
        When I open the balance history of "GIFT-MINE02"
        Then I should see the message "Happy birthday!" on the card

    @ui
    Scenario: A message written in markup is shown as text, not as markup
        Given the store has a gift card "GIFT-MINE03" worth "$50.00" used by "holder@example.com" saying "<script>alert(1)</script>"
        When I open the balance history of "GIFT-MINE03"
        Then the card's page should show "<script>alert(1)</script>" as text rather than as markup

    @ui
    Scenario: I can see where my balance went
        Given the store has a gift card "GIFT-MINE01" worth "$50.00" with "$20.00" left used by "holder@example.com"
        When I open the balance history of "GIFT-MINE01"
        Then I should see a remaining balance of "$20.00"
        And I should see 1 entry in the balance history

    @ui
    Scenario: Somebody else's card is not mine to see
        Given there is a customer account "other@example.com" identified by "password123"
        And the store has a gift card "GIFT-THEIRS" worth "$50.00" used by "other@example.com"
        When I browse my gift cards
        Then I should not see "GIFT-THEIRS" among the gift cards I use

    @ui
    Scenario: I cannot open somebody else's gift card by its address
        Given there is a customer account "other@example.com" identified by "password123"
        And the store has a gift card "GIFT-THEIRS" worth "$50.00" used by "other@example.com"
        When I try to open the gift card "GIFT-THEIRS" belonging to somebody else
        Then I should be refused access
