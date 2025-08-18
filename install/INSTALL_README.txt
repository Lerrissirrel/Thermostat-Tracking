Requirements:
PHP 5.2.4 or later
Mysql
KLogger
echarts
jquery

Install:
1. Place the folder somewhere in your web root.

2. Connect to mysql and create the database and user.

Example:
create database therm
create user 'therm'@'localhost' identified by 'SOMEPASS';
grant all on therm.* to 'therm'@'localhost';

3. Create the tables

At the end of install/create_tables.sql.IN, enter statements for each thermostat you wish to configure:

INSERT INTO `thermostats` (`ip`,`name`,`model`) VALUES ('192.168.1.171','Downstairs','CT30');

Then create the tables and import the data:
Example:
mysql therm -p < install/create_tables.sql.IN
where therm is the database (that you created above) and -p prompts for the admin user password

4. You may need to setup mysql timezone data:

mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql

5. Modify config.php

Update various user setable values as documented within config.php

6. Run "composer update" to install the logging library

If you'd like to try a modification to the logging library to keep a link from "logs/latest" to the most recent log file, from the
root directory where you installed, run:
# patch vendor/katzgrau/klogger/src/Logger.php < install/Logger.diff

7. Run "npm install echarts" to install a copy of echarts

8. Run "npm install jquery" to install a copy of jquery

9. Manually run scripts/thermo_update_status.php and scripts/thermo_update_temps.php

10. Add these scripts to the cron job/scheduled tasks. See install/create_schedule.readme

