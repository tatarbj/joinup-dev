@api @email
Feature:
  In order to efficiently manage users
  As a moderator of the website
  I need to be able to cancel user accounts

  Background:
    Given users:
      | Username      | Roles     | E-mail                   | First name | Family name |
      | Robin Kelley  | Moderator | RobinKelley@example.com  | Robin      | Kelley      |
      | Hazel Olson   |           | HazelOlson@example.com   | Hazel      | Olson       |
      | Amelia Barker |           | AmeliaBarker@example.com | Amelia     | Barker      |
      | alicia__1997  |           | AliciaPotter@example.com | Alicia     | Potter      |
    And collections:
      | title                   | state     |
      | Lugia was just released | validated |
      | Articuno is hunted      | validated |
    # Assign facilitator role in order to allow creation of a solution.
    # In UAT this can be done by creating the collection through the UI
    # with the related user.
    And the following collection user memberships:
      | collection              | user          | roles                      |
      | Lugia was just released | Hazel Olson   | administrator, facilitator |
      | Articuno is hunted      | Amelia Barker | administrator, facilitator |

  Scenario: Canceling users that are the sole owners of collections cannot be done.
    When I am logged in as a moderator
    And I click "People"
    And I check "Hazel Olson"
    And I check "Amelia Barker"
    And I select "Cancel the selected user account(s)" from "Action"
    And I press the "Apply to selected items" button
    Then I should not see the following lines of text:
      | This action cannot be undone.                |
      | When cancelling these accounts               |
      | Require email confirmation to cancel account |
      | Notify user when account is canceled        |
    But I should see the text "User Hazel Olson cannot be deleted as it is currently the sole owner of these collections:"
    And I should see the text "User Amelia Barker cannot be deleted as it is currently the sole owner of these collections:"
    And I should see the link "Lugia was just released"
    And I should see the link "Articuno is hunted"
    And I should see the link "Go back"
    And I should not see the button "Cancel accounts"

  Scenario: Canceling a user directly from the profile when he is the sole owner of a collection, cannot be done.
    When I am logged in as a moderator
    And I click "People"
    And I click "Hazel Olson"
    And I click "Edit" in the "Header" region
    And I press "Cancel account"
    Then I should not see the following lines of text:
      | This action cannot be undone.                |
      | When cancelling these accounts               |
      | Require email confirmation to cancel account |
      | Notify user when account is canceled        |
    But I should see the text "User Hazel Olson cannot be deleted as it is currently the sole owner of these collections:"
    And I should see the link "Go back"
    And I should not see the button "Cancel account"

  @javascript
  Scenario: A moderator deletes a user.
    When all e-mails have been sent
    And I am logged in as a moderator
    And I click "People"
    And I click "Alicia Potter"
    And I open the header local tasks menu
    And I click "Edit" in the "Header" region
    And I press "Cancel account"
    Then I should see the link "Go back"
    When I press "Cancel account"
    And I wait for the batch job to finish
    And the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                                                                                                                                                                                                                            |
      | subject        | Your account has been deleted.                                                                                                                                                                                                                                      |
      | body           | Your account alicia__1997 has been deleted.This action has been done in the framework of moderation activities regularly conducted on the Joinup platform. If you believe that this action has been performed by mistake, please contact The Joinup Support Team at |

  @javascript
  Scenario: Delete own account.
    When I am logged in as "alicia__1997"
    And all e-mails have been sent
    And I visit "/user"
    And I open the header local tasks menu
    And I click "Edit" in the "Header" region
    And I press "Cancel account"
    And I press "Cancel account"
    Then the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                           |
      | subject        | Account cancellation request for alicia__1997 at Joinup            |
      | body           | by clicking this link or copying and pasting it into your browser: |
    # Click the confirmation link in the email.
    When I click the delete confirmation link for the user "alicia__1997" from the last email
    And I wait for the batch job to finish
    Then the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                                                                 |
      | subject        | Your account has been deleted.                                                                           |
      | body           | If you believe that this action has been performed by mistake, please contact The Joinup Support Team at |
