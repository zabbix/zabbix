CREATE TABLE hosts_tmp (
	hostid		bigint DEFAULT '0'	NOT NULL,
	host		varchar(64)		DEFAULT ''	NOT NULL,
	dns		varchar(64)		DEFAULT ''	NOT NULL,
	useip		integer		DEFAULT '1'	NOT NULL,
	ip		varchar(15)		DEFAULT '127.0.0.1'	NOT NULL,
	port		integer		DEFAULT '0'	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	disable_until	integer		DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	available	integer		DEFAULT '0'	NOT NULL,
	errors_from	integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostid)
) with OIDS;
CREATE INDEX hosts_1 on hosts_tmp (host);
CREATE INDEX hosts_2 on hosts_tmp (status);

insert into hosts_tmp select hostid,host,host,useip,ip,port,status,disable_until,error,available,errors_from from hosts;
drop table hosts;
alter table hosts_tmp rename to hosts;
