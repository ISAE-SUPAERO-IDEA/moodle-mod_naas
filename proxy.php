<?php

// Proxy.php can be used for the NaaS API requests
// It uses an instance of the NaasClient.php to send the requests

// Accepts two kinds of headers:
// 1- ?fulltext=...&is_default_version=...&page_size=...
// 2- ?nugget_id=...

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once('classes/NaasClient.php');

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

$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new NaasClient($config);

// Maybe we should add a filter there
$path  = $_GET['path'];
$response = $naas->request('GET', $path);
echo json_encode($response);
?>