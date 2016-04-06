function login() {
	var win = window.open(auth_url, "windowname1", 'width=800, height=600'); 

	var pollTimer = window.setInterval(function() { 
		try {
			console.log(win.document.URL);
			if (win.document.URL.indexOf(REDIRECT) != -1) {
				window.clearInterval(pollTimer);
				var url =   win.document.URL;
				acToken =   gup(url, 'access_token');
				tokenType = gup(url, 'token_type');
				expiresIn = gup(url, 'expires_in');
				win.close();

				validateToken(acToken);
			}
		} catch(e) {
		}
	}, 500);
}

function validateToken(token) {
	jQuery.ajax({
		url: VALIDURL + token,
		data: null,
		success: function(responseText){  
			getUserInfo();
			console.log(responseText);
			loggedIn = true;
			$('#loginText').hide();
			$('#logoutText').show();
		},  
		dataType: "jsonp"  
	});
}

function getUserInfo() {
	jQuery.ajax({
		url: 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' + acToken,
		data: null,
		success: function(resp) {
			console.log(resp);
		},
		dataType: "jsonp"
	});
}

//credits: http://www.netlobo.com/url_query_string_javascript.html
function gup(url, name) {
	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
	var regexS = "[\\#&]"+name+"=([^&#]*)";
	var regex = new RegExp( regexS );
	var results = regex.exec( url );
	if( results == null )
		return "";
	else
		return results[1];
}

function startLogoutPolling() {
	jQuery('#loginText').show();
	jQuery('#logoutText').hide();
	loggedIn = false;
	jQuery('#uName').text('Welcome ');
	jQuery('#imgHolder').attr('src', 'none.jpg');
}
  jQuery(function() {
    jQuery( "#startdate" ).datepicker();
	jQuery( "#enddate" ).datepicker();
  });