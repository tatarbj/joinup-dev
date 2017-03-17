@api
Feature: Solution homepage
  In order find content around a topic
  As a user of the website
  I need to be able see all content related to a solution on the solution homepage

  Scenario: The solution homepage shows related content
    Given the following solutions:
      | title                        | description                           | logo     | banner     | state     |
      | Information sharing protocol | Handling information sharing securely | logo.png | banner.jpg | validated |
      | Security audit tools         | Automated test of security            | logo.png | banner.jpg | validated |
    And the following releases:
      | title             | release number | release notes                               | is version of                | state     |
      | IS protocol paper | 1              | First stable version.                       | Information sharing protocol | validated |
      | Fireproof         | 0.1            | First release for the firewall bypass tool. | Security audit tools         | validated |
    And the following distributions:
      | title           | description                                        | access url | solution                     | parent                       |
      | PDF version     | Pdf version of the paper.                          | text.pdf   | Information sharing protocol | IS protocol paper            |
      | ZIP version     | Zip version of the paper.                          | test.zip   | Information sharing protocol | IS protocol paper            |
      # One distribution directly attached to the "Information sharing protocol" solution.
      | Protocol draft  | Initial draft of the protocol.                     | text.pdf   | Information sharing protocol | Information sharing protocol |
      | Source code     | Source code for the Fireproof tool.                | test.zip   | Security audit tools         | Fireproof                    |
      # One distribution directly attached to the "Security audit tools" solution.
      | Code of conduct | Code of conduct for contributing to this software. | text.pdf   | Security audit tools         | Security audit tools         |

    When I go to the homepage of the "Information sharing protocol" solution
    # I should see only the related release.
    Then I should see the "IS protocol paper 1" tile
    # And the distribution directly associated.
    And I should see the "Protocol draft" tile
    # Distribution associated to a release should not be shown.
    But I should not see the "PDF version" tile
    And I should not see the "ZIP version" tile
    # Unrelated content should not be shown.
    And I should not see the "Fireproof" tile
    And I should not see the "Code of conduct" tile
    # Nor the solution itself should be shown.
    And I should not see the "Information sharing protocol" tile

    # Test the filtering on the facets.
    When I click the Distribution content tab
    Then I should see the "Protocol draft" tile
    But I should not see the "IS protocol paper 1" tile

    # Verify that the other solution is showing its related content.
    When I go to the homepage of the "Security audit tools" solution
    Then I should see the "Fireproof 0.1" tile
    #Then I should see the text "Fireproof 0.1"
    And I should see the "Code of conduct" tile
    But I should not see the "IS protocol paper 1" tile
    And I should not see the "Protocol draft" tile