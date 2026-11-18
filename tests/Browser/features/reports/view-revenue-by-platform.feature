Feature: View revenue by platform
  In order to see which sales channel brings the most business
  As an Admin
  I want the reports to break bookings and revenue down by booking platform

  Scenario: Admin views revenue by platform to compare channels
    Given this month a client booked "Deluxe Suite" through Agoda for ₱1,800.00 and paid ₱1,800.00, verified
    And this month a client booked "Deluxe Suite" at the regular rate for ₱2,500.00 and paid ₱1,000.00, verified
    And this month a booking through Booking.com for ₱2,100.00 was cancelled
    And I am signed in as an Admin
    When I open Reports
    Then the "By Booking Platform" card shows Agoda with 1 booking, ₱1,800.00 booked and ₱1,800.00 verified revenue
    And it shows Regular with 1 booking, ₱2,500.00 booked and ₱1,000.00 verified revenue
    And it does not show Booking.com
