var applicationslug = "unofficialnexusmodssurvey";

/*
 * @author Reneer <reneerbot@gmail.com>
 * @copyright 2019-2020 Reneer.
 * @License: MIT, see LICENSE.TXT
*/

function uuidv4() 
{
	return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
		(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
	)
}

function heartbeat() 
{
	if (!socket) return;
	if (socket.readyState !== 1) return;
	socket.send("ping");
	setTimeout(heartbeat, 30000);
}

function connect_nexus_ws()
{	
	/*
	document.getElementById("login_buttons_div").style.visibility = "hidden";
	document.getElementById("advanced_opts").style.visibility = "hidden";	
	*/
	
	console.log("Connect_nexus_ws entered");
	
	if (document.getElementById("my_api_key").value != null && document.getElementById("my_api_key").value.length >= 3)
	{
		console.log("Connect_nexus_ws found API key (1), sending form.");
		document.getElementById("api_submit_button").click();
		return;
	}
	
	var nexus_socket = new WebSocket("wss://sso.nexusmods.com/");
	
	var heartfunc = function()
	{
		if (!this) return;
		if (this.readyState !== 1) return;
		this.send("ping");
		console.log("Heartbeat sent");
		setTimeout(heartfunc, 30000);	
	}.bind(nexus_socket);
	
	nexus_socket.onopen = function(event) 
	{
		var user_uuid = sessionStorage.getItem("uuid");
		var token = sessionStorage.getItem("connection_token");
		if (user_uuid == null || user_uuid.length <= 10)
		{
			user_uuid = uuidv4();
			sessionStorage.setItem('uuid', user_uuid);
			token = "";
		}
		var data = { id: user_uuid, token: token, protocol: 2 };
		var jsondata = JSON.stringify(data);

		console.log("Sent data to SSO: " + jsondata);
		this.send(jsondata);
		//heartfunc();
	}.bind(nexus_socket);

	// Listen for messages
	nexus_socket.onmessage = function(event) 
	{		
		console.log('Message from server ', event.data);
		var jsonresponse = JSON.parse(event.data);
	
		if (jsonresponse.data != null && jsonresponse.data.connection_token != null)
		{
			sessionStorage.setItem("connection_token", jsonresponse.data.connection_token);
			window.open('https://www.nexusmods.com/sso?id=' + sessionStorage.getItem("uuid") + '&application=' + applicationslug, "_blank");							
		}
	
		if (jsonresponse.data != null && jsonresponse.data.api_key != null)
		{
			console.log("Got API key from SSO: " + jsonresponse.data.api_key);
			this.close();
			document.getElementById("my_api_key").value = jsonresponse.data.api_key;
			
			if (document.getElementById("user_save_info").checked == true)
			{				
				localStorage.setItem('user_api_key', document.getElementById("my_api_key").value);
			}			
			
			console.log("Connect_nexus_ws found API key (2), sending form.");
			
			document.getElementById("api_submit_button").click();
		}
	}.bind(nexus_socket);
}