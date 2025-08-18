<?php

require_once ('common_chart.php');

// Default to today as the end date
$to_date = date('Y-m-d');

// Check for a passed in end date
if (isset($_POST['chart_daily_toDate']))
{ // Use provided date
   $to_date = $_POST['chart_daily_toDate'];
}

if (!validate_date($to_date)) return;

// Verify that date is not future?

// interval_measure indicates what interval was selected in the drop down.  Are we showing days, weeks, months or years?
// Currently only used to figure out how many days to generate data for.  We do generate less frequent data points if there
// are "too many" days picked, but that is done later when preparing the DB query
$interval_measure = 0; // Default to days
if (isset($_POST['chart_daily_interval_group']))
{
   $interval_measure = $_POST['chart_daily_interval_group'];
   if ($interval_measure < 0 || $interval_measure > 3)
   { 
      // 0: days, 1: weeks, 2: months, 3: years
      $interval_measure = 0;
   }
}

if (isset($_POST['chart_daily_interval_length']))
{
   $interval_length = $_POST['chart_daily_interval_length'];

   // Bounds checking - Let's keep ourselves under 1 years! (Which is probably already too long)
   
   if ($interval_length < 0) $interval_length = 1;
   
   $days_array = array(
      0 => 1,
      1 => 7,
      2 => 30,
      3 => 365 
   );

   $days = $days_array[$interval_measure];

   // The math isn't great when months are involved since we're approximating months with 30 days
   if ($interval_length * $days > 365) 
   {
      $interval_length = floor(365 / $days);
   }
}
else
{
   $log->Error("gendata_daily.php: chart_daily_interval_length was not set");
   $interval_length = 1;
}

$date_text = array(
   0 => 'days',
   1 => 'weeks',
   2 => 'months',
   3 => 'years'
);

// Since the from_date is the specified (end) date minus the interval, it is always off by one day.  For example, 
// "today - 1 day" would be yesterday, but we don't WANT to show yesterday, we wanted to show one day ending on 
// today: today.  Likewise, "today - 1 week" also goes back one too many days, etc.  So while we subtract off the 
// $interval_length in units of $date_text[$interval_measure] we always add one extra day which brings the beginning 
// date forward by one day

$interval_string = $to_date . ' -' . $interval_length . ' ' . $date_text[$interval_measure]. ' + 1 days';

// Compute the "from date"
$from_date = date('Y-m-d', strtotime($interval_string));

// Start and end time include the time and DO include midnight of the next day
$start_time = strftime('%Y-%m-%d 00:00:00', strtotime($from_date));         // "2012-07-10 00:00:00";
$end_time   = strftime('%Y-%m-%d 00:00:00', strtotime("$to_date +1 days")); // "2012-07-11 00:00:00";  Catch the first data point of the next day to show as midnight

if (strftime('%Y-%m-%d', strtotime($to_date)) == strftime('%Y-%m-%d', strtotime('now')))
{
   // We're ending on today, so let's do our calculations of the start based on the current time vs just the current day.
   // This means that if we're showing 1 day, and it's currently 3:00pm we'll show 3:00pm yesterday through 3:00pm today, 
   // rather than 12:00am today through 3:00pm today

   $start_time = substr(strftime('%Y-%m-%d 00:00:00', strtotime($start_time." -1 day")), 0, 10)." ".(strftime('%H:%M:%S', strtotime("now")));

   // We'll round the start time to the next lowest half hour so that we've always got a full complement of 
   // values to display at the starting point (not just the setpoint and indoor temp/humidity)
   if (strftime('%M') > 30)
   {
      $new_minute = "30:00";
   }
   else
   {
      $new_minute = "00:00";
   }
   $start_time = substr($start_time, 0, 13).":".$new_minute;

   // Adjust $end_date, too
   $end_time = substr($to_date, 0, 10)." ".(strftime('%H:%M:%S', strtotime('now')));;

   // We might have moved the start time to the prior day, so also adjust $from_date
   $from_date = date('Y-m-d', strtotime($start_time));
}

$log->Info("gendata_daily: From_date: ".$from_date."  Start_time: ".$start_time."  End_time: ".$end_time);

// OK, now that we have a bounding range of dates, calculate how many days involved.
$date1 = new DateTime($from_date);
$date2 = new DateTime($to_date);
$dayCount = ($date2->diff($date1))->format('%a');

///
// Prepare all our sql statements
///

// sqlOne pulls all the indoor/outdoor temperature and humidity data.  Note this was once only on half hour boundaries but
// it can now happen more frequently if we've noticed a large enough change while doing the thermostat per-minute checks
$sqlOne = "SELECT tstat_uuid, date, indoor_temp, outdoor_temp, indoor_humidity, outdoor_humidity
         FROM {$dbConfig['table_prefix']}temperatures
         WHERE tstat_uuid = ?
                AND date BETWEEN ? AND ?
         ORDER BY date ASC";

if ($dayCount > 365)
{ 
   // Reduce data set to one data point every hour if we're charting more than one year
   $sqlOne = "SELECT tstat_uuid, date, indoor_temp, outdoor_temp, indoor_humidity, outdoor_humidity
              FROM {$dbConfig['table_prefix']}temperatures
              WHERE tstat_uuid = ?
                AND date BETWEEN ? AND ? 
                AND MINUTE(date) = 0
              ORDER BY date ASC";
}

$queryOne = $pdo->prepare($sqlOne);

// sqlTwo includes any hvac/fan cycle that ends or starts within the specified time window.
// Cycles that cross the left or right margins get truncated.
$sqlTwo = "SELECT system, start_time, end_time
           FROM {$dbConfig['table_prefix']}hvac_cycles
           WHERE end_time >= ? AND start_time <= ? AND tstat_uuid = ?
           ORDER BY start_time ASC";

$queryTwo = $pdo->prepare($sqlTwo);

// sqlThree is for any heat/cool/fan cycle that is currently running
$sqlThree = "SELECT heat_status, cool_status, fan_status, start_date_fan, start_date_cool, start_date_heat
             FROM {$dbConfig['table_prefix']}hvac_status
             WHERE tstat_uuid = ?";

$queryThree = $pdo->prepare($sqlThree);

// sqlFour 
$sqlFour = "SELECT id, set_point, mode, switch_time
            FROM {$dbConfig['table_prefix']}setpoints
            WHERE id = ?
               AND switch_time BETWEEN ? AND ?
            UNION ALL
            SELECT id, set_point, mode, switch_time
            FROM (
               SELECT *
               FROM {$dbConfig['table_prefix']}setpoints
               WHERE switch_time < ? AND
                  id = ? 
               ORDER BY switch_time DESC
               LIMIT 1
            ) AS one_before_start
            ORDER BY switch_time ASC";

$queryFour = $pdo->prepare($sqlFour);

// sqlFive gets all the times the schedule was overridden
$sqlFive = "SELECT id, start_time, end_time
         FROM {$dbConfig['table_prefix']}override
         WHERE id = ?
                AND ((start_time BETWEEN ? AND ?)
                 OR  (end_time   BETWEEN ? AND ?))
         ORDER BY start_time ASC";

$queryFive = $pdo->prepare($sqlFive);

// sqlSix gets all the times someone put a "hold" on the current setting
$sqlSix = "SELECT id, start_time, end_time
         FROM {$dbConfig['table_prefix']}hold
         WHERE id = ?
                AND ((start_time BETWEEN ? AND ?)
                 OR  (end_time   BETWEEN ? AND ?))
         ORDER BY start_time ASC";

$querySix = $pdo->prepare($sqlSix);

///
// Now that we prepared all the sql, let's execute!
///

// Execute sqlOne which handles all the temp/humidity data points from start to end
$log->Info("gendata_daily.php: Executing sqlOne with ($uuid, $start_time, $end_time)");

$queryOneResult = $queryOne->execute(array(
   $uuid,
   $start_time,
   $end_time
));

// Execute sqlTwo which includes any cycle that ends or starts within the specified time window.
// Cycles that cross the left or right margins get truncated.
$log->Info("gendata_daily.php: Executing sqlTwo with ($uuid, $start_time, $end_time)");

$queryTwoResult = $queryTwo->execute(array(
   $start_time,
   $end_time,
   $uuid
));

// Execute queryThree which catches only heat/cool/fan cycles that are currently going, so we'll add it onto the results
// from queryTwo later
$log->Info("gendata_daily.php: Executing sqlThree with ($uuid)");

$queryThreeResult = $queryThree->execute(array(
    $uuid
));
   
// sqlFour gets all the changes in the set temperature (point).  These are more rare
// than temperature changes so we don't bother reducing the query for long time periods
$log->Info("gendata_daily.php: Executing sqlFour ($sqlFour) for values $id, $start_time, $end_time");

$queryFourResult = $queryFour->execute(array(
   $id,
   $start_time,
   $end_time,
   $start_time,
   $id
));

// sqlFive queries schedule overrides.  These are also pretty rare, so no need to special case long periods
$log->Info("gendata_daily.php: Executing sqlFive ($sqlFive) for values $id, $start_time, $end_time");

$queryFiveResult = $queryFive->execute(array(
   $id,
   $start_time,
   $end_time,
   $start_time,
   $end_time,
));

// sqlSix queries temperature holds.  These are also pretty rare, so no need to special case long periods
$log->Info("gendata_daily.php: Executing sqlSix ($sqlSix) for values $id, $start_time, $end_time");

// Actually query the DB for hold information, we'll use it and the result later
$querySixResult = $querySix->execute(array(
   $id,
   $start_time,
   $end_time,
   $start_time,
   $end_time,
));

///
// All done executing sql, let's do something with the results!
///

$queryOneData   = array(); // Unused
$queryTwoData   = array();
$queryThreeData = array(); // Unused
$queryFourData  = array();
$queryFiveData  = array();
$querySixData   = array();

if ($queryTwoResult == true)
{
   while ($row = $queryTwo->fetch(PDO::FETCH_ASSOC))
   {
      $queryTwoData[] = $row;
   }
}
else
{
   // It's possible to not have any hvac activity in our time range, so we'll just silently skip any failures
   //  $log->Error("0 skipped due to query two results ".$queryTwoResult);
}

if ($queryFourResult == true)
{
   while ($row = $queryFour->fetch(PDO::FETCH_ASSOC))
   {
      $queryFourData[] = $row;
   }
}
else
{
   // It's possible to not have any setpoint info in the database, so we'll just silently skip any failures
   // $log->Error("0 skipped due to query for result: ".$queryFourResult);
}

if ($queryFiveResult == true)
{
   while ($row = $queryFive->fetch(PDO::FETCH_ASSOC))
   {
      $queryFiveData[] = $row;
   }
}
else
{
   // It's possible to not have any override info in the database, so we'll just silently skip any failures
   //    $log->Error("0 skipped due to query for result: ".$queryFiveResult);
   
}

if ($querySixResult == true)
{
   while ($row = $querySix->fetch(PDO::FETCH_ASSOC))
   {
      $querySixData[] = $row;
   }
}
else
{
   // It's possible to not have any hold info in the database, so we'll just silently skip any failures
   //    $log->Error("0 skipped due to query for result: ".$querySixResult);

}

   ///
   // Generate all the json files that we're feeding to our page to turn into echarts!
   ///

   // Generate all the indoor/outdoor temp/humidity data

   $data_for_echart = array();
   if ($queryOneResult == true)
   {
      // Each loop through we generate $x_col and then slap that onto the end of $data_for_echart
      while ($row = $queryOne->fetch(PDO::FETCH_ASSOC))
      {
         $x_col = array();
   
         // I probably should have used explicit indexes into $x_col
         $x_col[] = $row['date'];
         $x_col[] = strtotime($row['date']);
         $x_col[] = ($row['indoor_temp']  == 'VOID' ? null : $row['indoor_temp']);
         $x_col[] = ($row['outdoor_temp'] == 'VOID' ? null : $row['outdoor_temp']);
         $x_col[] = (($row['indoor_humidity']  == 'VOID' || $row['indoor_humidity']  == '-1.00') ? null : $row['indoor_humidity']);
         $x_col[] = (($row['outdoor_humidity'] == 'VOID' || $row['outdoor_humidity'] == '-1.00') ? null : $row['outdoor_humidity']);

         // I'm not sure this ever happens any more, but leaving it just in case as it can't hurt
         if (!$x_col[2] && !$x_col[3] && !$x_col[4] && !$x_col[5])
         {
            $log->Warning("Skipping the last point full of zeros");
         }
         else
         {
            $data_for_echart[] = array(
               "Text_Date"    => $x_col[0],
               "Label"        => $x_col[1],
               "Indoor_Temp"  => $x_col[2],
               "Outdoor_Temp" => $x_col[3],
               "Indoor_Hum"   => $x_col[4],
               "Outdoor_Hum"  => $x_col[5]
            );
         }
      }
   }
   else
   {
      // This MIGHT be legal if we really don't have anything in the table...?
      $log->Error("gendata_daily.php: queryOne failed: " . $queryOneResult);
   }

   $array_print = json_encode($data_for_echart);

   // The print_r version pretty prints it for debug, uncomment the next line if you want it
   //file_put_contents('tmp_data/Daily_temps_pp.txt', print_r($data_for_echart, true));

   file_put_contents('tmp_data/Daily_temps.txt', $array_print);


   // Generate the cycles data

   $data_for_echart = array();

   if ($queryTwoResult == true && count($queryTwoData) != 0)
   {
      foreach ($queryTwoData as $row)
      {
         $data_for_echart[] = array(
            "Cycle_Type"  => $row['system'],
            "Cycle_Start" => $row['start_time'],
            "Cycle_End"   => $row['end_time']
         );
      }
   }
   else
   {
      $log->Warning("queryTwo skipped due to query two results (no hvac cycles at all??)".$queryTwoResult." count ".count($queryTwoData));
   }

   // Generate the heat/cool/fan cycle data

   if ($queryThreeResult == true)
   {
      while ($row = $queryThree->fetch(PDO::FETCH_ASSOC))
      { 
         // Should be only one row!

         // If the Heat is on now
         if ($row['heat_status'] == 1)
         { 
            $heat_start_time = $row['start_date_heat'];
            $heat_end_time   = date("Y-m-d H:i", strtotime('now'));

            $data_for_echart[] = array(
               "Cycle_Type"  => 1, // Because 1 == 'heat' of course
               "Cycle_Start" => $heat_start_time,
               "Cycle_End"   => $heat_end_time
            );
         }

         // If the AC is on now
         if ($row['cool_status'] == 1)
         { 
            $cool_start_time = $row['start_date_cool'];
            $cool_end_time = date("Y-m-d H:i", strtotime('now'));

            $data_for_echart[] = array(
               "Cycle_Type"  => 2, // Because 2 == 'cool' of course
               "Cycle_Start" => $cool_start_time,
               "Cycle_End"   => $cool_end_time
            );
         }

         // If the fan is on now
         if ($row['fan_status'] == 1)
         { 
            $fan_start_time = $row['start_date_fan'];
            $fan_end_time = date("Y-m-d H:i", strtotime('now'));

            $data_for_echart[] = array(
               "Cycle_Type"  => 3,
               "Cycle_Start" => $fan_start_time,
               "Cycle_End"   => $fan_end_time
            );
         }
      }
   }
   else
   {
      $log->Info("queryThree skipped due to query three results (no hvac cycles today?)".$queryThreeResult);
   }

   // Write out the json for the heat/cool/fan periods 
   $array_print = json_encode($data_for_echart);

   // The print_r version pretty prints it for debug, uncomment the next line if you want it
   //file_put_contents('tmp_data/Daily_on_pp.txt', print_r($data_for_echart, true));

   file_put_contents('tmp_data/Daily_on.txt', $array_print);


   // Generate the "set point" data points

   $data_for_echart = [];

   if ($queryFourResult == true && count($queryFourData) != 0)
   {
      foreach ($queryFourData as $row)
      {
         // If setpoint is 0 it is probably because both heat and cool were turned off, so don't show a setpoint in that case
         $temp_sp = (($row['set_point'] == 0 && $row['mode'] == 0)? null : $row['set_point']);
         
         $data_for_echart[] = array(
            "Mode_Switch_Time" => $row['switch_time'],
            "Mode"             => $row['mode'],
            "Set_Temp"         => $temp_sp
         );
      }
   }
   else if ($queryFourResults == false)
   {
//       $log->Error("queryFourResult failure, skipped setpoint: ".$queryFourResult);
   }

   $array_print = json_encode($data_for_echart);

   // The print_r version pretty prints it for debug, uncomment the next line if you want it
   //file_put_contents('tmp_data/Daily_sp_pp.txt', print_r($data_for_echart, true));

   file_put_contents('tmp_data/Daily_sp.txt', $array_print);


   // Generate the "override" data points

   $data_for_echart = [];

   if ($queryFiveResult == true && count($queryFiveData) != 0)
   {
      foreach ($queryFiveData as $row)
      {
         $data_for_echart[] = array(
            "Override_Start_Time" => $row['start_time'],
            "Override_End_Time"   => $row['end_time'],
         );
      }
   }
   elseif ($queryFiveResult == false)
   {
//       $log->Error("queryFiveResult failure, skipped override: ".$queryFiveResult);
   }

   $array_print = json_encode($data_for_echart);

   // The print_r version pretty prints it for debug, uncomment the next line if you want it
   //file_put_contents('tmp_data/Daily_override_pp.txt', print_r($data_for_echart, true));

   file_put_contents('tmp_data/Daily_override.txt', $array_print);


   // Generate the "hold" data points

   $data_for_echart = [];

   if ($querySixResult == true && count($querySixData) != 0)
   {
      foreach ($querySixData as $row)
      {
         $data_for_echart[] = array(
            "Hold_Start_Time" => $row['start_time'],
            "Hold_End_Time"   => $row['end_time'],
         );
      }
   }
   elseif ($querySixResult == false)
   {
//       $log->Error("querySixResult failure, skipped override: ".$querySixResult);
   }

   $array_print = json_encode($data_for_echart);

   // The print_r version pretty prints it for debug, uncomment the next line if you want it
   //file_put_contents('tmp_data/Daily_hold_pp.txt', print_r($data_for_echart, true));

   file_put_contents('tmp_data/Daily_hold.txt', $array_print);
?>

