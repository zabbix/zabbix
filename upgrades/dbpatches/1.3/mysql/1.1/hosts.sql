CREATE TABLE hosts (
	hostid		int(4)		NOT NULL auto_increment,
	host		varchar(64)	DEFAULT '' NOT NULL,
	useip		int(1)		DEFAULT '1' NOT NULL,
	ip		varchar(15)	DEFAULT '127.0.0.1' NOT NULL,
	port		int(4)		DEFAULT '0' NOT NULL,
	status		int(4)		DEFAULT '0' NOT NULL,
-- If status=UNREACHABLE, host will not be checked until this time
	disable_until	int(4)		DEFAULT '0' NOT NULL,
	error		varchar(128)	DEFAULT '' NOT NULL,
	available	int(4)		DEFAULT '0' NOT NULL,
	errors_from	int(4)		DEFAULT '0' NOT NULL,
	templateid	int(4)		DEFAULT '0' NOT NULL,
	PRIMARY KEY	(hostid),
	UNIQUE		(host),
	KEY		(status)
) type=InnoDB;
