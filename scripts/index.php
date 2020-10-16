<?php

	/*
		Code to log in Nexus Mods users.
		Copyright 2019-2020 Reneer
		License: MIT, see LICENSE.txt
	*/

	session_start();

	include("./../unms_config.php");

	$g_user_api_key = "";
	$g_user_mod_url = "";

	$g_user_info = "";
	$g_mod_info = "";

	$g_is_mod_author = false;

	$base_nexus_url = "https://api.nexusmods.com";

	$nexus_socket_url = "sso.nexusmods.com";
	$nexus_app_id = "";

	$nexus_curl = curl_init();
	
	//$website_html = file_get_contents("../index.html");
	
	$survey_base = "./lime/index.php/";

	function get_include_contents($filename) 
	{
		print "<br/>" . $filename . "<br/>";
		if (is_file($filename)) 
		{
			ob_start();
			include $filename;
			return ob_get_clean();
		}
		return false;
	}

	function gen_uuid() 
	{
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,

			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	function nexus_sso_login()
	{
		try {
			$data = [
				"id" => gen_uuid(),
				"appid" => $GLOBALS["nexus_app_id"]
				];

			$in = json_encode($data);

			$service_port = 443;
			$address = $GLOBALS["nexus_socket_url"];

			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			if ($socket === false) 
			{
				echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
			}

			$result = socket_connect($socket, $address, $service_port);
			print $result;
			if ($result === false) 
			{
				echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
			}

			socket_write($socket, $in, strlen($in));

			while ($out = socket_read($socket, 2048)) 
			{
				if ($out == 'Ping')
				{
					socket_write($socket, 'Pong', strlen('Pong'));
				}
				echo $out;
			}

			header("Location: https://www.psychfox.com/scripts/lime/index.php");

			socket_close($socket);

		} catch (Exception $e) {
			echo 'Error on ws'.$e;
		}
	}

	function get_user_tracked_files()
	{
		// /v1/user/tracked_mods.json
		
		curl_setopt($GLOBALS["nexus_curl"], CURLOPT_URL, $GLOBALS["base_nexus_url"] . "/v1/user/tracked_mods.json");		
		
		$response = curl_exec($GLOBALS["nexus_curl"]);
		
		$GLOBALS["g_tracked_mods"] = json_decode($response, true);
		//curl_close($nexus_curl);	
	}

	function curl_set_apikey()
	{
		$headerarray = array('http_user_agent: ' . $_SERVER['HTTP_USER_AGENT'], 'apikey: ' . $GLOBALS["g_user_api_key"], 'Referer: https://www.psychfox.com', 'application-name: Unofficial Nexus Mods Survey', 'application-version: ' . UNMS_VERSION );
		curl_setopt($GLOBALS["nexus_curl"], CURLOPT_HTTPHEADER, $headerarray);
		//curl_setopt($GLOBALS["nexus_curl"], CURLOPT_VERBOSE, true);		
		//curl_setopt($GLOBALS["nexus_curl"], CURLOPT_HEADER, true);
		curl_setopt($GLOBALS["nexus_curl"], CURLOPT_RETURNTRANSFER, true);		
	}

	function get_mod_info($gamename, $modid)
	{
		// /v1/games/{game_domain_name}/mods/{id}.json
		
		curl_setopt($GLOBALS["nexus_curl"], CURLOPT_URL, $GLOBALS["base_nexus_url"] . "/v1/games/" . $gamename . "/mods/" . $modid . ".json");		
		
		$response = curl_exec($GLOBALS["nexus_curl"]);
		
		$GLOBALS["g_mod_info"] = json_decode($response, true);
		//curl_close($GLOBALS["nexus_curl"]);
	}

	function get_user_mod()
	{
		// /v1/games/{game_domain_name}/mods/{id}.json
		
		$url_parts = explode("/mods/", str_replace("https://www.nexusmods.com/", "", $GLOBALS["g_user_mod_url"]));
		
		curl_setopt($GLOBALS["nexus_curl"], CURLOPT_URL, $GLOBALS["base_nexus_url"] . "/v1/games/" . $url_parts[0] . "/mods/" . $url_parts[1] . ".json");		
		
		$response = curl_exec($GLOBALS["nexus_curl"]);
		
		$GLOBALS["g_mod_info"] = json_decode($response, true);
		//curl_close($GLOBALS["nexus_curl"]);
	}

	function kick_out()
	{
		print "There was an error logging you in. Please try again. Redirecting you to the homepage in 5 seconds.";
		print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com'\",5000);</script>";
		die();
	}

	function get_user_data()
	{
		// /v1/users/validate.json
		
		if (strlen($_POST["my_api_key"]) > 0)
		{
			$GLOBALS["g_user_api_key"] = htmlspecialchars($_POST["my_api_key"]);
			$GLOBALS["g_user_mod_url"] = htmlspecialchars($_POST["opt_mod_url"]); // full URL
		} else {
			kick_out();
			die();
		}
		
		curl_set_apikey();
		
		curl_setopt($GLOBALS["nexus_curl"], CURLOPT_URL, $GLOBALS["base_nexus_url"] . "/v1/users/validate.json");	
		
		$response = curl_exec($GLOBALS["nexus_curl"]);	
		
		$GLOBALS["g_user_info"] = json_decode($response, true);
		
		$_SESSION["userid"] = $GLOBALS["g_user_info"]["user_id"];
		
		if (strlen($GLOBALS["g_user_mod_url"]) > 0)
		{
			get_user_mod();
			
			if ($GLOBALS["g_user_info"]["user_id"] === $GLOBALS["g_mod_info"]["user"]["member_id"])
			{
				// we have a mod author.
				$GLOBALS["g_is_mod_author"] = true;				
				show_mod_author_surveys();
				curl_close($GLOBALS["nexus_curl"]);							
			} else {
				kick_out();
				die();
			}
		} else {
			get_user_tracked_files();
			
			$number = 10;
			
			foreach ($GLOBALS["g_tracked_mods"] as $mod)
			{
				if ($GLOBALS["g_is_mod_author"] == false)
				{
					$gamename = $mod["domain_name"];
					$modid = $mod["mod_id"];	
					get_mod_info($gamename, $modid);
					
					if ($GLOBALS["g_user_info"]["user_id"] === $GLOBALS["g_mod_info"]["user"]["member_id"])
					{
						// we have a mod author.
						$GLOBALS["g_is_mod_author"] = true;
						show_mod_author_surveys();
						curl_close($GLOBALS["nexus_curl"]);						
						break;				
					}
				}
				$number -= 1;
				if ($number <= 0)
				{
					curl_close($GLOBALS["nexus_curl"]);					
					not_mod_author();					
					die();
				}
			}
			if ($GLOBALS["g_is_mod_author"] == false)
			{
				not_mod_author();
				die();
			}
		}
		if (isset($GLOBALS["nexus_curl"]))
		{
			@curl_close($GLOBALS["nexus_curl"]);		
		}
		not_mod_author();
		die();		
	}
	
	function not_mod_author()
	{	
		$_POST["my_api_key"] = $GLOBALS["g_user_info"]["user_id"];
		
		header('Location: https://www.psychfox.com/scripts/lime/index.php');
	}
	
	function show_mod_author_surveys()
	{		
		//$website_code = str_replace("./mainStyle.css", "../mainStyle.css", $website_html);
		
		$_SESSION["ismodauthor"] = true;
		
		$_POST["my_api_key"] = $GLOBALS["g_user_info"]["user_id"];
		
		header('Location: https://www.psychfox.com/scripts/lime/index.php');
	}

	if (isset($_POST["my_api_key"]) && strlen($_POST["my_api_key"]) > 0)
	{
		get_user_data();
	} else {
		kick_out();
	}

?>