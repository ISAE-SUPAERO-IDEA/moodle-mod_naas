<?php
// Proxy.php can be used for the NaaS API requests
// It uses an instance of the NaasClient.php to send the requests

// Accepts two kinds of headers:
// 1- ?fulltext=...&is_default_version=...&page_size=...
// 2- ?nugget_id=...

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once('classes/NaasClient.php');

$path  = $_GET['path'];

// We allow requests to these specific URIs
$WHITELIST = [
	'/^\/nuggets\/([\w]+-?)+\/default_version$/',
	'/^\/persons\/[\w]+\/?$/',
	'/^\/vocabularies\/nugget_domains_vocabulary\/[\d]+\/?$/'];

$match = false;
foreach($WHITELIST as $pexp) {
	if (preg_match($pexp, $path) == 1 ){
		$match = true;
		break;
	}
}

if (!$match) {
	if (!is_siteadmin()) {
		// Only managers and teachers can use the proxy
		$roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
		$isManager = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

		if (!$isManager) {
			$roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
			$isTeacher = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);
			if (!$isTeacher) die;
		}
	}
}

$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new NaasClient($config);

// Add nql filter
$nql = $config->naas_filter;
if ($nql) {
	$nql = urlencode($nql);
	if (strpos($path, "/nuggets/search") === 0) {
		$separator = strpos($path, "?") >= 0 ? "&" : "?";
		$path = "$path$separator"."nql=$nql";
	}
}

$response = $naas->request('GET', $path);
echo json_encode($response);
