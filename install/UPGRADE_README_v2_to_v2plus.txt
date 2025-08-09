This is the set of instructions on how to upgrade your version 2 code install to version 2plus.  Version "2plus" is a hacky way
of describing the addition of tracking the current thermostat mode (heat/cool) along with each setpoint change

If you do not have a prior version of this software installed, check the INSTALL_README.txt document for those instructions.

If you have v1 installed, follow the instructions in UPGRADE_README_v1_to_v2_txt first

-- index --

1. Install the new code
2. Disable the old data collection
3. Update the setpoints table
4. Enable the new data collection
5. Verify that it's all working


--step by step--

1. Install the new code
	a. Download the code from GitHub
	b. Unzip locally
	c. Connect to your web host and upload the files into a NEW subdirectory - not on top of your old location (suggest "thermo2")

2. Disable the old data collection
	a. Probably crontab if you are on a unix server.
	b. The best way for now is to comment out the line, but not remove it from your cron entry.  Add # to the front of the line

3. Update the setpoints table
	a. Run "php add_setpoint_mode.php" from the "install" directory
	b. Verify using your favorite method (phpMyAdmin, for instance) that there is now a "mode" volumn in the thermo2__setpoints table (Note there are supposed to be any values in that column yet)

4. Enable the new data collection
	a. Probably crontab if you are on a unix server.
	b. Copy the old lines from your cron and paste them in as duplicates and then remove the comment marker form the new one and change the directory name to match your choice from step 1c
	
5. Verify that it's all working
	a. Wait a couple of minutes past when the setpoint on one of your thermstats has changed at least once (manually changing it as a test is fine!).
	b. Using your favorite tool (phpMyAdmin, for instance) look at the setpoints table in the DB find the entry for the time you changed a setpoint (in step 3), validate that there is a value in the "mode" column for any setpoint changes since you completed step 4
	c. Load the new web page and see if you can see color (red for heat, blue for cool) changes in the setpoint line 
	
Et Voila!
