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
$config = json_decode($config_str, true);

if($config===null)
{
	error_log("Unable to decode json data, configuration is probably not set correctly\n");
	die(2);
}

if(!isset($config['notificationconf']['subject']) || !isset($config['notificationconf']['when']) || !isset($config['notificationconf']['to']) || !isset($config['notificationconf']['body']))
{
	error_log("Invalid configuration\n");
	die(3);
}

$xmldoc = new DOMDocument();
$xmldoc->loadXML((string)$config['instance']);

$xpath = new DOMXpath($xmldoc);

// Compute variables for job's output
// Compute variables for job's tasks output
$nodes = $xpath->evaluate('//subjobs/job/tasks/task');
foreach($nodes as $node)
{
	$job_name = $node->parentNode->parentNode->getAttribute('name');
	$task_name = $node->hasAttribute("path")?$node->getAttribute("path"):$node->getAttribute("name");
	$retval = $node->hasAttribute('retval')?$node->getAttribute('retval'):0;
	
	$output = [
		'name' => $task_name,
		'output' => $xpath->evaluate("string(output)", $node),
		'stderr' => $xpath->evaluate("string(stderr)", $node),
		'log' => $xpath->evaluate("string(log)", $node)
	];

	// For all tasks
	if(!isset($vars["#OUTPUT_{$job_name}#"]))
		$vars["#OUTPUT_{$job_name}#"] = [];
	
	$vars["#OUTPUT_{$job_name}#"][] = $output;
	
	// For errors
	if($retval!=0)
	{
		if(!isset($vars['#ERRORS#']))
			$vars['#ERRORS#'] = [];
		
		$vars['#ERRORS#'][] = $output;
	}
}


// Read workflow instance informations from evQueue engine
$vars['#ID#'] = $argv[1];

$worflow = ($xmldoc->getElementsByTagName('workflow'))->item(0);

$vars['#NAME#'] = (string)$worflow->getAttribute("name");
$vars['#START_TIME#'] = (string)$worflow->getAttribute("start_time");
$vars['#END_TIME#'] = (string)$worflow->getAttribute("end_time");

$parameters = $xpath->evaluate('//parameters/parameter');
foreach($parameters as $parameter)
{
	$vars["#PARAMETER_{$parameter->getAttribute("name")}#"] = $parameter->textContent;
	$vars['#PARAMETERS#'][$parameter->getAttribute("name")] = $parameter->textContent;
}

// Extract mail informations from config
$to = $config['notificationconf']['to'];
$subject = $config['notificationconf']['subject'];
$body = $config['notificationconf']['body'];
$when = $config['notificationconf']['when'];

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
foreach($vars as $var => $value)
{
	if(!is_array($value))
	{
		$subject = str_replace($var,$value,$subject);
		$body = str_replace($var,$value,$body);
	}
}

// Format variables for HTML
foreach($vars as $var_name => $tasks)
{
	if(substr($var_name, 0, 8)!='#OUTPUT_')
		continue;
	
	$output_html = "";
	foreach($tasks as $task)
		$output_html .= "<div><b>{$task['name']}</b><pre>{$task['output']}</pre></div>";
	
	$body = str_replace($var_name, $output_html, $body);
}

if(!empty($vars['#ERRORS#']))
{
	$errors_html = '';
	
	foreach($vars['#ERRORS#'] as $task)
	{
		$errors_html .= "<table cellpadding='10' cellspacing='1' border='1' align='center' style='width:80%'><thead><tr><th>{$task['name']}</th><th>Description</th></tr></thead><tbody>";
		if(!empty($task['stderr']))
			$errors_html .= "<tr><td>stderr</td><td><pre style='white-space: pre-line'>{$task['stderr']}<pre></td></tr>";
		if(!empty($task['log']))
			$errors_html .= "<tr><td>stderr</td><td><pre style='white-space: pre-line'>{$task['log']}<pre></td></tr>";
		$errors_html .= '</tbody></table><table><tr><td></td></tr></table>';
	}

	$body = str_replace('#ERRORS#', $errors_html, $body);
}

if(!empty($vars['#PARAMETERS#']))
{
	$param_html = '<table  cellpadding="10" cellspacing="1" border="1" align="center"  style="width: 80%"><thead><tr><th scope="col">Key</th><th scope="col">Value</th></tr></thead><tbody>';
	
	foreach($vars['#PARAMETERS#'] as $name => $param)
	{
		if(@unserialize($param) !== false)
			$param =  json_encode(@unserialize($param));

		$param_html .= "<tr><td>{$name}</td><td><pre style='white-space: pre-line'>{$param}<pre></td></tr>";
	}

	$param_html .= '</tbody></table><table><tr><td></td></tr></table>';

	$body = str_replace('#PARAMETERS#', $param_html, $body);
}

// Send email
$cmd = [
	"/usr/bin/mutt",
	"-e",
	"set from = \"{$config['pluginconf']['from']}\"",
	"-e",
	"set realname = \"{$config['pluginconf']['from_name']}\"",
	"-e",
	"set content_type=text/html",
	"-s",
	$subject,
	$to,
];

$proc = proc_open($cmd, [0 => ['pipe', 'r']], $pipes);
if(!is_resource($proc))
{
	error_log("Unable to execute /usr/bin/mutt\n");
	die(-8);
}

fwrite($pipes[0], $body);
fclose($pipes[0]);

$ret = proc_close($proc);

die($ret);
