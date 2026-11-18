Feature: Book without a client email
  In order to serve walk-in and phone-only guests
  As a Staff member
  I want to create and keep bookings for clients who have no email address

  Background:
    Given a suite "Deluxe Suite" with a regular rate of ₱2,500.00 per night
    And I am signed in as a Staff member

  Scenario: Staff creates a booking without an email to serve a walk-in guest
    When I start a booking for "Deluxe Suite" next week
    Then the email field is labelled "Client Email (optional)"
    And I see "Optional. Needed for the booking confirmation, payment and reminder emails."
    When I enter the client's name "Pedro Walk-in" and phone "09181112222" and leave the email empty
    And I create the booking
    Then I see the booking details for "Pedro Walk-in"
    And I see "No email — the client won't receive email notifications"
    And no email is sent for the booking

  Scenario: Staff enters an invalid email to see it rejected
    When I start a booking for "Deluxe Suite" next week
    And I enter the client's name "Pedro Walk-in", phone "09181112222" and email "pedro..walkin@example.com"
    And I create the booking
    Then I stay on the booking form
    And I see an email error under the email field
    And no booking is created

  Scenario: Staff views a booking without an email to know the client won't be notified
    Given "Ana Phone-only" booked "Deluxe Suite" next week with phone "09183334444" and no email
    And "Ben Emailer" booked "Deluxe Suite" in two weeks with email "ben@example.com"
    When I open the booking of "Ana Phone-only"
    Then I see "No email — the client won't receive email notifications"
    When I open the booking of "Ben Emailer"
    Then I see "ben@example.com"
    And I do not see the no-email notice

  Scenario: Staff updates a booking to remove the email when the client has none
    Given "Carla Typo" booked "Deluxe Suite" next week with email "carla@wrong.example" and phone "09185556666"
    When I edit the booking
    And I clear the client email
    And I update the booking
    Then I see "No email — the client won't receive email notifications"
    And the booking keeps the phone "09185556666"
    And the booking is still "Pending"

  Scenario: Staff opens a client without an email to see their bookings by phone
    Given "Dino Walk-in" booked "Deluxe Suite" next week with phone "09187778888" and no email
    When I open Clients
    Then I see "Dino Walk-in" with "No email"
    When I view Dino's bookings
    Then I see Dino's client page with "No email" and phone "09187778888"
    And I see Dino's booking

  Scenario: Admin adds an email to a phone-only client to start sending notifications
    Given "Dino Walk-in" booked "Deluxe Suite" next week with phone "09187778888" and no email
    And I am signed in as an Admin instead
    When I open Dino's client page from Clients
    And I add the email "dino@example.com" in Edit Client Info and save
    Then I see "Client information updated successfully."
    And Dino's client page shows "dino@example.com"
    And Dino's booking now shows "dino@example.com" instead of the no-email notice
