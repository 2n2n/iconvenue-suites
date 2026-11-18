Feature: Sign in repeatedly without being locked out
  In order to keep working through a busy shift
  As a Staff member
  I want my correct sign-ins never to count towards the failed sign-in lockout

  Scenario: Staff signs in several times in a row to keep working without being locked out
    Given I am an active Staff member
    When I sign in with my email and password 7 times within a minute, signing out after each time
    Then each time I see the Dashboard
    And I never see "Too many login attempts"
