---- Patching table `interfaces`

CREATE TABLE interface (
	interfaceid              number(20)                                NOT NULL,
	hostid                   number(20)                                NOT NULL,
	main                     number(10)      DEFAULT '0'               NOT NULL,
	type                     number(10)      DEFAULT '0'               NOT NULL,
	useip                    number(10)      DEFAULT '1'               NOT NULL,
	ip                       nvarchar2(39)   DEFAULT '127.0.0.1'       ,
	dns                      nvarchar2(64)   DEFAULT ''                ,
	port                     nvarchar2(64)   DEFAULT '10050'           ,
	PRIMARY KEY (interfaceid)
);
CREATE INDEX interface_1 on interface (hostid,type);
CREATE INDEX interface_2 on interface (ip,dns);
ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;

-- Passive proxy interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - (trunc(hostid / 100000000000)*100000000000)) * 3 + (trunc(hostid / 100000000000)*100000000000),
		hostid,1,0,ip,dns,useip,port
	FROM hosts
	WHERE status IN (6));

-- Zabbix Agent interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - (trunc(hostid / 100000000000)*100000000000)) * 3 + (trunc(hostid / 100000000000)*100000000000),
		hostid,1,1,ip,dns,useip,port
	FROM hosts
	WHERE status IN (0,1));

-- SNMP interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - (trunc(hostid / 100000000000)*100000000000)) * 3 + (trunc(hostid / 100000000000)*100000000000) + 1,
		hostid,1,2,ip,dns,useip,'161'
	FROM hosts
	WHERE status IN (0,1)
		AND EXISTS (SELECT DISTINCT i.hostid FROM items i WHERE i.hostid=hosts.hostid and i.type IN (1,4,6)));

-- IPMI interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - (trunc(hostid / 100000000000)*100000000000)) * 3 + (trunc(hostid / 100000000000)*100000000000) + 2,
		hostid,1,3,'',ipmi_ip,0,ipmi_port
	FROM hosts
	WHERE status IN (0,1) AND useipmi=1);

---- Patching table `items`

ALTER TABLE items MODIFY itemid DEFAULT NULL;
ALTER TABLE items MODIFY hostid DEFAULT NULL;
ALTER TABLE items MODIFY units nvarchar2(255);
ALTER TABLE items MODIFY templateid DEFAULT NULL;
ALTER TABLE items MODIFY templateid NULL;
ALTER TABLE items MODIFY valuemapid DEFAULT NULL;
ALTER TABLE items MODIFY valuemapid NULL;
ALTER TABLE items ADD lastns number(10) NULL;
ALTER TABLE items ADD flags number(10) DEFAULT '0' NOT NULL;
ALTER TABLE items ADD filter nvarchar2(255) DEFAULT '';
ALTER TABLE items ADD interfaceid number(20) NULL;
ALTER TABLE items ADD port nvarchar2(64) DEFAULT '';

UPDATE items SET templateid=NULL WHERE templateid=0;
UPDATE items SET templateid=NULL WHERE templateid IS NOT NULL AND templateid NOT IN (SELECT itemid FROM items);
UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE valuemapid IS NOT NULL AND valuemapid NOT IN (SELECT valuemapid from valuemaps);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
ALTER TABLE items ADD CONSTRAINT c_items_4 FOREIGN KEY (interfaceid) REFERENCES interface (interfaceid);

UPDATE items SET port=snmp_port;
ALTER TABLE items DROP COLUMN snmp_port;

CREATE INDEX items_5 on items (valuemapid);

-- host interface for non IPMI, SNMP and non templated items
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=1)
	WHERE EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type IN (0,3,10,11,13,14)	-- ZABBIX, SIMPLE, EXTERNAL, DB_MONITOR, SSH, TELNET
/

-- host interface for SNMP and non templated items
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=2)
	WHERE EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type IN (1,4,6)		-- SNMPv1, SNMPv2c, SNMPv3
/

-- host interface for IPMI and non templated items
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=3)
	WHERE EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type IN (12)		-- IPMI
/

-- clear port number for non SNMP items
UPDATE items
	SET port=''
	WHERE type NOT IN (1,4,6)		-- SNMPv1, SNMPv2c, SNMPv3
/

ALTER TABLE hosts MODIFY hostid DEFAULT NULL;
ALTER TABLE hosts MODIFY proxy_hostid DEFAULT NULL;
ALTER TABLE hosts MODIFY proxy_hostid NULL;
ALTER TABLE hosts MODIFY maintenanceid DEFAULT NULL;
ALTER TABLE hosts MODIFY maintenanceid NULL;
ALTER TABLE hosts DROP COLUMN ip;
ALTER TABLE hosts DROP COLUMN dns;
ALTER TABLE hosts DROP COLUMN port;
ALTER TABLE hosts DROP COLUMN useip;
ALTER TABLE hosts DROP COLUMN useipmi;
ALTER TABLE hosts DROP COLUMN ipmi_ip;
ALTER TABLE hosts DROP COLUMN ipmi_port;
ALTER TABLE hosts DROP COLUMN inbytes;
ALTER TABLE hosts DROP COLUMN outbytes;
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);

-- added column for visible name
ALTER TABLE hosts ADD name nvarchar2(64) DEFAULT '';
CREATE INDEX hosts_4 on hosts (name);
