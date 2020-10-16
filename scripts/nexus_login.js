// not used anymore, but works well.

/*
 * @author Reneer <reneerbot@gmail.com>
 * @copyright 2019-2020 Reneer.
 * @License: MIT, see LICENSE.TXT
*/

var nexus_socket = new WebSocket("wss://sso.nexusmods.com/");

var nexus_http = new XMLHttpRequest();

var nexus_url = "https://api.nexusmods.com/"

var applicationslug = "";

var g_user_id = null;

var g_is_mod_author = false;

var g_nexus_games_list = [];

function sleep(ms) 
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

function uuidv4() {
	return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
		(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
	)
}

nexus_http.onload = function(event)
{
	message_received(this);
};

function nexus_get_games_list()
{
	// /v1/games.json
	
	nexus_http.open("GET", nexus_url + "/v1/games.json?include_unapproved=false");
	set_apikey_header();
	nexus_http.send();	
}

function search_games_list()
{
	// unused and not fully functional due to changes.
	console.log("Searching: " + document.getElementById("opt_game_name").value + ", " + document.getElementById("opt_game_name").value.length);
	sleep(1000);
	if (document.getElementById("opt_game_name").value.length >= 3)
	{
		var sel = document.getElementById("opt_game_list");				
		
		for (child in sel)
		{
			if (sel[child] != null && sel[child].tagName == "HTMLOptionElement")
			{
				if (sel[child].text.indexOf(document.getElementById("opt_game_name").value) == -1)
				{
					sel.removeChild(sel[child]);
				}
			}
		}	
		
		for (game in g_nexus_games_list)
		{
			if (g_nexus_games_list[game].indexOf(document.getElementById("opt_game_name").value) >= 0)
			{
				//console.log("Found: " + g_nexus_games_list[game]);
				var opt = document.createElement('option');
				opt.text = g_nexus_games_list[game];
				// set value property of opt
				opt.value = 'option value';
				opt.onclick = function(gamename) {
					document.getElementById("opt_game_name").value = gamename;
				}.bind(null, g_nexus_games_list[game]);
				// add opt to end of select box (sel)
				sel.appendChild(opt); 			
			}
		}	
	}
}

function set_apikey_header()
{
	if (window.personalapikey != null)
	{
		nexus_http.setRequestHeader("apikey", window.personalapikey);
	} else {
		nexus_http.setRequestHeader("apikey", localStorage.getItem("api_key"))			
	}
}

function get_user_info()
{
	// /v1/users/validate.json
	// https://api.nexusmods.com/v1/users/validate.json

	nexus_http.open("GET", nexus_url + "v1/users/validate.json");
	set_apikey_header();
	nexus_http.send();
}

function get_user_tracked_files()
{
	// /v1/user/tracked_mods.json
	
	nexus_http.open("GET", nexus_url + "/v1/user/tracked_mods.json");
	set_apikey_header();
	nexus_http.send();
}

function check_if_mod_author(user_id)
{
	get_user_tracked_files();
	
	/*
	nexus_http.open("GET", "https://www.nexusmods.com/users/" + user_id);
	set_apikey_header();
	nexus_http.send();
	*/
}

function get_published_files(textdata)
{
	var cleaneddata = textdata.replace( /[\r\n]+/gm, "" );  // remove newlines
	if (cleaneddata.indexOf("<span class=\"tab-label\">User Files</span><span class=\"alert\">") >= 0)
	{
		// found user file info
		var numindex = cleaneddata.indexOf("<span class=\"tab-label\">User Files</span><span class=\"alert\">") + "<span class=\"tab-label\">User Files</span><span class=\"alert\">".length;
		var files_published_int = Number(cleaneddata.substr(numindex, cleaneddata.indexOf("</span>", numindex)-numindex));
		if (files_published_int > 0)
		{
			console.log("User is a mod author with " + files_published_int.toString() + " published files.");
		}
	}
}

function get_mod_info(gamename, modid)
{
	// /v1/games/{game_domain_name}/mods/{id}.json
	nexus_http.open("GET", nexus_url + "/v1/games/" + gamename + "/mods/" + modid + ".json");
	set_apikey_header();
	nexus_http.send();
}

async function message_received(eventdata)
{
	var mimetype = eventdata.getResponseHeader('content-type').split('/')[1];
	var responseURL = eventdata.responseURL;

	if (mimetype.indexOf("json") >= 0)
	{
		var jsonresponse = JSON.parse(eventdata.responseText);
			
		if (jsonresponse.message != null && jsonresponse.message.indexOf("valid API Key") >= 0)
		{
			alert(jsonresponse.message);
			return;
		}
		
		if (jsonresponse.data != null && jsonresponse.data.connection_token != null)
		{
			localStorage.setItem('connection_token', jsonresponse.data.connection_token);
			// Open the browser window, using the uuid and your application's reference
			window.open("https://www.nexusmods.com/sso?id="+jsonresponse.data.connection_token+"&application="+applicationslug);
		}
		if (jsonresponse.api_key != null)
		{
			localStorage.setItem('api_key', jsonresponse.data.api_key);
			nexus_socket.close();
			get_user_info();
		}
		if (jsonresponse.user_id != null)
		{
			// we've got the user ID.
			document.getElementById("login_buttons_div").style.display = "none";
			document.getElementById("http_request_info").innerHTML = "Got Nexus User ID: " + jsonresponse.user_id + "<br/>";
			g_user_id = jsonresponse.user_id;
			localStorage.setItem('user_id', jsonresponse.user_id);
			
			if (document.getElementById("opt_mod_url").value != "")
			{
				var fullurl = document.getElementById("opt_mod_url").value;
				var urlparts = fullurl.replace("https://www.nexusmods.com/", "").split("/mods/");
				
				get_mod_info(urlparts[0], urlparts[1]);
			} else {
				check_if_mod_author(jsonresponse.user_id);
			}
		}
		if (responseURL.indexOf("tracked_mods.json") >= 0)
		{
			// got tracked mods info from user
			document.getElementById("http_request_info").innerHTML += "Getting tracked mods...<br/>";
			
			var number = 100;
			for (mod in jsonresponse)
			{
				if (g_is_mod_author == false)
				{
					var game = jsonresponse[mod].domain_name;
					var modid = jsonresponse[mod].mod_id;		
					get_mod_info(game, modid);
					await sleep(250);
				}
				number -= 1;
				if (number <= 0)
				{
					break;
				}
			}
			await sleep(500);
			if (g_is_mod_author == false)
			{
				// user is not a mod author.
				alert("We are sorry, but we can not determine if you are a mod author on Nexus Mods. Please check to make sure you are tracking at least one of your released mods in the Nexus Mods system.");
			}
		}
		if (responseURL.indexOf("/mods/") >= 0)
		{
			var author_id = jsonresponse.user.member_id;
			
			if (author_id == g_user_id)
			{
				g_is_mod_author = true;				
				nexus_http.abort();				
			}				
			
			if (jsonresponse.name != null)
			{
				document.getElementById("http_request_info").innerHTML += "Checking mod: " + jsonresponse.name + ". User ID: " + g_user_id + ", Author ID: " + author_id + "<br/>";			
			}
			
			if (document.getElementById("opt_mod_url").value != "")
			{
				// only one input.
				await sleep(500);
				if (g_is_mod_author == false)
				{
					alert("We are sorry, but your User ID did not match the Author ID from mod: " + jsonresponse.name);
				}
			}
			if (g_is_mod_author == true && document.getElementById("surveylist") == null)
			{
				document.getElementById("http_request_info").style.display = "none";
				
				var scriptElement = document.createElement('script');
				scriptElement.id = "surveyid";
				scriptElement.src = "https://embed-cdn.surveyhero.com/js/user/embed.695f91a0.js";
				scriptElement.setAttribute("async", "");
				document.body.appendChild(scriptElement);				
			}
		}
		if (responseURL.indexOf("games.json") >= 0)
		{
			console.log("Got games list...");
			for (game in jsonresponse)
			{
				g_nexus_games_list.push(jsonresponse[game].domain_name);
			}
		}
	} else if (mimetype.indexOf("document") >= 0 || eventdata.responseType == "") 
	{
		// HTML document
	}
}

function nexus_login(api_key = null)
{		
	if (api_key != null || localStorage.getItem('user_api_key') != null)
	{
		if (localStorage.getItem('user_api_key') == null)
		{
			if (document.getElementById("user_save_info").checked == true)
			{				
				localStorage.setItem('user_api_key', api_key);
			}
		}
		if (localStorage.getItem('user_mod_url') == null || document.getElementById("opt_mod_url").value != null)
		{
			if (document.getElementById("user_save_info").checked == true)
			{					
				localStorage.setItem('user_mod_url', document.getElementById("opt_mod_url").value)
			}
		}
		window.personalapikey = api_key;
		nexus_socket.close();
		get_user_info();
	} else {
	
		// retrieve previous uuid and token
		var uuid = localStorage.getItem("uuid");
		var token = localStorage.getItem("connection_token");

		if (uuid == null)
		{
			uuid = uuidv4();
			localStorage.setItem('uuid', uuid);		
		}
		if (token == null)
		{
			token = null;
		}
		
		var data = {
			id: uuid,
			appid: applicationslug,
			token: token,
			protocol: 2
		};	

		nexus_socket.onopen = function(data)
		{
			// Send the request
			this.send(JSON.stringify(data));
		}.bind(nexus_socket, data);

		nexus_socket.onmessage = function(event)
		{
			if (this.readyState == 4 && this.status == 200) 
			{
				message_received(this);
			}
		};
	}
}