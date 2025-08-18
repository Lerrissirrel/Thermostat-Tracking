<?php
require_once( 'common_chart.php' );

$to_date = date( 'Y-m-d' );
if( isset( $_POST['history_to_date'] ) )
{ // Use provided date
  $to_date = $_POST['history_to_date'];
}

if( ! validate_date( $to_date ) ) return;

// Verify that date is not future?

$interval_measure = 0;	// Default to days
if( isset( $_POST['interval_measure'] ) )
{
  $interval_measure = $_POST['interval_measure'];
}
if( $interval_measure < 0 || $interval_measure > 3 )
{
   $interval_measure = 0;
}

// How many "measure"s are we trying to chart?  Make sure it's less than 3 years
if (isset($_POST['interval_length']))
{
   $interval_length = $_POST['interval_length'];

   if ($interval_length < 0) $interval_length = 1;

   $days_array = array( 0 => 1, 1 => 7, 2 => 30, 3 => 365);
   $days = $days_array[$interval_measure];

   // This chart is pretty efficient (vs "daily"), so we'll let it go out to 3 years
   if ($interval_length * $days > (3*365)) 
   {
      $interval_length = floor(3*365/$days);
   }
}

// Generate a date string that subtracks off the right number of "measures" so we know where to start charting
$date_text = array( 
               0 => 'days', 
               1 => 'weeks', 
               2 => 'months',
               3 => 'years' );
$interval_string = $to_date . ' -' . $interval_length . ' ' . $date_text[$interval_measure];

// Compute the from date
$from_date = date( 'Y-m-d', strtotime( $interval_string ) );

// The handling of this failing is not great.  Need to figure out something better like maybe default to just this 
// week or something?  Or maybe little Bobby Tables can show up in the echart div?
if( ! validate_date( $from_date ) ) return;

switch( $interval_measure )
{
   case 2:
      // Do weekly formatting here (for now it all defaults to daily)
      $group_by_text = "date_format( date, '%Y/%m/%d' )";
   break;
   case 1:
      // Do weekly formatting here (for now it all defaults to daily)
      $group_by_text = "date_format( date, '%Y/%m/%d' )";
   break;
   default:
      $group_by_text = "date_format( date, '%Y/%m/%d' )";
   break;
}

$sql = "SELECT
          a.date,
          a.outdoor_max,
          a.outdoor_min,
          a.indoor_max,
          a.indoor_min,
          IFNULL(b.heat_runtime, 'VOID') AS heat_runtime,
          IFNULL(b.cool_runtime, 'VOID') AS cool_runtime
        FROM (
          SELECT
            DATE(date) AS date,
            tstat_uuid,
            MAX(outdoor_temp) AS outdoor_max,
            MIN(outdoor_temp) AS outdoor_min,
            MAX(indoor_temp) AS indoor_max,
            MIN(indoor_temp) AS indoor_min
          FROM {$dbConfig['table_prefix']}temperatures
          WHERE tstat_uuid = ?
            AND DATE(date) BETWEEN '$from_date' AND '$to_date'
          GROUP BY {$group_by_text}
        ) a
        LEFT JOIN {$dbConfig['table_prefix']}run_times b
        ON a.date = DATE(b.date) AND a.tstat_uuid = b.tstat_uuid";

$query = $pdo->prepare( $sql );
$query->execute( array( $uuid ) );

$days = $query->rowCount();	// Determine the number of days in the resultset.

$total_heat_runtime = 0;
$total_cool_runtime = 0;

while( $row = $query->fetch( PDO::FETCH_ASSOC ) )
{
   $x_col = array();

   // Put the data in $x_col to later add to the json with appropriate labels.  Could probably just do it all right here to be honest

   $x_col[] = $row['date'];
   $x_col[] = strtotime($row['date']);

   $x_col[] = $row[ 'outdoor_min' ];
   $x_col[] = $row[ 'outdoor_max' ];

   $x_col[] = $row[ 'indoor_min' ];
   $x_col[] = $row[ 'indoor_max' ];


   if( $row[ 'heat_runtime' ] != 'VOID' )
   {
      $x_col[] = $row[ 'heat_runtime' ];
      $total_heat_runtime += $row[ 'heat_runtime' ];
   }

   if( $row[ 'cool_runtime' ] != 'VOID' )
   {
      $x_col[] = $row[ 'cool_runtime' ];
      $total_cool_runtime += $row[ 'cool_runtime' ];
   }

   $data_for_echart[] = array(
      "Text_Date" => $x_col[0],
      "Label" => $x_col[1],
      "Outdoor_Min" => $x_col[2],
      "Outdoor_Max" => $x_col[3],
      "Indoor_Min" => $x_col[4],
      "Indoor_Max" => $x_col[5],
      "Heat_Runtime" => $x_col[6],
      "Cool_Runtime" => $x_col[7]
      );
}

$array_print = json_encode($data_for_echart);

// The print_r version pretty prints it for debug, uncomment the next line if you want to get it
// file_put_contents('tmp_data/History_temps_pp.txt', print_r($data_for_echart, true));

file_put_contents('tmp_data/History_temps.txt', $array_print);

?>
