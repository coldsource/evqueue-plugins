#!/usr/bin/php
<?php
// Read configuration
$stdin = fopen('php://stdin','r');

$config_str = stream_get_contents($stdin);

if($config_str==false)
{
	error_log("No configuration could be read on stdin\n");
	die(1);
}

// Decode configuration
$config = json_decode($config_str, true);

if($config===null)
{
	error_log("Unable to decode json data, configuration is probably not set correctly\n");
	die(2);
}

if(!isset($config['notificationconf']['subject']) || !isset($config['notificationconf']['to']) || !isset($config['notificationconf']['body']))
{
	error_log("Invalid configuration\n");
	die(3);
}

$logs_txt = '';

foreach($config['logs'] as $log)
{
	$logs_txt .= '<table cellpadding="10" cellspacing="1" border="1" align="center" style="width:100%"><thead><tr>';

	foreach (array_keys($log) as $key) 
			$logs_txt       .= "<th scope='col'>{$key}</th>";

	$logs_txt .= '</tr></thead><tbody><tr>';

	foreach (array_values($log) as $value) 
			$logs_txt       .= "<td scope='col'>{$value}</td>";

	$logs_txt .= "</tr>";

	$logs_txt .= '</tbody></table><table><tr><td></td></tr></table>';
}


// Read workflow instance informations from evQueue engine
$vars['#NLOGS#'] = sizeof($config['logs']);
$vars['#LOGS#'] = $logs_txt;

// Extract mail informations from config
$to = $config['notificationconf']['to'];
$subject = $config['notificationconf']['subject'];
$body = $config['notificationconf']['body'];
$cc = isset($config['notificationconf']['cc'])?$config['notificationconf']['cc']:false;

// Do variables substitution
$values = array_values($vars);
$vars = array_keys($vars);

$subject = str_replace($vars,$values,$subject);
$body = str_replace($vars,$values,$body);

// Send email
$msg = [
	"key" => $config['pluginconf']['key'],
	"message" => [
		"html" => $body,//$body,
		"subject" => $subject,
		"from_email" => $config['pluginconf']['from'],
		"from_name" => $config['pluginconf']['from_name'],
		"to" => [["email" => $to,"type" => "to"]]
	]
];

$ch = curl_init("https://mandrillapp.com/api/1.0/messages/send.json");
curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($msg));
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
$output = curl_exec($ch);

$curl_infos = curl_getinfo($ch);
if($curl_infos['http_code']!='200')
{
	error_log("Error calling Mandrill : $output\n");
	die(7);
}

die();
