Feature: Sign in to manage bookings
  In order to manage bookings, payments and venues
  As an Admin or Staff member
  I want to sign in to the admin panel

  Scenario: Admin signs in to access the dashboard
    Given I am an active Admin
    When I open the sign-in page
    And I sign in with my email and password
    Then I see the Dashboard
    And I see my booking and venue totals

  Scenario: Guest submits wrong credentials to see an error
    Given I am on the sign-in page
    When I sign in with an email and password that do not match any account
    Then I stay on the sign-in page
    And I see "The provided credentials do not match our records."
