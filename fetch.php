#!/usr/bin/php
<?php

$cookie_file = getenv("cookiefile") ?: __DIR__ . "/.cookie.jar";
$username = getenv("username") ?: $argv[1] ?: "default_login";
$password = getenv("password") ?: $argv[2] ?: "default_pass";


$ch = curl_init();
//curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Connection: keep-alive']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);


$o = api($ch, "internetusage");
if($o === null) {
	login($ch, $username, $password);

	$o = api($ch, "internetusage");
	if($o === null) {
		fprintf(STDERR, "FAILED\n");
		exit(1);
	}
}

$usage = $o->internetusage[0]->availableperiods[0]->usages[0];

$max=$usage->includedvolume;
$used=$usage->totalusage->includedvolume;


$MiB = 1<<20;

$Max = $max / $MiB;
$Used = $used / $MiB;

printf("kiB %9d / %3d\n", $used, $max); 
printf("GiB %9.3f / %3d\n", $Used, $Max); 

exit(0); 


function login($ch, $username, $password) {
	curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Connection: keep-alive','x-alt-referer: https://www2.telenet.be/nl#']);

	//first get session cookies from main website
	curl_get($ch, "https://www2.telenet.be/nl");

	//get things ready for csrf
	curl_get($ch, "https://www2.telenet.be/libs/granite/csrf/token.json");


	//fetch Nonce and state 
	$details = curl_get($ch, "https://api.prd.telenet.be/ocapi/oauth/userdetails");
	list($state, $nonce) = explode(',', $details);


	//build the qoauth query
	$q = http_build_query([
		"claims" => '{"id_token":{"http://telenet.be/claims/roles":null,"http://telenet.be/claims/licenses":null}}',
		"client_id" => 'ocapi', 
		'lang' => 'nl',
		'nonce' => $nonce,
		"response_type" => 'code',
		'state' => $state,
	]);

	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: keep-alive', 'Referer: https://www2.telenet.be/']);
	curl_get($ch, "https://login.prd.telenet.be/openid/oauth/authorize?$q");


	$postFields = http_build_query([
		"j_username" => $username,
		"j_password" => $password,
		"rememberme" => "true"  
	]);

	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields );

	//do the actual login
	curl_get($ch, "https://login.prd.telenet.be/openid/login.do");


	curl_setopt($ch, CURLOPT_POST, 0);
	//curl_setopt($ch, CURLOPT_POSTFIELDS, null );

	curl_get($ch, "https://www2.telenet.be/nl");
}

function api($ch, $page) {
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: keep-alive', "x-alt-referer: https://www2.telenet.be/nl/klantenservice/#/menu=selfservice", "Referer: https://www2.telenet.be/", "x-Requested-width: XMLHttpRequest"]);
	$json = curl_get($ch, "https://api.prd.telenet.be/ocapi/public/?". http_build_query(["p" => $page]));
	$o = json_decode($json);

	return $o;
}

function curl_get($ch, $url){
	curl_setopt($ch, CURLOPT_URL, $url);
	return curl_exec($ch);
}
