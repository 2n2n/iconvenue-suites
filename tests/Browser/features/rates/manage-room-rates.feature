Feature: Manage room rates
  In order to keep every room's walk-in and channel prices right
  As an Admin
  I want to see and edit all room rates on one grid

  Background:
    Given Agoda and Booking.com are active booking platforms
    And a suite "Deluxe Suite" with a regular rate of ₱2,500.00 and an Agoda rate of ₱2,800.00
    And a suite "Family Suite" with a regular rate of ₱3,500.00 and no platform rates

  Scenario: Admin edits rates for several rooms at once to update prices in one save
    Given I am signed in as an Admin
    When I open Room Rates
    And I change the regular rate of "Deluxe Suite" to 2700
    And I set the Booking.com rate of "Family Suite" to 3300
    Then both edited cells are highlighted
    And I see "2 unsaved changes"
    When I save the changes
    Then I see "2 rates updated. New bookings will use the new prices."
    And "Deluxe Suite" has a regular rate of 2700
    And "Family Suite" has a Booking.com rate of 3300
    And nothing is left unsaved

  Scenario: Admin sets one price for selected rooms to change a platform's rate quickly
    Given I am signed in as an Admin
    When I open Room Rates
    And I select "Deluxe Suite" and "Family Suite"
    And I set the Agoda rate of the selected rooms to 3000
    Then both rooms show an Agoda rate of 3000, highlighted
    And I see "2 unsaved changes"
    When I save the changes
    Then I see "2 rates updated. New bookings will use the new prices."
    And both rooms keep an Agoda rate of 3000

  Scenario: Admin raises a platform rate without selecting a room to see a reminder
    Given I am signed in as an Admin
    When I open Room Rates
    And I raise the Agoda rate of the selected rooms by 10% without selecting any room
    Then I see "Select at least one room first."
    And I see "0 unsaved changes"

  Scenario: Admin discards unsaved rate edits to keep the current prices
    Given I am signed in as an Admin
    When I open Room Rates
    And I change the Agoda rate of "Deluxe Suite" to 1
    And I clear the regular rate of "Family Suite"
    Then I see "2 unsaved changes"
    When I discard the changes
    Then "Deluxe Suite" shows its Agoda rate of 2800 again
    And "Family Suite" shows its regular rate of 3500 again
    And I see "0 unsaved changes" and cannot save
    When I open Room Rates again
    Then the saved rates are unchanged

  Scenario: Admin spots rooms without a channel price to fill in missing rates
    Given I am signed in as an Admin
    When I open Room Rates
    Then the Agoda cell of "Deluxe Suite" shows 2800
    And the Agoda cell of "Family Suite" is empty and shows its regular rate 3500 as a hint
    When I fill in the Agoda rate of "Family Suite" with 3200
    And I save the changes
    Then "Family Suite" has an Agoda rate of 3200

  Scenario: Staff sees each rate's price when booking to choose the right channel
    Given I am signed in as a Staff member
    When I start a booking for "Deluxe Suite" next week
    Then the Rate choices read:
      | Regular rate — ₱2,500.00 / night          |
      | Agoda — ₱2,800.00 / night                 |
      | Booking.com — ₱2,500.00 / night (regular) |
