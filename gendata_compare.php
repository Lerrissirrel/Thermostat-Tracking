<?php
$start_time = microtime(true);
require_once( 'common_chart.php' );

/**
* To compute degree days....
*
* For heating degree days
*  1. Determine average temperature for the whole day
*  2. For each average in excess of 65 degrees F, count only those degrees above 65
*  3. Sum those degrees for the month
* For cooling degree days
*  1. Determine average temperature for the whole day
*  2. For each average below 65 degrees F, count only those degrees below 65
*  3. Sum those degrees for the month
*
* To use these computed numbers.
*  Compare the the heating degree days for a given month on two successive years to see which is
*  the hotter month.
*  Examine the HVAC runtime in hours for that same month on those two years.
*  If all things are equal, the run time for a given number of degrees will remain the same.
*  If your run time begins to increase then you may need to tune/refill/maintain your unit.
*
*  This is also very handy if one year your electricity bill is much higher, you can see if the
*  Summer was very much hotter.
*
*  65 is the magic number in the US.  Your number may vary.  Your number WILL vary.
*  http://www.degreedays.net/introduction
*  See particularly the section about determining your own numbers
*
*/

// These are the temps referred to, above, set yours as you will!
$hddBaseF = 68;
$cddBaseF = 66;

$hddBaseC = ( ( $hddBaseF - 32 ) * 5 ) / 9;
$cddBaseC = ( ( $cddBaseF - 32 ) * 5 ) / 9;

if( $config['units'] = 'F' )
{
   $hddBase = $hddBaseF;
   $cddBase = $cddBaseF;
}
else
{
   $hddBase = $hddBaseC;
   $cddBase = $cddBaseC;
}

if (isset($_POST['show_year_1']))
{ 
   $show_year_1 = $_POST['show_year_1'];
   $log->Info("gendata_compare.php: show_year_1: ".$show_year_1);
}
else
{
   $log->Error("gendata_compare.php: show_year_1: Failed");
}

if (isset($_POST['show_year_2']))
{ 
   $show_year_2 = $_POST['show_year_2'];
   $log->Info("gendata_compare.php: show_year_2: ".$show_year_2);
}
else
{
   $log->Error("gendata_compare.php: show_year_2: Failed");
}

$log->Info("gendata_compare.php: id: ".$uuid." y1: ".$show_year_1." y2: ".$show_year_2);

$giantSQL = "SELECT
	t1.theMonthNumber,
	t1.theMonth AS theMonth,
	heatHours12, heatHours13,
	coolHours12, coolHours13,
	hdd12, hdd13,
	cdd12, cdd13
FROM
	(
		SELECT
			DATE_FORMAT(rt.date, '%m') AS theMonthNumber,
			DATE_FORMAT(rt.date, '%M') AS theMonth,
			ROUND(SUM(IF( DATE_FORMAT(rt.date, '%Y') = '".$show_year_1."', rt.heat_runtime, 0))/60,0) AS heatHours12,
			ROUND(SUM(IF( DATE_FORMAT(rt.date, '%Y') = '".$show_year_2."', rt.heat_runtime, 0))/60,0) AS heatHours13,
			ROUND(SUM(IF( DATE_FORMAT(rt.date, '%Y') = '".$show_year_1."', rt.cool_runtime, 0))/60,0) AS coolHours12,
			ROUND(SUM(IF( DATE_FORMAT(rt.date, '%Y') = '".$show_year_2."', rt.cool_runtime, 0))/60,0) AS coolHours13
		FROM {$dbConfig['table_prefix']}run_times rt
		WHERE tstat_uuid = ?
		GROUP BY 1, 2
	) t1,
	(
		SELECT
			DATE_FORMAT(dt.date, '%m') AS theMonthNumber,
			DATE_FORMAT(dt.date, '%M') AS theMonth,
			ROUND(SUM(IF( DATE_FORMAT(dt.date, '%Y') = '".$show_year_1."', dt.avgTempHDD, 0)),1) AS hdd12,
			ROUND(SUM(IF( DATE_FORMAT(dt.date, '%Y') = '".$show_year_2."', dt.avgTempHDD, 0)),1) AS hdd13,
			ROUND(SUM(IF( DATE_FORMAT(dt.date, '%Y') = '".$show_year_1."', dt.avgTempCDD, 0)),1) AS cdd12,
			ROUND(SUM(IF( DATE_FORMAT(dt.date, '%Y') = '".$show_year_2."', dt.avgTempCDD, 0)),1) AS cdd13
		FROM
		(
			SELECT
				date_format(t.date, '%Y-%m-%d') AS date,
				IF( AVG(t.outdoor_temp) <= {$hddBase}, {$hddBase} - AVG(t.outdoor_temp), 0 ) AS avgTempHDD,
				IF( AVG(t.outdoor_temp) >= {$cddBase}, AVG(t.outdoor_temp) - {$cddBase}, 0 ) AS avgTempCDD
			FROM {$dbConfig['table_prefix']}temperatures t
			WHERE tstat_uuid = ?
			GROUP BY DATE_FORMAT(date, '%Y-%m-%d')
		) dt
	GROUP BY 1, 2
	) t2
WHERE
    t1.theMonthNumber = t2.theMonthNumber
AND t1.theMonth = t2.theMonth
";

$log->Info($giantSQL);
$queryGiant = $pdo->prepare( $giantSQL );
$queryGiant->execute( array( $uuid, $uuid ) );

$data_for_echarts = array();

// No data manipulation to do here, just dump it all in some json and return it for charting
while( $row = $queryGiant->fetch( PDO::FETCH_ASSOC ) )
{
   $tmp_array = array(
      "Label"   => $row['theMonth'],
      "Year1"   => $show_year_1,
      "Year2"   => $show_year_2,
      "HHours1" => $row[ 'heatHours12' ],
      "HDeg1"   => $row[ 'hdd12' ],
      "HHours2" => $row[ 'heatHours13' ],
      "HDeg2"   => $row[ 'hdd13' ],
      "CHours1" => $row[ 'coolHours12' ],
      "CDeg1"   => $row[ 'cdd12' ],
      "CHours2" => $row[ 'coolHours13' ],
      "CDeg2"   => $row[ 'cdd13' ],
   );
   $data_for_echart[] = $tmp_array;
}

$array_print = json_encode($data_for_echart);

// The print_r version pretty prints it for debug, uncomment the next line to get it
//file_put_contents('tmp_data/Compare_data_pp.txt', print_r($data_for_echart, true));

file_put_contents('tmp_data/Compare_data.txt', $array_print);

$log->Info( 'gendata_compare.php: execution time was ' . (microtime(true) - $start_time) . ' seconds.' );
?>
