Feature: Get locked out after repeated failed sign-ins
  In order to keep the admin panel safe from password guessing
  As a Guest on the public sign-in page
  I want repeated failed sign-ins for one email to be paused for a short time

  Scenario: Guest repeats failed logins to get temporarily locked out
    Given I am on the sign-in page
    When I sign in 5 times with an email and password that do not match any account
    Then each time I see "The provided credentials do not match our records."
    When I sign in once more with the same email within a minute
    Then I see "Too many login attempts. Please try again in"
    And the email field still shows the email I typed

  Scenario: Guest logs in with another email to stay unaffected by someone else's lockout
    Given another email has been locked out after 6 failed sign-ins
    When I sign in with a different email and a wrong password
    Then I see "The provided credentials do not match our records."
    And I do not see "Too many login attempts"
