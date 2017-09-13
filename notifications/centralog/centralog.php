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

if(!isset($config->notificationconf->when) || !isset($config->notificationconf->level) || !isset($config->notificationconf->body))
{
	error_log("Invalid configuration\n");
	die(3);
}

// Read workflow instance informations from evQueue engine
$vars = array('#ID#'=>$argv[1]);
$vars['#ERRORNUM#'] = $argv[3];
$xml = simplexml_load_string($config->instance);
$workflow_attributes = $xml->attributes();
$vars['#NAME#'] = (string)$workflow_attributes['name'];
$vars['#COMMENT#'] = (string)$workflow_attributes['comment'];
$vars['#START_TIME#'] = (string)$workflow_attributes['start_time'];
$vars['#END_TIME#'] = (string)$workflow_attributes['end_time'];


// Extract mail informations from config
$when = $config->notificationconf->when;
$level = $config->notificationconf->level;
$uuid = 'UUID';
$body = $config->notificationconf->body;

if (substr($vars['#COMMENT#'], 0, 5) == 'UUID:'){
  $uuid = substr($vars['#COMMENT#'], 5);
}


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
//2017-09-12 06:25:08.001049
//body = 'web6 web6.priv [2017-02-13 18:19:59.450418] PHP Warning:  file_get_contents(http://175.65.1.2/): failed to open stream: Connection timed out in /var/www/html/test.php on line 2'

$log = gethostname().' '.gethostname().' 0.0.0.0 '.$uuid.' ['.$workflow_attributes['end_time'].'] '.str_replace(array("\r\n", "\r", "\n"), "<br>", $body);

// Send email
$cmdline  = $config->pluginconf->ClientPath;
$cmdline .= ' --ip '.$config->pluginconf->ServerIp;
$cmdline .= ' -p '.$config->pluginconf->ServerPort;
$cmdline .= ' --source evQueue';
$cmdline .= ' --crit '.$level;

//file_put_contents('/tmp/evqueue_centralog_notif.txt', '# '.$cmdline);
//file_put_contents('/tmp/evqueue_centralog_notif.txt', "\n".$log, FILE_APPEND);

$fd = array(0 => array('pipe', 'r'));
$proc = proc_open($cmdline, $fd, $pipes);

if(!is_resource($proc))
	die(4);

fwrite($pipes[0],$log);
fclose($pipes[0]);
$return_value = proc_close($proc);

die($return_value);
