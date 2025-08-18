<?php
/** Config file for thermostat monitoring and reporting software.
	*
	*/
	/* DO NOT CHANGE $my_root or $rootDir! */
$my_root = realpath(__DIR__.'/..');
$rootDir = dirname(__FILE__) . '/../';

	/* If you really want, you can change the logs directory but best to leave the default  */
$logDir =  $rootDir . 'logs/';

/**
	* Lockfile - set to a path that exists on your system
	*  the thermostat id from the database will be appended to this filename
	* This keeps thermo_update_temps and thermo_update_status from running at the same time.  If they both hit the
	* thermostat at the same time, the thermostat could be overloaded and become unresponsive for 20-30 minutes
	* until the wifi module resets.
	*/
	/* Setting REQUIRED! */

$lockFile = '/tmp/thermo_control.lock';

/**
	* Really need to have timezone for each location so that all data is stored in the 'local' zone.
	* At present this is used to force the servers (php procesor, web server, DB server) to think they
	*  are in the same timezone as the location of all the thermostats.
	*
	* If you are using a system that does not understand timezones (for example Synology NAS) or you are
	*  using it in a 100% local environment then uses SYSTEM.
	* $timezone = 'SYSTEM';
	*/
	/* Setting REQUIRED! */

$timezone = 'America/New_York';

	/* Your ZIP code  (still assuming that all thermostats are in one location.  Multi-location support comes later. */
	/* Setting required only if using a weather API */
$ZIP = '10001';

/** Weather - External Temperature
	* weatherunderground requires an api key - register for a free one
	* weatherbug requires an api key - register for a free one. !!temps in F only!!
	* noaa requires an api_loc - see http://w1.weather.gov/xml/current_obs/ for the correct xml url for your location
	*/
	/* Setting required only if using a weather API */
$weatherConfig = array(
	'useWeather'  => TRUE,											// TRUE, FALSE (Flag to use external temperature for all stats)
	'useForecast' => FALSE,											// TRUE, FALSE (Flag to show forecast on dashboard))
	'type'    => 'noaa',			// weatherunderground, noaa, weatherbug
	'units'   => 'F',												// F, C
	'api_key' => '0000000000000000',				// Registered API key
	// api_loc needs work - it is relied upon by noaa code, but the others pretty much ignore it.
	'api_loc' => 'https://forecast.weather.gov/xml/current_obs/KJRB.xml'                   		// NULL (but NEVER blank ''), http://w1.weather.gov/xml/current_obs/KUZA.xml
	// URL - User Specific
	// See  http://www.wunderground.com/weather/api/d/docs
	//      http://weather.weatherbug.com/desktop-weather/api-documents.html
	//      http://w1.weather.gov/xml/current_obs/
);

	/* Database connection parameters */
	/* Setting REQUIRED! */
$dbConfig = array(
	'username'     	=> 'dbuser',
	'password'     	=> 'dbpasswd',
	'host'		=> '192.168.1.10',
	'db'          	=> 'therm2',
	'port'         	=> '3306',
	'table_prefix' 	=> 'thermo2__'             // Prefix to attach to all table/procedure names to make unique in unknown environment.
	// Using a double underscore as the end of the prefix enables some magic in phpMyAdmin
);

	/* Admin account name */
        /* This user has access to the Admin tab where you can manipulate the Thermostat list and some other minor things */
	/* Setting is optional if you aren't using users or don't want to use the admin tab */
        /* Only one admin allowed at the moment, leave blank to not use it */
        /* Note the users are from the local Linux/UNIX machine.  Any local user can "log in" and that will give them the ability to control thermostats */
$admin_account = 'yourloginhere';

	/* Allow thermostat control access (without logging in) for IP addresses that match this netmask */
        /* Only supports IPV4 right now.  So if you're using IPV6 you'll need to leave this blank
	/* Comment out the following line to disable (which effectively requires logging in to control thermostats) */
$full_access_netmask = '192.168.1.0/24';

	/* Name to "Welcome" when connected via an IP in $full_access_netmask */
        /* Setting is optional, an empty string of "" is valid */
$full_access_name = 'Household';

	/* Allow users to ask to remain logged in via checkbox when logging in (no time based auto logout) */
	/* Must be set to "true" if you want to allow it, or anything else if you don't */
$allow_no_auto_logout = true;

	/* Default idle timeout, in minutes, for a login that hasn't used the no_auto_logout */
	/* Setting REQUIRED! Default is 60 seconds */
$login_timeout = 60;

	/* Amount of time between updates of the server uptime data on the About tab, in seconds.  0 = just on page load */
	/* Setting REQUIRED! Default is 0 - don't refresh */
$about_tab_uptime_interval = 0;

	/* Default log level to actually put in the log */
	/* Setting REQUIRED! Default is "ERROR" */
        /*  Other values, in increasing verbosity, are: EMERGENCY, ALERT, CRITICAL, ERROR, WARNING, NOTICE, INFO, DEBUG */
$log_level = 'ERROR';

/**
	* The following ought to be stored in the DB with a config page
	*
	* But before it can be remotely configurable there has to be an ID/PW system for some tabs
	* I guess a tab would have to contain an iframe and the iframe has a page that checks permissions.
	*/
//// Email is not implemented yet!
// $send_end_of_day_email = 'Y';     // 'Y' or 'N'
// $send_eod_email_time = '0800';    // format is HHMM (24-hour) as text string
// $send_eod_email_address = 'foo@foobar.com';
// $send_eod_email_smtp = '';
// $send_eod_email_pw = '';

/**
	* Add a check at the end of the one per minute task to see if time now == $send_eod_email_time
	* The better way would be to use Windows Scheduler to create a task to run at the named time
	*  In order to implement that, need to store Windows ID and Password to be able to write the
	*  command line necesary to change the existing schedule.  Those two items should be in this
	*  config file on the theory that the file system is slightly more secure than a DB that is
	*  already available online.  Make sure to use a non-privilaged account!
	*/

	/* The default layout of the applications structure

+-- <your www root
    +-- <whatever>
    +-- thermo2
        +-- backup
        +-- config
        +-- images
        +-- install
        +-- lib
        |   +-- fonts
        |   +-- tabs
        +-- logs
        +-- node_modules (Created by npm)
        |   + <npm installed modules>
        +-- php
        +-- resources
        +-- scripts
        +-- tmp_data
        +-- vendor (Created by composer)
            + <composer installed libraries>

	* In order to be able to reference those files without hard coded path names, the PHP include path needs to know about the relative location of
	*  those libraries.
	*/
function add_include_path( $path )
{
	foreach( func_get_args() AS $path )
	{
		if( !file_exists( $path ) OR ( file_exists( $path ) && filetype( $path ) !== 'dir' ) )
		{
			trigger_error( "Include path '{$path}' not exists", E_USER_WARNING );
			continue;
		}

		$paths = explode( PATH_SEPARATOR, get_include_path() );

		if( array_search( $path, $paths ) === false )
		{
			array_push( $paths, $path );
		}

		set_include_path( implode( PATH_SEPARATOR, $paths ) );
	}
}
add_include_path( $rootDir );
?>
