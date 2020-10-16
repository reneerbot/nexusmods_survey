<?php

	include("./unms_config.php");

?>

<!--
 * @author Reneer <reneerbot@gmail.com>
 * @copyright 2019-2020 Reneer.
 * @License: MIT, see LICENSE.TXT
-->

<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Unofficial Nexus Mods Survey</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" type="text/css" href="/mainStyle.css">
		
		<script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>			
		
		<script data-ad-client="ca-pub-9430752577919797" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
		<script src="/scripts/nexus_websocket.js"></script>
		
		<link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
		<link rel="manifest" href="/images/site.webmanifest">
		<link rel="mask-icon" href="/images/safari-pinned-tab.svg" color="#5bbad5">
		<link rel="shortcut icon" href="/images/favicon.ico">
		<meta name="msapplication-TileColor" content="#da532c">
		<meta name="msapplication-config" content="/images/browserconfig.xml">
		<meta name="theme-color" content="#000000">
	</head>

	<body	
		
		<header>
			<?php include(PROJECT_ROOT."header.html"); ?>
		</header>

		<nav>
			<?php include(PROJECT_ROOT."navbar.php"); ?>
		</nav>

		<main id="mainContent">
			<div>Welcome to the Unofficial Nexus Mods Survey website (UNMS for short). The purpose of this website is to identify trends within the modding community and survey the opinions of both content creators and users alike.
			<br/><br/>
			All data collected from Nexus Mods users is shared freely with Nexus Mods itself. Please note that no personally identifiable information, such as your IP address or Nexus Mods username, is stored within the survey data.
			<br/><br/>
			To get started taking surveys please follow the instructions below:
			</div>
			<br/>
			<div id="login_buttons_div">
				<form id="user_info_form" action="/scripts/index.php" method="post">
					<input type="button" id="btnUseSSO" value="Login / Use Nexus Single Sign On" onclick="connect_nexus_ws()"> (Opens a new window / tab)
					<br/>
					<script>
						var inputCheckboxElement = document.createElement('input');
						inputCheckboxElement.type = "checkbox";

						if (localStorage.getItem('user_save_info') == null)
						{
							inputCheckboxElement.checked = false;
						} else {
							inputCheckboxElement.checked = localStorage.getItem('user_save_info');
						}

						inputCheckboxElement.id = "user_save_info";
						document.getElementById("user_info_form").appendChild(inputCheckboxElement);				
					</script>
					Save API key and other information to your browser's LocalStorage.
					<script>
						var inputClearStorageElement = document.createElement('input');
						inputClearStorageElement.type = "button";
						inputClearStorageElement.value = "Logout / Clear LocalStorage";
						inputClearStorageElement.addEventListener('click', function()
						{
							localStorage.clear();
							sessionStorage.clear();
							console.log("Cleared localStorage");
							document.getElementById('my_api_key').value	= null;	
							document.getElementById('opt_mod_url').value = null;
							document.getElementById('user_save_info').checked = false;
						});				
						document.getElementById("user_info_form").appendChild(inputClearStorageElement);								
					</script>					
					<br/><br/>
					<a id="advanced_display" href="#">Mod Author / Advanced Settings (click to expand)</a>
					
					<script>
						document.getElementById("advanced_display").addEventListener('click', function()
						{
							if (document.getElementById("advanced_opts").style.visibility == "hidden")
							{
								document.getElementById("advanced_opts").style.visibility = "visible";
							} else {
								document.getElementById("advanced_opts").style.visibility = "hidden";
							}
						});
					</script>
					
					<div id="advanced_opts" style="visibility:hidden;">
						<br/><br/>
						API Key:<br/><input type="text" id="my_api_key" name="my_api_key" size="100"><br/>
						
						<script type="text/Javascript">
							var inputElement = document.createElement('input');
							inputElement.type = "button";
							inputElement.value = "Access using API Key";
							
							inputElement.id = "api_submit_button";
							
							inputElement.addEventListener('click', function()
							{
								if (document.getElementById("user_save_info").checked == true)
								{				
									localStorage.setItem('user_api_key', document.getElementById("my_api_key").value);
									localStorage.setItem('user_save_info', true);
								}
								if (localStorage.getItem('user_mod_url') == null || document.getElementById("opt_mod_url").value != null)
								{
									if (document.getElementById("user_save_info").checked == true)
									{					
										localStorage.setItem('user_mod_url', document.getElementById("opt_mod_url").value)
									}
								}
							
								document.getElementById("user_info_form").submit();
								//nexus_login(document.getElementById('my_api_key').value);
							});				
							
							document.getElementById("advanced_opts").appendChild(inputElement);
							
						</script>
						<br/>

						<br/><a target="_blank" href="https://www.nexusmods.com/users/myaccount?tab=api">Find your API key here at the bottom of the page</a> (may need to be generated)<br/><br/>
						(Optional, avoids having to track your own mods)<br/>URL to one of your mods:<br/>
						<input type="text" id="opt_mod_url" name="opt_mod_url" value="" size="100">
						<br/>(example: https://www.nexusmods.com/fallout4/mods/8962)<br/>
						<a target="_blank" href="https://www.nexusmods.com/users/myaccount?tab=my+files">Click here for your Nexus Mods My Files page</a><br/>
						
						<script>
							if (localStorage.getItem('user_api_key') != null)
							{
								document.getElementById('my_api_key').value = localStorage.getItem('user_api_key');
							}
							if (localStorage.getItem('user_mod_url') != null)
							{
								document.getElementById('opt_mod_url').value = localStorage.getItem('user_mod_url');
							}			
						</script>
					</div>
					
				</form>
				
			</div>
			<div id="http_request_info"></div>

			<br/>		
		</main>
		
		<footer>
			<?php include(PROJECT_ROOT."footer.html"); ?>
		</footer>

	</body>
</html>