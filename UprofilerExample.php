<?php
//require 'vendor/autoload.php';
//
//use Aws\DynamoDb\DynamoDbClient;
//
//$client = DynamoDbClient::factory(array(
//    'profile' => 'project1',
//    'version' => 'latest',
//    'region' => 'us-west-2', #replace with your desired region
//    'endpoint' => 'http://localhost:8000'
//));
//
////// Create an "errors" table
////$client->createTable(array(
////  'TableName' => 'errors',
////  'AttributeDefinitions' => array(
////    array(
////      'AttributeName' => 'id',
////      'AttributeType' => 'N'
////    ),
////    array(
////      'AttributeName' => 'time',
////      'AttributeType' => 'N'
////    )
////  ),
////  'KeySchema' => array(
////    array(
////      'AttributeName' => 'id',
////      'KeyType'       => 'HASH'
////    ),
////    array(
////      'AttributeName' => 'time',
////      'KeyType'       => 'RANGE'
////    )
////  ),
////  'ProvisionedThroughput' => array(
////    'ReadCapacityUnits'  => 10,
////    'WriteCapacityUnits' => 20
////  )
////));
//
//$result = $client->listTables();
//
//// TableNames contains an array of table names
//foreach ($result['TableNames'] as $tableName) {
//  echo $tableName . "\n";
//}
//
//$result = $client->describeTable(array(
//  'TableName' => 'errors'
//));
//
//// The result of an operation can be used like an array
//echo $result['Table']['ItemCount'] . "\n";
////> 0
//
//// Use the getPath() method to retrieve deeply nested array key values
//echo $result->getPath('Table/ProvisionedThroughput/ReadCapacityUnits') . "\n";
////> 15
//
//
//$time = time();
//
//$result = $client->putItem(array(
//  'TableName' => 'errors',
//  'Item' => array(
//    'id'      => array('N' => '1201'),
//    'time'    => array('N' => $time),
//    'error'   => array('S' => 'Executive overflow'),
//    'message' => array('S' => 'no vacant areas')
//  )
//));
//
//$result = $client->getItem(array(
//  'ConsistentRead' => true,
//  'TableName' => 'errors',
//  'Key'       => array(
//    'id'   => array('N' => '1201'),
//    'time' => array('N' => $time)
//  )
//));
//
//// Grab value from the result object like an array
//echo $result['Item']['id']['N'] . "\n";
////> 1201
//echo $result->getPath('Item/id/N') . "\n";
////> 1201
//echo $result['Item']['error']['S'] . "\n";
////> Executive overflow
//echo $result['Item']['message']['S'] . "\n";
//


//$uprofiler_ROOT = getcwd(). "/uprofiler";
//include_once $uprofiler_ROOT . "/uprofiler_lib/utils/uprofiler_lib.php";
//include_once $uprofiler_ROOT . "/uprofiler_lib/utils/uprofiler_runs.php";





function bar($x) {
  if ($x > 0) {
    bar($x - 1);
  }
}

function foo() {
  for ($idx = 0; $idx < 2; $idx++) {
    bar($idx);
    $x = strlen("abc");
  }
}

// start profiling
//uprofiler_enable();
include(dirname(__FILE__) . "/UprofilerGetSaveRuns.php");
// run program
foo();

// stop profiler
$uprofiler_data = uprofiler_disable();
$uprofiler_runs = new UprofilerGetSaveRuns();
//$uprofiler_runs->deleteTable();
$run_id = $uprofiler_runs->save_run($uprofiler_data, "brand_name");

echo "---------------\n".
  "Assuming you have set up the http based UI for \n".
  "uprofiler at some address, you can view run at \n".
  "http://<uprofiler-ui-address>/index.php?run=$run_id&source=brand_name\n".
  "---------------\n";