RENAME TABLE history TO history_old;
CREATE TABLE `history` (
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` DOUBLE PRECISION DEFAULT '0.0000' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemid,clock,ns)
) ENGINE=InnoDB;

RENAME TABLE history_uint TO history_uint_old;
CREATE TABLE `history_uint` (
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` bigint unsigned DEFAULT '0' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemid,clock,ns)
) ENGINE=InnoDB;

RENAME TABLE history_str TO history_str_old;
CREATE TABLE `history_str` (
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` varchar(255) DEFAULT '' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemid,clock,ns)
) ENGINE=InnoDB;

RENAME TABLE history_log TO history_log_old;
CREATE TABLE `history_log` (
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`timestamp` integer DEFAULT '0' NOT NULL,
	`source` varchar(64) DEFAULT '' NOT NULL,
	`severity` integer DEFAULT '0' NOT NULL,
	`value` text NOT NULL,
	`logeventid` integer DEFAULT '0' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemid,clock,ns)
) ENGINE=InnoDB;

RENAME TABLE history_text TO history_text_old;
CREATE TABLE `history_text` (
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` text NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemid,clock,ns)
) ENGINE=InnoDB;

