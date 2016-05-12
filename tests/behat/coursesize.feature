@report @report_coursesize @_file_upload
Feature: Course size report calculates correct information

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I log out

  @javascript
  Scenario: Check coursesize report for course
    When I log in as "admin"
    And I navigate to "Course size" node in "Site administration > Reports"
    Then I should see "File usage report"
    And I should see "bytes used by course C1"