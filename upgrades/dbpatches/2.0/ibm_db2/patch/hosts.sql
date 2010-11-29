ALTER TABLE hosts ALTER COLUMN hostid SET WITH DEFAULT NULL;
REORG TABLE hosts;
ALTER TABLE hosts ALTER COLUMN proxy_hostid SET WITH DEFAULT NULL;
REORG TABLE hosts;
ALTER TABLE hosts ALTER COLUMN proxy_hostid DROP NOT NULL;
REORG TABLE hosts;
ALTER TABLE hosts ALTER COLUMN maintenanceid SET WITH DEFAULT NULL;
REORG TABLE hosts;
ALTER TABLE hosts ALTER COLUMN maintenanceid DROP NOT NULL;
REORG TABLE hosts;
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
REORG TABLE hosts;
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);
REORG TABLE hosts;

CREATE TABLE interface (
	interfaceid              bigint                                    NOT NULL,
	hostid                   bigint                                    NOT NULL,
	main                     integer         WITH DEFAULT '0'          NOT NULL,
	type                     integer         WITH DEFAULT '0'          NOT NULL,
	useip                    integer         WITH DEFAULT '1'          NOT NULL,
	ip                       varchar(39)     WITH DEFAULT '127.0.0.1'  NOT NULL,
	dns                      varchar(64)     WITH DEFAULT ''           NOT NULL,
	port                     varchar(64)     WITH DEFAULT '10050'      NOT NULL,
	PRIMARY KEY (interfaceid)
);
REORG TABLE interface;
CREATE INDEX interface_1 on interface (interfaceid);
REORG TABLE interface;
CREATE INDEX interface_2 on interface (hostid,type);
REORG TABLE interface;
CREATE INDEX interface_3 on interface (ip,dns);
REORG TABLE interface;
ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
REORG TABLE interface;

INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 2 + ((hostid / 100000000000)*100000000000),
		hostid,1,1,ip,dns,useip,port
	FROM hosts 
	WHERE status IN (0,1));

INSERT INTO interface (interfaceid,hostid,main,type,ip,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 2 + ((hostid / 100000000000)*100000000000) + 1,
		hostid,1,3,ipmi_ip,0,ipmi_port
	FROM hosts 
	WHERE status IN (0,1) AND useipmi=1);
	
REORG TABLE hosts;

ALTER TABLE hosts DROP COLUMN ip; 
REORG TABLE hosts;
ALTER TABLE hosts DROP COLUMN dns; 
REORG TABLE hosts;
ALTER TABLE hosts DROP COLUMN port; 
REORG TABLE hosts;
ALTER TABLE hosts DROP COLUMN useip; 
REORG TABLE hosts;
ALTER TABLE hosts DROP COLUMN ipmi_ip; 
REORG TABLE hosts;
ALTER TABLE hosts DROP COLUMN ipmi_port;
REORG TABLE hosts;
