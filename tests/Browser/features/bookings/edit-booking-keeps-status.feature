Feature: Edit a booking without changing its status
  In order to keep a booking's status tied to its verified payments
  As a Staff member
  I want editing a booking's details to leave its status as it was

  Background:
    Given a suite "Deluxe Suite" with a regular rate of ₱2,500.00 per night
    And I am signed in as a Staff member

  Scenario: Staff edits a pending booking to keep it pending until payment is verified
    Given I created a booking for "Deluxe Suite" next week for "Elena Pending"
    And the booking is "Pending"
    When I edit the booking
    Then the status shows "Pending"
    When I change only the notes to "Guest arrives late, around 9 PM"
    And I update the booking
    Then I see the notes "Guest arrives late, around 9 PM"
    And the booking is still "Pending"

  Scenario: Staff edits a confirmed booking to keep it confirmed
    Given "Felix Confirmed" has a confirmed, fully paid booking for "Deluxe Suite" next week
    When I edit the booking
    Then the status shows "Confirmed"
    When I change only the notes to "Extra towels requested"
    And I update the booking
    Then I see the notes "Extra towels requested"
    And the booking is still "Confirmed"
