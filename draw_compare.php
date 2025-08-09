<?php
$start_time = microtime(true);
require_once( 'common_chart.php' );

use CpChart\Data;
use CpChart\Image;

$hddBaseF = 65;
$cddBaseF = 65;

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

if (isset($_GET['show_compare_mode']))
{ 
   $compare_mode = $_GET['show_compare_mode'];
//   $log->logError("compare_mode: ".$compare_mode);

   if ($compare_mode < 0 || $compare_mode > 1)
   { // If it is out of bounds, default to heat.  1: cool; 0: heat
      $log->logError("No compare mode was specified");
      $compare_mode = 0;
   }
}
else
{
   $log->logError("show_compare_mode: Failed");
}

if (isset($_GET['show_year_1']))
{ 
   $show_year_1 = $_GET['show_year_1'];
//   $log->logError("show_year_1: ".$show_year_1);
}
else
{
   $log->logError("show_year_1: Failed");
}

if (isset($_GET['show_year_2']))
{ 
   $show_year_2 = $_GET['show_year_2'];
//   $log->logError("show_year_2: ".$show_year_2);
}
else
{
   $log->logError("show_year_2: Failed");
}

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

$log->logInfo($giantSQL);
$queryGiant = $pdo->prepare( $giantSQL );
$queryGiant->execute( array( $uuid, $uuid ) );

$minmaxSQL = "SELECT
    MIN(YEAR (end_time)) AS earliest_year,
    MAX(YEAR (end_time)) AS latest_year
FROM {$dbConfig['table_prefix']}hvac_cycles
WHERE tstat_uuid = ?;
";

//$log->logError($minmaxSQL);
$queryMinmax = $pdo->prepare( $minmaxSQL );
$result = $queryMinmax->execute( array($uuid) );
//$log->logError("minmax result: ".$result);
while ($row = $queryMinmax->fetch(PDO::FETCH_ASSOC))
{
  $log->logInfo("raw data: ".print_r($row, true));
}
// Create and populate the pData object
$MyData = new Data();

while( $row = $queryGiant->fetch( PDO::FETCH_ASSOC ) )
{
    $MyData->addPoints( $row[ 'theMonth' ], 'Labels' );

    if ($compare_mode == 0) // Heat
    {
//        $log->logError($show_year_1." ".$row['theMonth']." heat Hours: ".$row['heatHours12']." heat degrees: ".$row['hdd12']);
//        $log->logError($show_year_2." ".$row['theMonth']." heat Hours: ".$row['heatHours13']." heat degrees: ".$row['hdd13']);
	$MyData->addPoints( $row[ 'heatHours12' ], 'Heating '.$show_year_1 );
	$MyData->addPoints( $row[ 'hdd12' ], 'Heating Degrees '.$show_year_1 );
	$MyData->addPoints( $row[ 'heatHours13' ], 'Heating '.$show_year_2 );
	$MyData->addPoints( $row[ 'hdd13' ], 'Heating Degrees '.$show_year_2 );
        $MyData->setSerieOnAxis( 'Heating '.$show_year_1, 0 );
        $MyData->setSerieOnAxis( 'Heating '.$show_year_2, 0 );
        $MyData->setSerieOnAxis( 'Heating Degrees '.$show_year_1, 1 );
        $MyData->setSerieOnAxis( 'Heating Degrees '.$show_year_2, 1 );
    }
    else
    {
//        $log->logError($show_year_1." ".$row['theMonth']." cool Hours: ".$row['coolHours12']." cool degrees: ".$row['cdd12']);
//        $log->logError($show_year_2." ".$row['theMonth']." cool Hours: ".$row['coolHours13']." cool degrees: ".$row['cdd13']);
	$MyData->addPoints( $row[ 'coolHours12' ], 'Cooling '.$show_year_1 );
	$MyData->addPoints( $row[ 'cdd12' ], 'Cooling Degrees '.$show_year_1 );
	$MyData->addPoints( $row[ 'coolHours13' ], 'Cooling '.$show_year_2 );
	$MyData->addPoints( $row[ 'cdd13' ], 'Cooling Degrees '.$show_year_2 );
        $MyData->setSerieOnAxis( 'Cooling '.$show_year_1, 0 );
        $MyData->setSerieOnAxis( 'Cooling '.$show_year_2, 0 );
        $MyData->setSerieOnAxis( 'Cooling Degrees '.$show_year_1, 1 );
        $MyData->setSerieOnAxis( 'Cooling Degrees '.$show_year_2, 1 );
    }
}

// Set names for Y-axis labels
$MyData->setAxisName( 0, 'Hours' );
$MyData->setAxisName( 1, 'Degrees' );
$MyData->setAxisPosition( 1, AXIS_POSITION_RIGHT );

// Set names for X-axis labels
$MyData->setSerieDescription( 'Labels', 'Months' );
$MyData->setAbscissa( 'Labels' );

/**
	* Set variables for going into common block
	*/
$picTitle = 'Show the comparison run times';
$chartTitle = 'HVAC run times for each month in the record';

/**
	* START of common block - this code should be identical for all charts so that they have a common look and feel
	*/
$myPicture = new Image( 900, 430, $MyData );	// Create the pChart object
$myPicture->Antialias = FALSE;								// Turn OFF Antialiasing (it draws faster)

// Draw the background
$Settings = array( 'R' => 170, 'G' => 183, 'B' => 87, 'Dash' => 1, 'DashR' => 190, 'DashG' => 203, 'DashB' => 107, 'Alpha' => 60 );
$myPicture->drawFilledRectangle( 0, 0, 900, 430, $Settings );

// Overlay with a gradient
$Settings = array( 'StartR' => 219, 'StartG' => 231, 'StartB' => 139, 'EndR' => 1, 'EndG' => 138, 'EndB' => 68, 'Alpha' => 50 );
$myPicture->drawGradientArea( 0, 0, 900, 430, DIRECTION_VERTICAL, $Settings );
$Settings = array( 'StartR' => 0, 'StartG' => 0, 'StartB' => 0, 'EndR' => 50, 'EndG' => 50, 'EndB' => 50, 'Alpha' => 80 );
$myPicture->drawGradientArea( 0, 0, 900,	20, DIRECTION_VERTICAL, $Settings );

// Add a border to the picture
$myPicture->drawRectangle( 0, 0, 899, 429, array( 'R' => 0, 'G' => 0, 'B' => 0 ) );

// Set font for all descriptive text
$myPicture->setFontProperties( array( 'FontName' => 'Copperplate_Gothic_Light.ttf', 'FontSize' => 10 ) );

// Write picture and chart titles
$myPicture->drawText( 10, 14, $picTitle, array( 'R' => 255, 'G' => 255, 'B' => 255) );
$myPicture->drawText( 60, 55, $chartTitle, array( 'FontSize' => 12, 'Align' => TEXT_ALIGN_BOTTOMLEFT ) );

// Write the picture timestamp
$myPicture->drawText( 680, 14, 'Last update ' . date( 'Y-m-d H:i' ), array( 'R' => 255, 'G' => 255, 'B' => 255) );

// Define the chart area
$graphAreaStartX = 60;
$graphAreaEndX = 850;
$graphAreaStartY = 60;
$graphAreaEndY = 390;
$myPicture->setGraphArea( $graphAreaStartX, $graphAreaStartY, $graphAreaEndX, $graphAreaEndY );

// Draw the scale
$myPicture->setFontProperties( array( 'FontName' => 'pf_arma_five.ttf', 'FontSize' => 6 ) );
//$scaleSettings = array( 'Mode' => SCALE_MODE_MANUAL, 'ManualScale' => $AxisBoundaries, 'GridR' => 200, 'GridG' => 200, 'GridB' => 200, 'LabelingMethod' => LABELING_DIFFERENT, 'DrawSubTicks' => TRUE, 'CycleBackground' => TRUE, 'YMargin' => 0,'Floating' => TRUE );
$scaleSettings = array( 'GridR' => 200, 'GridG' => 200, 'GridB' => 200, 'LabelingMethod' => LABELING_DIFFERENT, 'DrawSubTicks' => TRUE, 'CycleBackground' => TRUE, 'YMargin' => 0,'Floating' => TRUE );
$myPicture->drawScale( $scaleSettings );

// Write the chart legend - convert all legends to left aligned because there is no auto right alignment
$myPicture->setFontProperties( array( 'FontName' => 'pf_arma_five.ttf', 'FontSize' => 6 ) );
$myPicture->setShadow( TRUE, array( 'X' => 1, 'Y' => 1, 'R' => 0, 'G' => 0, 'B' => 0, 'Alpha' => 10 ) );
$myPicture->drawLegend( 60, 412, array( 'Style' => LEGEND_NOBORDER, 'Mode' => LEGEND_HORIZONTAL ) );
// END of common block


// Draw the chart
//$myPicture->drawLineChart( array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO ) );
//$myPicture->drawBarChart( array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO ) );

if ($compare_mode == 0) // Heat
{
   $Settings = array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO, 'Gradient' => 1, 'AroundZero' => TRUE, 'Interleave' => 2  );
   $MyData->setSerieDrawable( 'Heating '.$show_year_1, TRUE );
   $MyData->setSerieDrawable( 'Heating '.$show_year_2, TRUE );
   $MyData->setSerieDrawable( 'Heating Degrees '.$show_year_1, FALSE );
   $MyData->setSerieDrawable( 'Heating Degrees '.$show_year_2, FALSE );
   $myPicture->drawBarChart( $Settings );

   $Settings = array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO );
   $MyData->setSerieDrawable( 'Heating '.$show_year_1, FALSE );
   $MyData->setSerieDrawable( 'Heating '.$show_year_2, FALSE );
   $MyData->setSerieDrawable( 'Heating Degrees '.$show_year_1, TRUE );
   $MyData->setSerieDrawable( 'Heating Degrees '.$show_year_2, TRUE );
   $myPicture->drawLineChart( $Settings );

}
else
{
   $Settings = array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO, 'Gradient' => 1, 'AroundZero' => TRUE, 'Interleave' => 2  );
   $MyData->setSerieDrawable( 'Cooling '.$show_year_1, TRUE );
   $MyData->setSerieDrawable( 'Cooling '.$show_year_2, TRUE );
   $MyData->setSerieDrawable( 'Cooling Degrees '.$show_year_1, FALSE );
   $MyData->setSerieDrawable( 'Cooling Degrees '.$show_year_2, FALSE );
   $myPicture->drawBarChart( $Settings );

   $Settings = array( 'DisplayValues' => FALSE, 'DisplayColor' => DISPLAY_AUTO );
   $MyData->setSerieDrawable( 'Cooling '.$show_year_1, FALSE );
   $MyData->setSerieDrawable( 'Cooling '.$show_year_2, FALSE );
   $MyData->setSerieDrawable( 'Cooling Degrees '.$show_year_1, TRUE );
   $MyData->setSerieDrawable( 'Cooling Degrees '.$show_year_2, TRUE );
   $myPicture->drawLineChart( $Settings );
}

// Render the picture
$myPicture->autoOutput( 'images/compare_chart.png' );
$log->logInfo( 'draw_compare.php: execution time was ' . (microtime(true) - $start_time) . ' seconds.' );

?>
