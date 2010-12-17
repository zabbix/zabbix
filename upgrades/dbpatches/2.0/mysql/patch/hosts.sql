---- Patching table `interfaces`

CREATE TABLE interface (
	interfaceid              bigint unsigned                           NOT NULL,
	hostid                   bigint unsigned                           NOT NULL,
	main                     integer         DEFAULT '0'               NOT NULL,
	type                     integer         DEFAULT '0'               NOT NULL,
	useip                    integer         DEFAULT '1'               NOT NULL,
	ip                       varchar(39)     DEFAULT '127.0.0.1'       NOT NULL,
	dns                      varchar(64)     DEFAULT ''                NOT NULL,
	port                     varchar(64)     DEFAULT '10050'           NOT NULL,
	PRIMARY KEY (interfaceid)
) ENGINE=InnoDB;
CREATE INDEX interface_1 on interface (hostid,type);
CREATE INDEX interface_2 on interface (ip,dns);
ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;

-- Passive proxy interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid div 100000000000)*100000000000)) * 3 + ((hostid div 100000000000)*100000000000),
		hostid,1,0,ip,dns,useip,port
	FROM hosts
	WHERE status IN (6));	-- HOST_STATUS_PROXY_PASSIVE

-- Zabbix Agent interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid div 100000000000)*100000000000)) * 3 + ((hostid div 100000000000)*100000000000),
		hostid,1,1,ip,dns,useip,port
	FROM hosts
	WHERE status IN (0,1));

-- SNMP interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid div 100000000000)*100000000000)) * 3 + ((hostid div 100000000000)*100000000000) + 1,
		hostid,1,2,ip,dns,useip,'161'
	FROM hosts
	WHERE status IN (0,1)
		AND EXISTS (SELECT DISTINCT i.hostid FROM items i WHERE i.hostid=hosts.hostid and i.type IN (1,4,6)));	-- SNMPv1, SNMPv2c, SNMPv3

-- IPMI interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid div 100000000000)*100000000000)) * 3 + ((hostid div 100000000000)*100000000000) + 2,
		hostid,1,3,'',ipmi_ip,0,ipmi_port
	FROM hosts
	WHERE status IN (0,1) AND useipmi=1);

---- Patching table `items`

ALTER TABLE items MODIFY itemid bigint unsigned NOT NULL,
		  MODIFY hostid bigint unsigned NOT NULL,
		  MODIFY units varchar(255) DEFAULT '' NOT NULL,
		  MODIFY templateid bigint unsigned NULL,
		  MODIFY valuemapid bigint unsigned NULL,
		  ADD lastns integer NULL,
		  ADD flags integer DEFAULT '0' NOT NULL,
		  ADD filter varchar(255) DEFAULT '' NOT NULL,
		  ADD interfaceid bigint unsigned NULL,
		  ADD port varchar(64) DEFAULT '' NOT NULL;

UPDATE items SET templateid=NULL WHERE templateid=0;
CREATE TEMPORARY TABLE tmp_items_itemid (itemid bigint unsigned PRIMARY KEY);

INSERT INTO tmp_items_itemid (itemid) (SELECT itemid FROM items);
UPDATE items SET templateid=NULL WHERE templateid IS NOT NULL AND templateid NOT IN (SELECT itemid FROM tmp_items_itemid);
DROP TABLE tmp_items_itemid;

UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE valuemapid IS NOT NULL AND valuemapid NOT IN (SELECT valuemapid from valuemaps);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';

DELETE FROM items WHERE hostid NOT IN (SELECT hostid FROM hosts);

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
		AND type IN (0, 3, 10, 11, 13, 14);     -- Agent, Simple, External, Database, SSH, TELNET

-- host interface for SNMP and non templated items
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=2)
	WHERE EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type IN (1,4,6);		-- SNMPv1, SNMPv2c, SNMPv3

-- host interface for IPMI and non templated items
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=3)
	WHERE EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type IN (12);		-- IPMI

-- clear port number for non SNMP items
UPDATE items
	SET port=''
	WHERE type NOT IN (1,4,6);		-- SNMPv1, SNMPv2c, SNMPv3

---- Patching table `hosts`

ALTER TABLE hosts MODIFY hostid bigint unsigned NOT NULL,
		  MODIFY proxy_hostid bigint unsigned NULL,
		  MODIFY maintenanceid bigint unsigned NULL,
		  DROP COLUMN ip,
		  DROP COLUMN dns,
		  DROP COLUMN port,
		  DROP COLUMN useip,
		  DROP COLUMN useipmi,
		  DROP COLUMN ipmi_ip,
		  DROP COLUMN ipmi_port,
		  DROP COLUMN inbytes,
		  DROP COLUMN outbytes;
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);
