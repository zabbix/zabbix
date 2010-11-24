ALTER TABLE hosts MODIFY hostid bigint unsigned NOT NULL,
		  MODIFY proxy_hostid bigint unsigned NULL,
		  MODIFY maintenanceid bigint unsigned NULL;
		  
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);

CREATE TABLE interface (
	interfaceid		bigint unsigned				NOT NULL,
	hostid			bigint unsigned				NOT NULL,
	main			integer         DEFAULT '0'		NOT NULL,
	type			integer		DEFAULT '0'		NOT NULL,
	dns                      varchar(64)     DEFAULT ''		NOT NULL,
	useip                    integer         DEFAULT '1'		NOT NULL,
	ip                       varchar(39)     DEFAULT '127.0.0.1'	NOT NULL,
	port                     integer         DEFAULT '10050'	NOT NULL,
	PRIMARY KEY (interfaceid)
) ENGINE=InnoDB;
CREATE INDEX interface_1 on interface (interfaceid);
CREATE INDEX interface_2 on interface (hostid, type);
CREATE INDEX interface_3 on interface (ip,dns);

ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
 
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid div 100000000000)*100000000000)) * 2 + ((hostid div 100000000000)*100000000000),
		hostid,1,1,ip,dns,useip,port
	FROM hosts 
	WHERE status IN (0,1));

INSERT INTO interface (interfaceid,hostid,main,type,dns,useip,port)
	(SELECT (hostid - ((hostid div 100000000000)*100000000000)) * 2 + ((hostid div 100000000000)*100000000000) + 1,
		hostid,1,3,ipmi_ip,0,ipmi_port
	FROM hosts 
	WHERE status IN (0,1) AND useipmi=1);

ALTER TABLE hosts DROP COLUMN ip, 
		DROP COLUMN dns, 
		DROP COLUMN port, 
		DROP COLUMN useip, 
		DROP COLUMN ipmi_ip, 
		DROP COLUMN ipmi_port;
