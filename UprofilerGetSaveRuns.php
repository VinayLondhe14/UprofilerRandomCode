<?php

require 'vendor/autoload.php';

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\ResourceNotFoundException;
use Aws\S3\S3Client;

include(dirname(__FILE__) . "/uprofiler/uprofiler_lib/utils/uprofiler_lib.php");
include(dirname(__FILE__) . "/uprofiler/uprofiler_lib/utils/uprofiler_runs.php");

class UprofilerGetSaveRuns implements \iUprofilerRuns {

  private $tableName = 'pc30';
  private $bucketName = 'pubcentral';
  public function __construct($tableName = "pc30", $bucketName = "pubcentral") {
    $this->tableName = $tableName;
    $this->bucketName = $bucketName;
  }

  public function deleteTable() {
    $client = $this->getDynamoDbClient();
    $client->deleteTable(array(
      'TableName' => $this->tableName
    ));

    $client->waitUntil('TableNotExists', array(
      'TableName' => $this->tableName
    ));
    echo "Table deleted";
  }

  private function gen_run_id($type) {
    return uniqid();
  }

  private function getDynamoDbClient() {

    $client = DynamoDbClient::factory(array(
      'profile' => 'project1',
      'version' => 'latest',
      'region' => 'us-west-2', #replace with your desired region
      'endpoint' => 'http://localhost:8000'
    ));
    return $client;
  }

  private function getAmazonS3Client() {
    $client = S3Client::factory(array(
      'profile' => 'project2'
    ));
    return $client;
  }

  private function checkIfTableExists($client) {

    try {
      $result = $client->describeTable(array(
        "TableName" => $this->tableName
      ));
    } catch (ResourceNotFoundException $e) {
      // if this exception is thrown, the table doesn't exist
      return false;
    }

    // no exception thrown? table exists!
    return true;
  }

  private function checkIfBucketExists($client) {

    return $client->doesBucketExist($this->bucketName);
  }

  private function createDynamoDbTable($client) {

    $client->createTable(array(
      'TableName' => $this->tableName,
      'AttributeDefinitions' => array(
        array(
          'AttributeName' => 'constant_hash',
          'AttributeType' => 'N'
        ),
        array(
          'AttributeName' => 'time',
          'AttributeType' => 'N'
        )
      ),
      'KeySchema' => array(
        array(
          'AttributeName' => 'constant_hash',
          'KeyType'       => 'HASH'
        ),
        array(
          'AttributeName' => 'time',
          'KeyType'       => 'RANGE'
        )
      ),
      'ProvisionedThroughput' => array(
        'ReadCapacityUnits'  => 10,
        'WriteCapacityUnits' => 20
      )
    ));

    $client->waitUntil('TableExists', array(
      'TableName' => $this->tableName
    ));
  }

  private function createAmazonS3Bucket($client) {
    $client->createBucket(array('Bucket' => $this->bucketName));
    $client->waitUntil('BucketExists', array('Bucket' => $this->bucketName));
  }

  private function putItemInDynamoDb($client, $id, $brand_name) {

    $result = $client->putItem(array(
      'TableName' => $this->tableName,
      'Item' => array(
        'constant_hash' => array('N' => '1'),
        'time' => array('N' => time()),
        'id' => array('S' => $id),
        'brand_name' => array('S' => $brand_name),
      )
    ));
  }

  private function putObjectInAmazonS3($client, $id, $uprofiler_data) {
    $ddbclient = $this->getDynamoDbClient();
    $year_month_array = $this->getYearAndMonthForRunId($ddbclient, $id);
      $result = $client->putObject(array(
      'Bucket' => $this->bucketName,
        'Key'    => "uprofiler/" . $year_month_array[0] . "/" . $year_month_array[1] . "/" . $id . ".txt",
      'Body'   => serialize($uprofiler_data)
    ));
  }

  private function getYearAndMonthForRunId($ddbclient, $id) {
    $ddb_query_result = $ddbclient->scan(array(
      'TableName' => $this->tableName,
      'ExpressionAttributeValues' =>  array (
        ':val1' => array('S' => $id)) ,
      'FilterExpression' => 'id = :val1'
    ));
    foreach($ddb_query_result['Items'] as $key => $value) {
      $year = date('Y', $value['time']['N']);
      $month = date('M', $value['time']['N']);
    }
    return array($year, $month);
  }

  public function get_run($run_id, $type = null, &$run_desc = null) {
    $ddbclient = $this->getDynamoDbClient();
    $year_month_array = $this->getYearAndMonthForRunId($ddbclient, $run_id);
    $s3client = $this->getAmazonS3Client();
    $result = $s3client->getObject(array(
      'Bucket' => $this->bucketName,
      'Key'    => "uprofiler/" . $year_month_array[0] . "/" . $year_month_array[1] . "/" . $run_id . ".txt",
    ));
    return unserialize($result['Body']);
  }

  public function save_run($uprofiler_data, $brand_name, $run_id = null) {

    if ($run_id === null) {
      $run_id = $this->gen_run_id($brand_name);
    }
    $ddbclient = $this->getDynamoDbClient();
    if(!$this->checkIfTableExists($ddbclient)) {
      $this->createDynamoDbTable($ddbclient);
    }
    $this->putItemInDynamoDb($ddbclient, $run_id, $brand_name);

    $s3client = $this->getAmazonS3Client();
    if(!$this->checkIfBucketExists($s3client)) {
      $this->createAmazonS3Bucket($s3client);
    }
    $this->putObjectInAmazonS3($s3client, $run_id, $uprofiler_data);
    //echo $run_id;
    //$result = $ddbclient->listTables();
    return $run_id;
  }


  public function list_runs() {
    try {
      $client = $this->getDynamoDbClient();
      $result = $client->query(array(
        'TableName' => $this->tableName,
        'KeyConditions' => array(
          'constant_hash' => array(
            'AttributeValueList' => array(
              array('N' => 1)
            ),
            'ComparisonOperator' => 'EQ'
          )
        ),
        'ScanIndexForward' => FALSE,
        'Limit' => 10,
      ));

      foreach ($result['Items'] as $key => $value) {
        $id = $value['id']['S'];
        $link_address = "http://localhost:8888/index.php?run=" . $id . "&source=" . $value['brand_name']['S'];
        echo 'Id: ' . "<a href='$link_address'> $id </a>" . "Brand Name: " . $value['brand_name']['S'] . "<br>";
      }
    } catch(\Exception $e) {
        echo "No profiler runs found";
    }
  }
}