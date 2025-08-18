#!/usr/bin/ksh
LOC=`dirname $0`
cd ${LOC}
. ../config/config.ksh

/usr/bin/php ${LOC}/thermo_update_temps.php
