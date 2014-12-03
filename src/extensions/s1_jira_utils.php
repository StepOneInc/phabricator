<?php 

define('JIRA_URL', 'http://jira.steponeinc.com/rest/api/latest/issue/');

function transition_issue($resource, $data , $username, $password){
	$jdata = json_encode($data) ;
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_POST => 1,
		CURLOPT_URL => JIRA_URL . $resource  . '/transitions?expand=transitions.fields',
		CURLOPT_USERPWD => $username . ':' . $password,
		CURLOPT_POSTFIELDS => $jdata,
		CURLOPT_HTTPHEADER => array(
			'Accept: application/json',
			'Content-Type: application/json'
			),
		CURLOPT_RETURNTRANSFER => true
	));
	$result = curl_exec($ch);
	$ch_error = curl_error($ch);
	if($ch_error){
		$result = $ch_error;
	}
	curl_close($ch);
	return json_decode($result, true);
}

function get_status($resource, $username, $password) {
	//convert array to JSON string
	$ch = curl_init();
	//configure CURL
	curl_setopt_array($ch, array(
		CURLOPT_CUSTOMREQUEST=>"GET",
		CURLOPT_URL => JIRA_URL . $resource . '/?fields=status',
		CURLOPT_USERPWD => $username . ':' . $password,
		CURLOPT_HTTPHEADER => array('Content-type: application/json'),
		CURLOPT_RETURNTRANSFER => true
	));
	$result = curl_exec($ch);
	$ch_error = curl_error($ch);
	if($ch_error){
		$result = $ch_error;
	}
	curl_close($ch);
	return json_decode($result, true);
}

