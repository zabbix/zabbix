ALTER TABLE hosts MODIFY hostid DEFAULT NULL;
ALTER TABLE hosts MODIFY proxy_hostid DEFAULT NULL;
ALTER TABLE hosts MODIFY proxy_hostid NULL;
ALTER TABLE hosts MODIFY maintenanceid DEFAULT NULL;
ALTER TABLE hosts MODIFY maintenanceid NULL;
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);

CREATE TABLE interface (
	interfaceid              number(20)                                NOT NULL,
	hostid                   number(20)                                NOT NULL,
	main                     number(10)      DEFAULT '0'               NOT NULL,
	dns                      nvarchar2(64)   DEFAULT ''                ,
	useip                    number(10)      DEFAULT '1'               NOT NULL,
	ip                       nvarchar2(39)   DEFAULT '127.0.0.1'       ,
	port                     number(10)      DEFAULT '10050'           NOT NULL,
	PRIMARY KEY (interfaceid));
CREATE INDEX interface_1 on interface (interfaceid);
CREATE INDEX interface_2 on interface (hostid);
ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;

 
INSERT INTO interface (interfaceid,hostid,main,ip,dns,useip,port)
	(SELECT (hostid - (round(hostid / 100000000000)*100000000000)) * 2 + (round(hostid / 100000000000)*100000000000),
		hostid,1,ip,dns,useip,port
	FROM hosts 
	WHERE status IN (0,1));

INSERT INTO interface (interfaceid,hostid,main,dns,useip,port)
	(SELECT (hostid - (round(hostid / 100000000000)*100000000000)) * 2 + (round(hostid / 100000000000)*100000000000) + 1,
		hostid,0,ipmi_ip,0,ipmi_port
	FROM hosts 
	WHERE status IN (0,1) AND useipmi=1);

ALTER TABLE hosts DROP COLUMN ip;
ALTER TABLE hosts DROP COLUMN dns;
ALTER TABLE hosts DROP COLUMN port;
ALTER TABLE hosts DROP COLUMN useip;
ALTER TABLE hosts DROP COLUMN ipmi_ip;
ALTER TABLE hosts DROP COLUMN ipmi_port;