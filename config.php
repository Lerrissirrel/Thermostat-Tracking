<?php
/** Config file for thermostat monitoring and reporting software.
	*
	*/

$rootDir = dirname(__FILE__) . '/';
$logDir =  $rootDir . 'logs/';

/**
	* Lockfile - set to a path that exists on your system
	*  the thermostat id from the database will be appended to this filename
	* This keeps thermo_update_temps and thermo_update_status from running at the same time.  If they both hit the
	* thermostat at the same time, the thermostat could be overloaded and become unresponsive for 20-30 minutes
	* until the wifi module resets.
	*/
$lockFile = '/tmp/thermo.lock';

/**
	* Really need to have timezone for each location so that all data is stored in the 'local' zone.
	* At present this is used to force the servers (php procesor, web server, DB server) to think they
	*  are in the same timezone as the location of all the thermostats.
	*
	* If you are using a system that does not understand timezones (for example Synology NAS) or you are
	*  using it in a 100% local environment then uses SYSTEM.
	* $timezone = 'SYSTEM';
	*/
$timezone = 'America/Los_Angeles';

// Your ZIP code  (still assuming that all thermostats are in one location.  Multi-location support comes later.
$ZIP = '90210';

/** Weather - External Temperature
	* weatherunderground requires an api key - register for a free one
	* weatherbug requires an api key - register for a free one. !!temps in F only!!
	* noaa requires an api_loc - see http://w1.weather.gov/xml/current_obs/ for the correct xml url for your location
	*/
$weatherConfig = array(
	'useWeather'  => TRUE,											// TRUE, FALSE (Flag to use external temperature for all stats)
	'useForecast' => TRUE,											// TRUE, FALSE (Flag to show forecast on dashboard.  Currently only supported for weatherunderground))
	'type'    => 'weatherunderground',			// weatherunderground, noaa, weatherbug
	'units'   => 'F',												// F, C
	'api_key' => '0000000000000000',				// Registered API key
	// api_loc needs work - it is relied upon by noaa code, but the others pretty much ignore it.
	'api_loc' => NULL                   		// NULL (but NEVER blank ''), http://w1.weather.gov/xml/current_obs/KUZA.xml
	// URL - User Specific
	// See  http://www.wunderground.com/weather/api/d/docs
	//      http://weather.weatherbug.com/desktop-weather/api-documents.html
	//      http://w1.weather.gov/xml/current_obs/
);


// Database connection parameters
$dbConfig = array(
	'username'     => 'user',
	'password'     => 'password',
	'host'					=> 'localhost',
	'db'          	=> 'thermo',
	'port'         	=> '3306',
	'table_prefix' => 'thermo2__'             // Prefix to attach to all table/procedure names to make unique in unknown environment.
	// DO make this prefix DIFFERENT than you used for version 2 (if you had the old code installed)
	// Using a double underscore as the end of the prefix enables some magic in phpMyAdmin
);


// Config edit PW
$password = 'admin';

// Display variables

/**
	* Set normal temperature range so the charts always scale the same way
	*
	* Hi/Low temps for each month (January to December).  Based on normal hi/low temps.  These temps are in
	* degrees F and are  manually updated for now.
	* Add +/- 10 when displaying to try to keep the lines in the chart from banging into the edges of the area.
	*
	* Ideas for future:
	*  + Connect normal high/low to location.
	*  + Store in the DB along with the locations.
	*  + Keep track of F/C in the database and convert to preference when displaying
	*  + Always store in degrees F and convert to degrees C for display since each degree F is a smaller
	*     increment than each degree C.
	*
	*                  Jan Feb Mar Apr May  Jun  Jul  Aug Sep Oct Nov Dec
	*/
$normalHighs = array( 60, 60, 70, 80, 90, 100, 100, 100, 90, 70, 70, 60 );
$normalLows  = array( 30, 50, 40, 50, 60,  70,  70,  70, 60, 50, 40, 30 );

// Amount of space to add to y scale to keep lines inside the chart
$chartPaddingLimit = 5;		// When to trigger the addition of space (pixels)
$chartPaddingSpace = 10;	// Amount of space to add (pixels)

/**
	* The following ought to be stored in the DB with a config page
	*
	* But before it can be remotely configurable there has to be an ID/PW system for some tabs
	* I guess a tab would have to contain an iframe and the iframe has a page that checks permissions.
	*/
$send_end_of_day_email = 'Y';     // 'Y' or 'N'
$send_eod_email_time = '0800';    // format is HHMM (24-hour) as text string
$send_eod_email_address = 'your_address@wherever.com';
$send_eod_email_smtp = '';
$send_eod_email_pw = '';
/**
	* Add a check at the end of the one per minute task to see if time now == $send_eod_email_time
	* The better way would be to use Windows Scheduler to create a task to run at the named time
	*  In order to implement that, need to store Windows ID and Password to be able to write the
	*  command line necesary to change the existing schedule.  Those two items should be in this
	*  config file on the theory that the file system is slightly more secure than a DB that is
	*  already available online.  Make sure to use a non-privilaged account!
	*/

/** For libraries that are not uniquely part of the application code base, there is a common
	* location on the webserver so that all projects can use one instance of the library.

+-- www
    +-- common
    |   +-- css
    |   +-- html
    |   +-- js
    |   +-- php
    |       +-- pChart -> pChart2.1.4
    |       +-- pChart2.1.3
    |       |   +-- cache
    |       |   +-- class
    |       |   +-- data
    |       |   +-- examples
    |       |   |   +-- delayedLoader
    |       |   |   +-- imageMap
    |       |   |   |   +-- scripts
    |       |   |   |   +-- tmp
    |       |   |   +-- pictures
    |       |   |   +-- resources
    |       |   |   +-- sandbox
    |       |   |       +-- graphix
    |       |   |       +-- includes
    |       |   |       +-- script
    |       |   +-- fonts
    |       |   +-- palettes
    |       +-- pChart2.1.4
    |           +-- cache
    |           +-- class
    |           +-- data
    |           +-- examples
    |           |   +-- delayedLoader
    |           |   +-- imageMap
    |           |   |   +-- scripts
    |           |   |   +-- tmp
    |           |   +-- pictures
    |           |   +-- resources
    |           |   +-- sandbox
    |           |       +-- graphix
    |           |       +-- includes
    |           |       +-- script
    |           +-- fonts
    |           +-- palettes
    +-- thermo2
        +-- backup
        +-- images
        +-- lib
        |   +-- fonts
        |   +-- tabs
        +-- logs
        +-- resources
        +-- scripts

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
add_include_path( $rootDir . '../common/php/' );
?>
