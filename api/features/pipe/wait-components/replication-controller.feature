Feature:
  In order to prevent any settle-down time after deployment
  As a developer
  I want the deployment to be ready only once the components are actually ready

  Background:
    Given I am authenticated
    And I am building a deployment request
    And the target environment name is "my-environment"
    And the target cluster identifier is "my-cluster"
    And the credentials bucket is "00000000-0000-0000-0000-000000000000"
    And there is a cluster in the bucket "00000000-0000-0000-0000-000000000000" with the following configuration:
      | identifier | type       | address         | version | username | password |
      | my-cluster | kubernetes | https://1.2.3.4 | v1      | username | password |

  Scenario: It is waiting the RC to have at least one ready pod
    Given the specification come from the template "simple-app"
    And the pods of the replication controller "app" will be pending after creation
    And the pods of the replication controller "app" will become running later
    And the pods of the replication controller "mysql" will be running after creation
    When I send the built deployment request
    Then the deployment request should be successfully created
    And at least one pod of the replication controller "app" should be running

  Scenario: It should not wait infinitely
    Given the specification come from the template "simple-app"
    And the pods of the replication controller "app" will be pending after creation
    When I send the built deployment request
    Then the deployment request should be successfully created
    And the deployment should be failed
