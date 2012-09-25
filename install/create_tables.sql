
CREATE TABLE IF NOT EXISTS `thermo2__hvac_cycles` (
  `tstat_uuid` varchar(15) NOT NULL,
  `system` smallint(6) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `thermo2__hvac_status` (
  `tstat_uuid` varchar(15) NOT NULL,
  `date` datetime NOT NULL,
  `start_date_fan` datetime,
  `start_date_cool` datetime,
  `start_date_heat` datetime,
  `heat_status` tinyint(1) NOT NULL,
  `cool_status` tinyint(1) NOT NULL,
  `fan_status` tinyint(1) NOT NULL,
  PRIMARY KEY `id` (`tstat_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `thermo2__run_times` (
  `tstat_uuid` varchar(15) NOT NULL,
  `date` date NOT NULL,
  `heat_runtime` smallint(6) NOT NULL,
  `cool_runtime` smallint(6) NOT NULL,
  PRIMARY KEY `id_date` (`tstat_uuid`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `thermo2__temperatures` (
  `tstat_uuid` varchar(15) NOT NULL,
  `date` datetime NOT NULL,
  `indoor_temp` decimal(5,2) NOT NULL,
  `outdoor_temp` decimal(5,2) DEFAULT NULL,
  `set_point` decimal(5,2) DEFAULT NULL,
  `indoor_humidity` decimal(5,2) DEFAULT NULL,
  `outdoor_humidity` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY `id_date` (`tstat_uuid`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `thermo2__time_index` (
  `time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `thermo2__time_index` (`time`) VALUES
('00:00:00'),
('00:30:00'),
('01:00:00'),
('01:30:00'),
('02:00:00'),
('02:30:00'),
('03:00:00'),
('03:30:00'),
('04:00:00'),
('04:30:00'),
('05:00:00'),
('05:30:00'),
('06:00:00'),
('06:30:00'),
('07:00:00'),
('07:30:00'),
('08:00:00'),
('08:30:00'),
('09:00:00'),
('09:30:00'),
('10:00:00'),
('10:30:00'),
('11:00:00'),
('11:30:00'),
('12:00:00'),
('12:30:00'),
('13:00:00'),
('13:30:00'),
('14:00:00'),
('14:30:00'),
('15:00:00'),
('15:30:00'),
('16:00:00'),
('16:30:00'),
('17:00:00'),
('17:30:00'),
('18:00:00'),
('18:30:00'),
('19:00:00'),
('19:30:00'),
('20:00:00'),
('20:30:00'),
('21:00:00'),
('21:30:00'),
('22:00:00'),
('22:30:00'),
('23:00:00'),
('23:30:00'),
('24:00:00');

CREATE TABLE IF NOT EXISTS `thermo2__thermostats` (
  `id` int(2) NOT NULL AUTO_INCREMENT,
  `tstat_uuid` varchar(15) NULL,
  `model` varchar(10) NULL,
  `fw_version` varchar(10) NULL,
  `wlan_fw_version` varchar(10) NULL,
  `ip` varchar(15) NOT NULL,
  `name` varchar(254) NULL,
  `description` varchar(254) NULL,
  PRIMARY KEY `id` (`id`),
  INDEX `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `thermo2__thermostats` (`ip`,`name`,`model`) VALUES ('192.168.1.171','Downstairs','CT30');
INSERT INTO `thermo2__thermostats` (`ip`,`name`,`model`) VALUES ('192.168.1.170','Upstairs','CT30');

