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
$i = 0;
foreach($config['logs'] as $log)
{
	if($i>0)
		$logs_txt.="--------------------------------------------------------\n";
	foreach($log as $key=>$value)
		$logs_txt.="$key: $value\n";
	
	$i++;
}

// Read workflow instance informations from evQueue engine
$vars = [
	'#NLOGS#' => sizeof($config['logs']),
	'#LOGS#' => $logs_txt
];

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
$cmdline = '/usr/bin/mail';
$cmdline .= " -s '".addslashes($subject)."'";
if($cc)
	$cmdline .= " -c '".addslashes($cc)."'";
$cmdline .= " -a '".addslashes('From: '.$config['pluginconf']['from'])."'";
$cmdline .= ' '.addslashes($to);

$fd = array(0 => array('pipe', 'r'));
$proc = proc_open($cmdline, $fd, $pipes);

if(!is_resource($proc))
	die(4);

fwrite($pipes[0],$body);
fclose($pipes[0]);
$return_value = proc_close($proc);

die($return_value);
?>