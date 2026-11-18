Feature: Book a suite at a channel rate
  In order to charge each guest the price of the channel they booked through
  As a Staff member
  I want to choose the rate source when I book a suite

  Background:
    Given a suite "Deluxe Suite" with a regular rate of ₱2,500.00 per night
    And Agoda and Booking.com are active booking platforms
    And "Deluxe Suite" has an Agoda rate of ₱1,800.00 and no Booking.com rate
    And I am signed in as a Staff member

  Scenario: Staff books a suite at a platform rate to charge the channel price
    When I start a booking for "Deluxe Suite" next week
    And I enter the client's name, email and phone
    And I set the number of days to 2
    And I choose the "Agoda" rate
    And I create the booking
    Then I see the rate "Agoda — ₱1,800.00 / night"
    And I see the total amount of ₱3,600.00

  Scenario: Staff books a suite at the regular rate to charge walk-in pricing
    When I start a booking for "Deluxe Suite" next week
    And I enter the client's name, email and phone
    Then the Rate is "Regular rate"
    And I do not see a platform reference field
    When I create the booking
    Then I see the rate "Regular — ₱2,500.00 / night"
    And I see the total amount of ₱2,500.00

  Scenario: Staff books a suite on a platform without a room rate to fall back to the regular rate
    When I start a booking for "Deluxe Suite" next week
    And I enter the client's name, email and phone
    And I choose the "Booking.com" rate
    Then I see "No Booking.com rate set for this room — regular rate applied."
    When I create the booking
    Then I see the rate "Booking.com — ₱2,500.00 / night"
    And I see the total amount of ₱2,500.00

  Scenario: Staff applies a discount to a platform-rate booking to reduce the total
    When I start a booking for "Deluxe Suite" next week
    And I enter the client's name, email and phone
    And I set the number of days to 2
    And I choose the "Agoda" rate
    And I give a 10% discount for "Loyalty"
    Then the discount preview shows a final amount of ₱3,240.00
    When I create the booking
    Then I see the original amount of ₱3,600.00
    And I see a discount of ₱360.00
    And I see the total amount of ₱3,240.00

  Scenario: Staff records the platform reference to reconcile with the channel
    When I start a booking for "Deluxe Suite" next week
    And I enter the client's name, email and phone
    And I choose the "Agoda" rate
    Then I see the platform reference field
    When I enter the platform reference "AGD-778812"
    And I create the booking
    Then I see the platform reference "AGD-778812"

  Scenario: Staff books a venue to keep event pricing free of platform rates
    Given a venue "Grand Hall" with a full-day price of ₱20,000.00
    When I start a booking for "Grand Hall" next week
    Then I do not see a Rate choice
    When I enter the client's name, email and phone
    And I create the booking
    Then I see the total amount of ₱20,000.00
    And I do not see a booking rate
