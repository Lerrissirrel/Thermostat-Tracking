<?php
$skip_timeout_check = true;
require_once( 'common.php' );

date_default_timezone_set( $timezone );

// Set Config tab icon default value
$lockIcon = 'tab-sprite lock';			// Default to locked
$lockAlt  = 'icon: lock';

if( session_value('isloggedin') )
{
   // Set Config tab icon logged-in value
   $lockIcon = 'tab-sprite unlock';	// Change to UNlocked icon only when user is logged in
   $lockAlt  = 'icon: unlock';
}
?>

<!DOCTYPE html>
<html lang="en" id=bigbox_html>
   <head>
      <meta http-equiv=Content-Type content='text/html, charset=utf-8'>
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>3M-50 Thermostat Tracking</title>
      <link rel='shortcut icon' type='image/x-icon' href='favicon.ico' >

      <!-- adding the filetime as a variable is a trick to make sure the client doesn't cache an old version of the css -->
      <link rel='stylesheet' type='text/css' href='resources/thermo.css?v=<?php echo filemtime('resources/thermo.css'); ?>' >
      <link rel='stylesheet' type='text/css' href='lib/tabs/tabs.css?v=<?php echo filemtime('lib/tabs/tabs.css'); ?>' >
      <!-- Set the default theme to the current selected one or default to 'green' -->
      <link rel='stylesheet' id='pagestyle' type='text/css' title='green' href='lib/tabs/tabs-
<?php
         $theme = (isset($_COOKIE['theme'])) ? $_COOKIE['theme'] : 'green';
         echo $theme.".css?v=".filemtime('lib/tabs/tabs-'.$theme.'.css'); 
?>
       '>
      <link rel='stylesheet' id='pagestyle' type='text/css'>
      <!-- Not sure which "green" this is referring to and whether it's even currect to do this, given the tylesheet link above -->
      <meta http-equiv='Default-Style' content='green'>

      <!-- Load the stuff that makes it go -->
      <script src="node_modules/jquery/dist/jquery.js"></script>
      <script src='node_modules/echarts/dist/echarts.min.js'></script>
      <script src='resources/echarts_thermo_themes.js'></script>
      <script src='resources/thermo.js'></script>
   </head>

   <body>
      <div class='header' id='header' ><?php require_once( $rootDir . '/php/header.php' ); ?></div>
      <div id='bigbox' >
         <div class='all_tabs' style='white-space: nowrap; top: 1.5em;'>
<?php
            // Include all the tab classes

            require_once( 'daily_tab.class' );
            require_once( 'dashboard_tab.class' );
            require_once( 'history_tab.class' );
            require_once( 'compare_tab.class' );
            require_once( 'about_tab.class' );
            require_once( 'register_tab.class' );
            if( session_value('isloggedin') ) 
            {
               require_once( 'control_tab.class' );
               require_once( 'account_tab.class' );
               if (session_value('isadmin'))
               {
                  require_once( 'admin_tab.class' );
               }
            }

            // Generate all the tab classes

            $dailyDetail = new DailyDetail();
            $dashboard   = new Dashboard();
            $history     = new History();
            $compare     = new Compare();

            if( session_value('isloggedin') ) {
               $control = new Control();
               $account = new Account();
               if (session_value('isadmin'))
               {
                  $admin = new Admin();
               }
            }

            $about    = new About();
            $register = new Register();

            // Display all the tab classes

            $dailyDetail->displayTab();
            $dashboard->displayTab();
            $history->displayTab();
            $compare->displayTab();

            if( session_value('isloggedin') ) {
               $control->displayTab();
               $account->displayTab();
               echo "<script>loadThermostat( \"schedule\" );update( \"getcurrentstat\" );</script>";
               if (session_value('isadmin'))
               {
                  $admin->displayTab();
               }
            }

            // Only show the registration tab if no user is logged in.
            // MJH "&& 0" until it's working
            if( session_value('isloggedin') && 0)
            {
               $register->displayForm();
            }

            $about->displayTab();
?>
         </div> <!-- all_tabs -->
      </div> <!-- bigbox -->
      <!-- Get some javascript things going for us like checking login timeout -->
      <script src='resources/thermo_runtime.js'></script>
   </body>
</html>

