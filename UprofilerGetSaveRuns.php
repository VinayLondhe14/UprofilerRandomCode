<?php

require 'vendor/autoload.php';

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\ResourceNotFoundException;
use Aws\S3\S3Client;

include(dirname(__FILE__) . "/uprofiler/uprofiler_lib/utils/uprofiler_lib.php");
include(dirname(__FILE__) . "/uprofiler/uprofiler_lib/utils/uprofiler_runs.php");

class UprofilerGetSaveRuns implements \iUprofilerRuns {

  private $tableName = 'pc5';
  private $bucketName = 'pubcentral';
  public function __construct($tableName = "pc5", $bucketName = "pubcentral") {
    $this->tableName = $tableName;
    $this->bucketName = $bucketName;
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
          'AttributeName' => 'id',
          'AttributeType' => 'S'
        )
      ),
      'KeySchema' => array(
        array(
          'AttributeName' => 'id',
          'KeyType'       => 'HASH'
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
        'id' => array('S' => $id),
        'brand_name' => array('S' => $brand_name),
        'time' => array('N' => time())
      )
    ));
  }

  private function putObjectInAmazonS3($client, $id, $uprofiler_data) {
    $result = $client->putObject(array(
      'Bucket' => $this->bucketName,
      'Key'    => "uprofiler/" . $id . ".txt",
      'Body'   => serialize($uprofiler_data)
    ));
  }

  public function get_run($run_id, $type = null, &$run_desc = null) {

    $s3client = $this->getAmazonS3Client();
    $result = $s3client->getObject(array(
      'Bucket' => $this->bucketName,
      'Key'    => "uprofiler/" . $run_id . ".txt"
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

    $client = $this->getDynamoDbClient();
    $response = $client->scan(array(
      'TableName' => $this->tableName
    ));
    echo "<br>";
    echo "The following id's are available:" . "<br>";
    foreach ($response['Items'] as $key => $value) {
      $id = $value['id']['S'];
      $brand_name = $value['brand_name']['S'];
      $time_in_unix = $value['time']['N'];
      $link_address = "http://localhost:8888/index.php?run=" . $id . "&source=" . $brand_name;
      echo 'Id: ' . "<a href='$link_address'> $id </a>" . str_repeat('&nbsp;', 20) . 'Brand Name:' . $brand_name . " Date: " . date('m/d/Y H:i:s', $time_in_unix) . "<br>";
    }
    echo "---------------\n".
      "Assuming you have set up the http based UI for \n".
      "uprofiler at some address, you can view run at \n".
      "http://<uprofiler-ui-address>/index.php?run=Id&source=brand_name\n".
      "---------------\n";
  }
}