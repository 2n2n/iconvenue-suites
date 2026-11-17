Feature: Manage booking platforms to price rooms per channel
  In order to charge each sales channel its own room price
  As an Admin
  I want to keep a list of the booking platforms we sell rooms on

  Scenario: Admin adds a booking platform to price rooms per channel
    Given I am signed in as an Admin
    And Agoda and Booking.com are already listed as booking platforms
    When I open Room Rates
    And I choose to manage platforms
    And I add a platform named "Expedia"
    Then I see "Expedia" in the platform list marked "Active"
    And I still see "Agoda" and "Booking.com"

  Scenario: Admin adds a platform that is already listed to see it rejected
    Given I am signed in as an Admin
    And "Agoda" is already listed as a booking platform
    When I open Booking Platforms
    And I add a platform named "Agoda"
    Then I see "The name has already been taken."
    And "Agoda" is listed only once

  Scenario: Admin deactivates a booking platform to stop staff from selecting it
    Given I am signed in as an Admin
    And Agoda and Booking.com are active booking platforms
    And a suite "Deluxe Suite" is available
    When I open Booking Platforms
    And I deactivate "Booking.com"
    Then I see "Booking.com" marked "Inactive"
    When a Staff member starts a booking for "Deluxe Suite"
    Then the Rate choices are "Regular rate" and "Agoda"
    And Agoda shows the room's regular price because the room has no Agoda rate
    And "Booking.com" is not offered
