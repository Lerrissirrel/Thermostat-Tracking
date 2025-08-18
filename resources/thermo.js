"use strict";
/** These are the functions associated with the thermostat viewer web page.
	*
	*/

function saveThermostat( chart )
{
// These need to be split apart to individual saves
   switch( chart )
   {
      case 'daily':
         setCookie( 'chart.daily.thermostat',    document.getElementById( 'chart.daily.thermostat' ).value );
      break;
	
      case 'historic':
         setCookie( 'chart.history.thermostat',  document.getElementById( 'chart.history.thermostat' ).value );
      break;
		
      case 'compare':	
         setCookie( 'chart.compare.thermostat',  document.getElementById( 'chart.compare.thermostat' ).value );
      break;

      case 'schedule':
         setCookie( 'chart.schedule.thermostat', document.getElementById( 'chart.schedule.thermostat' ).value );
      break;

      default:
      break;
   }
}

function loadThermostat( chart )
{
// These need to be split apart to individual loads
   switch( chart )
   {
      case 'daily':
        if( getCookie( 'chart.daily.thermostat' ) && document.getElementById( 'chart.daily.thermostat' ))
          document.getElementById( 'chart.daily.thermostat' ).value = getCookie( 'chart.daily.thermostat' );
      break;

      case 'historic':
        if( getCookie( 'chart.history.thermostat' ) && document.getElementById( 'chart.history.thermostat' ))
          document.getElementById( 'chart.history.thermostat' ).value = getCookie( 'chart.history.thermostat' );
      break;

      case 'compare':
        if( getCookie( 'chart.compare.thermostat' ) && document.getElementById( 'chart.compare.thermostat' ))
          document.getElementById( 'chart.compare.thermostat' ).value = getCookie( 'chart.compare.thermostat' );
      break;

      case 'schedule':
        if( getCookie( 'chart.schedule.thermostat' ) && document.getElementById( 'chart.schedule.thermostat' ))
          document.getElementById( 'chart.schedule.thermostat' ).value = getCookie( 'chart.schedule.thermostat' );
      break;

      default:
      break;
   }
}

/**
	* chart is one of 'daily' or 'historic'
	*/
function saveDateData( chart )	// Change to use actual name of element...
{
	// These need to be split apart to individual saves
	switch( chart )
	{
		case 'daily':
			setCookie( 'chart.daily.interval.length', document.getElementById( 'chart.daily.interval.length' ).value );	// How many?
			setCookie( 'chart.daily.interval.group',  document.getElementById( 'chart.daily.interval.group' ).value );	// Days, weeks, months, years
			setCookie( 'chart.daily.toDate',          document.getElementById( 'chart.daily.toDate' ).value );					// Ending on date
		break;
		
		case 'historic':	// Change this to literal "history"
			setCookie( 'chart.history.interval.length', document.getElementById( 'chart.history.interval.length' ).value );	// How many?
			setCookie( 'chart.history.interval.group',  document.getElementById( 'chart.history.interval.group' ).value );	// Days, weeks, months, years
			setCookie( 'chart.history.toDate',          document.getElementById( 'chart.history.toDate' ).value );					// Ending on date
		break;
                case 'compare':
                        setCookie( 'chart.compare.firstDate', document.getElementById( 'chart.compare.firstDate' ).value );
                        setCookie( 'chart.compare.secondDate', document.getElementById( 'chart.compare.secondDate' ).value );
                break;
		default:
                   console.log("saveDateData invalid type: "+chart);
		break;
	}
}

function loadDateData( chart )
{
	switch( chart )
	{
		case 'daily':
	    if( getCookie( 'chart.daily.interval.length' ) )
		document.getElementById( 'chart.daily.interval.length' ).value = getCookie( 'chart.daily.interval.length' );	// How many?
	    if( getCookie( 'chart.daily.interval.group' ) )
		document.getElementById( 'chart.daily.interval.group' ).value  = getCookie( 'chart.daily.interval.group' );	// Days, weeks, months, years
	    if( getCookie( 'chart.daily.toDate' ) )
		document.getElementById( 'chart.daily.toDate' ).value          = getCookie( 'chart.daily.toDate' );					// Ending on date
		break;
		
		case 'historic':
	    if( getCookie( 'chart.history.interval.length' ) )
		document.getElementById( 'chart.history.interval.length' ).value = getCookie( 'chart.history.interval.length' );	// How many?
	    if( getCookie( 'chart.history.interval.group' ) )
		document.getElementById( 'chart.history.interval.group' ).value  = getCookie( 'chart.history.interval.group' );	// Days, weeks, months, years
	    if( getCookie( 'chart.history.toDate' ) )
		document.getElementById( 'chart.history.toDate' ).value          = getCookie( 'chart.history.toDate' );					// Ending on date
		break;
	        case 'compare':
            if( getCookie( 'chart.compare.firstDate' ) )
                document.getElementById( 'chart.compare.firstDate' ).value = getCookie( 'chart.compare.firstDate' ); 
            if( getCookie( 'chart.compare.secondDate' ) )
                document.getElementById( 'chart.compare.secondDate' ).value = getCookie( 'chart.compare.secondDate' ); 	
                break;
		default:
                   console.log("loadDateData invalid type: "+chart);
		break;
	}
}


/**
	*	Save the value of the field for later - and update the chart with the new value
	*/
function update_daily_value( field )
{
	setCookie( field, document.getElementById(field).value );
	display_daily_temperature();
}


/**
	* Default cookies last for ten years.
	*
	* exdays is an optional parameter that defaults to ten years when missing.
	*/
function setCookie( c_name, value, exdays )
{
	// Chrome does not like to see '=' in the argument list of a function declaration.  Here is plan B that works in all browsers.
	if( typeof( exdays ) === 'undefined' ) exdays = 3650;	// Three === check type as well as value.

	var exdate = new Date();
	exdate.setDate( exdate.getDate() + exdays );
	var c_value = escape(value) + ( ( exdays == null ) ? '' : '; expires = ' + exdate.toUTCString() );
	document.cookie = c_name + '=' + c_value + ";path=/";
}

// Got a problem here because "false" in the cookie is coming back as being "set" and it should not.
// The work around is to test for a literal "true" in the value when using the cookie and anything that is not true is therefore false.
function getCookie( c_name )
{
	var i, key, value, ARRcookies = document.cookie.split( ';' );
	for( i = 0; i < ARRcookies.length; i++ )
	{
		key = ARRcookies[i].substr( 0, ARRcookies[i].indexOf( '=' ) );
		value = ARRcookies[i].substr( ARRcookies[i].indexOf( '=' ) + 1 );
		key = key.replace( /^\s+|\s+$/g, '' );
		if( key == c_name )
		{
			return unescape( value );
		}
	}
}

/**
	* To erase a cookie, set it with an expiration date prior to now.
	*/
function deleteCookies( chart )
{
	if( chart == 0 )
	{	// Clear cookies that remember daily settings
		setCookie( 'auto_refresh', '', -1 );
		setCookie( 'chart.daily.showSetpoint', '', -1 );
		setCookie( 'chart.daily.showHeat', '', -1 );
		setCookie( 'chart.daily.showCool', '', -1 );
		setCookie( 'chart.daily.showFan', '', -1 );
		setCookie( 'chart.daily.interval.length', '', -1 );
		setCookie( 'chart.daily.interval.group', '', -1 );
		setCookie( 'chart.daily.toDate', '', -1 );

		/* These are left over from the failed experiment to set a background color when a value came from a cookie
		   The experiment failed because browsers do not let you set styles on ALL the imputs.  Several inherit from the OS
		document.getElementById('chart.daily.showSetpoint').className = '';
		document.getElementById('chart.daily.showHeat').className = '';
		document.getElementById('chart.daily.showCool').className = '';
		document.getElementById('chart.daily.showFan').className = '';
		document.getElementById('chart.daily.interval.length').className = '';
		document.getElementById('chart.daily.interval.group').className = '';
		document.getElementById('chart.daily.toDate').className = '';
		*/
	}

	if( chart == 1 )
	{	// Clear cookies that remember history settings
		setCookie( 'chart.history.interval.length', '', -1 );
		setCookie( 'chart.history.interval.group', '', -1 );
		setCookie( 'chart.history.toDate', '', -1 );

		/* These are left over from the failed experiment to set a background color when a value came from a cookie
		   The experiment failed because browsers do not let you set styles on ALL the imputs.  Several inherit from the OS
		document.getElementById('chart.history.interval.length').className = '';
		document.getElementById('chart.history.interval.group').className = '';
		document.getElementById('chart.history.toDate').className = '';
		*/
	}
}

// Expected values are -1 or +1
function interval( direction )
{
    var valueString = document.getElementById( 'chart.daily.toDate' ).value;	// Hold in intermediate variable for debugging
    var stupidDate = new Date( valueString );	// This date is stupid because '2014-02-06' becomes '2014-02-05 18:00'
    var toDate = new Date( stupidDate.getTime() + (stupidDate.getTimezoneOffset()*60*1000) );	// The time zone offset is presented in minutes
    
    var oneDay = 86400000;  // 24*60*60*1000 (milliseconds)
    var multiplier;
    
    switch( document.getElementById( 'chart.daily.interval.group' ).value )
    {
	case '1':
	// Weeks
	multiplier = 7;
	break;
	case '2':
	// Months.  Yes, technically depending on WHICH month it is, this should be a different length.
	multiplier = 30;
	break;
	case '3':
	// Years
	multiplier = 365;
	break;
	default:
	// Days and catchall
	multiplier = 1;
	
    }
    var intervalLength = document.getElementById( 'chart.daily.interval.length' ).value * (oneDay * multiplier);
    var nextDate, curDate;
    if( direction == 1 )
    {	// Compute next interval ending date
	nextDate = new Date( toDate.getTime() + intervalLength );
        curDate  = new Date();
        // Don't let it go further forward than today
        if (nextDate.getTime() > curDate.getTime())
        {
           nextDate = curDate;
        }
    }
    else
    {	// Compute previous interval ending date
        // Unfortunately, no sanity check is available against going before our recorded date without first doing a search on the DB to find that date.
        // Doesn't seem worth it.
	nextDate = new Date( toDate.getTime() - intervalLength );
    }
    var monthString = nextDate.getMonth() + 1; // Because getMonth() is zero based
    if( monthString < 10 )
    {
	monthString = '0' + monthString;
    }
    
    var dateString = nextDate.getDate();
    
    if( dateString < 10 )
    {
	dateString = '0' + dateString;
    }
    
    valueString = '' + nextDate.getFullYear() + '-' + monthString + '-' + dateString; 	// Hold in intermediate variable for debugging
    
    document.getElementById( 'chart.daily.toDate' ).value = valueString;
    
    display_chart();	// Now go show the new interval
}


/**
	* Process return from Ajax call.
	*/
function processAjaxResponse( doc, action, ...args )
{
    var f_success = new Boolean(true);
    var our_status = doc.status;
    var local_args = args[0]; // It seems when we try to pass the args from update() into here, it all comes as element 0 of an array, so strip it off

    if (our_status != 200)
    {
	// We did not succeed
        console.log("Ajax failure: "+our_status+": "+doc.responseText);
	f_success = false;
// Seemed like a good idea to force a refresh to handle any unknown consequences of a failure, but in cases where we generate an Ajax call as part of the page reload
// we could force an infinite reload loop
//        location.reload();
    }
    else
    {
//        console.log("Ajax success: "+our_status+": "+doc.responseText);
    }
    
    if( doc.readyState != 4 ) return;	// Not done cooking yet, ignore...
    
    // This switch is for handling the results of the Ajax call
    // f_success:         true if Ajax status was good
    // doc.responseText:  holds whatever text was output from the called URL
    // local_args:        array of additional arguments to update() (not including the action)
    if (action == 'test_therm' || action == 'backup' || action == 'delete_therm' || action == 'find_therms' || action == 'getcurrentstat' || action == 'getprogram' || action == 'settemp' || action == 'setfan' || action == 'setmode' || action == 'get_year_range' || action == 'sethold' || action == 'setprogram' || action == 'settime' || action == 'update_order' || action == 'update_thermostats' || action == 'uptime' || action == 'conditions' || action == 'forecast' || action == 'check_timeout')
{
    var ret_text = '';
    var ret_status = 100;
    var ret_success_data = '', ret_fail_response = ''; 
    if (f_success == true)
    {
       ret_text = JSON.parse(doc.responseText);
       ret_status = ret_text['status'];

       if (ret_status == 0)
       {
          ret_success_data = ret_text['data'];
       }
       else
       {
          var tmp = ret_text['data'];
          ret_fail_response = tmp['response'];
       }
    }
}
else if (action != 'logout')
{
   console.log("action: "+action+" has not been updated to handle the new return scheme");
}
    switch( action )
	{
            case 'find_therms':
            {
               var f_glob_found_invalid = 0, f_glob_found_good = 0, f_glob_found_duplicate = 0;
               var counter, results_text = "";
               if (f_success && ret_status == 0)
               {
               var found_therms = ret_success_data;
               // console.log(" find_therms: length: "+found_therms.length+" "+found_therms);
               const table = document.getElementById('therm_table_admin');
               const rows = table.rows;

               for (counter = 0; counter < found_therms.length; counter++)
               {
                  var check_row = 0, f_found_duplicate = 0;;

                  for ( check_row = 1; check_row < rows.length; check_row++)
                  {
                     if (found_therms[counter] == rows[check_row].cells[3].children[0].value)
                     {
                        // Duplicate ip address
                        // console.log("find_therms: Duplicate ip: "+rows[check_row].cells[3].children[0].value);
                        f_found_duplicate = 1;
                     }
                  }
                  if (f_found_duplicate == 0)
                  {
                     if (ValidateIPaddress(found_therms[counter]) != false)
                     {
                        add_row(found_therms[counter]);
                        f_glob_found_good++;
                     }
                     else
                     {
                        console.log("find_therms: Invalid ip: "+rows[check_row].cells[3].children[0].value);
                        f_glob_found_invalid++;
                     }
                  }
                  else
                  {
                     f_glob_found_duplicate++;
                  }
               } 
               if (found_therms.length == 0)
               {
                  results_text = "Did not find any thermostats on the network";
               }
               else
               {
                  var clauses = 0;
                  results_text = 'Found';
                  if (f_glob_found_good > 0)
                  {
                     results_text = results_text+' '+f_glob_found_good+' new'; 
                     clauses++;
                  }
                  if (f_glob_found_invalid > 0)
                  {
                     if (clauses > 0)
                     {
                        if (f_glob_found_duplicate == 0)
                        {
                           results_text = results_text+' and';
                        }
                        else
                        {
                           results_text = results_text+',';
                        }
                     }
                     results_text = results_text+' '+f_glob_found_invalid+' invalid'; 
                     clauses++;
                  }
                  if (f_glob_found_duplicate > 0)
                  {
                     if (clauses > 0)
                     {
                        results_text = results_text+' and';
                     }
                     results_text = results_text+' '+f_glob_found_duplicate+' known'; 
                     clauses++;
                  }
                  results_text = results_text+' thermostat';
                  if (found_therms.length > 1)
                  {
                     results_text = results_text+'s';
                  } 
               }
               }
               else
               {
                 // Ajax call failure
               }

               document.getElementById( 'find_therms_button' ).removeAttribute('disabled');
            
               if (f_success && ret_status != 0)
               {
                  results_text = ret_fail_response;
               }
               else if (!f_success)
               {
                  results_text = "Unknown failure";
               }
               document.getElementById( 'find_therms_text' ).textContent = results_text;
            }
            break;
            case 'get_year_range':
	    {
                if (f_success && ret_status == 0)
                {
		   set_compare_year_dropdowns(ret_success_data);
                   if (local_args.length > 0 && local_args[0] == 'redraw')
                   {
                      // Sort of a special case when the "Compare" tab first loads - to draw the first chart, we need to wait
                      // until we've populated the year dropdowns.  Perhaps better to leave it blank until the user actually
                      // selects something and clicks "Show" but all the other tabs autoload.  May want to save the therm id
                      // and year selections in a cookie and just start there next time
                      displayCompareChartExec();
                   }
	       }
            }
	    break;

            case 'logout':
            {
               // Nothing to do here?
               location.reload();
            }
            break;
            case 'check_timeout':
            {
               if (f_success && ret_status == 0)
               {
                  var return_data = ret_success_data['response'];

                  if (return_data == 'timeout')
                  {
                    // Check if we're seeing this change for the first time, refresh the screen to get go back to the "logged out" state
                    update('logout');
                    setCookie('c_isloggedin', 0);
                  }
                 else if (return_data == 'good')
                 {
                    // Nothing to do
                 }
                 else
                 {
                   // Check if we're seeing this change for the first time, refresh the screen to get go back to the "logged out" state
                   if (getCookie('c_isloggedin') == 1)
                   {
                      // This logout shouldn't be necessary if we got here because we first detected the login timeout in the backend but
                      // since this is a catch-all case, do it just to be safe.
                      update('logout');
                   }
                   setCookie('c_isloggedin', 0);
                 }
               }
            }
            break;
	    case 'conditions':
	    {
                if (f_success && ret_status == 0)
                {
		   document.getElementById( 'status' ).innerHTML = ret_success_data['response'];
                }
                else if (f_success && ret_status != 0)
                {
                   document.getElementById( 'status' ).innerHTML = ret_fail_response;
                }
                else
                {
                   document.getElementById( 'status' ).innerHTML = "Failed to get conditions";
                }

	    }
	    break;
	    
	    case 'uptime':
	    {
                if (f_success && ret_status == 0)
		{
                   document.getElementById( 'uptime' ).innerHTML = ret_success_data['response'];
                }
                else if (f_success && ret_status != 0)
                {
                   document.getElementById( 'uptime' ).innerHTML = ret_fail_response;
                }
                else
                {
                   document.getElementById( 'uptime' ).innerHTML = "Failed to get system uptime";
                }
	    }
	    break;

	    case 'forecast':
	    {
                if (f_success && ret_status == 0)
                {
		   document.getElementById( 'forecast' ).innerHTML = ret_success_data['response'];
                }
                else if (f_success && ret_status != 0)
                {
                   document.getElementById( 'forecast' ).innerHTML = ret_fail_response;
                }
                else
                {
                   document.getElementById( 'forecast' ).innerHTML = "Failed to get forecast";
                }
	    }
	    break;
	    
	    case 'backup':
	    {
                var output_text;
                if (f_success)
                {
                   if (ret_status == 0)
                   {
                      output_text = ret_success_data['response'];
                   }
                   else
                   {
                      output_text = ret_fail_response;
                   }
                }
                else
                {
                   output_text = "Request failed";
                }
                document.getElementById( 'backup' ).innerHTML = output_text;
                document.getElementById( 'backup_button' ).removeAttribute('disabled');
	    }
	    break;
	    
	    case 'getcurrentstat':
	    {
		if (f_success == true && ret_status == 0)
		{
		    document.getElementById( 'sched_sched_rescan' ).value = "Get Current Status";

		    rescan_currentstat(ret_success_data);
                    set_schedule_elements_readwrite('false');
// MJH the success case seems to disable the save of the other program (As if the schedule we have is no longer valid) - is that intentional?  Did we screw up its data while loading this schedule?
		}
		else
		{
                    //set_schedule_elements_readwrite('false');
                    // Let them know it failed but let them try again
		    document.getElementById( 'sched_sched_rescan' ).value = "Failed";
                    document.getElementById( 'sched_sched_rescan' ).removeAttribute('disabled');
// MJH problems here - buttons don't get reenabled properly
		    //eescan_currentstat(doc.responseText);
		}
	    }
	    break;
   	    case 'getprogram':
	    {
               var heat_or_cool;

               if (local_args.length > 0 && local_args[0] != '')
               {
		  if (local_args[0] == 'cool')
                  {
                     heat_or_cool = 'cool';
                     set_schedule_cool_fieldset_readwrite();
                  }
                  else if (local_args[0] == 'heat')
                  {
                     heat_or_cool = 'heat';
                     set_schedule_heat_fieldset_readwrite();
                  }
                  else
                  {
                     // failure - not sure what failed, so can't indicate it anywhere - just open the user interface again
                     set_schedule_elements_readwrite('false');
                  }
               }
	       if (f_success == true && ret_status == 0)
	       {
		  document.getElementById( 'sched_getprog_'+heat_or_cool ).value = "GetProg";
                  rescan(JSON.stringify(ret_success_data), heat_or_cool);
	       }
	       else
	       {
	    	  document.getElementById( 'sched_getprog_'+heat_or_cool ).value = "Failed";
	       }
               // Re-enable the interface
               set_schedule_elements_readwrite('false');
            }
            break;
            case 'setprogram':
            {
               var heat_or_cool;

               if (local_args.length > 0 && (local_args[0] == 'cool' || local_args[0] == 'heat'))
               {
                  heat_or_cool = local_args[0];

                  if (heat_or_cool == 'cool')
                  {
                     set_schedule_cool_fieldset_readwrite();
                  }
                  else if (heat_or_cool == 'heat')
                  {
                     set_schedule_heat_fieldset_readwrite();
                  }

                  if (f_success == true && ret_status == 0)
                  {
                     document.getElementById( 'sched_setprog_'+heat_or_cool ).value = "Save";
                  }
                  else
                  {
                     document.getElementById( 'sched_setprog_'+heat_or_cool ).value = "Failed";
                  }
               }
               set_schedule_elements_readwrite('false');
            }
            break;
	    case 'settemp':
	    {
               if (f_success == true && ret_status == 0)
               {
	          // Since we just overrode the program set temp, indcate so in the text box by changing the color
		  document.getElementById( 'sched_cur_set_temp').style.backgroundColor = '#ee55ee';
		  document.getElementById( 'sched_cur_set_temp').title = 'Program currently overriden';
                  set_schedule_elements_readwrite('false');
               }
               else
               {
                  // Maybe not super wise to initiate another query to the thermostat since we probably failed
                  // due to lack of access to the thermostat, but this will reset the temperature bar and text box
                  // and re-enable the buttons for us
                  update('getcurrentstat');
               }
	    }
	    break;
	    case 'sethold':
	    {
               if (f_success == true && ret_status == 0)
               {
		  if (document.getElementById('sched_cur_hold').value == 'On')
	  	  {
		    document.getElementById('sched_cur_hold').value = 'Off';
                    // If we just turned the hold off, it will go back to the program, so go scan the current set temp
                    update('getcurrentstat');
		  }
		  else if (document.getElementById('sched_cur_hold').value == 'Off')
		  {
		    document.getElementById('sched_cur_hold').value = 'On';
                    // If we just turned the hold on, no need to udpate anything, just unfreeze the interface
                    set_schedule_elements_readwrite('false');
		  }
		  else
		  {
		    // Neither "On" nor "Off" ignore - something went wrong, just unfreeze the interface
                    set_schedule_elements_readwrite('false');
		  }
		  document.getElementById('sched_cur_hold').style.backgroundColor = '';
                }
                else
                {
                   // Something went wrong, set the button red
		   document.getElementById('sched_cur_hold').style.backgroundColor = '#ff5050';
                   set_schedule_elements_readwrite('false');
                }
	    }
	    break;
            case 'delete_therm':
            {
               var index = 99;
               var tr_id;
               if (local_args.length <= 0 || local_args[0] < 0 || local_args[0] >= 10)
               {
                  // failure
                  return;
               }
               else
               {
                  index = local_args[0];
               }
               console.log("delete_therm: args len: "+local_args.length+" id: "+index+" Success? "+f_success+" return status: "+ret_status);
               if (f_success == true && ret_status == 0)
               {
                  setCookie( 'adm_name_'+index,    null);
                  setCookie( 'adm_desc_'+index,    null);
                  setCookie( 'adm_ip_'+index,      null);
                  setCookie( 'adm_enabled_'+index, null);

                  tr_id = document.getElementById('adm_row_'+index);
                  document.getElementById('adm_delete_'+index).style.backgroundColor = '#00ff00';
                  tr_id.remove();
                  location.reload();
               }
               else
               {
                  // Can't delete the row from the display since we failed to delete it from the db
                  document.getElementById('adm_delete_'+index).style.backgroundColor = '#ff0000';
                  document.getElementById('adm_delete_'+index).disabled = false;
               }
            }
            break;
            case 'test_therm':
            {
               var index = 99;

               if (local_args.length <= 0 || local_args[0] < 0 || local_args[0] >= 10)
               {
                  // failure
                  // Probably should reload the screen here since we didn't even have a valid thermostat ID and, presumably, we 
                  // left a "Test" button greyed out.
                  return;
               }
               else
               {
                  index = local_args[0];
               }
               if (f_success == true && ret_status == 0)
               {
		  var therm_info = ret_success_data;

                  // We update the thermostat list columns with what we learned from querying the thermostat
                  // Note this is primary for adding a new thermostat, but will overwrite the data of an existing one too
                  // If something DOES come back from a known thermostat that is different, we probably need to automatically
                  // set "editable" on that thermostat so that it can be saved.  Or maybe just don't update the columns for
                  // a non-editable thermostat?
                  if (therm_info['name'] != "")
                  {
                     document.getElementById('adm_name_'+index).value = therm_info['name'];
                  }
                  document.getElementById('adm_fw_version_'+index).innerHTML = therm_info['fw_version'];
                  document.getElementById('adm_wlan_fw_version_'+index).innerHTML = therm_info['wlan_fw_version'];
                  document.getElementById('adm_model_'+index).innerHTML = therm_info['model'];
                  document.getElementById('adm_uuid_'+index).innerHTML = therm_info['uuid'];

                  document.getElementById('adm_test_'+index).style.backgroundColor = '#00ff00';

                  // call to check all of the uuids for duplicates here
                  check_uuids(false);
               }
               else
               {
                   // Update something on the account screen to indicate a failed test
                   document.getElementById('adm_test_'+index).style.backgroundColor = '#ff0000';
                   location.reload(); // MJH This should go away once all AJAX is using the standard response scheme and this reload can be placed in a generic location above
               }
               document.getElementById('adm_test_'+index).disabled = false;
            }
            break;
            case 'update_thermostats':
            {
               // console.log("Got to end of update_thermostats");
               // should update something to show we're done
               if (f_success == true && ret_status == 0)
               {
                  var cur_row, any_changed = 0;

                  document.getElementById('save_thermostats_text').innerHTML = 'Successfully saved thermostat properties';

                  const table = document.getElementById('therm_table_admin');
                  const rows = table.rows;

                  // The rows actually start at 0 but row 0 is the header.  So it really IS correct to start at 1 and go to (rows.length-1)
                  for ( cur_row = 1; cur_row < rows.length; cur_row++)
                  {
                     const row = rows[cur_row];
                     var counter = row.cells[11].textContent; // Should probably sanity check this
                     
                      console.log("Therm "+counter+" is "+document.getElementById('adm_name_'+counter).disabled);
                      if (document.getElementById('adm_name_'+counter).disabled != true)
                      {
                         document.getElementById('adm_name_'+counter).disabled = true;
                         document.getElementById('adm_desc_'+counter).disabled = true;
                         document.getElementById('adm_ip_'+counter).disabled = true;
                         document.getElementById('adm_enabled_'+counter).disabled = true;
                         document.getElementById('adm_edit_'+counter).value = "Edit";
                         document.getElementById('adm_id_'+counter).setAttribute('data-saved', 'yes');
                         any_changed = 1; 
                      }
                   }
                   //  This reload is to get the various thermostat drop downs to update if we enabled or disabled any thermostats
                   //  What should really happen is code that tweaks the options of those dropdowns directly from here, without
                   //  forcing a page reload.  It should also only do that if we know we're enabling/disabling a thermostat.
                   //  that might need to get passed in somehow.
                   if (any_changed != 0)
                   {
                      location.reload();
                   }
               }
               else if (f_success == true && ret_status != 0)
               {
                  document.getElementById('save_thermostats_text').innerHTML = ret_fail_response;
               }
               else
               {
                  document.getElementById('save_thermostats_text').innerHTML = 'Failed to save thermostat properties';
               }
            }
            break;
            case 'update_order':
            {
               //console.log("Got to end of update_order");
               // should update something to show we're done
               if (f_success == true && ret_status == 0)
               {
                  document.getElementById('save_order_text').innerHTML = 'Successfully saved thermostat order';
                  // Need to reload to get all the thermostat drop downs to update.  That should really be done dynamically
                  // via javascript but this will do for now
                  location.reload();
               }
               else if (f_success == true)
               {
                  document.getElementById('save_order_text').innerHTML = ret_fail_response;
               }
               else
               {
                  document.getElementById('save_order_text').innerHTML = 'Failed to save thermostat order';
               }
            }
            break;
	    case 'settime':
	    {
                // We really only want to update the time here but there's no method of getting only the time
                // just yet
               if (f_success == true && ret_status == 0)
               {
                  update('getcurrentstat');
		  document.getElementById('sched_cur_set_time').style.backgroundColor = '';
               }
               else
               {
		  document.getElementById('sched_cur_set_time').style.backgroundColor = '#ff5050';
                  set_schedule_elements_readwrite('false');
               }
	    }
	    break;
	    case 'setfan':
	    {
               if (f_success == true && ret_status == 0)
               {
		  if (document.getElementById('sched_cur_fan_mode').value == 'On')
		  {
	  	    document.getElementById('sched_cur_fan_mode').value = 'Auto';
		  }
		  else if (document.getElementById('sched_cur_fan_mode').value == 'Auto')
		  {
		    document.getElementById('sched_cur_fan_mode').value = 'On';
		  }
		  else
		  {
		    // Neither "On" nor "Auto" ignore
		  }
                  // Reset the color back to default
		  document.getElementById('sched_cur_fan_mode').style.backgroundColor = '';
                }
                else
                {
                   // We failed - turned the button red to indicate it MJH
		   document.getElementById('sched_cur_fan_mode').style.backgroundColor = '#ff5050';
                }

                set_schedule_elements_readwrite('false');
	    }
	    break;
	    case 'setmode':
	    {
               if (f_success == true && ret_status == 0)
               {
		  var change_mode_resp = ret_success_data;
		  var cur_mode = change_mode_resp['mode'] 
		
		  setCookie( 'schedule.mode', cur_mode );
               }
               update('getcurrentstat');
	    }
	    default:
	    // Do nothing - not even complain!
	    break;
	}

        // We may have detected the logout as part of the ajax call.  We'll handle the failure
        // but then we want to trigger the actual logout processing
        if (f_success == true && ret_status == 99)
        {
           // Looks like got logged out, call the logout routine
           update('logout');
        }
}

/**
	* Make Ajax call to get present data to fill in blanks on the dashboard
	*/
function update(action, ...args)
{
    var xmlDoc;
	// Need to add the Wheels icon to the sprite map and set relative position in the thermo.css file

    var local_args = args;
        if (args.length)
        {
           console.log("action: "+action+" args: "+args.toString());
        }
        // This switch is for actions to take just before kicking off the Ajax request
        // In theory, this and the generation of the Ajax request could be done in one switch
        // Do things like update the screen to indicate that an action is in progress, here.
	switch( action )
	{
                case 'find_therms':
                {
                   document.getElementById( 'find_therms_button' ).disabled = true;
                   document.getElementById( 'find_therms_text' ).textContent = 'Searching for thermostats';
                }
                break;
                case 'get_year_range':
                {
                   // nothing to do here?  In other cases this spot is used to indicate in the page that we're acting on something
                   break;
                }
                case 'logout':
                {
                   // nothing to do here?  In other cases this spot is used to indicate in the page that we're acting on something
//                   console.log("Starting logout");
                   break;
                }
                case 'check_timeout':
                {
                   // nothing to do here
                   break;
                }
		case 'conditions':
                {
                   document.getElementById(   'status' ).innerHTML = "<p class='dashboard'><table><tr><td><img src='images/img_trans.gif' width='1' height='1' class='wheels' /></td><td>Looking up present status and conditions. (This may take some time)</td></tr></table></p>";
                }
		break;
		
		case 'uptime':
                {
                   // nothing to do here?  In other cases this spot is used to indicate in the page that we're acting on something
                   break;
                }
		case 'forecast':
                {
                   // The hidden input field may only have a string value, it may not have a Boolean, so have to test for literal "false"
// We'll figure out whether forecast is enabled or not once we make the Ajax call since it's defined in the php config
/*
                   if( document.getElementsByName('useForecast')[0].value === 'false' )
                   {
                      // Only bother asking for info if we're set up to ask for it.
                      return;
                   }
*/
                   document.getElementById(   'forecast' ).innerHTML = "<p class='dashboard'><table><tr><td><img src='images/img_trans.gif' width='1' height='1' class='wheels' /></td><td>Looking up current forecast</td></tr></table></p>";
                }
		break;
		
		case 'backup':
                        document.getElementById( 'backup_button' ).disabled = true;
			document.getElementById( 'backup' ).innerHTML = 'Backup started...';
		break;

	    case 'getcurrentstat':
	    {
                set_schedule_elements_readwrite('false');
		document.getElementById( 'sched_sched_rescan' ).value = 'Updating';
                set_schedule_elements_readonly();
	    }
	    break;
	    case 'getprogram':
     	    {
               if (local_args.length != 1)
               {
                   // failure
                   return;
               }
               set_schedule_elements_readwrite('false');
	       document.getElementById( 'sched_getprog_'+local_args[0] ).value = 'Reading';
               set_schedule_elements_readonly();
	    }
	    break;
	    case 'setprogram':
            {
               if (local_args.length > 0 && (local_args[0] == 'cool' || local_args[0] == 'heat'))
               {
                  var heat_or_cool = local_args[0];

                  if (heat_or_cool == 'cool')
                  {
                     set_schedule_elements_readwrite('false');
                     document.getElementById( 'sched_setprog_cool' ).value = 'Saving';
                  }
                  else if (heat_or_cool == 'heat')
                  {
                     set_schedule_elements_readwrite('false');
                     document.getElementById( 'sched_setprog_heat' ).value = 'Saving';
                  }
               }
               set_schedule_elements_readonly();
            }
            break;
	    case 'sethold':
	    {
		// probably should indicate somewhere that we're updating the thermostat
                set_schedule_elements_readonly();
	    }
	    break;
	    case 'settemp':
	    {
                set_schedule_elements_readwrite('false');
		// probably should indicate somewhere that we're updating the thermostat
                set_schedule_elements_readonly();
	    }
	    break;
	    case 'setfan':
	    {
                set_schedule_elements_readwrite('false');
		// probably should indicate somewhere that we're updating the thermostat
                set_schedule_elements_readonly();
	    }
	    break;
            case 'delete_therm':
            {
                var index = 0;

                // Max possible thermostats of 10?  I'd rather make this dynamic dependent on the current situation
                if (args.length <= 0 || args[0] < 0 || args[0] >= 10)
                {
                   // failure
                   return;
                }
                else
                {
                   index = args[0];
                }

                let text = "Are you sure you want to delete "+document.getElementById('adm_name_'+index).value;
                if (confirm(text) == false) {
                   console.log("Delete cancelled");
                   document.getElementById('adm_delete_'+index).disabled = false;
                   return;
                }
                if (document.getElementById('adm_enabled_'+index).checked == true)
                {
                   text = "This thermostat is currently enabled.\nAre you REALLY sure you want to delete it?";
                   if (confirm(text) == false) {
                      console.log("Delete 2 cancelled");
                      document.getElementById('adm_delete_'+index).disabled = false;
                      return;
                   }
                }

               // If we never saved this thermostat to the DB then just delete the row and skip the ajax call to update the disk DB
               if (document.getElementById('adm_id_'+index).getAttribute('data-saved') == 'no')
               {
                  var tr_id;
                  console.log("This thermostat was never saved to the DB "+index);
                  setCookie( 'adm_name_'+index,    null);
                  setCookie( 'adm_desc_'+index,    null);
                  setCookie( 'adm_ip_'+index,      null);
                  setCookie( 'adm_enabled_'+index, null);

                  tr_id = document.getElementById('adm_row_'+index);
                  document.getElementById('adm_delete_'+index).style.backgroundColor = '#00ff00';
                  tr_id.remove();
                  check_uuids(false);
                  return;
               }
               else
               {
                  document.getElementById('adm_delete_'+index).style.backgroundColor = '';
                  document.getElementById('adm_delete_'+index).disabled = true;
               }
            }
            break;
            case 'test_therm':
            {
                var index = 0;

                // Max possible thermostats of 10?  I'd rather make this dynamic dependent on the current situation
                if (args.length <= 0 || args[0] < 0 || args[0] >= 10)
                {
                   // failure
                   return;
                }
                else
                {
                   index = args[0];
                }

                // Should probably indicate that we're working on the thermostat test
                document.getElementById('adm_test_'+index).style.backgroundColor = '';
                document.getElementById('adm_test_'+index).disabled = true;
            }
            break;
            case 'update_thermostats':
            {
                // Should probably indicate that we're updating the database
                document.getElementById('save_thermostats_text').innerHTML = 'Updating the database with new thermostat properties';
            }
            break;
            case 'update_order':
            {
                // Should probably indicate that we're updating the database
                document.getElementById('save_order_text').innerHTML = 'Updating the database with new thermostat order';
            }
            break;
	    case 'settime':
	    {
                set_schedule_elements_readwrite('false');
		// probably should indicate somewhere that we're updating the thermostat
                set_schedule_elements_readonly();
	    }
	    break;
	    case 'setmode':
                set_schedule_elements_readwrite('false');
	        // probably should indicate somewhere that we're updating the thermostat
                set_schedule_elements_readonly();
	    break;
		default:
			// Do nothing - not even complain!
		break;
	}
	
	// Please someone test that this actually works in IE (I don't even have IE on my system)
	if( typeof window.ActiveXObject != 'undefined' )
	{
		xmlDoc = new ActiveXObject( 'Microsoft.XMLHTTP' );
		xmlDoc.onreadystatechange = function(){ processAjaxResponse( xmlDoc, action, args ); };
	}
	else
	{
		xmlDoc = new XMLHttpRequest();
		xmlDoc.ontimeout = function(){return processAjaxResponse( xmlDoc, action, args ); };	// Can use arguments in here if I wrap in an anonymous function
		xmlDoc.onload = function(){return processAjaxResponse( xmlDoc, action, args ); };	// Can use arguments in here if I wrap in an anonymous function
	}
	xmlDoc.timeout = 60000; // 60s timeout

	// Replace use of session ID here with thermo.seed and a pseudorandom generator.  Send both the prng and the iteration number
	// On server side, check that iteration number matches what it should be (each one used once and that the prng is right (because seed is stored there too)
	//var session_id = getCookie( 'thermo.session' );
	var heat_or_cool; // This is hear because we use this in some cases which fall through to the next case, so can't declare it there

        // Can set POST data as "foo=bar&foo2=bar3&etc=more" rather than including them in the URL which is less secure
        var params = ''; 
	switch( action )
	{
            case 'find_therms':
            {
                var no_cache_string = 'nocache=' + Math.random();
                var urlString;

                urlString = 'php/find_therms.php?'+no_cache_string;
                //console.log(urlString);
                xmlDoc.open( 'GET', urlString, true );
                xmlDoc.setRequestHeader('Accept', 'application/json');

            } 
            break;
	    case 'get_year_range':
	    {
               var show_thermostat_id = 'id=' + document.getElementById( 'chart.compare.thermostat' ).value;

                var no_cache_string = 'nocache=' + Math.random();
                var urlString;

                urlString = 'php/get_year_range.php?'+show_thermostat_id+"&"+no_cache_string;
                //console.log(urlString);
                xmlDoc.open( 'GET', urlString, true );
                xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
            case 'logout':
	    {
                urlString = 'php/logout.php'
                xmlDoc.open( 'GET', urlString, true );
                xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
            break;
            case 'check_timeout':
	    {
		xmlDoc.open( 'GET', 'php/check_timeout.php', true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
            break;
	    case 'conditions':
	    {
		xmlDoc.open( 'GET', 'php/get_instant_status.php', true );
	    }
	    break;
	    
	    case 'uptime':
	    {
		xmlDoc.open( 'GET', 'php/get_uptime.php', true );
	    }
	    break;

	    case 'forecast':
	    {
		xmlDoc.open( 'GET', 'php/get_instant_forecast.php', true );
	    }
	    break;
	    
	    case 'backup':
	    {
		xmlDoc.open( 'GET', 'php/backup.php', true );
	    }
	    break;
	    
	    case 'getcurrentstat':
	    {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		
		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/getcurrentstat.php?'+show_thermostat_id+"&"+no_cache_string;
		xmlDoc.open( 'GET', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
	    
	    case 'getprogram':
            {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		
		var no_cache_string = 'nocache=' + Math.random();
		var urlString;

		if (local_args.length != 1 || (local_args[0] != 'cool' && local_args[0] != 'heat'))
                {
                   // failure
                   return;
                }

		urlString = 'php/getprogram.php?'+show_thermostat_id+"&"+no_cache_string+"&heat_or_cool="+local_args[0];
		xmlDoc.open( 'GET', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
            case 'setprogram':
            {
               var heat_or_cool;

               if (local_args.length > 0 && local_args[0] != '')
               {
                  heat_or_cool = local_args[0];
                  if (heat_or_cool == 'heat' || heat_or_cool == 'cool')
                  {
                     var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
 
                     var no_cache_string = 'nocache=' + Math.random();
                     var urlString;
                     var day = 0, period = 0;
                     var sched_string = '{';
 		
                     var hoc_offset = "0";
 
                     if (heat_or_cool == 'heat')
                     {
                        hoc_offset = "1";
                     }
 
                     for (day = 0; day < 7; day++)
                     {
                        // Monday = 0 for the thermostat
                        // Sunday = 0 for indexing for us
                        var use_day = (day + 1)%7;
 
                        sched_string = sched_string+'"'+day+'":[';
 					
                        for (period = 0; period < 4; period++ )
                        {
                           var timeParts;
                           var minutes;
 			 								
                           timeParts = (document.getElementById( 'd'+use_day+'p'+period+'time'+hoc_offset ).value).split(":");
                           minutes = Number(timeParts[0]) * 60 + Number(timeParts[1])
                           sched_string = sched_string+minutes+',';
                           sched_string = sched_string+document.getElementById( 'd'+use_day+'p'+period+'temp'+hoc_offset ).value;
  
                           if (period != 3)
                           {
                              sched_string = sched_string+',';
                           }
                        }
                        sched_string = sched_string+']';
                        if (day < 6)
                        {
                           sched_string = sched_string+',';
                        }
                     }
                     sched_string = sched_string+'}}';

                     var urlString = 'php/setprogram.php?'+show_thermostat_id+"&"+no_cache_string+"&newsched="+sched_string+"&heat_or_cool="+heat_or_cool;
                     xmlDoc.open( 'GET', urlString, true );
                     xmlDoc.setRequestHeader('Accept', 'application/json');
                  }
                  else 
                  {
                    // failure
                    return;
                  }
               }
               else 
               {
                 // failure
                 return;
               }
            }
            break;
	    case 'sethold':
	    {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		var hold_value = document.getElementById('sched_cur_hold').value;
		var hold_string;
		
		if (hold_value == 'On')
		{
		    hold_string = 'hold=Off';
		}
		else if (hold_value == 'Off')
		{
		    hold_string = 'hold=On';
		}
		else
		{
		    hold_string = 'hold=none';
		}

		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/sethold.php?'+show_thermostat_id+"&"+hold_string+"&"+no_cache_string;

		xmlDoc.open( 'GET', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
	    case 'settemp':
	    {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		var temp_value = document.getElementById('sched_cur_set_temp').value;
		var temp_string;
		
		temp_string = 'temp='+temp_value;
		if (getCookie( 'schedule.mode') == 'Heat')
		{
		    mode_string = "mode=heat";
		}
		else if (getCookie( 'schedule.mode') == 'Cool')
		{
		    mode_string = "mode=cool";
		}
		else
		{
		    mode_string = "mode=none";
		}

		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/settemp.php?'+show_thermostat_id+"&"+temp_string+"&"+mode_string+"&"+no_cache_string;

                xmlDoc.open( 'POST', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
            case 'delete_therm':
            {
                var index = 0;

                // What can we sanity check against?  Max highest thermostat id of 10?  
                // In theory it could be really high if enough thermostats were added
                // then deleted, over time
                if (args.length <= 0 || args[0] < 0 || args[0] >= 10)
                {
                   // failure
                   return;
                }
                else
                {
                   index = args[0];
                }

                var no_cache_string = 'nocache=' + Math.random();
                var urlString = 'php/delete_thermostat.php?id='+index+"&"+no_cache_string;
                console.log("delete_therm: "+urlString);

                xmlDoc.open( 'POST', urlString, true );
                xmlDoc.setRequestHeader('Accept', 'application/json');
            }
            break;
            case 'test_therm':
            {
                var index = 0, any_changed = 0;
                var therm_array = {};
                // Max possible thermostats of 10?  I'd rather make this dynamic dependent on the current situation
                if (args.length <= 0 || args[0] < 0 || args[0] >= 10)
                {
                   // failure
                   return;
                }
                else
                {
                   index = args[0];
                }

                var element = document.getElementById('adm_uuid_'+index);

                // Using the display order selector to loop over since we know this value won't be null because the web page
                // is in control of its value (unlike the other three properties we're dealing with here)
                if (element != null)
                {
                   console.log("Id: "+index+" value: "+(element).value);
                   therm_array['id'] = index;
                   therm_array['name'] = document.getElementById('adm_name_'+index).value;
                   therm_array['desc'] = document.getElementById('adm_desc_'+index).value;
                   therm_array['ip']   = document.getElementById('adm_ip_'+index).value;
                   therm_array['enabled']   = document.getElementById('adm_enabled_'+index).checked;
                   if (ValidateIPaddress(therm_array['ip']) == false)
                   {
                     console.log("Bad ip address: "+therm_array['ip']);
                     document.getElementById('adm_ip_'+index).setAttribute('data-problem', 'yes');
                     document.getElementById('adm_ip_'+index).style.backgroundColor = '#ff0000';
                     document.getElementById('adm_test_'+index).style.backgroundColor = '#ff0000';
                     document.getElementById('adm_test_'+index).disabled = false;
                     return;
                   }
                   any_changed = 1;
                }
                else
                {
                   // failure
                   console.log("test_therm: no changes to save");
                   return;
                }

                var no_cache_string = 'nocache=' + Math.random();
                var urlString = 'php/test_thermostat.php?therm='+JSON.stringify(therm_array)+"&"+no_cache_string;
                console.log("test_therm: "+urlString);

                xmlDoc.open( 'POST', urlString, true );
                xmlDoc.setRequestHeader('Accept', 'application/json');
            }
            break;

            case 'update_thermostats':
            {
                var problem = 0;
                var counter = 0, any_changed = 0;
                var therm_array = {};

                problem = check_uuids(true);

                const table = document.getElementById('therm_table_admin');
                const rows = table.rows;

                 // The rows actually start at 0 but row 0 is the header.  So it really IS correct to start at 1 and go to (rows.length-1)
                 for ( counter = 1; counter < rows.length; counter++) 
                 {
                    var check_row;
                    therm_array[counter] = {};
                    const row = rows[counter];
                    // Just checking to make sure that we had set, at least, the Name field enabled for editing, we on'y want to consider those therms for updating
                    if (row.cells[1].children[0].disabled != true)
                    {
                       therm_array[counter]['changed'] = 'yes';
                       therm_array[counter]['uuid'] = row.cells[0].textContent;
                       therm_array[counter]['name'] = row.cells[1].children[0].value;
                       therm_array[counter]['desc'] = row.cells[2].children[0].value;
                       therm_array[counter]['ip']   = row.cells[3].children[0].value;
                       therm_array[counter]['model']   = row.cells[4].textContent.trim();
                       therm_array[counter]['fw_version']   = row.cells[5].textContent;
                       therm_array[counter]['wlan_fw_version']   = row.cells[6].textContent;
                       therm_array[counter]['enabled']   = row.cells[7].children[0].checked;
                       therm_array[counter]['id'] = row.cells[11].textContent;

                       therm_array[counter]['changed'] = 'yes';
                       any_changed = 1;
                    }
                    else
                    {
                        // We did not change this thermostat
                        therm_array[counter]['changed'] = 'no';
                    }

                 }
                 if (problem != 0)
                 {
                    return;
                 }

                if (any_changed == 0)
                {
                   // console.log("update_thermostats: no changes to save");
                   document.getElementById('save_thermostats_text').innerHTML = 'No thermostat changes to save';
                   return; 
                }
                else
                {
                   // Force fresh of all the tabs
                }
		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/update_thermostats.php';
                params = "therms="+JSON.stringify(therm_array)+"&"+no_cache_string;

		xmlDoc.open( 'POST', urlString, true );
                xmlDoc.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xmlDoc.setRequestHeader('Accept', 'application/json');
            }
            break;
            case 'update_order':
            {
                var counter = 0, i = 0;
                const therm_array = [];
                const therm_array_check = [];
                var element = document.getElementById('acc_sel_'+counter);

                while (element != null)
                {
                   therm_array[counter] = Number((element).value);
              
                   counter++;
                   element = document.getElementById('acc_sel_'+counter);
                }
                for (i = 0; i < counter; i++)
                {
                   if (therm_array[i] >= counter || therm_array[i] < 0)
                   {
                      console.log("update_order: got a value that was out of range for thermostat "+i+": "+therm_array[i]);
                      document.getElementById('save_order_text').innerHTML = 'Failed: Thermostat '+i+' value is out of range '+therm_array[i];
                      return;
                   }
                   if (therm_array_check[therm_array[i]])
                   {
                      console.log("update_order: Same value ("+therm_array[i]+") was chosen more than once");
                      document.getElementById('save_order_text').innerHTML = 'Failed: Same value ('+therm_array[i]+') was chosen more than once';
                      return;
                   }
                   else
                   {
                      therm_array_check[therm_array[i]] = therm_array[i]+1;
                   }
                }
                for (i = 0; i < counter; i++)
                {
                   if (therm_array_check[i] != i+1)
                   {
                      console.log("update_order: The chosen order has a hole in it, no thermostat is at index: "+i);
                      document.getElementById('save_order_text').innerHTML = 'Failed: The chosen order has a hole in it, no thermostat is at index: '+i;
                      return;
                   }
                }

		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/update_display_order.php?order='+JSON.stringify(therm_array)+"&"+no_cache_string;
		console.log("update_order: "+urlString);

		xmlDoc.open( 'POST', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
            }
            break;

	    case 'settime':
	    {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		
		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/settime.php?'+show_thermostat_id+"&"+no_cache_string;

		xmlDoc.open( 'GET', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
	    case 'setfan':
	    {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		var fan_value = document.getElementById('sched_cur_fan_mode').value;
		var fan_string;
		
		if (fan_value == 'On')
		{
		    fan_string = 'fan=Auto';
		}
		else if (fan_value == 'Auto')
		{
		    fan_string = 'fan=On';
		}
		else
		{
		    fan_string = 'fan=none';
		}

		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/setfan.php?'+show_thermostat_id+"&"+fan_string+"&"+no_cache_string;

		xmlDoc.open( 'GET', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
	    case 'setmode':
	    {
		var show_thermostat_id = 'id=' + document.getElementById( 'chart.schedule.thermostat' ).value;
		var mode_value = getCookie( 'schedule.mode' );
		var mode_string;
		
		mode_string = 'mode=' + mode_value;
		
		var no_cache_string = 'nocache=' + Math.random();
		var urlString = 'php/setmode.php?'+show_thermostat_id+"&"+mode_string+"&"+no_cache_string;

		xmlDoc.open( 'GET', urlString, true );
		xmlDoc.setRequestHeader('Accept', 'application/json');
	    }
	    break;
	    
	    default:
	    {
		// Do nothing - not even complain!
	    }
	    break;
	}

    if (params != '')
    {
       xmlDoc.send( params );
    }
    else
    {
       xmlDoc.send( null );
    }
}

function set_schedule_heat_fieldset_readwrite()
{
  document.getElementById( 'sched_heat_fieldset' ).removeAttribute('disabled');
  document.getElementById( 'sched_heat_fieldset' ).removeAttribute('readonly');
  document.getElementById( 'sched_setprog_heat' ).removeAttribute('disabled');
}

function set_schedule_cool_fieldset_readwrite()
{
  document.getElementById( 'sched_cool_fieldset' ).removeAttribute('disabled');
  document.getElementById( 'sched_cool_fieldset' ).removeAttribute('readonly');
  document.getElementById( 'sched_setprog_cool' ).removeAttribute('disabled');
}

function set_schedule_elements_readwrite(f_not_target_temp)
{
//  document.getElementById( 'sched_cur_temp' ).removeAttribute('readonly');
   if (document.getElementById( 'sched_cur_heat' ))
      document.getElementById( 'sched_cur_heat' ).removeAttribute('disabled');
   if (document.getElementById( 'sched_cur_cool' ))
      document.getElementById( 'sched_cur_cool' ).removeAttribute('disabled');
   if (document.getElementById( 'sched_cur_off' ))
      document.getElementById( 'sched_cur_off' ).removeAttribute('disabled');
   if (f_not_target_temp != 'true')
   {
      if (document.getElementById( 'sched_cur_set_temp'))
      {
         document.getElementById( 'sched_cur_set_temp').removeAttribute('readonly');
      }
      if (document.getElementById( 'sched_cur_set_temp_slider'))
      {
         document.getElementById( 'sched_cur_set_temp_slider').removeAttribute('disabled');
      }
   }
   document.getElementById( 'sched_cur_fan_mode' ).removeAttribute('disabled');
   document.getElementById( 'sched_cur_hold' ).removeAttribute('disabled');
   document.getElementById( 'sched_sched_rescan' ).removeAttribute('disabled');
   document.getElementById( 'sched_coolSched' ).removeAttribute('readonly');
   document.getElementById( 'sched_heatSched' ).removeAttribute('readonly');
   document.getElementById( 'sched_getprog_cool' ).removeAttribute('disabled');
   document.getElementById( 'sched_getprog_heat' ).removeAttribute('disabled');
 
   document.getElementById( 'sched_cur_set_time' ).removeAttribute('disabled');
}
function set_schedule_heat_fieldset_readonly()
{
   document.getElementById( 'sched_heat_fieldset' ).disabled = true;
   document.getElementById( 'sched_heat_fieldset' ).readOnly = true;
}

function set_schedule_cool_fieldset_readonly()
{
   document.getElementById( 'sched_cool_fieldset' ).disabled = true;
   document.getElementById( 'sched_cool_fieldset' ).readOnly = true;
}

function set_schedule_elements_readonly()
{
   document.getElementById( 'sched_cur_temp' ).readOnly = true;
   if (document.getElementById( 'sched_cur_heat' ))
      document.getElementById( 'sched_cur_heat' ).disabled = true;
   if (document.getElementById( 'sched_cur_cool' ))
      document.getElementById( 'sched_cur_cool' ).disabled = true;
   if (document.getElementById( 'sched_cur_off' ))
      document.getElementById( 'sched_cur_off' ).disabled = true;
  document.getElementById( 'sched_cur_set_temp').readOnly = true;
  document.getElementById( 'sched_cur_set_temp_slider' ).disabled = true;
  document.getElementById( 'sched_cur_fan_mode' ).disabled = true;
  document.getElementById( 'sched_cur_hold' ).disabled = true;
  document.getElementById( 'sched_sched_rescan' ).disabled = true;
  document.getElementById( 'sched_coolSched' ).readOnly = true;
  document.getElementById( 'sched_heatSched' ).readOnly = true;
  document.getElementById( 'sched_getprog_cool' ).disabled = true;
  document.getElementById( 'sched_setprog_cool' ).disabled = true;
  document.getElementById( 'sched_getprog_heat' ).disabled = true;
  document.getElementById( 'sched_setprog_heat' ).disabled = true;
  document.getElementById( 'sched_getprog_heat' ).disabled = true;
  document.getElementById( 'sched_cur_set_time' ).disabled = true;
}

function set_compare_year_dropdowns(response)
{
    const selectElement1 = document.getElementById('chart.compare.firstDate');
    const selectElement2 = document.getElementById('chart.compare.secondDate');
 
    var orig_first_date, orig_second_date;
 
    // On a reload the drop downs won't be set up yet, and so we can only get the information from the cookies.
    // In theory, there's no case where the cookies won't exist but the elements' values do (unless someone clears cookies) but it's an ok safety net
    if (getCookie( 'chart.compare.firstDate' ) && getCookie( 'chart.compare.secondDate' ))
    {
       orig_first_date  = getCookie( 'chart.compare.firstDate' )
       orig_second_date = getCookie( 'chart.compare.secondDate' );
    }
    else
    {
       orig_first_date  = document.getElementById('chart.compare.firstDate').value;
       orig_second_date = document.getElementById('chart.compare.secondDate').value;
    }
 
    var year_array = response;
    var i = 0;
    var L = selectElement1.options.length - 1, K = selectElement2.options.length -1;
 
    for(i = L; i >= 0; i--) 
    {
       selectElement1.remove(i);
    }
    for(i = K; i >= 0; i--) 
    {
       selectElement2.remove(i);
    }

   for ( i = Number(year_array['earliest_year']) ; i <= Number(year_array['latest_year']) ; i++)
   {
      // First date can't be last possible year as we have no later year to compare to
      if (i != Number(year_array['latest_year']))
      {
         const newOption1 = document.createElement('option');
         newOption1.value = i;
         newOption1.text = i;
         selectElement1.appendChild(newOption1);
      }
      const newOption2 = document.createElement('option');
      newOption2.value = i;
      newOption2.text = i;
      selectElement2.appendChild(newOption2);
   }

  // If we had valid years in the two drop downs already, and they fall within the range of the currently chosen thermostat, set the values back to them
  if (orig_first_date != "" && orig_second_date != "" && orig_first_date >= Number(year_array['earliest_year']) && orig_first_date <= Number(year_array['latest_year']))
  {
     // The first selected year is still valid, so we're going to set the value back to it
     document.getElementById('chart.compare.firstDate').value = orig_first_date;

     if (orig_second_date >= Number(year_array['earliest_year']) && orig_second_date <= Number(year_array['latest_year']))
     {
        // If the first year was valid, check if second year is also valid.
        document.getElementById('chart.compare.secondDate').value = orig_second_date;
     }
  }
  else
  {
     // If we have to pick new years from the drop downs, pick the most recent years that fit within the range of years this thermostat has
     if (year_array['earliest_year'] == year_array['latest_year'])
     {
        // Don't adjust anything, just leave it with the same year even though the compare tab won't actually work like this - we have no choice
        return;
     }
     else
     {
        // If the previously picked years don't work for the new thermostat, find the most recent years that do and set those as default
        for (i = Number(year_array['latest_year']); i > Number(year_array['earliest_year']); i--)
        {
           if ((year_array['latest_year'] - 1) >= year_array['earliest_year'])
           {
              document.getElementById('chart.compare.firstDate').value = (year_array['latest_year']-1);
              document.getElementById('chart.compare.secondDate').value = year_array['latest_year'];
              break;
           }
        }
     }

  }
  adjust_year_dropdowns("foo");

}

function save_stat_order()
{
   var counter = 0;
   const therm_array = [];
   var element = document.getElementById('acc_sel_'+counter);

   while (element != null)
   {
      console.log("Id: "+counter+" value: "+(element).value);
      therm_array[counter] = (element).value; 
 
      counter++;
      element = document.getElementById('acc_sel_'+counter);
   }
}

function switch_style( css_title )
{
   console.log("Switch style: "+css_title);
   setCookie( 'theme', css_title);
   document.getElementById("pagestyle").setAttribute("href", "lib/tabs/tabs-"+css_title+".css");

   echart_draw();
   echart_compare_draw();
   echart_history_draw();

   return;
}
function get_current_theme()
{
   var cur_theme = 'green';
 
   if (getCookie('theme'))
   {
      cur_theme = getCookie('theme');
   }
 
   return cur_theme;
}

function set_editable( id )
{
   if (document.getElementById('adm_name_'+id).disabled == true)
   {
      // Remember the original values in a cookie
      setCookie( 'adm_name_'+id, document.getElementById('adm_name_'+id).value);
      setCookie( 'adm_desc_'+id, document.getElementById('adm_desc_'+id).value);
      setCookie( 'adm_ip_'+id,   document.getElementById('adm_ip_'+id  ).value);
      setCookie( 'adm_enabled_'+id,   document.getElementById('adm_enabled_'+id  ).checked);

      // And enable editing of this therms properties
      document.getElementById('adm_name_'+id).removeAttribute('disabled');
      document.getElementById('adm_desc_'+id).removeAttribute('disabled');
      document.getElementById('adm_ip_'+id).removeAttribute('disabled');
      document.getElementById('adm_enabled_'+id).removeAttribute('disabled');

      document.getElementById('adm_edit_'+id).value = "Reset";
   }
   else
   {
      // Get the original values from the cookie
      document.getElementById('adm_name_'+id).value = getCookie( 'adm_name_'+id);
      document.getElementById('adm_desc_'+id).value = getCookie( 'adm_desc_'+id);
      document.getElementById('adm_ip_'+id  ).value = getCookie( 'adm_ip_'+id);
      document.getElementById('adm_enabled_'+id  ).checked = getCookie( 'adm_enabled_'+id);

      // And disable editing of this therms properties
      document.getElementById('adm_name_'+id).disabled = true;
      document.getElementById('adm_desc_'+id).disabled = true;
      document.getElementById('adm_ip_'+id).disabled   = true;
      document.getElementById('adm_enabled_'+id).disabled   = true;

      document.getElementById('adm_edit_'+id).value = "Edit";
   }
}

function add_row(ip_address)
{
   var index = 0, counter = 0, i = 0;
   var table = document.getElementById('therm_table_admin');

   const rows = table.rows;
   //  Loop over all entries in the table, find the highest id # and then used the next highest
   for ( counter = 1; counter < rows.length; counter++)
   {
      const row = rows[counter];
      if (row.cells[11].textContent > index)
      {
         index = row.cells[11].textContent;
      }
   }
   index++;

   // Double check that somehow we didn't decide on an already used index
   if (document.getElementById('adm_uuid_'+index))
   {
      return;
   }

   var row = table.insertRow();
   row.id = 'adm_row_'+index;

   // Add the uuid column
   var uuid = row.insertCell(0);
   uuid.outerHTML = "<td align='left' id='adm_uuid_"+index+"' style='padding: 5px;'></td>";

   // Add the name column
   var name = row.insertCell(1);
   name.outerHTML = "<td align='left' style='padding: 5px;'><input class='therm_table_text_input' style='width: 10ch; background: white' disabled id='adm_name_"+index+"' value='New Thermostat'></td>";

   // Add the description column
   var desc = row.insertCell(2);
   desc.outerHTML = "<td align='left' style='padding: 5px;'><input class='therm_table_text_input' style='width: 10ch; background: white' disabled id='adm_desc_"+index+"' value='New Thermostat'></td>";

   // Add the ip address column
   var ip = row.insertCell(3);

   // Use the passed in ip address assuming it's valid
   if (ip_address == "" || ValidateIPaddress(ip_address) == false) 
   {
      console.log("Tried to add bad ip address: "+ip_address);
      ip_address = "0.0.0.0";
   }

   ip.outerHTML = "<td align='left' style='padding: 5px;'><input class='therm_table_text_input' style='background: white; width: 15ch' disabled id='adm_ip_"+index+"' value='"+ip_address+"' oninput='adm_clear_therm("+index+");'></td>";

   // Add the model column
   var model = row.insertCell(4);
   model.outerHTML = "<td align='center' id='adm_model_"+index+"' style='padding: 5px;'></td>"

   // Add the fw_version column
   var fw_version = row.insertCell(5);
   fw_version.outerHTML = "<td align='left' id='adm_fw_version_"+index+"' style='padding: 5px;'></td>"

   // Add the wlan_fw_version column
   var wlan_fw_version = row.insertCell(6);
   wlan_fw_version.outerHTML = "<td align='left' id='adm_wlan_fw_version_"+index+"' style='padding: 5px;'></td>"

   // Add the enabled column
   var f_enabled = row.insertCell(7);
   f_enabled.outerHTML = "<td align='left' style='text-align: center; padding: 5px;'><input type='checkbox' style='background: white' disabled id='adm_enabled_"+index+"'></td>";

   // Add the Edit button
   var edit_button = row.insertCell(8);
   edit_button.outerHTML = "<td align='center' style='padding: 5px;'><input class='therm_table_button' id='adm_edit_"+index+"' type='button' value='Edit' onClick='javascript: set_editable("+index+");'></td>"
   document.getElementById('adm_edit_'+index).disabled = true;

   // Add the Test button
   var test_button = row.insertCell(9);
   test_button.outerHTML = "<td align='center' style='padding: 5px;'><input class='therm_table_button' id='adm_test_"+index+"' type='button' value='Test' onClick='javascript: update(\"test_therm\", "+index+");'></td>"

   // Add the Delete button
   var delete_button = row.insertCell(10);
   delete_button.outerHTML = "<td align='center' style='padding: 5px;'><input class='therm_table_button' id='adm_delete_"+index+"' type='button' value='Delete' onClick='javascript: update(\"delete_therm\", "+index+", this);'></td>"

   // Add the hidden id column - it seems like there must be a better way of "saving" this information along with the row
   var id = row.insertCell(11);
   id.outerHTML = "<td align='left' style='visibility: hidden;' id='adm_id_"+index+"' style='padding: 5px;'>"+index+"</td>";
   document.getElementById('adm_id_'+index).setAttribute('data-saved', 'no');

   set_editable(index);
}

function test_therm(therm_id)
{
   console.log("test_therm() got called");
}

function adm_clear_therm(therm_id)
{
   var check_row, check_row2;
   const therm_ip_el = document.getElementById('adm_ip_'+therm_id);
   if (ValidateIPaddress(therm_ip_el.value))
   {
      therm_ip_el.style.backgroundColor = null;
      therm_ip_el.removeAttribute('data-problem');
      const therm_test_el = document.getElementById('adm_test_'+therm_id);
      therm_test_el.style.backgroundColor = null;
   }
   const table = document.getElementById('therm_table_admin');
   const rows = table.rows;

   for ( check_row = 1; check_row < rows.length; check_row++)
   {
      var found_duplicate = 0;
      for ( check_row2 = 1; check_row2 < rows.length; check_row2++)
      {
         
         if (check_row2 != check_row && rows[check_row2].cells[3].children[0].value == rows[check_row].cells[3].children[0].value)
         {
            found_duplicate = 1;
         }
      }
      if (found_duplicate == 0)
      {
         rows[check_row].cells[3].children[0].style.backgroundColor = "white";
      }
   }
}

function check_uuids(f_only_unsaved)
{
   var counter, check_row, problem = 0;
   const table = document.getElementById('therm_table_admin');
   const rows = table.rows;

   // The rows actually start at 0 but row 0 is the header.  So it really IS correct to start at 1 and go to (rows.length-1)
   for ( counter = 1; counter < rows.length; counter++)
   {
      var uuid_problem = 0, ip_problem = 0;
      const row = rows[counter];
      if (row.cells[7].children[0].disabled != true)
      {
         for ( check_row = 1; check_row < rows.length; check_row++)
         {
            if (check_row != counter)
            {
               if (row.cells[0].textContent != "" && row.cells[0].textContent == rows[check_row].cells[0].textContent)
               {
                  // Duplicate uuid
                  console.log("Duplicate uuid: "+rows[check_row].cells[0].textContent);
                  rows[check_row].cells[0].style.backgroundColor = '#ff0000';
                  row.cells[0].style.backgroundColor = '#ff0000';
                  document.getElementById('save_thermostats_text').innerHTML = "Duplicate uuid: "+rows[check_row].cells[0].textContent;
                  uuid_problem = 1;
               }

               if (row.cells[3].children[0].value == rows[check_row].cells[3].children[0].value)
               {
                  // Duplicate ip address
                  console.log("Duplicate ip: "+rows[check_row].cells[3].children[0].value);
                  rows[check_row].cells[3].children[0].style.backgroundColor = '#ff0000';
                  row.cells[3].children[0].style.backgroundColor = '#ff0000';
                  ip_problem = 1;
                  document.getElementById('save_thermostats_text').innerHTML = "Duplicate IP address: "+rows[check_row].cells[3].children[0].value;
               }
            }
   
         }

         if (ValidateIPaddress(row.cells[3].children[0].value) != true)
         {
             row.cells[3].children[0].style.backgroundColor = '#ff0000';
             ip_problem = 1;
             document.getElementById('save_thermostats_text').innerHTML = "Bad IP address: "+row.cells[3].children[0].value;
         }
         else
         {
             row.cells[3].children[0].removeAttribute('data-problem');
         }
      }
      if (uuid_problem != 1)
      {
         row.cells[0].style.backgroundColor = '';
      }
      if (ip_problem != 1)
      {
         row.cells[3].children[0].style.backgroundColor = 'white';
      }
      problem += (uuid_problem + ip_problem);
   }
   return problem;
}
function ValidateIPaddress(inputText)
{
   var ipformat = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
   if(inputText.match(ipformat) && inputText != "0.0.0.0")
   {
      return true;
   }
   else
   {
      return false;
   }
}

function fix_hover()
{
   const collection = document.getElementsByClassName("tab");
   for (let i = 0; i < collection.length; i++) 
   {
      var el = collection[i];
      var par = el.parentNode;
      var next = el.nextSibling;

      console.log(i+" Fixing: "+el.id);
      par.removeChild(el);
      setTimeout(function() {par.insertBefore(el, next);}, 0)
   }
}
