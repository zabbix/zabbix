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
	itemtype                 number(10)      DEFAULT '0'               NOT NULL,
	main                     number(10)      DEFAULT '0'               NOT NULL,
	dns                      nvarchar2(64)   DEFAULT ''                ,
	useip                    number(10)      DEFAULT '1'               NOT NULL,
	ip                       nvarchar2(39)   DEFAULT '127.0.0.1'       ,
	port                     number(10)      DEFAULT '10050'           NOT NULL,
	ipmi_authtype            number(10)      DEFAULT '0'               NOT NULL,
	ipmi_privilege           number(10)      DEFAULT '2'               NOT NULL,
	ipmi_username            nvarchar2(16)   DEFAULT ''                ,
	ipmi_password            nvarchar2(20)   DEFAULT ''                ,
	available                number(10)      DEFAULT '0'               NOT NULL,
	error                    nvarchar2(128)  DEFAULT ''                ,
	errors_from              number(10)      DEFAULT '0'               NOT NULL,
	disable_until            number(10)      DEFAULT '0'               NOT NULL,
	PRIMARY KEY (interfaceid));
CREATE INDEX interface_1 on interface (interfaceid);
CREATE INDEX interface_2 on interface (hostid, itemtype);
ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;

 
INSERT INTO interface (interfaceid,hostid,itemtype,main,ip,dns,port,useip,available,error,errors_from,disable_until)
	SELECT (hostid - (round(hostid / 100000000000)*100000000000)) * 2 + (round(hostid / 100000000000)*100000000000),
		hostid,0,1,ip,dns,port,useip,available,error,errors_from,disable_until 
	FROM hosts 
	WHERE status IN (0,1);

INSERT INTO interface (interfaceid,hostid,itemtype,main,dns,useip,port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,available,error,errors_from,disable_until)
	SELECT (hostid - (round(hostid / 100000000000)*100000000000)) * 2 + (round(hostid / 100000000000)*100000000000) + 1,
		hostid,12,1,ipmi_ip,0,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_available,ipmi_error,ipmi_errors_from,ipmi_disable_until 
	FROM hosts 
	WHERE status IN (0,1) AND useipmi=1;
