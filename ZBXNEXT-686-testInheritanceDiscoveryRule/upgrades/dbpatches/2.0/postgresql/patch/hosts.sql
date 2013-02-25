---- Patching table `interfaces`

CREATE TABLE interface (
	interfaceid              bigint                                    NOT NULL,
	hostid                   bigint                                    NOT NULL,
	main                     integer         DEFAULT '0'               NOT NULL,
	type                     integer         DEFAULT '0'               NOT NULL,
	useip                    integer         DEFAULT '1'               NOT NULL,
	ip                       varchar(39)     DEFAULT '127.0.0.1'       NOT NULL,
	dns                      varchar(64)     DEFAULT ''                NOT NULL,
	port                     varchar(64)     DEFAULT '10050'           NOT NULL,
	PRIMARY KEY (interfaceid)
);
CREATE INDEX interface_1 on interface (hostid,type);
CREATE INDEX interface_2 on interface (ip,dns);
ALTER TABLE ONLY interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;

-- Passive proxy interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000),
		hostid,1,0,ip,dns,useip,port
	FROM hosts
	WHERE status IN (6));	-- HOST_STATUS_PROXY_PASSIVE

-- Zabbix Agent interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000),
		hostid,1,1,ip,dns,useip,port
	FROM hosts
	WHERE status IN (0,1));

-- SNMP interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000) + 1,
		hostid,1,2,ip,dns,useip,'161'
	FROM hosts
	WHERE status IN (0,1)
		AND EXISTS (SELECT DISTINCT i.hostid FROM items i WHERE i.hostid=hosts.hostid and i.type IN (1,4,6)));	-- SNMPv1, SNMPv2c, SNMPv3

-- IPMI interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000) + 2,
		hostid,1,3,'',ipmi_ip,0,ipmi_port
	FROM hosts
	WHERE status IN (0,1) AND useipmi=1);

---- Patching table `items`

ALTER TABLE ONLY items RENAME COLUMN description TO name;
ALTER TABLE ONLY items
	ALTER itemid DROP DEFAULT,
	ALTER hostid DROP DEFAULT,
	ALTER units TYPE varchar(255),
	ALTER lastlogsize TYPE numeric(20),
	ALTER templateid DROP DEFAULT,
	ALTER templateid DROP NOT NULL,
	ALTER valuemapid DROP DEFAULT,
	ALTER valuemapid DROP NOT NULL,
	ADD lastns integer NULL,
	ADD flags integer DEFAULT '0' NOT NULL,
	ADD filter varchar(255) DEFAULT '' NOT NULL,
	ADD interfaceid bigint NULL,
	ADD port varchar(64) DEFAULT '' NOT NULL,
	ADD description text DEFAULT '' NOT NULL,
	ADD inventory_link integer DEFAULT '0' NOT NULL,
	ADD lifetime varchar(64) DEFAULT '30' NOT NULL;
UPDATE items
	SET templateid=NULL
	WHERE templateid=0
		OR NOT EXISTS (SELECT 1 FROM items i WHERE i.itemid=items.templateid);
UPDATE items
	SET valuemapid=NULL
	WHERE valuemapid=0
		OR NOT EXISTS (SELECT 1 FROM valuemaps v WHERE v.valuemapid=items.valuemapid);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE ONLY items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE ONLY items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
ALTER TABLE ONLY items ADD CONSTRAINT c_items_4 FOREIGN KEY (interfaceid) REFERENCES interface (interfaceid);

UPDATE items SET port=snmp_port;
ALTER TABLE items DROP COLUMN snmp_port;

CREATE INDEX items_5 on items (valuemapid);

-- host interface for non IPMI, SNMP and non templated items
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=1)
	WHERE EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type IN (0,3,10,11,13,14);	-- ZABBIX, SIMPLE, EXTERNAL, DB_MONITOR, SSH, TELNET

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

-- add a first parameter {HOST.CONN} for external checks

UPDATE items
	SET key_ = SUBSTR(key_, 1, STRPOS(key_, '[')) || '"{HOST.CONN}",' || SUBSTR(key_, STRPOS(key_, '[') + 1)
	WHERE type IN (10)	-- EXTERNAL
		AND STRPOS(key_, '[') <> 0;

UPDATE items
	SET key_ = key_ || '["{HOST.CONN}"]'
	WHERE type IN (10)	-- EXTERNAL
		AND STRPOS(key_, '[') = 0;

-- convert simple check keys to a new form

CREATE LANGUAGE 'plpgsql';

CREATE FUNCTION zbx_convert_simple_checks(v_itemid bigint, v_hostid bigint, v_key varchar(255))
RETURNS varchar(255) AS $$
DECLARE old_key varchar(255);
	new_key varchar(255);
	pos integer;
BEGIN
	old_key := v_key;
	new_key := 'net.tcp.service';
	pos := STRPOS(old_key, '_perf');
	IF 0 <> pos THEN
		new_key := new_key || '.perf';
		old_key := SUBSTR(old_key, 1, pos - 1) || SUBSTR(old_key, pos + 5);
	END IF;
	new_key := new_key || '[';
	pos := STRPOS(old_key, ',');
	IF 0 <> pos THEN
		new_key := new_key || '"' || SUBSTR(old_key, 1, pos - 1) || '"';
		old_key := SUBSTR(old_key, pos + 1);
	ELSE
		new_key := new_key || '"' || old_key || '"';
		old_key := '';
	END IF;
	IF 0 <> LENGTH(old_key) THEN
		new_key := new_key || ',,"' || old_key || '"';
	END IF;

	WHILE 0 != (SELECT COUNT(*) FROM items WHERE hostid = v_hostid AND key_ = new_key || ']') LOOP
		new_key := new_key || ' ';
	END LOOP;

	RETURN new_key || ']';
END;
$$ LANGUAGE 'plpgsql';

UPDATE items SET key_ = zbx_convert_simple_checks(itemid, hostid, key_)
	WHERE type IN (3)	-- SIMPLE
		AND (key_ IN ('ftp','http','imap','ldap','nntp','ntp','pop','smtp','ssh',
			'ftp_perf','http_perf', 'imap_perf','ldap_perf','nntp_perf','ntp_perf','pop_perf',
			'smtp_perf','ssh_perf')
			OR key_ LIKE 'ftp,%' OR key_ LIKE 'http,%' OR key_ LIKE 'imap,%' OR key_ LIKE 'ldap,%'
			OR key_ LIKE 'nntp,%' OR key_ LIKE 'ntp,%' OR key_ LIKE 'pop,%' OR key_ LIKE 'smtp,%'
			OR key_ LIKE 'ssh,%' OR key_ LIKE 'tcp,%'
			OR key_ LIKE 'ftp_perf,%' OR key_ LIKE 'http_perf,%' OR key_ LIKE 'imap_perf,%'
			OR key_ LIKE 'ldap_perf,%' OR key_ LIKE 'nntp_perf,%' OR key_ LIKE 'ntp_perf,%'
			OR key_ LIKE 'pop_perf,%' OR key_ LIKE 'smtp_perf,%' OR key_ LIKE 'ssh_perf,%'
			OR key_ LIKE 'tcp_perf,%');

DROP FUNCTION zbx_convert_simple_checks(v_itemid bigint, v_hostid bigint, v_key varchar(255));

DROP LANGUAGE 'plpgsql';

-- adding web.test.error[<web check>] items

CREATE SEQUENCE items_seq;
CREATE SEQUENCE httptestitem_seq;
CREATE SEQUENCE items_applications_seq;

SELECT setval('items_seq', max(itemid)) FROM items;
SELECT setval('httptestitem_seq', max(httptestitemid)) FROM httptestitem;
SELECT setval('items_applications_seq', max(itemappid)) FROM items_applications;

INSERT INTO items (itemid, hostid, type, name, key_, value_type, units, delay, history, trends, status)
	SELECT NEXTVAL('items_seq'), hostid, type, 'Last error message of scenario ''$1''', 'web.test.error' || SUBSTR(key_, STRPOS(key_, '[')), 1, '', delay, history, 0, status
	FROM items
	WHERE type = 9
		AND key_ LIKE 'web.test.fail%';

INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type)
	SELECT NEXTVAL('httptestitem_seq'), ht.httptestid, i.itemid, 4
	FROM httptest ht,applications a,items i
	WHERE ht.applicationid=a.applicationid
		AND a.hostid=i.hostid
		AND 'web.test.error[' || ht.name || ']' = i.key_;

INSERT INTO items_applications (itemappid, applicationid, itemid)
	SELECT NEXTVAL('items_applications_seq'), ht.applicationid, hti.itemid
	FROM httptest ht, httptestitem hti
	WHERE ht.httptestid = hti.httptestid
		AND hti.type = 4;

DROP SEQUENCE items_applications_seq;
DROP SEQUENCE httptestitem_seq;
DROP SEQUENCE items_seq;

DELETE FROM ids WHERE table_name IN ('items', 'httptestitem', 'items_applications');

---- Patching table `hosts`

ALTER TABLE ONLY hosts ALTER hostid DROP DEFAULT,
		       ALTER proxy_hostid DROP DEFAULT,
		       ALTER proxy_hostid DROP NOT NULL,
		       ALTER maintenanceid DROP DEFAULT,
		       ALTER maintenanceid DROP NOT NULL,
		       DROP COLUMN ip,
		       DROP COLUMN dns,
		       DROP COLUMN port,
		       DROP COLUMN useip,
		       DROP COLUMN useipmi,
		       DROP COLUMN ipmi_ip,
		       DROP COLUMN ipmi_port,
		       DROP COLUMN inbytes,
		       DROP COLUMN outbytes,
		       ADD jmx_disable_until integer DEFAULT '0' NOT NULL,
		       ADD jmx_available integer DEFAULT '0' NOT NULL,
		       ADD jmx_errors_from integer DEFAULT '0' NOT NULL,
		       ADD jmx_error varchar(128) DEFAULT '' NOT NULL,
		       ADD name varchar(64) DEFAULT '' NOT NULL;
UPDATE hosts
	SET proxy_hostid=NULL
	WHERE proxy_hostid=0
		OR NOT EXISTS (SELECT 1 FROM hosts h WHERE h.hostid=hosts.proxy_hostid);
UPDATE hosts
	SET maintenanceid=NULL,
		maintenance_status=0,
		maintenance_type=0,
		maintenance_from=0
	WHERE maintenanceid=0
		OR NOT EXISTS (SELECT 1 FROM maintenances m WHERE m.maintenanceid=hosts.maintenanceid);
UPDATE hosts SET name=host WHERE status in (0,1,3);	-- MONITORED, NOT_MONITORED, TEMPLATE
CREATE INDEX hosts_4 on hosts (name);
ALTER TABLE ONLY hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE ONLY hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);
