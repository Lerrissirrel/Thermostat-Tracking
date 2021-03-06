<?php
require_once( 'common.php' );

date_default_timezone_set( $timezone );
$show_date = date( 'Y-m-d', time() );	// Start with today's date
if( strftime( '%H%M' ) <= '0059' )
{ // If there is not enough (3 data points is 'enough') data to make a meaningful chart, default to yesterday
	$show_date =	date( 'Y-m-d', strtotime( '-1 day', time() ) );	// Start with yesterday's date
}

$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : '';	// Set default thermostat selection

// Login status
$isLoggedIn = false;	// Default to logged out.
if( isset($_POST['password']) && ($_POST['password'] == $password ) )
{ // Update logged in status to true only when the correct password has been entered.
	//session_register( 'user_name' );
	$_SESSION[ 'login_user' ] = 'user_name';
	$isLoggedIn = true;
}

// Now do things that depend on that newly determined login status
// Set Config tab icon default value
$lockIcon = 'tab-sprite lock';			// Default to locked
$lockAlt = 'icon: lock';
$verifyText = 'No';
if( $isLoggedIn )
{
	// Set Config tab icon logged-in value
	$lockIcon = 'tab-sprite unlock';	// Change to UNlocked icon only when user is logged in
	$lockAlt = 'icon: unlock';

	if( isset($_POST['save_settings'] ) )
	{
		$verifyText = 'Yes';
		//save_settings();
	}
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv=Content-Type content='text/html; charset=utf-8'>
		<title>3M-50 Thermostat Tracking</title>
		<link rel='shortcut icon' type='image/x-icon' href='favicon.ico' />

		<link rel='stylesheet' type='text/css' href='/common/css/reset.css' >
		<link rel='stylesheet' type='text/css' href='resources/thermo.css' />

		<link rel='stylesheet' type='text/css' href='lib/tabs/tabsE.css' />		 <!-- Add tab library and set default appearance -->
		<link rel='stylesheet' type='text/css' title='green' href='lib/tabs/tabs-green.css'>
		<meta http-equiv='Default-Style' content='green'>
		<link rel='stylesheet' type='text/css' title='white' href='lib/tabs/tabs-white.css'>

		<!-- Load the stuff that makes it go -->
		<script type='text/javascript' src='resources/thermo.js'></script>

		<!-- These styles are applied to the W3C HTML button on the About tab only and do not need to be part of the .css file -->
		<style>
			a > div.caveat
			{
				display: none;
				text-align: left;
			}
			a:hover > div.caveat
			{
				display: block;
				position: absolute;
				top: 60px;
				left: 100px;
				right: 100px;
				border: 3px double;
				padding: 0px 50px 0px 10px;
				z-index: 100;
				color: #000000;
				background-color: #DCDCDC;
				border-radius: 25px;
			}
			img.caveat
			{
				position: relative;
				width: 19px;
				height: 19px;
				top: 3px;
			}
		</style>
	</head>

	<body>
	<!-- Internal variable declarations START -->
	<input type='hidden' name='id' value='<?php echo urlencode($id) ?>'>
	<input type='hidden' name='useWeather' value=<?php echo $weatherConfig['useWeather']?'true':'false' ?>>
	<input type='hidden' name='useForecast' value=<?php echo $weatherConfig['useForecast']?'true':'false' ?>>
	<!-- Internal variable declarations END -->

	<div class='header'><?php require_once( $rootDir . '/header.php' ); ?></div>
	<br><br><br>
	<div id='bigbox'>

		<div class='all_tabs'>
			<div class='tab_gap'></div>
			<div class='tab_gap'></div>

<?php
			require_once( 'dashboard_tab.class' );
			require_once( 'daily_tab.class' );
			require_once( 'history_tab.class' );
			require_once( 'compare_tab.class' );

			$dashboard = new Dashboard();
			$dailyDetail = new DailyDetail();
			$history = new History();
			$compare = new Compare();

			$dashboard->displayTab();
			$dailyDetail->displayTab();
			$history->displayTab();
			$compare->displayTab();
?>

<style>
/* This set of styles looks proper in Firefox, but NOT in Chrome
div.schedule
{
	position: relative;
	width: 600px;
	margin-left: auto;
	margin-right: auto;
}

fieldset.schedule
{
	border: 1px solid #0000FF;
	width: 75%;
	display: inline-block;
	padding: 10px;
}
fieldset.schedule legend
{
	padding:  0 10px;
}


div.schedule form table.schedule
{
	width: 490px;
	border: 1px solid black;
	table-layout: fixed;
}
tr.day>td
{
	width: 140px;
	text-align: center;
}
div.schedule form table.schedule td.time>input, div.schedule form table.schedule td.temp>input
{
	border: 1px solid black;
	width: 70px;
	text-align: center;
}
div.schedule form table.schedule input[type=number]
{
	width: 70px;
}
*/
div.schedule
{
	position: relative;
	width: 770px;
	margin-left: auto;
	margin-right: auto;
}

fieldset.schedule
{
	border: 1px solid #0000FF;
	width: 75%;
	display: inline-block;
	padding: 10px;	/* This is the spacing around the name of the block */
}

fieldset.schedule legend
{
	padding:  0 10px;
}


/*div.schedule form table.schedule*/
table.schedule
{
	width: 770px;
	border: 1px solid black;
	table-layout: fixed;
}

col.time
{
	width: 65px;
}
col.temp
{
	width: 45px;
}

table.schedule td
{
	font-weight: bold;
	border: 1px solid #999999;
	padding: 5px;
}
col.even
{
	background-color: #888888;
}
col.odd
{
	background-color: #BBBBBB;
}
tr.day>td
{
	width: 80px;
	text-align: center;
}
td.time>input
{
	/* border: 1px solid green; */
	border: 1px solid #777777;
	background-color: #BBBBBB;
	/*color: green;*/	/* The semi-retarded color scheme is so I can verify that this CSS is being applied */
	width: 50px;	/* Chrome uses ~25 pixels on the right of a time input field for the magic input controller (so need additional 25px left padding to truly center it!) */
	text-align: center;
}
td.temp>input
{
	/* border: 1px solid red; */
	border: 1px solid #777777;
	background-color: #BBBBBB;
	/*color: red;*/
	width: 30px;	/* Chrome uses ~12 pixels on the right of a number input field for the magic input controller (so need additional 12px left padding to truly center it!) */
	text-align: center;
}
/*
div.schedule form table.schedule input[type=number]
{
	width: 70px;
}
*/
</style>

			<div class='tab' id='schedule'> <a href='#schedule'> Schedule </a>
				<div class='container'>
					<div class='tab-toolbar'>
						<select id='chart.history.thermostat'>
							<?php foreach( $thermostats as $thermostatRec ): ?>
								<option <?php if( $id == $thermostatRec['id'] ): echo 'selected '; endif; ?>value='<?php echo $thermostatRec['id'] ?>'><?php echo $thermostatRec['name'] ?></option>
							<?php endforeach; ?>
						</select>
						This is a non-functional alpha level set of code.
					</div>
					<div class='content' >
						<div class='schedule'>
<?php
							// This file was getting too large so I'm looking for other ways to make things go
							require_once( 'schedule_tab.class' );
							$form = new schedule();
							$form->displayForm();
?>


						</div>
					</div>
				</div>
			</div>
			<div class='tab_gap'></div>




<?php
		// Only show the account administration tab is the user is logged in.  Don't even hint that this tab exists unless they are logged in.
		if( $isLoggedIn )
		{
?>
			<div class='tab' id='account'> <a href='#account'> <img class='<?php echo $lockIcon;?>' src='images/img_trans.gif' width='1' height='1' alt='<?php echo $lockAlt;?>'/> Account </a>
				<div class='container'>
					<div class='tab-toolbar'>
					Edit your account details here
					</div>
					<div class='content'>
						<br>Manage login and thermostat details here in some kind of form.  This is where the add/edit/delete location and thermostat processes live.
						<br><br>
						<table>
							<tr>
								<th style="padding: 5px;">Name</th>
								<th style="padding: 5px;">Description</th>
								<th style="padding: 5px;">IP</th>
								<th style="padding: 5px;">Model</th>
								<th style="padding: 5px;">Firmware</th>
								<th style="padding: 5px;">WLAN Firmware</th>
								<th style="padding: 5px;">Action</th>
							</tr>
<?php
			//foreach( $userThermostats as $thermostatRec ):
			foreach( $thermostats as $thermostatRec ):
?>
							<tr>
								<td align='left' style="padding: 5px;"><?php echo $thermostatRec['name'] ?></td>
								<td align='left' style="padding: 5px;"><?php echo $thermostatRec['description'] ?></td>
								<td align='center' style="padding: 5px;"><?php echo $thermostatRec['ip'] ?></td>
								<td align='center' style="padding: 5px;"><?php echo $thermostatRec['model'] ?></td>
								<td align='left' style="padding: 5px;"><?php echo $thermostatRec['fw_version'] ?></td>
								<td align='left' style="padding: 5px;"><?php echo $thermostatRec['wlan_fw_version'] ?></td>
								<td align='center' style="padding: 5px;"><input type='button' value='Edit' onClick='javascript: alert("Not implemented");'></td>
						 </tr>
<?php
			endforeach;
?>
						</table>
						<br><br>
						<button type='button' onClick='javascript: backup();'>Backup</button>database. Should be SITE admin function, not USER admin function. <span id='backup' class='backup'></span>
						<br><br>
						<p>Choose appearance: <select id='colorPicker' onChange='javascript: switch_style( document.getElementById( "colorPicker" ).value )'>
							<option value='white'>Ice</option>
							<option value='green' selected>Leafy</option>
						</select></p>
						<br><br>
<form method='post'>
<input name='save_settings' type='hidden' value=''>
<!-- Would be better to handle this as an ajax call, but for now this is it -->
<input value='Save Changes' type='submit' onClick='javascript: save_settings.value="Save"; return( true );'>
					</div>
				</div>
			</div>
			<div class='tab_gap'></div>
<?php
		}
?>



<?php
		// Only show the registration tab if no user is logged in.
		if( ! $isLoggedIn )
		{
?>
			<div class='tab' id='register'> <a href='#register'> <img class='<?php echo $lockIcon;?>' src='images/img_trans.gif' width='1' height='1' alt='<?php echo $lockAlt;?>'/> Register </a>
				<div class='container'>
					<div class='tab-toolbar'>
					Enter your log in details here.
					</div>
					<div class='content'>
						<br><hr>
<?php
						// This file was getting too large so I'm looking for other ways to make things go
						require_once( 'register.class' );
						$form = new register();
						$form->displayForm();
?>
					</div>
				</div>
			</div>
			<div class='tab_gap'></div>
<?php
		}
?>


			<div class='tab' id='about'> <a href='#about'><img class='tab-sprite info' src='images/img_trans.gif' width='1' height='1' alt='icon: about'/> About </a>
				<div class='container'>
					<div class='content'>
						<br>
						<p>
						<p>Source code for this project can be found on <a target='_blank' href='https://github.com/ThermoMan/3M-50-Thermostat-Tracking'>github</a>
						<p>
						<br><br>The project originated on Windows Home Server v1 running <a target='_blank' href='http://www.apachefriends.org/en/xampp.html'>xampp</a>. Migrated to a 'real host' to solve issues with Windows Scheduler.
						<br>I used <a target='_blank' href='http://www.winscp.net'>WinSCP</a> to connect and edited the code using <a target='_blank' href='http://www.textpad.com'>TextPad</a>.
						<p>
						<p>This project also uses code from the following external projects
						<ul style='list-style-type: circle; margin-left: 20px;'>
							<li style='margin-top: 11px;'><a target='_blank' href='http://www.pchart.net/'>pChart</a>.</li>
							<li style='margin-top: 11px;'><a target='_blank' href='https://github.com/ThermoMan/Tabbed-Interface-CSS-Only'>Tabbed-Interface-CSS-Only</a> by ThermoMan.</li>
							<li style='margin-top: 11px;'><a target='_blank' href='http://www.customicondesign.com//'>Free for non-commercial use icons from Custom Icon Designs</a>.	These icons are in the package <a target='_blank' href='http://www.veryicon.com/icons/system/mini-1/'>Mini 1 Icons</a>.</li>
							<li style='margin-top: 11px;'><a target='_blank' href='http://www.stevedawson.com/article0014.php'>Password access loosely based on code by Steve Dawson</a>.</li>
							<li >The external temperatures and forecast come from <a target='_blank' href='http://www.wunderground.com/weather/api/'><img style='position:relative; top:10px; height:31px; border:0;' src='http://icons.wxug.com/logos/PNG/wundergroundLogo_4c_horz.png' alt='Weather Underground Logo'></a></li>
						</ul>
						<br><p>This project is based on the <a target='_blank' href='http://www.radiothermostat.com/filtrete/products/3M-50/'>Filtrete 3M Radio Thermostat</a>.
						<br><br><br><br>
						<div style='text-align: center;'>
							<a target='_blank' href='http://validator.w3.org/check?uri=referer'><img style='border:0;width:88px;height:31px;' src='images/valid-html5.png' alt='Valid HTML 5'/><div class='caveat'><!-- ANY whitespace between the start of the anchor and the start of the div adds an underscore to the page -->
								<br>
								<ul>
									<li>The first warning '<b><img class='caveat' src='images/w3c_info.png' alt='Info'>Using experimental feature: HTML5 Conformance Checker.</b>' is provisional until the HTML5 specification is complete.</li>
									<li>The 2 reported errors '<b><img class='caveat' src='images/w3c_error.png' alt='Error'>Attribute size not allowed on element input at this point.</b>' reported on use of the attribute "size" where input type="date" are incorrect because the HTML 5 validator is provisional until the specification is complete.</li>
									<li>The 2 other reported warnings '<b><img class='caveat' src='images/w3c_warning.png' alt='Warning'>The date input type is so far supported properly only by Opera. Please be sure to test your page in Opera.</b>' may also be read to include Chrome.</li>
<!--									<li>The final warning '<b><img class='caveat' src='images/w3c_warning.png' alt='Warning'>The scoped attribute on the style element is not supported by browsers yet. It would probably be better to wait for implementations.'</b> complains if the style is not scoped and differently when it is. The style that it is complaining about is local only to this very message and therefore should <i>not</i> be global.</li> -->
								</ul>
								<br>
							</div></a> <!-- ANY whitespace between the end of the div and the end of the anchor adds an underscore to the page -->
							<a target='_blank' href='http://jigsaw.w3.org/css-validator/check/referer'><img style='border:0;width:88px;height:31px;' src='http://jigsaw.w3.org/css-validator/images/vcss' alt='Valid CSS!'/></a>
							<br><br><br>The HTML5 components are tested to work in Chrome, Safari (Mac), Android 4.0.4 default browser.	They do not work (manually type in the date) in Firefox.	I've not tested the functionality in IE.	The HTML validator suggests that the HTML 5 components may also work in Opera.
 						</div>
						<br><br><br><br>

						<div style='text-align: center;'>
<?php
     // The uptime format for GNU/Linux is different than that parsed here.  For now, just skipping this section to avoid errors in the log!
     $OS = @exec( 'uname -o' );
     if (!strstr( $OS, 'GNU/Linux'))
     {
	$uptime = @exec( 'uptime' );
	if( strstr( $uptime, 'days' ) )
	{
		if( strstr( $uptime, 'min' ) )
		{
			preg_match( "/up\s+(\d+)\s+days,\s+(\d+)\s+min/", $uptime, $times );
			$days = $times[1];
			$hours = 0;
			$mins = $times[2];
		}
		else
		{
			preg_match( "/up\s+(\d+)\s+days,\s+(\d+):(\d+),/", $uptime, $times );
			$days = $times[1];
			$hours = $times[2];
			$mins = $times[3];
		}
	}
	else
	{
		preg_match( "/up\s+(\d+):(\d+),/", $uptime, $times );
		$days = 0;
		$hours = $times[1];
		$mins = $times[2];
	}
	preg_match( "/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/", $uptime, $avgs );
	$load = $avgs[1].", ".$avgs[2].", ".$avgs[3]."";

	echo "<br>Server Uptime: $days days $hours hours $mins minutes";
	echo "<br>Average Load: $load";
     }
?>
						</div>
					</div>
				</div>
			</div>

		</div>

	</div>
	</body>
</html>