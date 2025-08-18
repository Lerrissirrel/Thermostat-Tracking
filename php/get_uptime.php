<?php
require_once '../common.php';

     // The uptime format for GNU/Linux is different than that parsed here.  For now, just skipping this section to avoid errors in the log!
     $return_html = "Failed to get system uptime";
     $OS = @exec( 'uname -o' );
     if (strstr( $OS, 'GNU/Linux'))
     {
        $uptime = @exec( 'uptime' );
        if( strstr( $uptime, 'day' ) )
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
                        // Assume the form of "up..DD..day?..HH:MM" where ".." is anything but bounded by white space, DD = days, day? matches "day" or "days", HH = hours, MM = minutes,
                     // if (preg_match( "/up?.\s+(?P<days>\d+.)\s+day?.\s+(?P<hours>\d+):(?P<mins>\d+)/", $uptime, $times ) == 1)
                        $preg_match_status = preg_match( "/up?.\s+(?P<days>\d+)\s+day?.\s+(?P<hours>\d+):(?P<mins>\d+)/", $uptime, $times );
                        if ($preg_match_status == 1)
                        {
                                $days = $times["days"];
                                $hours = $times["hours"];
                                $mins = $times["mins"];
                        }
                        else
                        {
                                error_log("*".$uptime."*: failed to match uptime string with status: ".$preg_match_status);
                                $preg_match_status = preg_match( "/up?.\s+(?P<days>\d+)\s+day?.\s+(?P<hours>\d+):(?P<mins>\d+)/", $uptime, $times );
                                error_log("Tried again, got status: ".$preg_match_status);
//                                error_log($uptime." failed to match uptime string");
                        }
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

        if (isset($days) && isset($hours) && isset($mins))
                $return_html = "Server Uptime: $days days $hours hours $mins minutes";

        if (isset($load))
                $return_html .= "<br>Average Load: $load";
     }
print_status_and_data(0, $return_html);
?>
