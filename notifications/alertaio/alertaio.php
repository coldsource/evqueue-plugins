#!/usr/bin/php
<?php
// Sanity check
if($argc!=4)
{
	error_log("This process should only be called as an evQueue plugin\n");
	die(5);
}

// Read configuration
$stdin = fopen('php://stdin','r');

$config_str = stream_get_contents($stdin);

if($config_str==false)
{
	error_log("No configuration could be read on stdin\n");
	die(1);
}

// Decode configuration
$config = json_decode($config_str);
if($config===null)
{
	error_log("Unable to decode json data\n");
	die(2);
}

if(!isset($config->notificationconf->when) || !isset($config->notificationconf->severity) || !isset($config->notificationconf->environment))
{
	error_log("Invalid configuration\n");
	die(3);
}

// Read workflow instance informations from evQueue engine
$xml = simplexml_load_string($config->instance);
$workflow_attributes = $xml->attributes();

// Extract mail informations from config
$when = $config->notificationconf->when;
$severity = $config->notificationconf->severity;
$severity = strtolower($severity);
$environment = $config->notificationconf->environment;

if($when!='ON_SUCCESS' && $when!='ON_ERROR' && $when!='ON_BOTH')
{
	error_log("Invalid value for 'when' parameter\n");
	die(6);
}

// When should be trigger alert
if($when=='ON_SUCCESS' && $argv[2]!=0)
	die();

if($when=='ON_ERROR' && $argv[2]==0)
	die();

# file_put_contents('/tmp/evqueue_alertaio.txt', print_r($workflow_attributes, TRUE) , FILE_APPEND);

$json = '{';
$json .= '"environment":"'.$environment.'",';
$json .= '"severity":"'.$severity.'",';
$json .= '"resource":"'.$workflow_attributes['name'].'",';
$json .= '"event":"'.$workflow_attributes['name'].'",';
$json .= '"service":["EVQUEUE"],';
$json .= '"value":"-",';
$json .= '"group":"EVQUEUE",';
$json .= '"origin":"'.$config->pluginconf->link.'",';
$json .= '"text":"ID: '.$workflow_attributes['id'];
$json .= ' - NAME: '.$workflow_attributes['name'];
$json .= ' - STATUS: '.$workflow_attributes['status'];
$json .= ' - STARTTIME: '.$workflow_attributes['start_time'];
$json .= ' - ENDTIME: '.$workflow_attributes['end_time'];
if ($workflow_attributes['comment'] != '' && $workflow_attributes['comment'] != null){
    $comment = str_replace("\"","**",$workflow_attributes['comment']);
    $json .= ' - COMMENT: '.$comment;
}
$json .= '"';
$json .= '}';

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $config->pluginconf->url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => $json,
  CURLOPT_HTTPHEADER => array(
    "Authorization: Key ".$config->pluginconf->key,
    "Content-Type: application/json",
    "cache-control: no-cache"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}