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
	error_log("Unable to decode json data, configuration is probably not set correctly\n");
	die(2);
}

if(!isset($config->notificationconf->subject) || !isset($config->notificationconf->when) || !isset($config->notificationconf->to) || !isset($config->notificationconf->body))
{
	error_log("Invalid configuration\n");
	die(3);
}

// Read workflow instance informations from evQueue engine
$vars = array('#ID#'=>$argv[1]);
$xml = simplexml_load_string($config->instance);
$workflow_attributes = $xml->attributes();
$vars['#NAME#'] = (string)$workflow_attributes['name'];
$vars['#START_TIME#'] = (string)$workflow_attributes['start_time'];
$vars['#END_TIME#'] = (string)$workflow_attributes['end_time'];

// Extract mail informations from config
$to = $config->notificationconf->to;
$subject = $config->notificationconf->subject;
$body = $config->notificationconf->body;
$when = $config->notificationconf->when;

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

// Do variables substitution
$values = array_values($vars);
$vars = array_keys($vars);

$subject = str_replace($vars,$values,$subject);
$body = str_replace($vars,$values,$body);

// Send email
$msg = [
	"key" => $config->pluginconf->key,
	"message" => [
		"text" => $body,
		"subject" => $subject,
		"from_email" => $config->pluginconf->from,
		"from_name" => $config->pluginconf->from_name,
		"to" => [["email" => $to,"type" => "to"]]
	]
];

$ch = curl_init("https://mandrillapp.com/api/1.0/messages/send.json");
curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($msg));
curl_exec($ch);

die();
?>