Feature: Set platform rates for a suite
  In order to match the price each booking channel shows our guests
  As an Admin
  I want to set a nightly rate per booking platform for every suite

  Scenario: Admin sets platform rates for a suite to match each channel's price
    Given I am signed in as an Admin
    And a suite "Deluxe Suite" with a regular rate of ₱2,500.00 per night
    And Agoda and Booking.com are active booking platforms
    When I open Suites
    And I open the Rates for "Deluxe Suite"
    Then I am on Room Rates with the "Deluxe Suite" row highlighted
    And I see its regular rate of 2500
    When I set its Agoda rate to 1800
    And I set its Booking.com rate to 2100
    Then I see "2 unsaved changes"
    When I save the changes
    Then I see "2 rates updated. New bookings will use the new prices."
    And I see the Agoda rate is 1800
    And I see the Booking.com rate is 2100

  Scenario: Admin clears a platform rate to charge the regular rate on that channel
    Given I am signed in as an Admin
    And a suite "Deluxe Suite" with a regular rate of ₱2,500.00 per night
    And "Deluxe Suite" has an Agoda rate of ₱1,800.00
    When I open Room Rates
    And I clear the Agoda rate of "Deluxe Suite"
    And I save the changes
    Then the Agoda rate is empty and shows the regular rate 2500 as its placeholder

  Scenario: Staff is denied access to platform rates to keep pricing admin-controlled
    Given I am signed in as a Staff member
    And a suite "Deluxe Suite" exists
    Then I do not see "Room Rates" in the menu
    When I try to open Room Rates
    Then I am refused access
    And I see no rate fields
    When I try to open the old Rates page for "Deluxe Suite"
    Then I am refused access
    When I try to open Booking Platforms
    Then I am refused access
