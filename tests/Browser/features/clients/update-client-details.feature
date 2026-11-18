Feature: Update a client's details
  In order to keep client contact information correct and controlled
  As an Admin
  I want to be the only one who can edit a client's name, email and phone

  Background:
    Given a suite "Deluxe Suite" with a regular rate of ₱2,500.00 per night
    And "Maria Santos" (maria@example.com, 09171234567) has a pending booking for "Deluxe Suite" next week

  Scenario: Admin updates a client's details to keep their contact information correct
    Given I am signed in as an Admin
    When I open the client "Maria Santos"
    And I choose "Edit Client"
    Then I see the client's current name, email and phone in the form
    When I change the name to "Maria Santos-Reyes"
    And I change the email to "maria.reyes@example.com"
    And I change the phone to "09179876543"
    And I save the changes
    Then I see "Client information updated successfully."
    And I see the client "Maria Santos-Reyes" with email "maria.reyes@example.com" and phone "09179876543"
    And the client's booking shows the new details

  Scenario: Staff views a client without an edit option to keep client records admin-controlled
    Given I am signed in as a Staff member
    When I open the client "Maria Santos"
    Then I see the client "Maria Santos" with email "maria@example.com" and phone "09171234567"
    And I see the client's booking in the booking history
    But I do not see an "Edit Client" button
    And there is no form to edit the client's details
