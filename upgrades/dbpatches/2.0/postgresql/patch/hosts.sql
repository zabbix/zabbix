ALTER TABLE ONLY hosts ALTER hostid DROP DEFAULT,
		       ALTER proxy_hostid DROP DEFAULT,
		       ALTER proxy_hostid DROP NOT NULL,
		       ALTER maintenanceid DROP DEFAULT,
		       ALTER maintenanceid DROP NOT NULL;
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE ONLY hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE ONLY hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);

CREATE TABLE interface (
	interfaceid              bigint                                    NOT NULL,
	hostid                   bigint                                    NOT NULL,
	itemtype                 integer         DEFAULT '0'               NOT NULL,
	main                     integer         DEFAULT '0'               NOT NULL,
	dns                      varchar(64)     DEFAULT ''                NOT NULL,
	useip                    integer         DEFAULT '1'               NOT NULL,
	ip                       varchar(39)     DEFAULT '127.0.0.1'       NOT NULL,
	port                     integer         DEFAULT '10050'           NOT NULL,
	ipmi_authtype            integer         DEFAULT '0'               NOT NULL,
	ipmi_privilege           integer         DEFAULT '2'               NOT NULL,
	ipmi_username            varchar(16)     DEFAULT ''                NOT NULL,
	ipmi_password            varchar(20)     DEFAULT ''                NOT NULL,
	available                integer         DEFAULT '0'               NOT NULL,
	error                    varchar(128)    DEFAULT ''                NOT NULL,
	errors_from              integer         DEFAULT '0'               NOT NULL,
	disable_until            integer         DEFAULT '0'               NOT NULL,
	PRIMARY KEY (interfaceid)
) with OIDS;
CREATE INDEX interface_1 on interface (interfaceid);
CREATE INDEX interface_2 on interface (hostid, itemtype);
ALTER TABLE ONLY interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts(hostid) ON DELETE CASCADE;
 
INSERT INTO interface (interfaceid,hostid,itemtype,main,ip,dns,port,useip,available,error,errors_from,disable_until)
	SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 2 + ((hostid / 100000000000)*100000000000),
		hostid,0,1,ip,dns,port,useip,available,error,errors_from,disable_until 
	FROM hosts 
	WHERE status IN (0,1);

INSERT INTO interface (interfaceid,hostid,itemtype,main,dns,useip,port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,available,error,errors_from,disable_until)
	SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 2 + ((hostid / 100000000000)*100000000000) + 1,
		hostid,12,1,ipmi_ip,0,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_available,ipmi_error,ipmi_errors_from,ipmi_disable_until 
	FROM hosts 
	WHERE status IN (0,1) AND useipmi=1;

ALTER TABLE ONLY hosts DROP COLUMN ip, 
					DROP COLUMN dns, 
					DROP COLUMN port, 
					DROP COLUMN useip, 
					DROP COLUMN available, 
					DROP COLUMN error, 
					DROP COLUMN errors_from, 
					DROP COLUMN disable_until, 
					DROP COLUMN ipmi_ip, 
					DROP COLUMN ipmi_port, 
					DROP COLUMN ipmi_authtype, 
					DROP COLUMN ipmi_privilege, 
					DROP COLUMN ipmi_username, 
					DROP COLUMN ipmi_password, 
					DROP COLUMN ipmi_available, 
					DROP COLUMN ipmi_error, 
					DROP COLUMN ipmi_errors_from, 
					DROP COLUMN ipmi_disable_until;