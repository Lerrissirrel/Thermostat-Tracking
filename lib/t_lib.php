<?php

/**
 * API Class to connect to Radio Thermostat
 *
 */

class Thermostat_Exception extends Exception
{
}


class Stat
{
    //global $log;   // Wow, putting this global brings the entire system to a halt.  Do not like...

    protected
        $ch,
        $IP;   // Most likely an URL and port number rather than a strict set of TCP/IP octets.

    // Low level communication adjustments
    protected static
        // The idea is to give ourselves a lot of time for the first try, and if it doesn't work, give ourselves less time for subsequent retries
        // with a total timeout by 59s worst case
        $maxRetries        = 2,          // Try at most 4 times before giving up (5 + 15 + 25 = 45 seconds spent trying!)
        $retrySleep        = 2,          // Time, in seconds, to sleep between attempts
        $initialTimeout    = 33000,      // Start with a 35 second timeout
	$maxAttemptTime    = 59000,      // Maximum amount of time we will spend trying to succeed, regardless of number of attempts
	$subsequentTimeout = 9000;       // After the initialTimeout how long do we give subsequent attempts

    private $debug = false;

    // Would prefer these to be private/protected and have get() type functions to return value.
    // But for now, public will do because I'm lazy.
    public
        $temp =        null,
        $tmode =       null,
        $fmode =       null,
        $override =    null,
        $hold =        null,
        $t_cool =      null,
        $tstate =      null,
        $fstate =      null,
        $day =         null,
        $time =        null,
        $t_type_post = null,
        $humidity =    null;

    public $dummy_time = null, $dummy_temp = null;

    public
        $runTimeCool = null,
        $runTimeHeat = null,
        $runTimeCoolYesterday = null,
        $runTimeHeatYesterday = null;
    
    // Set to -1 before each curl_exec call.  A value of 0 means it worked.  Otherwise it gets the last encountered curl error number
    public $connectOK = null;
    
    //
    public $errStatus = null;
    //
    public $model = null;

    // System vars
    public $uuid = null,
        $api_version = null,
        $fw_version = null,
        $wlan_fw_version = null,
        $ssid = null,
        $bssid = null,
        $channel = null,
        $security = null,
        $passphrase = null,
        $ipaddr = null,
        $ipmask = null,
        $ipgw = null,
        $rssi = null;

    public function __construct( $ip )
    {
        $this->IP = $ip;
        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_USERAGENT, 'A' );
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, 1 );

        $this->debug = 0;

        // Stat variables initialization
        $this->temp = 0;
        $this->tmode = 0;
        $this->fmode = 0;
        $this->override = 0;
        $this->hold = 0;
        $this->t_cool = 0;
        $this->tstate = 0;
        $this->fstate = 0;
        $this->day = 0;
        $this->time = 0;
        $this->t_type_post = 0;
        $this->humidity = -1;
        //
        $this->runTimeCool = 0;
        $this->runTimeHeat = 0;
        $this->runTimeCoolYesterday = 0;
        $this->runTimeHeatYesterday = 0;
        //
        $this->errStatus = 0;
        //
        $this->model = 0;

        // System variables
        $this->uuid = 0;
        $this->api_version = 0;
        $this->fw_version = 0;
        $this->wlan_fw_version = 0;
        $this->ssid = 0;
        $this->bssid = 0;
        $this->channel = 0;
        $this->security = 0;
        $this->passphrase = 0;
        $this->ipaddr = 0;
        $this->ipmask = 0;
        $this->ipgw = 0;
        $this->rssi = 0;

        // Cloud variables
    }

    public function __destruct()
    {
        curl_close( $this->ch );
    }

    protected function getStatData( $cmd )
    {
        global $log;
        $gsd_time_start = hrtime(true);
        $gsd_start_time = microtime(true);
        $commandURL = 'http://' . $this->IP . $cmd;
        $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." getStatData URL: ".$commandURL);
        $this->connectOK = -1;
        $newTimeout = self::$initialTimeout;

        // For reference http://www.php.net/curl_setopt
        curl_setopt( $this->ch, CURLOPT_URL, $commandURL );

        //$log->Info( 't_lib: getStatData trying...' );
        $retry = 0;

	$attempts['count'] = 0;
	$attempts['stati'] = array();
	$attempts['time'] = array();

        do
        {
	    $start_time = microtime(true);
            if ($retry == 0)
            {
              $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: getStatData: '.$this->IP.' doing try '.$retry.' after '.(intval((hrtime(true)-$gsd_time_start)/1000000)).'ms ... '.$commandURL);
            }
            else if ($retry > 0 && $retry <= 2)
            {
              $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: getStatData: '.$this->IP.' doing retry '.$retry.' after ' .(intval((hrtime(true)-$gsd_time_start)/1000000)).'ms ... '.$commandURL);
            }
            else
            {
              $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: getStatData: '.$this->IP.' doing retry '.$retry.' after ' .(intval((hrtime(true)-$gsd_time_start)/1000000)).'ms ... '.$commandURL);
            }

            if( $retry > 0 ) $log->Warning( "t_lib: getStatData: ".$this->IP." setting timeout to $newTimeout for retry number $retry." );
            curl_setopt( $this->ch, CURLOPT_TIMEOUT_MS, $newTimeout );

            $outputs = curl_exec( $this->ch );

            $my_curl_errno = curl_errno( $this->ch );
            $my_curl_msg = "Failed to set error message";
            if ($my_curl_errno == 0)
            {
                $obj = json_decode( $outputs );
                if ($this->containsTransient( $obj ))
                {
                   $my_curl_errno = 255;
                   $my_curl_msg = "Thermo transient data";
                   $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: ".$this->IP." Transient failure (after ".intval(microtime(true)-$gsd_start_time)."s), retrying.  Outputs: ". $outputs );
                }
            }
            else
            {
               $my_curl_msg = curl_error( $this->ch );
            }

            if( $my_curl_errno != 0)
            {
                $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: getStatData: at '.intval(microtime(true)-$gsd_start_time).'s, curl error '.$my_curl_errno.': '.$my_curl_msg.'. Cmd: '.$commandURL.' try #'.$retry );
                if(( (self::$maxAttemptTime - microtime(true) - $gsd_start_time) <= self::$initialTimeout) && ($newTimeout != self::$subsequentTimeout))
                {
                    if ($my_curl_errno == 255)
                    {
                       $newTimeout = 15000;
                    }
                    else
                    {
                       $newTimeout = self::$subsequentTimeout;
                    }
                    $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: getStatData: ".$this->IP." changed timeout to $newTimeout after curl timeout. (total elapsed time = ".microtime(true) - $gsd_start_time.")" );
                }
                else
                {
                    $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: getStatData: ".$this->IP." left timeout at $newTimeout after curl timeout. (total elapsed time = ".microtime(true) - $gsd_start_time.")" );
                }
            }
            /** Build in one second sleep after each communication attempt
             * based on code from phareous - he had 2 second delay here and there
             * The thermostat will stop responding for 20 to 30 minutes (until next WiFi reset) if you overload the connection.
             * Previously I was not using a delay and had not problems, but caution is better.
             *
             * Later on, in a many thermostat environment, each stat will need to be queried in a thread so that the delays
             *   do not stack up and slow the overall application to a crawl.
             */
            if ((microtime(true) - $start_time) > 20)
            {
               $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: getStatData: ".$this->IP." one attempt took ".(microtime(true)-$start_time)." seconds");
            }

            if ($my_curl_errno != 0)
            {
               $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: getStatData: Attemp ".$retry." ".$this->IP." timeout check ".(((microtime(true) - $gsd_start_time) * 1000) + $newTimeout)." vs ".self::$maxAttemptTime); 
               // Remember all of our failures so we can report on them at the end
               $attempts['count']++;
               $attempts['stati'][$retry] = $my_curl_errno;
               $attempts['time'][$retry]  = microtime(true) - $start_time;
               $attempts['time_index'][$retry] = microtime(true) - $gsd_start_time;
               $retry++;
            }
            sleep( self::$retrySleep );
        }
        while( ($my_curl_errno != 0) && (((microtime(true) - $gsd_start_time) * 1000) + $newTimeout < self::$maxAttemptTime) );

        if ((microtime(true) - $gsd_start_time) > 60) 
        {
            $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: >60s Made $retry attempts in ".intval((microtime(true) - $gsd_start_time) * 1000)."ms.  Last curl status was " . $my_curl_errno );
        } 
        else if( $retry > 1 )
        {
            $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: Made $retry attempts in ".intval((microtime(true) - $gsd_start_time) * 1000)."ms.  Last curl status was " . $my_curl_errno );
//            $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib: Made $retry attempts and last curl status was " . curl_errno( $this->ch ) );
        }

        $this->connectOK = curl_errno( $this->ch );   // Only capture the last status because the retries _might_ have worked!

        if( $this->connectOK != 0 )
        {   // Drat some problem.  Now what?
            for ($j = 0; $j < $attempts['count']; ++$j)
            {
                $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: getStatData: Attempt '.$j." status: ".$attempts['stati'][$j]." length: ".$attempts['time'][$j]." at: ".$attempts['time_index'][$j]);
            }
            $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: getStatData unable to complete request in '.(intval((hrtime(true)-$gsd_time_start)/1000000)).'ms and '.$retry.' attempts: '.$commandURL);
            throw new Thermostat_Exception( 'getStatData: communication error ' . $this->IP.' after '.(intval((hrtime(true)-$gsd_time_start)/1000000)).'ms');
        }

        return $outputs;
    }

    protected function setStatData( $command, $value )
    {
        global $log;
        $this->connectOK = -1;

        $commandURL = 'http://' . $this->IP . $command;

        curl_setopt( $this->ch, CURLOPT_URL, $commandURL );
        curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $value );
        
            $log->Error( "t_lib: setStatData: commandURL was \"$commandURL\" and data was \"$value\"" );
        if( $this->debug   )
        {
            $log->Error( "t_lib: setStatData: commandURL was $commandURL" );
            // echo '<br>commandURL: ' . $commandURL . '<br>';
        }

        if( !$outputs = curl_exec( $this->ch ) )
        {
            throw new Thermostat_Exception( 'setStatData: ' . curl_error($this->ch) );
        }
        $this->connectOK = curl_errno( $this->ch );

        // Need to wait for a response...   object(stdClass)#4 (1) { ['success']=> int(0) }
        
        if( $this->connectOK != 0 )
        {   // Drat some problem.  Now what?
            $log->Error( 't_lib: setStatData communication error: '.$this->IP.' status: '.$this->connectOK );
            throw new Thermostat_Exception( 'setStatData: communication error' . $this->IP.' status: '.$this->connectOK);
        }

        // return $outputs;

        // Once we actually start using the set function the error detection, timeout, and retry logic will begin to apply here too.
        return;
    }

    protected function containsTransient( $obj )
    {
        global $log;
        $retval = false;
        // Aha!  This might be how to detect the missing connection?
        //$log->Info( 't_lib: containsTransient looking...' );
        if( is_object($obj) )
        {
            foreach( $obj as $key => &$value )
            {
                //$log->Info( 't_lib: containsTransient key...' );
                // Warning: Invalid argument supplied for foreach() in ~/thermo2/lib/t_lib.php on line 171
                // It was line 171 before I started adding comments!
                if( is_object($value) )
                {
                    foreach( $value as $key2 => &$value2 )
                    {
                        $log->Info( 't_lib: containsTransient, nested first level key...'. print_r($value, true) );
                        if (is_object($value2))
                        {
                            $log->Info( 't_lib: containsTransient, nested second level key '.print_r($value2, true));
                            foreach( $value2 as $key3 => &$value3 )
                            {
                               //$log->Info( 't_lib: containsTransient nested key...' );
                               if (is_object($value3))
                               {
                                  $log->Error( 't_lib: containsTransient, nested third level key?? '.print_r($value3, true));
                                  // Should probably fail here, in some way??
                               }
                               else
                               {
                                  // These thermostats may return "-1" as a value if it had a problem while trying to build the response.  The API guide
                                  // refers to these as "tranients"
                                  if( $value3 == -1 )
                                  {
                                      $log->Warning( 't_lib: containsTransient WARNING (' . date(DATE_RFC822) . '): ' . $key3 . " contained a transient" );
                                      $retval = true;
                                  }
                               }
                            }
                        }
                        else
                        {
                           // These thermostats may return "-1" as a value if it had a problem while trying to build the response.  The API guide
                           // refers to these as "tranients"
                           if( $value2 == -1 )
                           {
                               $log->Warning( 't_lib: containsTransient WARNING (' . date(DATE_RFC822) . '): ' . $key2 . " contained a transient" );
                               $retval = true;
                           }
                        }
                    }
                }
                else
                {   // Comment out because this message appears even when everything is working!
                    // $log->Error( 't_lib: containsTransient: value was NOT an object!' );
                }
                // These thermostats may return "-1" as a value if it had a problem while trying to build the response.  The API guide
                // refers to these as "tranients"
                if( gettype($value) != "object" && $value == -1 )
                {
                    //echo 'WARNING (' . date(DATE_RFC822) . '): ' . $key . " contained a transient\n";
                    $log->Warning( 't_lib: containsTransient WARNING (' . date(DATE_RFC822) . '): ' . $key . " contained a transient" );
                    $retval = true;
                }
            }
        }
        else
        {
            $log->Error( 't_lib: containsTransient: argument obj was NOT an object!' );
            $retval = true;
        }
        return $retval;
    }

    public function showMe()
    {
        // For now hard coded HTML <br> but later let CSS do that work
        echo '<br><br>Thermostat data (Yaay!   I found the introspection API - hard coding SUCKS)';
        echo '<table id="stat_data">';
        echo '<tr><th>Setting</th><th>Value</th><th>Explanation</th></tr>';

        $rc = new ReflectionClass('Stat');
        $prs = $rc->getProperties();
        $i = 0;
        foreach( $prs as $pr )
        {
            if( $i == 0 )
            {
                $i = 1;
                echo '<tr>';
            }
            else
            {
                $i = 0;
                echo '<tr class="alt">';
            }
            $key = $pr->getName();
            $val = $this->{$pr->getName()};
            if( $key == 'ZIP' || $key == 'ssid' )
            {
                // Once we have password protected pages, allow these to be shown?
                $val = 'MASKED';
            }
            echo '<td>' . $key . '</td><td>' . $val . '</td></tr>';
        }
    }

    // Still need a list of explanation and values to interpret.
    public function showMeOld()
    {
        // For now hard coded HTML <br> but later let CSS do that work
        echo '<br><br>Thermostat data';
        echo '<table id="stat_data">';
        echo '<tr><th>Setting</th><th>Value</th><th>Explanation</th></tr>';

        //      echo '<br><br>From /tstat command';
        echo '<tr><td>this->temp</td><td>' . $this->temp . '</td><td>�F</td></tr>';
        // The degree mark is not HTML5 compliant.
        // Instead of forcing a degree F, check the mode from config.php

        $statTMode = array( 'Auto?', 'Heating', 'Cooling' );
        echo '<tr class="alt"><td>this->tmode</td><td>' . $this->tmode            . '</td><td> [ ' . $statTMode[$this->tmode] . ' ] </td></tr>';

        $statFanMode = array( 'Auto', 'On' );
        echo '<tr><td>this->fmode</td><td>' . $this->fmode            . '</td><td> [ ' . $statFanMode[$this->fmode] . ' ] </td></tr>';

        echo '<tr class="alt"><td>this->override</td><td>' . $this->override . '</td><td></td></tr>';

        $statHold = array( 'Normal', 'Hold Active' );
        echo '<tr><td>this->hold</td><td>' . $this->hold             . '</td><td> [ ' . $statHold[$this->hold] . ' ] </td></tr>';

        echo '<tr class="alt"><td>this->t_cool</td><td>' . $this->t_cool . '</td><td>�F</td></tr>';

        $statTState = array( 'Off', 'Heating', 'Cooling' );
        echo '<tr class="alt"><td>this->tstate</td><td>' . $this->tstate          . '</td><td> [ ' . $statTState[$this->tstate] . ' ] </td></tr>';

        $statFanState = array( 'Off', 'On' );
        echo '<tr><td>this->fstate</td><td>' . $this->fstate          . '</td><td> [ ' . $statFanState[$this->fstate] . ' ] </td></tr>';

        //      echo '<br>this->day            : ' . $this->day               . ' [ ' . jddayofweek($this->day,1) . ' ] </td><td></td></tr>';
        $statDayOfWeek = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
        echo '<tr class="alt"><td>this->day</td><td>' . $this->day               . '</td><td> [ ' . $statDayOfWeek[$this->day] . ' ] </td></tr>';

        echo '<tr><td>this->time</td><td>' . $this->time . '</td><td></td></tr>';
        echo '<tr class="alt"><td>this->t_type_post</td><td>' . $this->t_type_post . '</td><td></td></tr>';

        //      echo '<br><br>From /tstat/datalog command (converted to minutes)';
        echo '<tr><td>this->runTimeCool</td><td>' . $this->runTimeCool . '</td><td></td></tr>';
        echo '<tr class="alt"><td>this->runTimeHeat</td><td>' . $this->runTimeHeat . '</td><td></td></tr>';
        echo '<tr><td>this->runTimeCoolYesterday</td><td>' . $this->runTimeCoolYesterday . '</td><td></td></tr>';
        echo '<tr class="alt"><td>this->runTimeHeatYesterday</td><td>' . $this->runTimeHeatYesterday . '</td><td></td></tr>';

        //      echo '<br><br>From /tstat/errorstatus command';
        echo '<tr><td>this->errStatus</td><td>' . $this->errStatus               . '</td><td>[ 0 is OK ]</td></tr>';

        //      echo '<br><br>From /tstat/model command';
        echo '<tr class="alt"><td>this->model</td><td>' . $this->model . '</td><td></td></tr>';

        echo '</table>';

        echo '<br><br>System data';

        echo '<table id="sys_data">';
        echo '<tr><th>Setting</th><th>Value</th><th>Explanation</th></tr>';

        //      echo '<tr><td>this->uuid</td><td>'                  . $this->uuid                  . '</td><td> MAC address of thermostat</td></tr>';
        echo '<tr><td>this->uuid</td><td>'                  . 'MASKED'                  . '</td><td> MAC address of thermostat</td></tr>';
        echo '<tr class="alt"><td>this->api_version</td><td>'       . $this->api_version       . '</td><td> 1 (?)</td></tr>';
        echo '<tr><td>this->fw_version</td><td>'         . $this->fw_version         . '</td><td> e.g. 1.03.24</td></tr>';
        echo '<tr class="alt"><td>this->wlan_fw_version</td><td>' . $this->wlan_fw_version . '</td><td> e.g. v10.99839</td></tr>';


        //      echo '<tr><td>this->ssid</td><td>'          . $this->ssid          . '</td><td>SSID</td></tr>';
        echo '<tr><td>this->ssid</td><td>'          . 'MASKED'          . '</td><td>SSID</td></tr>';
        //      echo '<tr class="alt"><td>this->bssid</td><td>'         . $this->bssid         . '</td><td>MAC address of wifi device</td></tr>';
        echo '<tr class="alt"><td>this->bssid</td><td>'         . MASKED         . '</td><td>MAC address of wifi device</td></tr>';
        echo '<tr><td>this->channel</td><td>'      . $this->channel      . '</td><td>Current wifi channel e.g. 11</td></tr>';
        //      echo '<tr class="alt"><td>this->security</td><td>'    . $this->security    . '</td><td>WiFi security protocol: 1 (WEP Open), 3 (WPA), 4 (WPA2 Personal)</td></tr>';
        echo '<tr class="alt"><td>this->security</td><td>'    . 'MASKED'    . '</td><td>WiFi security protocol: 1 (WEP Open), 3 (WPA), 4 (WPA2 Personal)</td></tr>';
        //      echo '<tr><td>this->passphrase</td><td>' . $this->passphrase . '</td><td>password (not shown in api_version 113)</td></tr>';
        echo '<tr><td>this->passphrase</td><td>' . 'MASKED' . '</td><td>password (not shown in api_version 113)</td></tr>';
        echo '<tr class="alt"><td>this->ipaddr</td><td>'       . $this->ipaddr       . '</td><td>IP address of thermostat (api_version 113 shows "1" ?)</td></tr>';
        echo '<tr><td>this->ipmask</td><td>'       . $this->ipmask       . '</td><td>Netmask (not shown in api_version 113?)</td></tr>';
        echo '<tr class="alt"><td>this->ipgw</td><td>'          . $this->ipgw          . '</td><td>Gateway (not shown in api_version 113?)</td></tr>';
        echo '<tr><td>this->rssi</td><td>'          . $this->rssi          . '</td><td>Received Signal Strength (api_version 113)</td></tr>';
        echo '</table>';


        return;
    }

    public function getStat()
    {
        global $log;
        /** Query thermostat for data and check the query for transients.
         * If there are transients repeat query up to 5 times for collecting good data
         * Continue when successful.
         */
        $time_start = hrtime(true);
        for( $i = 1; $i <= 5; $i++ )
        {
            $outputs = $this->getStatData( '/tstat' );   // getStatData() has it's own retry function.
            // {"temp":80.50,"tmode":2,"fmode":0,"override":0,"hold":0,"t_cool":80.00,"tstate":2,"fstate":1,"time":{"day":2,"hour":18,"minute":36},"t_type_post":0}
            $obj = json_decode( $outputs );

            if( !$this->containsTransient( $obj ) )
            {   // It worked?  Get out of the retry loop.
                break;
            }
            else
            {
                // Was original a check against 5 but transient checking was moved into getStatData, so if we get here and we
                // have a transient, we've spent our max amount of time already, so just give up
                if( $i > 1)
                {
                    $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib getStat: Too many thermostat transient communication failuress.' );
                    throw new Thermostat_Exception( 'Too many thermostat transient failures' );
                }
                else
                {
                    //echo "Transient (" . date(DATE_RFC822) . ") failure " . $i . " retrying...\n";
                    //$log->Error( "t_lib: Transient (" . date(DATE_RFC822) . ") failure " . $i . " retrying..." );
                    $log->Warning(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib getStat: ".$this->IP." Transient failure " . $i . " (after ".(intval((hrtime(true)-$time_start)/1000000))."ms, retrying.)");
                    $log->Info(str_pad(getmypid(), 5, " ", STR_PAD_LEFT)." t_lib getStat: ".$this->IP." Transient failure " . $i . " (after ".(intval((hrtime(true)-$time_start)/1000000))."ms, retrying.  Outputs: ". $outputs );
                }
            }

            if( empty( $obj ) )
            {
                $log->Error(str_pad(getmypid(), 5, " ", STR_PAD_LEFT).' t_lib: No output from thermostat in '.(intval(($hrtime(true)-$time_start)/1000000)).'ms' );
                throw new Thermostat_Exception( 'No output from thermostat' );
            }
        }

        // Move fetched data to internal data structure
        $this->temp = $obj->{'temp'};                   // Present temp in deg F (or C depending on thermostat setting)
        $this->tmode = $obj->{'tmode'};                // Present t-stat mode
        $this->fmode = $obj->{'fmode'};                // Present fan mode
        $this->override = $obj->{'override'};       // Present override status 0 = off, 1 = on
        $this->hold = $obj->{'hold'};                   // Present hold status 0 = off, 1 = on
        
        $this->t_cool = 0;
        $this->t_heat = 0;
        if( $this->tmode == 1 )
        { // mode 1 is heat
            $this->t_heat = $obj->{'t_heat'};          // Present heat target temperature in degrees
        }
        else if( $this->tmode == 2 )
        { // mode 2 is cool
            $this->t_cool = $obj->{'t_cool'};          // Present cool target temperature in degrees
        }
        // I kinda wish this was $this->t_target as we only need to distinguish between heat and cool desired temperatures in the schedules.

        $this->tstate = $obj->{'tstate'};             // Present heater/compressor state 0 = off, 1 = heating, 2 = cooling
        $this->fstate = $obj->{'fstate'};             // Present fan state 0 = off, 1 = on

        $var1 = $obj->{'time'};                            // Present time
        $this->day = $var1->{'day'};
        //      $this->time = sprintf(' %2d:%02d %s',($var1->{'hour'} % 13) + floor($var1->{'hour'} / 12), $var1->{'minute'} ,$var1->{'hour'}>=12 ? 'PM':'AM');
        $this->time = sprintf(' %2d:%02d',($var1->{'hour'}), $var1->{'minute'} );

        $this->t_type_post = $obj->{'t_type_post'};

        return;
    }

    public function getDataLog()
    {
        $outputs = $this->getStatData( '/tstat/datalog' );
        $obj = json_decode( $outputs );

        $var1 = $obj->{'today'};
        $var2 = $var1->{'heat_runtime'};
        $this->runTimeHeat = ($var2->{'hour'} * 60) + $var2->{'minute'};

        $var2 = $var1->{'cool_runtime'};
        $this->runTimeCool = ($var2->{'hour'} * 60) + $var2->{'minute'};

        $var1 = $obj->{'yesterday'};
        $var2 = $var1->{'heat_runtime'};
        $this->runTimeHeatYesterday = ($var2->{'hour'} * 60) + $var2->{'minute'};

        $var2 = $var1->{'cool_runtime'};
        $this->runTimeCoolYesterday = ($var2->{'hour'} * 60) + $var2->{'minute'};

        return;
    }

    public function getErrors()
    {
        $outputs = $this->getStatData( '/tstat/errstatus' );
        $obj = json_decode( $outputs );
        $this->errStatus = $obj->{'errstatus'};

        return;
    }

    public function getEventLog()
    {
        $this->debug = true;
        $outputs = $this->getStatData( '/tstat/eventlog' );
        $obj = json_decode( $outputs );
        $var1 = $obj->{'eventlog'};
        $this->debug = false;
        throw new Thermostat_Exception( 'getEventLog() - Not implemented' );

        return;
    }

    // Essentially a duplicate function, but it works
    public function getFMode()
    {
        $outputs = $this->getStatData( '/tstat/fmode' );
        $obj = json_decode( $outputs );
        $this->fmode = $obj->{'fmode'};                // Present fan mode

        return;
    }

    public function getHelp()
    {
        $this->debug = true;
        $outputs = $this->getStatData( '/tstat/help' );
        $obj = json_decode( $outputs );
        $this->debug = false;
        throw new Thermostat_Exception( 'getHelp() - Not implemented' );

        return;
    }

    // Essentially a duplicate function, but it works
    public function getHold()
    {
        $outputs = $this->getStatData( '/tstat/hold' );
        $obj = json_decode( $outputs );
        $this->hold = $obj->{'hold'};                   // Present hold status 0 = off, 1 = on

        return;
    }

    public function getHumidity()
    {
        $outputs = $this->getStatData( '/tstat/humidity' );   // {"humidity":-1.00} This is example of no sensor.
        $obj = json_decode( $outputs );
        $this->humidity = $obj->{'humidity'};                        // Present humidity

        return;
    }

    public function setLED()
    {
        throw new Thermostat_Exception( 'setLED() - Not implemented' );   // Prevent problems for now

        $outputs = $this->getStatData( '/tstat/led' );
        $obj = json_decode( $outputs );

        return;
    }

    public function getModel()
    {
        $outputs = $this->getStatData( '/tstat/model' );   // {"model":"CT50 V1.09"}
        $obj = json_decode( $outputs );
        $this->model = $obj->{'model'};

        return;
    }


    public function getSysName()
    {
        $outputs = $this->getStatData( '/sys/name' ); // {"name":"Home"}
        $obj = json_decode( $outputs );
        $this->sysName = $obj->{'name'};

        return;
    }


    // Essentially a duplicate function, but it works
    public function getOverride()
    {
        $outputs = $this->getStatData( '/tstat/override' );
        $obj = json_decode( $outputs );

        $this->override = $obj->{'override'};
        return;
    }

    public function getPower()
    {
        $outputs = $this->getStatData( '/tstat/power' );
        $obj = json_decode( $outputs );

        $this->power = $obj->{'power'};   // Milliamps?
        return;
    }

    public function setMode($mode)
    {
        global $log;

        $mode_value = 0;
        if ($mode == 'Heat')
        {
            $mode_value = 1;
        }
        else if ($mode == 'Cool')
        {
            $mode_value = 2;
        }
        else if ($mode == 'Auto')
        {
            $mode_value = 3;
        }

        $outputs = $this->setStatData( '/tstat/tmode','{"tmode":'.$mode_value.'}');
        $obj = json_decode( $outputs );

        return;
    }

    public function setHold($hold)
    {
        global $log;

        //        throw new Thermostat_Exception( 'setHold() - Not implemented' );   // Prevent problems for now
        $hold_value = 0;
        if ($hold == 'On')
        {
            $hold_value = 1;
        }

        $outputs = $this->setStatData( '/tstat/hold','{"hold":'.$hold_value.'}');
        $obj = json_decode( $outputs );

        return;
    }

    public function setTemp($temp, $mode)
    {
        global $log;

        $temp_value = $temp;
        if ($temp < 50)
        {
            $temp_value = 50;
        }
        else if ($temp > 90)
        {
            $temp_value = 90;
        }

        if ($mode == 'heat' ) 
        {
            $outputs = $this->setStatData( '/tstat/','{"it_heat":'.$temp_value.'}');
        }
        else if ($mode == 'cool' )
        {
            $outputs = $this->setStatData( '/tstat/','{"it_cool":'.$temp_value.'}');
        }
        else
        {
            //Auto?
            $log->Error("t_lib: setTemp didn't detect heat or cool being set tmode = $this->tmode");
            return;
        }
        
        $obj = json_decode( $outputs );

        return;
    }

    public function setFan($fan)
    {
        global $log;

        //        throw new Thermostat_Exception( 'setFan() - Not implemented' );   // Prevent problems for now
        $fan_value = 0;
        if ($fan == 'On')
        {
            $fan_value = 2;
        }

        $outputs = $this->setStatData( '/tstat/fmode','{"fmode":'.$fan_value.'}');
        $obj = json_decode( $outputs );

        return;
    }

    public function setBeep()
    {
        throw new Thermostat_Exception( 'setBeep() - Not implemented' );   // Prevent problems for now

        $outputs = $this->getStatData( '/tstat/beep' );
        $obj = json_decode( $outputs );

        return;
    }

    public function setUMA()
    {
        throw new Thermostat_Exception( 'setUMA() - Not implemented' );   // Prevent problems for now -- only CT80

        $outputs = $this->getStatData( '/tstat/uma' );
        $obj = json_decode( $outputs );

        return;
    }

    public function setPMA()
    {
        throw new Thermostat_Exception( 'setPMA() - Not implemented' );   // Prevent problems for now

        $outputs = $this->getStatData( '/tstat/pma' );
        $obj = json_decode( $outputs );

        return;
    }

    // Essentially a duplicate function, but it works
    public function getTemp()
    {
        $outputs = $this->getStatData( '/tstat/temp' );
        $obj = json_decode( $outputs );
        $this->temp = $obj->{'temp'};                   // Present temp in deg F (or C?)

        return;
    }

    // Essentially a duplicate function, but it works
    public function getTime()
    {
        $outputs = $this->getStatData( '/tstat/time' );
        $obj = json_decode( $outputs );

        $var1 = $obj;
        $this->day = $var1->{'day'};
        $this->time = sprintf(' %2d:%02d',($var1->{'hour'}), $var1->{'minute'} );

        return;
    }

    // Uses the time from the http server to set the time of the thermostat.  Note this will not
    // do the right thing if the thermostat is not in the same time zone as the http server.

    public function setTime()
    {
        global $log;

        $day_of_week = date("w");
        $hour        = date("H");
        $minute      = date("i");

        // Therm wants Monday = 0; but php time gives Sunday = 0;
        if ($day_of_week == 0)
        {
           $day_of_week = 7;
        }

        $cmd = '/tstat/time';
        $value = '{"day":'.($day_of_week-1).',"hour":'.$hour.',"minute":'.$minute.'}';
        $outputs = $this->setStatData( $cmd, $value );
        if ( $this->connectOK != 0)
        {
          $log->Error( "t_lib: setTime connectOK shows an error ($this->connectOK)" );
        }
        else
        {
          $log->Info( "t_lib: setTime response ($outputs)" );
        }
        return;
    }

    public function getSysInfo()
    {
        global $log;

        $outputs = $this->getStatData( '/sys' );   // '/sys/info' No longer works as of API version ???

        if( $this->connectOK == 0 )
        {   // If the connection worked, decode the output
            $obj = json_decode( $outputs );
            // {"uuid":"xxxxxxxxxxxx","api_version":113,"fw_version":"1.04.84","wlan_fw_version":"v10.105576"}

            $this->uuid = $obj->{'uuid'};
            $this->api_version = $obj->{'api_version'};
            $this->fw_version = $obj->{'fw_version'};
            $this->wlan_fw_version = $obj->{'wlan_fw_version'};
        }
        else
        {
            $log->Info( "t_lib: getSysInfo connectOK shows an error ($this->connectOK)" );
        }

        return;
    }

    public function getSysNetwork()
    {
        global $log;
        $outputs = $this->getStatData( '/sys/network' );

        if( $this->connectOK == 0 )
        {   // If the connection worked, decode the output
            $obj = json_decode( $outputs );

            $this->ssid = $obj->{'ssid'};
            $this->bssid = $obj->{'bssid'};
            $this->channel = $obj->{'channel'};
            $this->security = $obj->{'security'};
            $this->passphrase = $obj->{'passphrase'};
            $this->ipaddr = $obj->{'ipaddr'};
            $this->ipmask = $obj->{'ipmask'};
            $this->ipgw = $obj->{'ipgw'};
            $this->rssi = $obj->{'rssi'};
        }
        else
        {
            $log->Info( "t_lib: getSysNetwork connectOK shows an error ($this->connectOK)" );
        }

        return;
    }

    // When this routine works the first time, it can take up to 15 seconds
    public function getProgram($heat_or_cool)
    {
        global $log;
        $log->Info( 't_lib: getProgram start.' );
        $d_time = array( array() );
        $d_temp = array( array() );
        
        //$this->getStat();
        if ($heat_or_cool == 'cool')    
        {
            $outputs = $this->getStatData( '/tstat/program/cool' );
            //        $log->Error($outputs);
            //        $log->Error("after first output");
            $output_cool = $outputs;
        
            if( $this->connectOK == 0 )
            {   // If the connection worked, decode the output
            
                $obj = json_decode( $outputs );

               // Turn this into an array like: $program[<cool 0/heat 1>][<day 0-6?][<new time 0/new temp 1>] = ???
            
                if( is_object( $obj ) )
                {
                    //echo "\nobj is object";
                    foreach( $obj as $day => &$program )
                    {// I think this loop will be for each day
                        //echo "\nforeach got key value as $key $value";
                        $period = 0;
                        for( $index = 0; $index < 8; $index++ )
                        {
                            //echo "\nwhen key is $key and i $i then value[i] is $value[$i]";
                            $stat_program[0][$day][$period][0] = $program[$index]; // time
                            $index++;
                            $stat_program[0][$day][$period][1] = $program[$index]; // temp
                            $period++;
                        }
                    }
                }
            }
            else
            {
                $log->Error( 't_lib: getProgram fetch of COOL program failed.' );
                return;
            }
        }

        if ($heat_or_cool == 'heat')
        {
            $outputs = $this->getStatData( '/tstat/program/heat' );
            $output_heat = $outputs;

            if( $this->connectOK == 0 )
            {   // If the connection worked, decode the output
                $obj = json_decode( $outputs );

                if( is_object( $obj ) )
                {
                    foreach( $obj as $day => &$program )
                    {// I think this loop will be for each day
                        $period = 0;
                        for( $index = 0; $index < 8; $index++ )
                        {
                            $stat_program[1][$day][$period][0] = $program[$index]; // time
                            $index++;
                            $stat_program[1][$day][$period][1] = $program[$index]; // temp
                            $period++;
                        }
                    }
                }
            }
            else
            {
                $log->Error( 't_lib: getProgram fetch of HEAT program failed.' );
                return;
            }
        }

        if ($heat_or_cool != 'heat' && $heat_or_cool != 'cool')
        {
            $log->Error( 't_lib: getProgram neither heat or cool specified.' );  
            return;
        }

        // This is kind of dumb but the schedule is kept as one element of $this, and I don't want to have to learn both
        // programs if the user only needs one (rarely is someone adjusting both the heat and cool at the same time)
        // so we copy the one we got into the other one just to fill out the structure of $this->stat_program
        // Should either: 1) Make stat_program two things or 2) If we get here with something in $this->stat_program for
        // the one we didn't just learn, only update the new part.
        if ($heat_or_cool == 'heat')
        {
            $output_cool = $output_heat;
        }
        if ($heat_or_cool == 'cool')
        {
            $output_heat = $output_cool;
        }
        // This assignment is conditional - both cool and heat must have worked to use the data

        $this->stat_program = '{"cool":'.$output_cool.',"heat":'.$output_heat.'}';
        $log->Info( 't_lib: getProgram end good'.$this->stat_program );
        return;
    }

    // When this routine works the first time, it can take up to 15 seconds

    // May have to iterate through all days since the set function seems to be for one day
    // Or perhaps do a get and only send the modified days.
    public function setProgram($schedule, $heat_or_cool)
    {
        global $log;
        $log->Info( 't_lib: getProgram start.' );
        $d_time = array( array() );
        $d_temp = array( array() );
    
        //$this->getStat();
        $log->Info('t_lib: stat_program:\n');
        $log->Info( 't_lib: setProgram start.' );
        if ($heat_or_cool == 'cool')
        {
            $log->Info("t_lib: cool: ".$schedule."\n");
            $log->Info('t_lib: /tstat/program/cool'.$schedule);
            $obj = $this->setStatData( '/tstat/program/cool',$schedule);
        }
        else if ($heat_or_cool == 'heat')
        {
            $log->Info("t_lib: heat: ".$schedule."\n");
            $log->Info('t_lib: /tstat/program/heat'.$schedule);
            $obj = $this->setStatData( '/tstat/program/heat',$schedule);   
        }
        else
        {
            $log->Error("t_lib: Set program was told neither heat nor cool");
            return;
        }
        $log->Info("t_lib: program status: ".$obj);
    }
}

?>
