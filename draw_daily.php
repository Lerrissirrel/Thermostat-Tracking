<?php
$start_time = microtime(true);
require_once( 'common_chart.php' );
/**
	* If the user requests more than about 90 days it will take more than 30 seconds to render
	*	If it takes more than 30 seconds to render the chart package pukes.
	* Solve this perhaps by only getting one temperature per hour when span is 90+ days?
	*
	*/

$table_flag = false;
if( isset( $_GET['table_flag'] ) && $_GET['table_flag'] == 'true' )
{
	$table_flag = true;
}

$source = 2;	// Default to showing both
if( isset( $_GET['chart_daily_source'] ) )
{	// The "." character in the URL is somehow converted to an "_" character when PHP goes to look at it.
	$source = $_GET['chart_daily_source'];
}
if( $source < 0 || $source > 2 )
{ // If it is out of bounds, show both.  0: outdoor, 1: indoor, 2: both
	$source = 2;
}

$to_date = date( 'Y-m-d' );
if( isset( $_GET['chart_daily_toDate'] ) )
{ // Use provided date
	$to_date = $_GET['chart_daily_toDate'];
}
if( ! validate_date( $to_date ) ) return;
// Verify that date is not future?

$interval_measure = 0;	// Default to days
if( isset( $_GET['chart_daily_interval_group'] ) )
{
  $interval_measure = $_GET['chart_daily_interval_group'];
}
if( $interval_measure < 0 || $interval_measure > 3 )
{	// 0: days, 1: weeks, 2: months, 3: years
	$interval_measure = 0;
}

if( isset( $_GET['chart_daily_interval_length'] ) )
{
    $interval_length = $_GET['chart_daily_interval_length'];

	// Bounds checking
	if( $interval_length < 0 ) $interval_length = 1;
	if( $interval_length > 50 ) $interval_length = 21;
}

/* Get the width of the window so we can use it to scale the chart */
if ( isset( $_GET['chart_daily_width'] ))
{
    $window_width = $_GET['chart_daily_width'];
}
else
{
    $window_width = 1125; /* Default such that the % used below brings the chart to the original 900px */
}

/* Get the height of the window so we can use it to scale the chart */
if ( isset( $_GET['chart_daily_height'] ))
{
    $window_height = $_GET['chart_daily_height'];
}
else
{
    $window_height = 538; /* Default such that the % used below brings the chart to the original 430px */
}

/* These percentages were trial and error trying to fit as much as possible without causing scaling problem */
$chart_width = $window_width * .88;
$chart_height = $window_height * .62;
$chart_height = $window_height * .68;
              
$date_text = array( 0 => 'days', 1 => 'weeks', 2 => 'months', 3 => 'years' );
$interval_string = $to_date . ' -' . $interval_length . ' ' . $date_text[$interval_measure];

// Compute the "from date"
$from_date = date( 'Y-m-d', strtotime( $interval_string ) );

// There is the appearance of one extra day on every chart...
$from_date = date( 'Y-m-d', strtotime( "$from_date + 1 day" ) );

// Set default cycle display to none
$show_heat_cycles = (isset($_GET['chart_daily_showHeat']) && ($_GET['chart_daily_showHeat'] == 'false')) ? 0 : 1;
$show_cool_cycles = (isset($_GET['chart_daily_showCool']) && ($_GET['chart_daily_showCool'] == 'false')) ? 0 : 1;
$show_fan_cycles  = (isset($_GET['chart_daily_showFan'])  && ($_GET['chart_daily_showFan']  == 'false')) ? 0 : 1;

// Set default for displaying set point temp to "off"
$show_setpoint    = (isset($_GET['chart_daily_showSetpoint']) && ($_GET['chart_daily_showSetpoint']  == 'false')) ? 0 : 1;

// Set default humidity display to none
$show_indoor_humidity  = (isset($_GET['chart_daily_showIndoorHumidity']) && ($_GET['chart_daily_showIndoorHumidity'] == 'false')) ? 0 : 1;
$show_outdoor_humidity = (isset($_GET['chart_daily_showOutdoorHumidity']) && ($_GET['chart_daily_showOutdoorHumidity'] == 'false')) ? 0 : 1;

// OK, now that we have a bounding range of dates, build an array of all the dates in the range
$check_date = $from_date;
$days = array( $check_date );
$dayCount = 1;
while( $check_date != $to_date )
{
	$check_date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $check_date ) ) );
	array_push( $days, $check_date );
	$dayCount++;
}

/**
	*   The DB design for the project is still not as pretty as it could be.  The conversion to a 3 section system is starting though.
	* Section 1 has to do with the collection of data.  That is _mostly_ what is going on in there now.
	*           Check in \scripts for the processes that ADD data to the database.
	*
	* Section 2 will have to do with the presentation of the data in charts.  For instance that hvac_cycles table
	*           exists for two reasons.  Firstly it keeps the 'per minute' table lightweight and secondly it makes charting easier.
	*           If the application adds notifications (for instance power out or over temperature situations) that is reporting
	*           and will go here
	*           The new table time_index has been added to replace a really long nasty SQL section of hard-coded time stamps.  The
	*           table name ought to reflect the function. Perhaps should be renamed to chart_time_index?  And don't forget the
	*           global table name prefix either! (The name might look like thermo2__chart_time_index)
	*
	* Section 3 will be for the management of the website that presents the data.  If there will be a user registration system, the
	*           data for that will be stored in this set of tables.
	*
	*   The goal of this split of design is for two purposes.
	* Purpose 1 is for good MVC separation.  While ideological adherence to any design pattern is usually detrimental to real-world
	*           coding, patterns exist to make things easier to maintain in the long run.  Patterns are tools, use the ones that
	*           make life easy, discard the ones that are a PITA.
	*
	* Purpose 2 is for integration with other projects.  For instance the TED-5000 project also collects data and presents it.  The
	*           two projects can be used together and as such the data collection tables are unique to each project, but the website
	*           management are functionally identical and therefore when used together these tables should NOT be dupicated. In
	*           addition each project has it's own charting needs, but the combined charts will have constraints because of the shared
	*           presentation needs.
	*/

$sqlOne =
"SELECT CONCAT( ?, ' ', b.time ) AS date,
				IFNULL(a.indoor_temp, 'VOID') as indoor_temp,
				IFNULL(a.outdoor_temp, 'VOID') as outdoor_temp,
				IFNULL(a.indoor_humidity, 'VOID') as indoor_humidity,
				IFNULL(a.outdoor_humidity, 'VOID') as outdoor_humidity
 FROM {$dbConfig['table_prefix']}time_index b
 LEFT JOIN {$dbConfig['table_prefix']}temperatures a
 ON a.date = CONCAT( ?, ' ', b.time ) AND a.tstat_uuid = ? 
";

$sqlOneMore =
"SELECT CONCAT( ?, ' ', b.time ) AS date,
				IFNULL(a.indoor_temp, 'VOID') as indoor_temp,
				IFNULL(a.outdoor_temp, 'VOID') as outdoor_temp,
				IFNULL(a.indoor_humidity, 'VOID') as indoor_humidity,
				IFNULL(a.outdoor_humidity, 'VOID') as outdoor_humidity
 FROM {$dbConfig['table_prefix']}time_index b
 LEFT JOIN {$dbConfig['table_prefix']}temperatures a
 ON a.date = CONCAT( ?, ' ', '00:00:00' ) AND a.tstat_uuid = ? 
";

/*
$sqlOneMore =
"SELECT CONCAT ( ?, ' ', time_index.time ) AS date,
                           temperatures.indoor_temp, temperatures.outdoor_temp, 
                           temperatures.indoor_humidity, temperatures.outdoor_humidity
 FROM {$dbConfig['table_prefix']}time_index time_index
 LEFT JOIN {$dbConfig['table_prefix']}temperatures temperatures
 ON temperatures.date = CONCAT( ?, ' ', '00:00:00') AND temperatures.tstat_uuid = ? 
";
*/
$minutes = '30';

/** QQQ
if( $dayCount == 1 )
{
	$sqlOne .=
	"UNION
	SELECT ? AS date,
	IFNULL(a.indoor_temp, 'VOID') as indoor_temp,
	IFNULL(a.outdoor_temp, 'VOID') as outdoor_temp,
	IFNULL(a.indoor_humidity, 'VOID') as indoor_humidity,
	IFNULL(a.outdoor_humidity, 'VOID') as outdoor_humidity
	FROM thermo2__time_index b
	LEFT JOIN thermo2__temperatures a
	ON a.date = ? AND a.tstat_uuid = ?";
}
*/

if( $dayCount >= 70 )
{	// Reduce data set if there are more than 70 days.
	$sqlOne .= "WHERE SUBSTR( b.time, 3, 3 ) != ':30' ";
	$minutes = '60';	// Repeated setting is redundant, but it's better to keep this text change with the SQL change.
}
$queryOne = $pdo->prepare( $sqlOne );
$queryOneMore = $pdo->prepare( $sqlOneMore );

// Set default boundaries for chart
$chart_y_min = $normalLows[ date( 'n', strtotime($from_date) )-1 ];
$chart_y_max = $normalHighs[ date( 'n', strtotime($from_date) )-1 ];

use CpChart\Data;
use CpChart\Image;

if( ! $table_flag )
{ // Create, then populate the pData object (it expects to be presented as an img src=)
	$MyData = new Data();
}
else
{	// Start the tabular display
	echo '<link href="resources/thermo.css" rel="stylesheet" type="text/css" />';	// It expects to be presented in an iframe which does NOT inherit the parent css
	//echo "<br>Normal low for this month is $chart_y_min.";
	//echo "<br>Normal high for this month is $chart_y_max.";
	//echo "<br>The SQL<br>$sqlOne";
	echo '<table class="thermo_table"><th>Date</th>';
	if( $source == 1 || $source == 2 )
	{	// Indoor or both
		echo '<th>Indoor Temp</th>';
	}
	if( $source == 0 || $source == 2 )
	{	// Outdoor or both
		echo '<th>Outdoor Temp</th>';
	}
	if( $show_indoor_humidity == 1 )
	{
		echo '<th>Indoor Humidity</th>';
	}
	if( $show_outdoor_humidity == 1 )
	{
		echo '<th>Outdoor Humidity</th>';
	}
}

$dates = '';
$very_first = true;

$saved_string = VOID;	// Used to store the current X-axis' label until we tell pChart about it

//$log->logInfo( "draw_daily.php: sqlOne is ($sqlOne)" );	// Soooo wierd, this log line writes AFTER the completion log entry.
foreach( $days as $show_date )
{
	$dates .= $show_date . '   ';

    //		$oneMoreRow = strftime( '%Y-%m-%d 00:00:00', strtotime( "$show_date +1 days" ) );
    //		$queryOne->execute( array( $show_date, $show_date, $uuid, $oneMoreRow, $oneMoreRow, $uuid ) );
    $log->logInfo( "draw_daily.php: Executing sqlOne with ($show_date, $show_date, $uuid)" );
    $queryOne->execute( array( $show_date, $show_date, $uuid) );
    $show_onemore_date = strftime('%Y-%m-%d', strtotime( "$show_date +1 days" ));
    $queryOneMore->execute( array( $show_onemore_date, $show_onemore_date, $uuid) );

	$counter = 0;
	$first_row = true;

    // Keep track if we've hit the end of the data points.  Necessary due to not just being able to loop over one query
    // in the while() loop.
    $done = 0;
    
	while( $done == 0)
	{
        $row = $queryOne->fetch( PDO::FETCH_ASSOC );
        if ($row == FALSE)
        {
            // The first query for the whole day hit its end.  At most one more data point to get
            // from midnight
            $done = 1;

            // Check for a midnight data point
            $row = $queryOneMore->fetch( PDO::FETCH_ASSOC );
            
            if ($row == FALSE)
            {
                /* This is legal if, for instance, the day includes the current day */
                continue;
            }
        }
        
           /**
            * Chart of things that work for X-axis labels (work in progress to have optimal spacing)
			* days  divisor
			*  1		 $dayCount
			*  6		 $dayCount
			*  7		 6
			*  8		 6
			*  9		 8
			* 10		 8
			* 11		12 (date and noon)
			* 16		12
			* 17		24 (date only)
			* 31		24
			* 32		each week start date
			* 70 Change to every hour SELECT instead of every half hour SELECT (cuts data points in half!)
			* The charting software borks if the internal rendering time limit of 30 seconds is hit.
			* On my server, these limit happen around
			* ~75 days of every half-hour
			* ~80 days of hours
			* This crash is VERY is dependant upon server load...
			*/
		if( $dayCount > 13 ) $labelDivisor = 24;
		else if( $dayCount > 10 ) $labelDivisor = 12;
		else if( $dayCount >  8 ) $labelDivisor =  8;
		else if( $dayCount >  6 ) $labelDivisor =  6;
		else $labelDivisor = $dayCount;

//$log->logInfo( "draw_daily.php: Here is the data point to prove we got it {$row['date']}");	// So why isn't it showing up in the chart?

		if( ! $table_flag )
		{	// Only set X-Axis labels if we're displaying a chart
			if( $very_first )
			{	// Always show the first one - regardless of settings
				if( $dayCount < 6 )
				{	// Show time if we have only a few days.
					$saved_string = substr( $row['date'], 11, 5 );
				}
				else
				{	// Show date if we have a lot.
					$saved_string = substr( $row[ 'date' ], 5, 5 );
				}
                /* Oddly, the first label seems to be right under the Y-axis but you can't actually 
                   put a data point there, the data points only start one to the right.  So, using 
                   VOID for the first label allows midnight to be on the next point over, properly
                   aligning the times with the data points.
                */
                //                $MyData->addPoints( VOID, 'Labels' );
                //                $log->logError("first point x label: ".$saved_string);
                $MyData->addPoints( $saved_string, 'Labels' );
			}
            else
			{	/**
					* This seems pretty ugly, but pChart highlights a hash mark on the X axis whenever it finds the next point
					* in the abscissa array as being different than the previous one.  Using VOID for the value for those hash marks
					* you don't want to highlight (or get a grid line for) doesn't work since VOID is a valid value.  So you'd
					* get one more highlighted hash mark and a grid line just after the one you really wanted (the date or time) -
					* although it wouldn't actually SHOW anything because the value was "VOID".
					*
					* So, instead, for every hash mark that you don't want to highlight (or get a grid line for) just set it
					* to be the same as the previous hash mark's value.
					* -- Lerrissirrel
					*/
                //                $log->logError("Date: ".$row['date']);
				if( $dayCount <= 28 )
				{	// 13, 3 = minutes with colon (:MM), 11, 2 = two digit hour (HH)
					if( ( substr( $row['date'], 13, 3 ) == ':00' ) && ( (substr( $row['date'], 11, 2 ) % $labelDivisor) == 0 ) )
					{	// Only show axis every -interval- hours
						if( substr( $row['date'], 11, 2 ) == '00' ) // This is '00:mm' (and we already know that 'mm' is '00')
						{	// At midnight show the new date in MM-DD format
							// (How to add emphasis to distinguish from time stamps?)
							$saved_string = substr($row['date'], 5, 5);
						}
						else
						{	// Otherwise show the hour in HH:MM format
							$saved_string = substr($row['date'], 11, 5);
						}
					}
				}
				else
				{	// All other intervals...
					if( date_format( date_create( $row[ 'date' ]), 'N' ) == 7 )
					{ // Show the date only for the first day of each week in mm-dd format
						$saved_string = substr($row['date'], 5, 5);
					}
				}

				/** We may, or may not, have changed $saved_string, but if we didn't change it is is because we didn't
					* want to show a value for a particular point on the X axis - pChart detects that same value
					* and doesn't display anything (as opposed to VOID which is different than the previous value).
					* -- Lerrissirrel
					*/
				$MyData->addPoints( $saved_string, 'Labels' );
			}

			if( $source == 1 || $source == 2 )
			{	// Indoor or both
                if ($row['indoor_temp'] != VOID)
                {
                    $MyData->addPoints( ($row['indoor_temp'] == 'VOID' ? VOID : $row['indoor_temp']), 'Indoor Temp' );
                }
                //$log->logError( "draw_daily.php: Here is the data point {$row['date']} and {$row['indoor_temp']}");
			}
			if( $source == 0 || $source == 2 )
			{	// Outdoor or both
				$MyData->addPoints( ($row['outdoor_temp'] == 'VOID' ? VOID : $row['outdoor_temp']), 'Outdoor Temp' );
			}
			if( $show_setpoint == 1 )
			{	// Add a VOID point so we can get a legend for the Setpoint overlay
				$MyData->addPoints( VOID, 'Setpoint');
			}

			if( $show_indoor_humidity == 1 )
			{
                                $tmp_hum = $row['indoor_humidity'];
                                // -1 indicates "no such sensor" for the models that don't report it
                                if ($tmp_hum == '-1.00' || $tmp_hum == 'VOID')
                                {
                                   $tmp_hum = VOID; 
                                }
				$MyData->addPoints( $tmp_hum, 'Indoor Humidity' );
			}
			if( $show_outdoor_humidity == 1 )
			{
				$MyData->addPoints( ($row['outdoor_humidity'] == 'VOID' ? VOID : $row['outdoor_humidity']), 'Outdoor Humidity' );
			}

		}
		else
		{
			//echo '<tr><td>'.$row['date'].'</td><td>'.($row['indoor_temp'] == 'VOID' ? '&nbsp;' : $row['indoor_temp']).'</td><td>'.($row['outdoor_temp'] == 'VOID' ? '&nbsp;' : $row['outdoor_temp']).'</td></tr>';
			echo '<tr><td>'.$row['date'].'</td>';
			if( $source == 1 || $source == 2 )
			{	// Indoor or both
				echo '<td>'.($row['indoor_temp'] == 'VOID' ? '&nbsp;' : $row['indoor_temp']).'</td>';
			}
			if( $source == 0 || $source == 2 )
			{	// Outdoor or both
				echo '<td>'.($row['outdoor_temp'] == 'VOID' ? '&nbsp;' : $row['outdoor_temp']).'</td>';
			}
			if( $show_indoor_humidity == 1 )
			{
                                $tmp_hum = $row['indoor_humidity'];
                                if ($tmp_hum == 'VOID' || $tmp_hum == -1)
                                {
                                   $tmp_hum = '&nbsp;';
                                }
                                // -1 indicates "no such sensor" for the models that don't report it
//				echo '<td>'.($row['indoor_humidity'] == 'VOID' ? '&nbsp;' : $row['indoor_humidity']).'</td>';
				echo '<td>'.$tmp_hum.'</td>';
			}
			if( $show_outdoor_humidity == 1 )
			{
				echo '<td>'.($row['outdoor_humidity'] == 'VOID' ? '&nbsp;' : $row['outdoor_humidity']).'</td>';
			}
			echo '</tr>';
		}
		$very_first = false;

		/**
		  * Expand chart boundaries to contain data that exceeds the default boundaries
		  * 'VOID' values test poorly in inequality against numeric values so us 50 when the data is bad.
		  * Increment or decrement by ten to keep the chart boundaries pretty
			*/
		if( $source == 1 || $source == 2 )
		{	// Indoor or both
			while( ($row['indoor_temp'] == 'VOID' ? ($chart_y_min + $chartPaddingLimit) : $row['indoor_temp']) < $chart_y_min + $chartPaddingLimit )
            {
                $log->logInfo("min ".$chart_y_min." lowering due to indoor temp of: ".$row['indoor_temp']);
                $chart_y_min -= $chartPaddingSpace;
            }
			while( ($row['indoor_temp'] == 'VOID' ? ($chart_y_max - $chartPaddingLimit) : $row['indoor_temp']) > $chart_y_max - $chartPaddingLimit )
            {
                $log->logInfo("max ".$chart_y_max." raising due to indoor temp of: ".$row['indoor_temp']);
                $chart_y_max += $chartPaddingSpace;
            }
		}
		if( $source == 0 || $source == 2 )
		{	// Outdoor or both
			while( ($row['outdoor_temp'] == 'VOID' ? ($chart_y_min + $chartPaddingLimit) : $row['outdoor_temp']) < $chart_y_min + $chartPaddingLimit )
            {
                $log->logInfo("min ".$chart_y_min." lowering due to outdoor temp of: ".$row['outdoor_temp']);
                $chart_y_min -= $chartPaddingSpace;
            }
			while( ($row['outdoor_temp'] == 'VOID' ? ($chart_y_max - $chartPaddingLimit) : $row['outdoor_temp']) > $chart_y_max - $chartPaddingLimit )
            {
                $log->logInfo("max ".$chart_y_max." raising due to outdoor temp of: ".$row['outdoor_temp']);
                $chart_y_max += $chartPaddingSpace;
            }
		}
    }
}

if( ! $table_flag )
{	// Only set X-Axis labels if we're displaying a chart
	// Cram one more label on the very end to restore that last 30 minutes on the chart x-axis.
    //	$MyData->addPoints( $saved_string, 'Labels' );
    //  $log->logError( "draw_daily.php: 3 adding to AAA Label " . $saved_string);
}

// For a $show_date of '2012-07-10' get the start and end bounding datetimes
$start_date = strftime( '%Y-%m-%d 00:00:00', strtotime($from_date));	// "2012-07-10 00:00:00";
$end_date = strftime( '%Y-%m-%d 23:59:59', strtotime($to_date));			// "2012-07-10 23:59:59";

if( ($show_heat_cycles + $show_cool_cycles + $show_fan_cycles) > 0 )
{
  /**
		* This SQL includes any cycle that ends or starts within the specified time window.
		* Cycles that cross the left or right margins get truncated.
		*
		* Ought to graphically differentiate those open ended cycles somehow?
		*/
  $sqlTwo =
  "SELECT system,
					DATEDIFF( start_time, ? ) AS start_day,
					DATEDIFF( end_time, ? ) AS end_day,
          DATE_FORMAT( GREATEST( start_time, ? ), '%k' ) AS start_hour,
          TRIM(LEADING '0' FROM DATE_FORMAT( GREATEST( start_time, ? ), '%i' ) ) AS start_minute,
          DATE_FORMAT( LEAST( end_time, ? ), '%k' ) AS end_hour,
          TRIM( LEADING '0' FROM DATE_FORMAT( LEAST( end_time, ? ), '%i' ) ) AS end_minute
  FROM {$dbConfig['table_prefix']}hvac_cycles
  WHERE end_time >= ? AND start_time <= ? AND tstat_uuid = ?
  ORDER BY start_time ASC";

/*
echo "<br>sql is $sqlTwo";
echo "<br>start_date is $start_date";
echo "<br>end_date is $end_date";
echo "<br>uuid is $uuid";
*/
  $queryTwo = $pdo->prepare($sqlTwo);
  $result = $queryTwo->execute(array( $start_date, $start_date, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date, $uuid ) );

//$log->logInfo( "draw_daily.php: Executing sqlTwo ($sqlTwo) for values $start_date, $start_date, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date, $uuid" );

	$sqlThree = "SELECT heat_status
					,DATEDIFF( start_date_heat, ? ) AS start_day_heat
					,DATE_FORMAT( start_date_heat, '%k' ) AS start_hour_heat
					,TRIM(LEADING '0' FROM DATE_FORMAT( start_date_heat, '%i' ) ) AS start_minute_heat

					,cool_status
					,DATEDIFF( start_date_cool, ? ) AS start_day_cool
					,DATE_FORMAT( start_date_cool, '%k' ) AS start_hour_cool
					,TRIM(LEADING '0' FROM DATE_FORMAT( start_date_cool, '%i' ) ) AS start_minute_cool

					,fan_status
					,DATEDIFF( start_date_fan, ? ) AS start_day_fan
					,DATE_FORMAT( start_date_fan, '%k' ) AS start_hour_fan
					,TRIM(LEADING '0' FROM DATE_FORMAT( start_date_fan, '%i' ) ) AS start_minute_fan

					,DATEDIFF( date, ? ) AS end_day
					,DATE_FORMAT( date, '%k' ) AS end_hour
					,TRIM( LEADING '0' FROM DATE_FORMAT( date, '%i' ) ) AS end_minute

					FROM {$dbConfig['table_prefix']}hvac_status
					WHERE tstat_uuid = ?";

  $queryThree = $pdo->prepare($sqlThree);
  $result = $queryThree->execute(array( $from_date, $from_date, $from_date, $from_date, $uuid ) );

  //$log->logError( "draw_daily.php: Executing sqlThree ($sqlThree) for values $from_date, $from_date, $from_date, $from_date, $uuid" );

}

if( $show_setpoint == 1 )
{
	$sqlFour =
	"SELECT id, set_point, mode, switch_time
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
  $result = $queryFour->execute(array( $id, $start_date, $end_date, $start_date, $id ) );
//$log->logInfo( "draw_daily.php: Executing sqlFour ($sqlFour) for values $id, $start_date, $end_date, $start_date" );
	while( $row = $queryFour->fetch( PDO::FETCH_ASSOC ) )
	{
		$queryFourData[] = $row;
        
        while( (($row['set_point'] == 'VOID' || $row['set_point'] == 0)? ($chart_y_min + $chartPaddingLimit) : $row['set_point']) < $chart_y_min + $chartPaddingLimit )
        {
            $log->logInfo("min ".$chart_y_min." lowering due to setpoint of: ".$row['set_point']);
            $chart_y_min -= $chartPaddingSpace;
        }
        while( ($row['set_point'] == 'VOID' ? ($chart_y_min - $chartPaddingLimit) : $row['set_point']) > $chart_y_max - $chartPaddingLimit )
        {
            $log->logInfo("max ".$chart_y_max." raising due to setpoint of: ".$row['set_point']);
            $chart_y_max += $chartPaddingSpace;
        }
	}
}

if( $table_flag )
{	// If we're showing the data in a chart, we're done now.  Wrap up the table tag and press the eject button.
	echo '</table>';
	//echo "<br>Adjusted low for this month is $chart_y_min.";
	//echo "<br>Adjusted high for this month is $chart_y_max.";
	echo "Showing data every $minutes minutes for $dayCount days from $from_date to $to_date.";
	return;
}

// Attach the data series to the axis (by ordinal)
$MyData->setSerieOnAxis( 'Indoor Temp', 0 );
$MyData->setSerieOnAxis( 'Outdoor Temp', 0 );
$MyData->setSerieOnAxis( 'Setpoint', 0 );
if( $show_indoor_humidity == 1 )
{
	$MyData->setSerieOnAxis( 'Indoor Humidity', 1 );
	$MyData->setAxisPosition( 1, AXIS_POSITION_RIGHT );		// Draw runtime axis on right hand side
}
if( $show_outdoor_humidity == 1 )
{
	$MyData->setSerieOnAxis( 'Outdoor Humidity', 1 );
	$MyData->setAxisPosition( 1, AXIS_POSITION_RIGHT );		// Draw runtime axis on right hand side
}


// Set line style, color, and alpha blending level
$MyData->setSerieWeight( 'Indoor Temp', 1);
$MyData->setSerieTicks( 'Indoor Temp', 0 );  // 0 is a solid line
$serieSettings = array( 'R' => 50, 'G' => 150, 'B' => 80, 'Alpha' => 100 );
$MyData->setPalette( 'Indoor Temp', $serieSettings );

$MyData->setSerieTicks( 'Outdoor Temp', 2 ); // n is length in pixels of dashes in line
$serieSettings = array( 'R' => 150, 'G' => 50, 'B' => 80, 'Alpha' => 100 );
$MyData->setPalette( 'Outdoor Temp', $serieSettings );

$MyData->setSerieWeight( 'Setpoint', 1);
$MyData->setSerieTicks( 'Setpoint', 0 ); // This is only here to get the setpoint in the legend.  The lines are drawn with their own palette down below
$serieSettings = array( 'R' => 100, 'G' => 100, 'B' => 255, 'Alpha' => 60 );
$MyData->setPalette( 'Setpoint', $serieSettings );

if( $show_outdoor_humidity == 1 )
{
	$MyData->setSerieWeight( 'Outdoor Humidity', 1);
	$serieSettings = array( 'R' => 155, 'G' => 255, 'B' => 155, 'Alpha' => 60 );
	$MyData->setPalette( 'Outdoor Humidity', $serieSettings );
}
if( $show_indoor_humidity == 1 )
{
	$MyData->setSerieWeight( 'Indoor Humidity', 1);
	$serieSettings = array( 'R' => 75, 'G' => 200, 'B' => 75, 'Alpha' => 60 );
	$MyData->setPalette( 'Indoor Humidity', $serieSettings );
}

// Set names for Y-axis labels
$MyData->setAxisName( 0, 'Temperatures' );
if( $show_indoor_humidity + $show_outdoor_humidity > 0)
{
	$MyData->setAxisName( 1, 'Humidity' );
}

// Set names for X-axis labels
$MyData->setSerieDescription( 'Labels', 'The march of the hours' );
$MyData->setAbscissa( 'Labels' );

/**
	* Set variables for going into common block
	*/
if( $dayCount == 1 )
    $picTitle = "Show temperatures for $from_date";
else
    $picTitle = "Show temperatures for $from_date - $to_date ($dayCount days)";

$chartTitle = "Temperature every $minutes minutes across the span of dates";
// Explicity set a scale for the drawing.
if( ($show_indoor_humidity + $show_outdoor_humidity) == 0 )
{
	$AxisBoundaries = array( 0 => array ( 'Min' => $chart_y_min, 'Max' => $chart_y_max ) );
}
else
{
        $log->logInfo("Axis y min and max: ".$chart_y_min." x ".$chart_y_max);
	$AxisBoundaries = array( 0 => array ( 'Min' => $chart_y_min, 'Max' => $chart_y_max ), 1 => array( 'Min' => 0, 'Max' => 100 ) );
}

/**
	* START of common block - this code should be identical for all charts so that they have a common look and feel
	*/
$myPicture = new Image( $chart_width, $chart_height, $MyData );	// Create the pChart object

$myPicture->Antialias = FALSE;								// Turn OFF Antialiasing (it draws faster)

// Draw the background - puts some diagonal hashes in there - odd
//$Settings = array( 'R' => 255, 'G' => 255, 'B' => 255, 'Dash' => 1, 'DashR' => 1, 'DashG' => 1, 'DashB' => 1, 'Alpha' => 10 );
//$myPicture->drawFilledRectangle( 0, 0, 900, 430, $Settings );

// Overlay with a gradient

//$Settings = array( 'StartR' => 219, 'StartG' => 231, 'StartB' => 139, 'EndR' => 1, 'EndG' => 138, 'EndB' => 68, 'Alpha' => 50 );
//$myPicture->drawGradientArea( 0, 0, 900, 430, DIRECTION_VERTICAL, $Settings );
$Settings = array( 'StartR' => 0, 'StartG' => 0, 'StartB' => 0, 'EndR' => 100, 'EndG' => 100, 'EndB' => 100, 'Alpha' => 80 );
$myPicture->drawGradientArea( 0, 0, $chart_width,	20, DIRECTION_VERTICAL, $Settings );
// Overlay with a gradient (start X, start Y, width, height)
//$Settings = array( 'StartR' => 219, 'StartG' => 231, 'StartB' => 139, 'EndR' => 50, 'EndG' => 200, 'EndB' => 120, 'Alpha' => 60 );
$Settings = array( 'StartR' => 219, 'StartG' => 219, 'StartB' => 219, 'EndR' => 200, 'EndG' => 255, 'EndB' => 180, 'Alpha' => 60 );
$myPicture->drawGradientArea( 0, 21, $chart_width, $chart_height, DIRECTION_VERTICAL, $Settings );

// Add a border to the picture
$myPicture->drawRectangle( 0, 0, $chart_width - 1, $chart_height - 1, array( 'R' => 0, 'G' => 0, 'B' => 0 ) );

// Set font for all descriptive text
$myPicture->setFontProperties( array( 'FontName' => 'Copperplate_Gothic_Light.ttf', 'FontSize' => 10 ) );

// Define the chart area
/* .06667 and .1395 are the ratios of the original fixed sized graph to chart area */
$graphAreaStartX = .066667 * $chart_width;
$graphAreaStartX = .026667 * $chart_width;
$graphAreaEndX   = $chart_width - $graphAreaStartX;
$graphAreaStartY = .1395 * $chart_height;
$graphAreaEndY   = $chart_height - $graphAreaStartY;

// Write picture and chart titles
$myPicture->drawText( 10, 16, $picTitle, array( 'R' => 255, 'G' => 255, 'B' => 255) );
//$myPicture->drawText( 60, 55, $chartTitle, array( 'FontSize' => 12, 'Align' => TEXT_ALIGN_BOTTOMLEFT ) );
$myPicture->drawText( $graphAreaStartX, $graphAreaStartY - 2, $chartTitle, array( 'FontSize' => ($graphAreaEndY-$graphAreaStartY)/28 /*12*/, 'Align' => TEXT_ALIGN_BOTTOMLEFT ) );

// Write the picture timestamp
// Given a fixed font size, always start 225 pixels from the right side of the chart, 13 pixels vertical lines it up with the $picTitle
$myPicture->drawText( $chart_width - 225, 15, 'Last update ' . date( 'Y-m-d H:i' ), array( 'R' => 255, 'G' => 255, 'B' => 255) );

$myPicture->setGraphArea( $graphAreaStartX, $graphAreaStartY, $graphAreaEndX, $graphAreaEndY );

// Draw the scale
$myPicture->setFontProperties( array( 'FontName' => 'pf_arma_five.ttf', 'FontSize' => 6 ) );
//$scaleSettings = array( 'Mode' => SCALE_MODE_MANUAL, 'ManualScale' => $AxisBoundaries, 'GridR' => 200, 'GridG' => 200, 'GridB' => 200, 'LabelingMethod' => LABELING_DIFFERENT, 'DrawSubTicks' => TRUE, 'CycleBackground' => TRUE, 'XMargin' => 0,'YMargin' => 0,'Floating' => TRUE );

// This draws some manual horizontal grid lines based on the temperature (left) axis.  If you use the automatic gridding
// the fact that the major points on the two Y axis don't line up causes visual confusion
for ($temp = $chart_y_min; $temp<= $chart_y_max; $temp+=10)
{
    $myPicture->drawThreshold( $temp, array( 'R' => 0, 'G' => 0, 'B' => 0, 'Ticks' => 1, 'Alpha' => 20) );
}

// Leaving a note here, since it took me forever to figure out, that 'XMargin' allows to change the padding between
// the actual graph and the Y axis
// The default seems to be, what works out to, a 15 minute pad on either side as far as the $PixelsPerMinute calculation goes
$chartXMarginMins = 15;
//$scaleSettings = array('DrawXLines' => FALSE, 'DrawYLines' => NONE, 'Mode' => SCALE_MODE_MANUAL, 'ManualScale' => $AxisBoundaries, 'GridR' => 5, 'GridG' => 5, 'GridB' => 5, 'LabelingMethod' => LABELING_DIFFERENT, 'DrawSubTicks' => FALSE, 'CycleBackground' => FALSE, 'GridTicks' => 0, 'GridAlpha' => 5);
$scaleSettings = array('MinDivHeight' => 20, 'Factors' => [5, 10, 20], 'DrawXLines' => TRUE, 'DrawYLines' => FALSE, 'Mode' => SCALE_MODE_MANUAL, 'ManualScale' => $AxisBoundaries, 'GridR' => 5, 'GridG' => 5, 'GridB' => 5, 'LabelingMethod' => LABELING_DIFFERENT, 'DrawSubTicks' => FALSE, 'CycleBackground' => FALSE, 'GridTicks' => 0, 'GridAlpha' => 5);
/* With two Y axis (axes?) I can't find any way to make this shading look nice because the ticks don't line up between left
   and right.  Maybe we can get the scales to match and then add the shading back in? */
/*
               'BackgroundR1' => 255, 'BackgroundG1' => 0, 'BackgroundB1' => 0, 'BackgroundAlpha1' => 30,
               'BackgroundR2' =>   0, 'BackgroundG2' =>   0, 'BackgroundB2' =>  255, 'BackgroundAlpha2' =>  0 );
*/
//        $log->logError("b min and max: ".$AxisBoundaries[0]['Min']." ".$AxisBoundaries[0]['Max']);
$myPicture->drawScale( $scaleSettings );
//        $log->logError("a min and max: ".$scaleSettings["Axis"][0]["Min"]." ".$scaleSettings["Axis"][0]["Max"]);

// Write the chart legend
$myPicture->setFontProperties( array( 'FontName' => 'pf_arma_five.ttf', 'FontSize' => 6 ) );
$myPicture->setShadow( TRUE, array( 'X' => 1, 'Y' => 1, 'R' => 0, 'G' => 0, 'B' => 0, 'Alpha' => 0 /*10*/ ) );
$myPicture->drawLegend( $chart_width * .06667, $chart_height - 18, array( 'Style' => LEGEND_NOBORDER, 'Mode' => LEGEND_HORIZONTAL ) );
// END of common block


// Draw the chart(s)
$myPicture->setShadow( TRUE, array( 'X' => 1, 'Y' => 1, 'R' => 0, 'G' => 0, 'B' => 0, 'Alpha' => 40 ) );	// Define shadows under series lines
$myPicture->drawLineChart( array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO ) );
$myPicture->setShadow( FALSE );		// No more shadows (so they only apply to the lines)


/**
	* After the chart is created, prepare the overlays.  I draw these manually because I can't
	*  find a horizontal 'stacked' bar chart that allows missing pieces in it in pChart.
	*/

/* The nice thing about this calculation is that it automatically scales! */
/* The 2*$chartXMarginMins is due to there being a default pad on both the left and right sides of the graph area */
/* so even though we don't draw in those areas, they need to be part of the calculation to position things right */
/* pChart's default seems to be 15 minutes.  Any other change to $scaleSettings XMargin would be in pixels rather */
/* than in minute */

$PixelsPerMinute = (($graphAreaEndX - $graphAreaStartX) / (1440+(2*$chartXMarginMins))) / $dayCount;  // = 0.54861 (for dayCount = 1)
$chartXMarginPx  = $chartXMarginMins * $PixelsPerMinute;
// $graphXLastDataPoint = $graphAreaEndX - 30 * $PixelsPerMinute; // this is the number of pixels, to the right of the graph area left margin, to where the last temperature point will be displayed (11:30pm).  Ideally we should be able to display the midnight point, too.  But until then, I've stopped some things from drawing past this point for aesthetic reasons.  
$graphXLastDataPoint = $graphAreaEndX - 1; // this is the number of pixels, to the right of the graph area left margin, to where the last temperature point will be displayed (12:59pm).  Since we don't display the last points for the last day in the display window (they are shown as the first points of the next day), it looks a bit odd to have some other things display in that last half hour (like the cycles and setpoint).  The "-1" is so it doesn't overlap the vertical block line on the right hand y-axis

/**
	* Assumptions:
	*  1. The chart X-axis represents 24 hours
	*  2. The graph horizontal area (i.e. graph area) is 790 pixels wide (so each pixel represents 1.82 minutes)
	*
	* Why 0.54861?  (when dayCount is 1)
	* The chart area boundary is defined as 790px wide (850px - 60px start position).
	* 1440 is the number of minutes in a day.
	* $dayCount is the number of days that will be charted
	* ((850 - 60) / 1440) / 1
	* 790px / 1440 pixels/day = .54861 pixels per minute
	*
	* The $dayCount factor was added to account for the number of days in the display.  Too many days and the display will be really ugly
	*
	* Cycle data is represented by drawing objects, so it has to be AFTER the creation of $myPicture
	*/

// Positions are relative to the charting area rather than graphing area. Start at $chartXMarginMins off the y-axis
$LeftMargin = $graphAreaStartX + ($chartXMarginMins * $PixelsPerMinute);
$RightMargin = $graphAreaEndX - ($chartXMarginMins * $PixelsPerMinute);

if( ($show_heat_cycles + $show_cool_cycles + $show_fan_cycles) > 0 )
{	// The SQL has already been executed.  Now just draw it.

   // As stated above, it would be nice to adjust the gradient to take into account cycles that started before, or end after, the display window.  In theory the math could be worked out such that the start (or end) color in the gradient is what would be if the whole gradient was drawn.

  // The rounded corners look so much better, but the run times are so relatively short that the rounds seldom appear.
  $HeatGradientSettings = array( 'StartR' => 150, 'StartG' =>  50, 'StartB' =>  80, 'Alpha' => 65, 'Levels' => 90, 'BorderR' =>  140, 'BorderG' =>  40, 'BorderB' =>  70 );
  $HeatGradientSettings_left  = array( 'StartR' => 200, 'StartG' => 150, 'StartB' => 150, 'Alpha' => 20, 'EndR' => 150, 'EndG' =>  10, 'EndB' => 10);
  $HeatGradientSettings_right = array( 'StartR' => 150, 'StartG' =>  10, 'StartB' =>  10, 'Alpha' => 20, 'EndR' => 200, 'EndG' => 150, 'EndB' => 150);

  $CoolGradientSettings = array( 'StartR' =>  50, 'StartG' => 150, 'StartB' => 180, 'Alpha' => 65, 'Levels' => 90, 'BorderR' =>   40, 'BorderG' => 140, 'BorderB' => 170 );
  $CoolGradientSettings_left  = array( 'StartR' => 140, 'StartG' => 140, 'StartB' => 250, 'Alpha' => 20, 'EndR' =>  30, 'EndG' =>  30, 'EndB' => 200);
  $CoolGradientSettings_right = array( 'StartR' =>  30, 'StartG' =>  30, 'StartB' => 200, 'Alpha' => 20, 'EndR' => 140, 'EndG' => 140, 'EndB' => 250);

  $FanGradientSettings  = array( 'StartR' => 235, 'StartG' => 235, 'StartB' =>   0, 'Alpha' => 65, 'Levels' => 90, 'BorderR' =>  255, 'BorderG' => 255, 'BorderB' =>   0 );
  $FanGradientSettings_left   = array( 'StartR' => 255, 'StartG' => 255, 'StartB' =>  90, 'Alpha' => 20, 'EndR' => 255, 'EndG' => 255, 'EndB' => 0);
  $FanGradientSettings_right  = array( 'StartR' => 255, 'StartG' => 255, 'StartB' =>   0, 'Alpha' => 20, 'EndR' => 255, 'EndG' => 255, 'EndB' => 90);

  /* These defaults will scale with size of the window, and keep three rows, heat, fan, cool top to bottom */
  // These strange fractional values translate to the original fixed height values.
  //  $RectHeight  = .046 * $chart_height;
  //  $HeatRectRow = .349 * $chart_height;
  //  $FanRectRow  = .407 * $chart_height; /* I've moved the fan in between the heat and cool so it's always next to whichever is on
  //  $CoolRectRow = .465 * $chart_height;


  /* Personally, I like the half chart height for heat/cool and fan.  Generally the heat and cool won't come on at the same time */
  /* I find it makes it easier to see what's going on when you have a large date range (or, for me, any range size) */
  /* Lerrissirrel */
  $RectHeight  = ($graphAreaEndY - $graphAreaStartY ) / 2;
  $HeatRectRow = $graphAreaStartY;
  $CoolRectRow = $graphAreaStartY;
  $FanRectRow  = $HeatRectRow + $RectHeight;

//echo "<table border='1'>";
  /* Keep track of whether we've already gone off the right edge of the graph area for each possible row - heat, fan, cool */
  $already_off_x_axis = array(0, 0, 0, 0);
  while( $row = $queryTwo->fetch( PDO::FETCH_ASSOC ) )
  {
/*
echo '<tr>';
foreach($row as $cell)echo "<td>$cell</td>";
echo '</tr>';
*/
	
      /* Check we already went off the right edge of the screen for this one (heat/cool/fan) and go to next row if we did */
      if ($already_off_x_axis[$row['system']] != 0)
      {
          $log->logError("heat/cold/fan found another row but were already off the right side of the graph.  Should not happen!");
          continue;
      }
      // 'YYYY-MM-DD HH:mm:00'  There are NO seconds in these data points.
      
      /* Something is wonky with the start_minute being NULL sometimes.  It seems that maybe it was supposed to be 0 but */
      /* is somehow getting set to NULL in the database */
      $start_minute = $row['start_minute'];
      if ($row['start_minute'] == NULL || !isset($row['start_minute']))
      {
          /* This seems to happen when a gradient starts the prior day */
          /* So assume the start is midnight */
          $log->logWarn("Start minute was NULL");
          $start_minute = 0;
      }
      
      /* Just a sanity check to see if the start day or hour ever end up as NULL */
      if ($row['start_day'] == NULL || $row['start_hour'] == NULL)
      {
          $log->logError("Start day or hour was NULL. Start day hour min *".$row['start_day']."*".$row['start_hour']."*".$row['start_minute']."*");
          $cycle_start = $LeftMargin;
      }

      $cycle_start = $LeftMargin + ((($row['start_day'] * 1440) + ($row['start_hour'] * 60) + $start_minute ) * $PixelsPerMinute);
      
      /* Something is wonky with the end_minute being NULL sometimes.  It seems that maybe it was supposed to be 0 but */
      /* is somehow getting set to NULL in the database */
      $end_minute = $row['end_minute'];
      if ($row['end_minute'] == NULL)
      {
          /* This seems to happen when a gradient ends the next day */
          /* So assume the end is midnight */
          $log->logWarn("End minute was NULL");
          $end_minute = 0;
      }
      /* Just a sanity check to see if the end day or hour ever end up as NULL */
      if ($row['end_day'] == NULL || $row['end_hour'] == NULL)
      {
//          $log->logError("0 end   day or hour was NULL");
          $log->logError("0 isset end day hour min ".isset($row['end_day'])."*".isset($row['end_hour'])."*".isset($row['end_minute']));
//          $log->logError("0 end   day hour min *".$row['end_day']."*".$row['end_hour']."*".$row['end_minute']."*");
      }

      $cycle_end   = $LeftMargin + ((($row['end_day']   * 1440) + ($row['end_hour']   * 60) + $end_minute )   * $PixelsPerMinute);

      // If this cycle started the day before the display window, we want to start drawing it at 00:00
      if ($cycle_start < $LeftMargin)
      {
          $cycle_start = $LeftMargin;
      }
      
      // If this cycle ends past our display window, we want to clip it at the right most data point
      if ($cycle_end > $graphXLastDataPoint)
      {
          // since we had to truncate a line, we know that there should be no more rows to deal with after this
          $already_off_x_axis[$row['system']] = 1;
          
          $cycle_end = $graphXLastDataPoint;
      }


      /* Did the calculations somehow give us a start later than the end? */
      if ($cycle_start > $cycle_end)
      {
          // since our end turned out before our beginning, we've clearly gone off the deep end so just bail */
          $already_off_x_axis[$row['system']] = 1;
          $log->logWarn("heat/cold/fan start s later than the end!");
          continue;
      }
    
      if( $row['system'] == 1 && $show_heat_cycles == 1 )
      { // Heat
          //        $myPicture->drawGradientArea( $cycle_start, $HeatRectRow, $cycle_end, $HeatRectRow + $RectHeight, DIRECTION_HORIZONTAL, $HeatGradientSettings );
          $myPicture->drawGradientArea( $cycle_start, $HeatRectRow, intval($cycle_end - ($cycle_end - $cycle_start)/2), $HeatRectRow + $RectHeight, DIRECTION_HORIZONTAL, $HeatGradientSettings_left );
          $myPicture->drawGradientArea( intval($cycle_end - ($cycle_end - $cycle_start)/2) + 1, $HeatRectRow, $cycle_end, $HeatRectRow + $RectHeight, DIRECTION_HORIZONTAL, $HeatGradientSettings_right );
          
    }
    else if( $row['system'] == 2 && $show_cool_cycles == 1 )
    { // A/C
        //$myPicture->drawGradientArea( $cycle_start, $CoolRectRow, $cycle_end, $CoolRectRow + $RectHeight, DIRECTION_HORIZONTAL, $CoolGradientSettings );
        $myPicture->drawGradientArea( $cycle_start, $CoolRectRow, intval($cycle_end - ($cycle_end - $cycle_start)/2), $CoolRectRow + $RectHeight, DIRECTION_HORIZONTAL, $CoolGradientSettings_left );
        $myPicture->drawGradientArea( intval($cycle_end - ($cycle_end - $cycle_start)/2) + 1, $CoolRectRow, $cycle_end, $CoolRectRow + $RectHeight, DIRECTION_HORIZONTAL, $CoolGradientSettings_right );
    }
    else if( $row['system']== 3 && $show_fan_cycles == 1 )
    { // Fan
        //        $myPicture->drawGradientArea( $cycle_start, $FanRectRow, $cycle_end, $FanRectRow + $RectHeight, DIRECTION_HORIZONTAL, $FanGradientSettings );
        $myPicture->drawGradientArea( $cycle_start, $FanRectRow, intval($cycle_end - ($cycle_end - $cycle_start)/2), $FanRectRow + $RectHeight, DIRECTION_HORIZONTAL, $FanGradientSettings_left );
        $myPicture->drawGradientArea( intval($cycle_end - ($cycle_end - $cycle_start)/2) + 1, $FanRectRow, $cycle_end, $FanRectRow + $RectHeight, DIRECTION_HORIZONTAL, $FanGradientSettings_right );

    }
  }
//echo "</table>";

	// Now draw boxes for a presently running heat/cool/fan sessions.

  while( $row = $queryThree->fetch( PDO::FETCH_ASSOC ) )
  {	// Should be only one row!

      $log->logInfo("Last gradient ".$row['heat_status']." end ".$row['end_day']." ".$row['end_hour']." ".$row['end_minute']);

      /* If the end day is not 0 then it means that it is not for today and we should not draw it. */
      /* Something funky here when we're displaying the past and our last gradient goes into the next day */
      /* not on the current chart.  Bail if that happens */
      if ($row['end_day'] != 0)
      {
          continue;
      }
      
      /* Last heat gradient */
      if( $row['heat_status'] == 1 && $show_heat_cycles == 1 )
      {	// If the Heat is on now AND we want to draw it
          
          $start_day_heat = $row['start_day_heat'];
          if ($row['start_day_heat'] < 0)
          {
              /* If the heat carried over from the day prior to the graph, start at midnight */
              $start_day_heat = 0;
          }
          
          $cycle_start = $LeftMargin + (($start_day_heat   * 1440) + ($row['start_hour_heat'] * 60) + $row['start_minute_heat'] ) * $PixelsPerMinute;
          $cycle_end   = $LeftMargin + (($row['end_day']   * 1440) + ($row['end_hour']   * 60) + $row['end_minute']  )   * $PixelsPerMinute;
          
          $log->logInfo("heat start day hour min ".$start_day_heat." ".$row['start_hour_heat']." ".$row['start_minute_heat']);
          
          // If this cycle started the day before the display window, we want to start drawing it at 00:00
          if ($cycle_start < $LeftMargin)
          {
              $cycle_start = $LeftMargin;
          }
          // If this cycle ends past our display window, we want to clip it at the right most data point
          if ($cycle_end > $graphXLastDataPoint)
          {
              $cycle_end = $graphXLastDataPoint;
          }
          
          /* We don't know when this is going to end, so we can't fade out again, so just do the whole thing fading in */
          $myPicture->drawGradientArea( $cycle_start, $HeatRectRow, $cycle_end, $HeatRectRow + $RectHeight, DIRECTION_HORIZONTAL, $HeatGradientSettings_left );
      }
      
      /* Last cool gradient */
      if( $row['cool_status'] == 1 && $show_cool_cycles == 1 )
      {	// If the AC is on now AND we want to draw it

          $start_day_cool = $row['start_day_cool'];
          if ($row['start_day_cool'] < 0)
          {
              /* If the cool carried over from the day prior to the graph, start at midnight */
              $start_day_cool = 0;
          }
          
          $cycle_start = $LeftMargin + (($row['start_day_cool'] * 1440) + ($row['start_hour_cool'] * 60) + $row['start_minute_cool']) * $PixelsPerMinute;
          $cycle_end   = $LeftMargin + (($row['end_day']   * 1440) + ($row['end_hour']   * 60) + $row['end_minute'] )   * $PixelsPerMinute;
          
          $log->logInfo("Last cool start day hour min ".$row['start_day_cool']." ".$row['start_hour_cool']." ".$row['start_minute_cool']);
          
          // If this cycle started the day before the display window, we want to start drawing it at 00:00
          if ($cycle_start < $LeftMargin)
          {
              $cycle_start = $LeftMargin;
          }
          // If this cycle ends past our display window, we want to clip it at the right most data point
          if ($cycle_end > $graphXLastDataPoint)
          {
              $cycle_end = $graphXLastDataPoint;
          }
          
          /* We don't know when this is going to end, so we can't fade out again, so just do the whole thing fading in */
          $myPicture->drawGradientArea( $cycle_start, $CoolRectRow, $cycle_end, $CoolRectRow + $RectHeight, DIRECTION_HORIZONTAL, $CoolGradientSettings_left );
      }
      
      /* Last fan gradient */
      if( $row['fan_status'] == 1 && $show_fan_cycles == 1 )
      {	// If the fan is on now AND we want to draw it
          
          $start_day_fan = $row['start_day_fan'];
          if ($row['start_day_fan'] < 0)
          {
              /* If the fan carried over from the day prior to the graph, start at midnight */
              $start_day_cool = 0;
          }
          
          $cycle_start = $LeftMargin + (($row['start_day_fan'] * 1440) + ($row['start_hour_fan'] * 60) + $row['start_minute_fan']) * $PixelsPerMinute;
          $cycle_end   = $LeftMargin + (($row['end_day']   * 1440) + ($row['end_hour']   * 60) + $row['end_minute'] )   * $PixelsPerMinute;
          
          $log->logInfo("Last Fan start day hour min ".$row['start_day_fan']." ".$row['start_hour_fan']." ".$row['start_minute_fan']);
          
          // If this cycle started the day before the display window, we want to start drawing it at 00:00
          if ($cycle_start < $LeftMargin)
          {
              $cycle_start = $LeftMargin;
          }
          // If this cycle ends past our display window, we want to clip it at the right most data point
          if ($cycle_end > $graphXLastDataPoint)
          {
              $cycle_end = $graphXLastDataPoint;
          }
          
          /* We don't know when this is going to end, so we can't fade out again, so just do the whole thing fading in */
          $myPicture->drawGradientArea( $cycle_start, $FanRectRow, $cycle_end, $FanRectRow + $RectHeight, DIRECTION_HORIZONTAL, $FanGradientSettings_left );
      }
  }
}

/* This section is for drawing the set point which is unique as compared to the gradients and temp/humidity lines */
    
if( $show_setpoint == 1 )
{
    // The setpoint_scale is "Minutes per pixel", so PixelsPerMinute is just the inverse.
    $setpoint_scale = ($chart_y_max - $chart_y_min) / ($graphAreaEndY - $graphAreaStartY);
    $PixelsPerDegree = 1/$setpoint_scale;
    
    // Since the Y pixel coordinates go from top to bottom (the 0,0 coordinate is the top left), it makes most of
    // this math kind of ugly.  $TempBasePx is intended to easily allow you to just subtract your
    // (temperature * PixelsPerMinute) to get the y coordinate of your temperature
    $TempBasePx = $graphAreaEndY + ($chart_y_min * $PixelsPerDegree);
//    $log->logError("range: ".($chart_y_max - $chart_y_min)."PixelsPerDegree: ".$PixelsPerDegree." y_min: ".$chart_y_min." y_max: ".$chart_y_max." Graph Y End: ".$graphAreaEndY." Graph Y Start: ".$graphAreaStartY);

	$first_row = 1;

	// Used for a sanity check if we've already drawn off the right side of the display, don't bother with any more data points that came back in the query
	$already_off_x_axis = 0; 

    foreach( $queryFourData as $row )
	{
        // This allows some other functions to work if you want them, like writeBounds(), even though we
        // don't actually use these points to draw anything
        $MyData->addPoints( $row['set_point'], 'Setpoint' );
        
        /*** The query returns one row prior to the current date range so that
         *** we can determine the setpoint leading into the first drawn day
         *** This falls apart currently if there is not a setpoint for the prior day
         *** but there should always be one unless the database table is just starting
         *** to become populated with data.
         ***/

	   // If we already tried to draw a line that went off the right side of the graph, don't bother
	   // with any more rows.  We should never be looking at an additional row when the previous row
	   // was already in the next day!
	   if ($already_off_x_axis == 1)
	   {
	      $log->logError("Found another row but we're already off the right side of the graph.  Should not happen!");
	      continue;
	   }
       
       // Get the first set point from our query.  Under normal circumstances, this will be the last set point 
	   // from the day just before our "from date".  However, it can also be a point from within the current 
	   // display date range and if so, we have some special handling down below
	   if( $first_row == 1 )
	   {
           
	      if ($row['id'] != $id)
	      {
              // Probably not needed any more.  The inital code for the set point mechanism had a problem with the setpoint 
              // query when there were multiple thermostats.  
              // It sometimes picked a "previous point" which was the immediate prior change timewise, but for a different thermostat.
              $log->logError("No previous set point.  Starting with the first one instead. Expected id: ".$id." actual: ".$row['id']);
              continue;
	      }

	      // We're handling the first row right now, so for the next loop, we're not the first row anymore
	      $first_row = 0;

          // The prev_setpoint helps us know where to start drawing the line when we're handling what will be the
          // "current" set point when we get to the next one.
	      $prev_setpoint = $row['set_point'];
	      $switch_time = date_create($row['switch_time']);
//              $log->logError("Switch point: ".$prev_setpoint." at ".$switch_time->format('d')." ".$switch_time->format('h')." ".$switch_time->format('i') );
          // Under normal circumstances, we're really "continuing" a set point from a point off the left of the graph
          // so let's assume that to start.
	      $prev_switch_time = date_create( $from_date );
//       $log->logError("Mode 0 = ".$prev_set_point_mode."*".$row['mode']."*");
       $prev_set_point_mode = $row['mode'];

	      
	      if ($row['switch_time'] < $from_date)
	      {
              // If we're the first row from the query, and we're from the day before the "from date", start drawing
              // at the left hand margin
              $start_px = $LeftMargin;
	      }
	      else
	      {
              // If we're the first row from the query, but we're from somewhere within the current display
              // timeframe, it means our history doesn't go far enough back to be off the left side of the graph
              
              // Set things up so that we know where to start the first setpoint line, which we'll draw
              // on the next iteration of this foreach when we get the subsequent row which will be the end point
              // for the first horizontal line
              $pad_minutes = round((strtotime($row['switch_time']) - strtotime($from_date)) / 60);
              $start_px = $LeftMargin + ($pad_minutes * $PixelsPerMinute);

              // Our assumption, above, that the previous switch time is probably off the left of the current displ
              // was wrong.  Update the previous switch time to the first switch point we found so we start drawing
              // from that time forward
              $prev_switch_time = date_create($row['switch_time']);
//       $log->logError("Mode 1 = ".$prev_set_point_mode."*".$row['mode']."*");
       $prev_set_point_mode = $row['mode'];

	      }
          
	      // We were just figuring out our left most point, we're not drawing anything yet, so loop to the next row
	      continue;
	   }

       /* Now figure out how to connect the prior change (switch) point in the setpoint to the next switch point */
       /* Generally that means drawing a horizontal line from left to right between the points.  And if the set  */
       /* point value changed, a vertical line to connect the old setpoint temp to the new one */
       
	   // Compute the switch time delta between the current point and the most recent one
	   $setpoint = $row['set_point'];
	   $switch_time = date_create($row['switch_time']);
	   $interval = $prev_switch_time->diff($switch_time);
//       $log->logError("Switch point: ".$switch_time->format('d')." ".$switch_time->format('h')." ".$switch_time->format('i') );

	   // Compute the next end pixel based on the switch time difference
	   // Have to include all the minutes by adding up the days, hours and minutes since the last switch
	   $end_px = $start_px + ( $interval->days * 24 * 60 + $interval->h * 60 + $interval->i ) * $PixelsPerMinute;

	   // If the end goes off the right side of the graph, truncate it there
	   // Currently the right most temperature points on the graph are, at most, at 11:30pm,
       // we continue the setpoint out to the very edge, though.
	   if ($end_px > $graphXLastDataPoint - ($chartXMarginMins * $PixelsPerMinute))
	   {
           // The chart has a pad of 15 minutes between midnight and the humidity Y axis, so stop drawing
           // 15 minutes short of that edge so the line stops at midnight.
           $end_px = $graphXLastDataPoint - ($chartXMarginMins * $PixelsPerMinute);
          
	      // since we had to truncate a line, we know that there should be no more rows to deal with after this
	      $already_off_x_axis = 1;
	   }
	   
	   // Draw the horizontal setpoint line only if there was a valid previous set point value,
       // otherwise it probably means we failed to collect it from the thermostat for some reason.
       // In those cases we don't guess at the values, we just stop drawing the setpoint line for
       // the missing time range.
	   if ($prev_setpoint != 0)
	   {
//                      $log->logError("sp: ".$prev_setpoint."sx sy ex ey *".$start_px."*".($graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale)."*".$end_px."*".($graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale));
//             $log->logError("sp: scale: ".$setpoint_scale.", ".$prev_setpoint."sx sy ex ey *".$start_px."*".($graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale)."*".$end_px."*".($graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale));
//           $log->logError("Mode 2 = ".$prev_set_point_mode."*".$row['mode']."*");
           if ($prev_set_point_mode == '1' /* heat */)
           {
               $myPicture->drawLine( $start_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, array( 'R' => 255, 'G' => 100, 'B' => 100, 'Ticks' => 1, 'Alpha' => 60 , 'Weight' => 0) );
           }
           else if ($prev_set_point_mode == '2' /* cool */)
           {
               $myPicture->drawLine( $start_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, array( 'R' => 100, 'G' => 100, 'B' => 255, 'Ticks' => 1, 'Alpha' => 60 , 'Weight' => 0) );
           }
           else /* unknown */
           {
               $myPicture->drawLine( $start_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, array( 'R' => 100, 'G' => 100, 'B' => 100, 'Ticks' => 1, 'Alpha' => 60 , 'Weight' => 0) );
           }
	   }

	   // Draw the vertical setpoint change line only if both the lowest point and highest point are meaningful
       // otherwise we may have a case where we were unable to collect the setpoint for some reason or, if heat
       // and cool were both off, the thermostat does not report one.  We should not draw anything in those time
       // windows
//       $log->logError("Mode 2.5 = ".$prev_set_point_mode."*".$row['mode']."*".$prev_setpoint."*".$setpoint."*");
	   if ($prev_setpoint != 0 && $setpoint != 0 && $prev_setpoint != $setpoint)
	   {
//           $log->logError("Mode 2.75 = ".$prev_set_point_mode."*".$row['mode']."*".$prev_setpoint."*".$setpoint."*");
           if ($prev_set_point_mode != $row['mode'] || ($prev_set_point_mode == NULL || $row['mode'] == NULL))
           {
               $myPicture->drawLine( $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($setpoint-$chart_y_min)/$setpoint_scale, array( 'R' => 100, 'G' => 100, 'B' => 100, 'Ticks' => 1, 'Alpha' => 60 , 'Weight' => 0) );
           }
           else if ($row['mode'] == '1' /* heat */)
           {
               $myPicture->drawLine( $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($setpoint-$chart_y_min)/$setpoint_scale, array( 'R' => 255, 'G' => 100, 'B' => 100, 'Ticks' => 1, 'Alpha' => 60 , 'Weight' => 0) );
           }
           else if ($row['mode'] == '2' /* cool */)
           {
               $myPicture->drawLine( $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($setpoint-$chart_y_min)/$setpoint_scale, array( 'R' => 100, 'G' => 100, 'B' => 255, 'Ticks' => 1, 'Alpha' => 60 , 'Weight' => 0) );
           }
           else
           {
               $log->logError("Did not draw setpoint vertical line because the setpoint changed, but the current mode is neither heat nor cool");
           }
           


	   }
	   
	   // Reset parameters for next iteration
	   $prev_switch_time = $switch_time;
//       $log->logError("Mode 3 = ".$prev_set_point_mode."*".$row['mode']."*");
       $prev_set_point_mode = $row['mode'];

	   $prev_setpoint = $setpoint;
	   $start_px = $end_px;
	}

    /* Draw the last setpoint horizontal line but first determine how far it needs to be drawn
     * If the "to_date" (the most lastest day we're drawing) is either today, or in the future, 
     * we will stop this segment at the current time.  Otherwise, it means we're showing a date 
     * range that is wholly in the past so draw to the right most 23:59:59 marker.
     * If the "from_date" is in the future, be sure not do do anything at all!
     */
    if (isset($prev_switch_time) && $row['set_point'] != 0)
    {
        $now = date_create();
        $to_date_tmp = date_create($to_date);
        $from_date_tmp = date_create($from_date);
        $interval = $prev_switch_time->diff($now);

        /* If the end of the setpoint line is going to be within the chart from/to range, figure out where to end it */
        if( $to_date_tmp->format('Y-m-d') >= $now->format('Y-m-d') )
        {
            //            $log->logError("Got here - interval = ".( $interval->format('%d') * 1440 + $interval->format('%h') * 60 + $interval->format('%i') ));
            //            $log->logError("End time: ".$interval->format('%d')." ".$interval->format('%h')." ".$interval->format('%i'));
            $end_px = $start_px + ( $interval->format('%d') * 1440 + $interval->format('%h') * 60 + $interval->format('%i') ) * $PixelsPerMinute;
            
            // The chart has a pad of between midnight and the humidity Y axis, so stop drawing
            // $chartXMarginMins short of that edge so the line stops at midnight.
            if ($end_px > $graphXLastDataPoint - ($chartXMarginMins * $PixelsPerMinute))
            {
                $end_px = $graphXLastDataPoint - ($chartXMarginMins * $PixelsPerMinute);
            }
            //            $log->logError("End pixel: ".$end_px."PPM: ".$PixelsPerMinute);
        }
        else
        {
            // Otherwise the whole date range is all in the past, so just end the line at the right hand side of the chart area
            $end_px = $graphXLastDataPoint - ($chartXMarginMins * $PixelsPerMinute);
        }

        /* Only draw the line if the "from date" is today or in the past */
        if ( $from_date_tmp->format('Y-m-d') <= $now->format('Y-m-d'))
        {
//           $log->logError("Mode 4 = ".$prev_set_point_mode."*".$row['mode']."*");
//             $log->logError("sp2: scale: ".$setpoint_scale.", ".$prev_setpoint."sx sy ex ey *".$start_px."*".($graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale)."*".$end_px."*".($graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale));
           if ($prev_set_point_mode == '1' /* heat */)
           {
               $myPicture->drawLine( $start_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, array( "R" => 255, "G" => 100, "B" => 100, "Ticks" => 1, "Alpha" => 60, "Weight" => 0 ) );
           }
           else if ($prev_set_point_mode == '2' /* cool */)
           {
               $myPicture->drawLine( $start_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, array( "R" => 100, "G" => 100, "B" => 255, "Ticks" => 1, "Alpha" => 60, "Weight" => 0 ) );
           }
           else /* unknown */
           {
               $myPicture->drawLine( $start_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, $end_px, $graphAreaEndY-($prev_setpoint-$chart_y_min)/$setpoint_scale, array( "R" => 100, "G" => 100, "B" => 100, "Ticks" => 1, "Alpha" => 60, "Weight" => 0 ) );
           }
        }
    }
}

// Seemed like a good idea to show max and min of everything, but I can't manage to get any of the optional
// parameters to work, and by default it ends up very messy
//$myPicture->writeBounds(DISPLAY_AUTO, array('DisplayOffset' => 1));

/* Some fun image manipulation here! */

/* The idea here is to prepare a new image to show when we start rendering the next image.  We do this by keeping */
/* a copy of the current image, in grayscale, with "rendering..." written over it, in the images directory.  It   */
/* gets displayed just when we trigger a new display */

/* First, we'll render the image to a file in the "images" directory */
/* Second, we load it back in as $im, make a copy of it in $to_return */
/* Third, we write "Rendering..." on top of the image to mimick the old placeholder image (font's not italic though!) */
/* Fourth, we turn $im to grayscale and save it to a grayscale version of the image */
/* Then we generate the "normal" image for the page */

/* The grayscale image will be used in place of the older static sized placeholder.  The main reason for this */
/* is to allow the placeholder (grayscale of the chart here) to be the same size as the current chart since the */
/* chart size is dynamic to the size of the browser window */

/* Use a random number in the file name to avoid multiple executions of this file from overwriting each other */
/* This is one fo the downsides of not relying on pChart's autoOutput() to display the image directly to the window */
/* We might also have a problem with the page trying to read the grayscale image file while we're also writing */
/* it but I think that's less likely to happen and we'll do something clean(ish) when we try to load it, too */

/* Lastly - Relying on the static 'placeholder' image that has been a part of this package since the beginning */
/* wouldn't be a problem except for the the dynamic resize and, in particular, due to the auto redraw "onresize" */

/* We could just force them to hit "show" again after resizing the window. This might even be desirable since, as */
/* it is, the user cannot resize the screen, or change the browser zoom level, without it self adjusting. */
/* So, for instance, you can't get a chart, then zoom in with the browser to see some part of it more */
/* closely.  The chart essentially always renders the same size relative to your monitor regardless of browser */
/* window size or zoom level */

$rand_num = rand(1,1000000); // Should be random enough.  What are the chances of getting the same number twice??  (well, 1 in a million, actually)
/* Render the new chart to a file instead of directly to the screen */
$myPicture->render( 'images/daily_chart_'.$rand_num.'.png' );

/* Create a new image resource from the file we just wrote */
$im = imagecreatefrompng('images/daily_chart_'.$rand_num.'.png');

/* Then toss the file.  Is there any way to get an image resource directly from pChart?  ($myPicture->stroke()?) */
unlink('images/daily_chart_'.$rand_num.'.png');

if (!$im)
{
   /* Something is wrong with the file on disk, so revert the the placeholder and scale it */
  
   $im = imagecreatefrompng('images/daily_temperature_placeholder.png');
   imagepng(imagescale($im, $chart_width, $chart_height, IMG_BILINEAR_FIXED));
   return;
}
else
{
    /* We've got our new image resource with the chart we just prepared */

    /* Create a copy of that image resource in another image resource "$to_return" */
    $to_return = imagecreatetruecolor($chart_width, $chart_height);
    imagecopy($to_return, $im, 0, 0, 0, 0, $chart_width, $chart_height);

    /* Write "Rendering..." into it */
    $black = imagecolorallocate($im, 0, 0, 0);
    /* First figure out the size of the text rendering so that we can properly center it on the image */
    $bbox_size = imagettfbbox(($chart_height/20), 0, 'verdana.ttf', "Rendering...");

    /* Write the text over the image, with appropriate coordinates to center it horizontally and vertically */
    imagettftext ( $im , ($chart_height/20) , 0 , ($bbox_size[0] + (imagesx($im)/2) - ($bbox_size[4]/2)) , ($bbox_size[1] + (imagesy($im) / 2) - ($bbox_size[5]/2)), $black , "verdana.ttf" , "Rendering..." );

    /* Last but not least, turn it to grayscale, and write it to disk for use as the placeholder for the next rendering */
    if(imagefilter($im, IMG_FILTER_GRAYSCALE))
    {
        imagepng($im, 'images/daily_chart_greyscale.png');
    }
}
/* Not sure this is strictly necessary, I have to assume that these get freed at the end of the php segment's execution */
imagedestroy($im);

/* "Draw" the new chart just as we would have if we'd rendered directly from pChart */
imagepng($to_return);

imagedestroy($to_return);

?>
