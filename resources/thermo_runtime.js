// Code to be run when loading the page

// Allow switching of the tabs to work with browser history
var hashLinks = document.querySelectorAll("a[href^='#']");
[].forEach.call(hashLinks, function (link) {
   link.addEventListener("click", function (event) {
      event.preventDefault();
      history.pushState({}, "", link.href);

      // Update the URL again with the same hash, then go back
      history.pushState({}, "", link.href);
      history.back();
   });
});

// This stuff helps the icons embedded in some tabs to show as a good size for the current scaling of the wondow
var range = 1/1000;

document.documentElement.style.setProperty('--icon-scale', `${range * window.innerWidth}`);

window.addEventListener('resize', () =>
{
   document.documentElement.style.setProperty('--icon-scale', `${range * window.innerWidth}`);
});

// Check login timeout every 1 minute
setInterval(check_login, 60000);

// Get the uptime once on load
update_uptime();

////////////////////////
/// Functions below here

// Update the uptime every 30 (30000 milliseconds) seconds update the uptime information and check to see if we should timeout the current login
// Currently disabled since doing an AJAX call just to keep the uptime updated on the About page seems super inefficient.  Maybe a websocket is the right answer?  Or maybe
// it just doesn't matter that much and we just update it on page refresh. (Which is what it does now)
//setInterval(update_uptime, 30000);
function update_uptime()
{
  update('uptime');
}

function check_login()
{
   // These cookies are used as a way to control how often we actually check the backend for a timeout
   // c_loggedin gets set from authentication.php when we log in and is cleared in logout.php
   // This is not necessary for correctness. In all cases we'll still detect the "truth" if an active AJAX call is made.  
   // This check is just for the user experience of the web page.

   // This avoids making an ajax call in cases where there's no point like:
   // 1) We're not logged in
   // 2) We clicked "disable auto logout" when logging in
   // Note: We do still check if We're connected via a "full access ip" address because we might have changed networks (say on a mobil device)
   if (getCookie('c_isloggedin') == 1 && getCookie('c_notimeout') != 1)
   {
      update('check_timeout');
   }
}

// Check to see if we're currently portrait or landscape, and if portrait, assume all we want to see are the controls
function port_or_land()
{
   var f_hide = false;
   var cookie_state = "";
   var cur_state = "";

   // Get the current orientation
   cur_state = 'land';
   if ((window.innerWidth / window.innerHeight) <= 1)
   {
      cur_state = 'port';
   }

   // Ge our last known orietntation from the cookie, if it exists
   if (getCookie( 'port_or_land' ))
   {
      cookie_state = getCookie('port_or_land');
   }

   // If the state has changed, update things appropriately, otherwise, do nothing
   if (cur_state != cookie_state && cookie_state != "")
   {

      // Remember the current state in a cookie so we can detect a change, later
      setCookie('port_or_land', cur_state);
      // Default to assuming we're in portrait mode
      f_hide = true;

      // Check if we're in landscape mode, if so, redraw the charts for those tabs
      if ((window.innerWidth / window.innerHeight) > 1)
      {
         // If the page was last loaded while in portrait, these would not have drawn, so draw them now
         echart_draw();
         echart_compare_draw();
         echart_history_draw();

         f_hide = false;
      }

      // Hide or unhide the tabs that are unlikely to be useful in portrait orientation
      hide_div('daily', f_hide);
      hide_div('dashboard', f_hide);
      hide_div('history', f_hide);
      hide_div('compare', f_hide);
      // Leave some tabs even in portait mode.  The assumption, though, is that the Control tab (schedule) is what is really wanted.
      //   hide_div('about', f_hide);
      //   hide_div('schedule', f_hide);
      //   hide_div('account', f_hide);

      // If we're switching to portrait from landscape we do some special stuff
      if (cur_state == 'port')
      {
         // Point ourselves at the Control tab (schedule) because that's almost certainly what we want to do on a Portrait device
         window.location.href = '#schedule';

         // This seems excessive, but it is the only way I could manage to get rid of "stuck" hover behaviors for tabs - primarily for
         // whichever tab was selected before changing from landscape to portrait (if it wasn't already the Control tab)
         // I'd give credit to the original author but the site no longer exists: http://mvartan.com/2014/12/20/fixing-sticky-hover-on-mobile-devices/

         // Check if the device supports touch events
         if('ontouchstart' in document.documentElement)
         {
            // Loop through each stylesheet
            console.log("sheets length: "+document.styleSheets.length);
            for(var sheetI = document.styleSheets.length - 1; sheetI >= 0; sheetI--)
            {
               var sheet = document.styleSheets[sheetI];
               // Verify if cssRules exists in sheet
               if(sheet.cssRules)
               {
                  // Loop through each rule in sheet
                  console.log(document.styleSheets.length+" rules length: "+sheet.cssRules.length);
                  for(var ruleI = sheet.cssRules.length - 1; ruleI >= 0; ruleI--)
                  {
                     var rule = sheet.cssRules[ruleI];
                     // Verify rule has selector text
                     if(rule.selectorText) {
                        // Replace hover psuedo-class with active psuedo-class
                        rule.selectorText = rule.selectorText.replace(":hover", ":active");
                     }
                  }
               }
            }
         }
      }
   }
}

// Will hide, or unhide, the div whose "id" is passed in
function hide_div(id, f_hide)
{
   if (f_hide == true)
   {
      if (document.getElementById(id))
      {
         document.getElementById(id).style.display = 'none';
      }
   }
   else
   {
      if (document.getElementById(id))
      {
         document.getElementById(id).style.display = 'inline';
      }
   }
}

