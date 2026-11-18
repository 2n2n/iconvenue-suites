Feature: Reach the staff sign-in instead of a public website
  In order to keep bookings staff-created only
  As a Guest or Staff member
  I want the website to open on the staff sign-in page and offer no public booking path

  Scenario: Guest opens the website to land on the staff sign-in page
    Given I am not signed in
    When I open the website
    Then I see the staff sign-in page
    And I see the sign-in form
    And I do not see a "Back to Website" link

  Scenario: Guest opens an old venue page to find there is no public booking path
    Given an active venue "Grand Hall" exists
    When I open the old page for "Grand Hall"
    Then I see a "Not Found" page
    And I see no WhatsApp contact, booking request button or availability check
    When I open the old venues, suites and contact pages
    Then each shows a "Not Found" page
    And none offers a WhatsApp contact, booking request button or availability check

  Scenario: Staff signs out to return to the sign-in page
    Given I am signed in as a Staff member
    And I am on the dashboard
    When I sign out from the profile menu
    Then I see the staff sign-in page
    When I open the website again
    Then I see the staff sign-in page
