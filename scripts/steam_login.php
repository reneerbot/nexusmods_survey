<?php

/*
 * @author Reneer <reneerbot@gmail.com>
 * @copyright 2019-2020 Reneer.
 * @License: MIT, see LICENSE.TXT
*/

	include('./openid.php');

	$steam_secret = "84F434D87F9BC0EDC121122F4425E98F";

	$steam_curl = curl_init();

	curl_setopt($GLOBALS["steam_curl"], CURLOPT_RETURNTRANSFER, true);	

	function make_image_url($gameid, $hash)
	{
		return "http://media.steampowered.com/steamcommunity/public/images/apps/" . $gameid . "/" . $hash . ".jpg";
	}

	function get_user_game_stats($gameid)
	{
		$url = "";
	}

	function get_user_recent_games($steamkey)
	{
		print "Games:<br/>";
		$url = "http://api.steampowered.com/IPlayerService/GetRecentlyPlayedGames/v0001/?key=" .  $GLOBALS["steam_secret"] . "&steamid=" . $steamkey;
		
		curl_setopt($GLOBALS["steam_curl"], CURLOPT_URL, $url);		
		
		$response = curl_exec($GLOBALS["steam_curl"]);
		
		$decoded_data = json_decode($response, true);
		
		$output = "";
		foreach ($decoded_data["response"]["games"] as $game)
		{
			$output .= "<img src='" . make_image_url($game['appid'], $game['img_logo_url']) . "'><br/>";
		}
		print $output;		
	}

	function steam_login()
	{
		try {
			# Change 'localhost' to your domain name.
			$openid = new LightOpenID('www.psychfox.com');
			if (!$openid->mode) {
				$openid->identity = "https://steamcommunity.com/openid";
				header("Location: " . $openid->authUrl());
			} elseif ($openid->mode == 'cancel') {
				print 'User has canceled authentication!';
			} else {
				$openid->validate();
				$full_id = $openid->identity;
				$steamkey = str_replace("https://steamcommunity.com/openid/id/", "", $openid->identity);
				//print 'User ' . ($openid->validate() ? $openid->identity . ' has ' : ' has not ') . 'logged in.';
				get_user_recent_games($steamkey);
			}
		} catch(ErrorException $e) {
			print $e->getMessage();
		}
	}
	
	steam_login();

?>