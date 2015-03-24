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

ALTER TABLE items RENAME COLUMN description to name;
ALTER TABLE items MODIFY (
	itemid DEFAULT NULL,
	hostid DEFAULT NULL,
	units nvarchar2(255),
	templateid DEFAULT NULL NULL,
	lastlogsize number(20),
	valuemapid DEFAULT NULL NULL
);
ALTER TABLE items ADD (
	lastns number(10) NULL,
	flags number(10) DEFAULT '0' NOT NULL,
	filter nvarchar2(255) DEFAULT '',
	interfaceid number(20) NULL,
	port nvarchar2(64) DEFAULT '',
	description nvarchar2(2048) DEFAULT '',
	inventory_link number(10) DEFAULT '0' NOT NULL,
	lifetime nvarchar2(64) DEFAULT '30'
);
UPDATE items
	SET templateid=NULL
	WHERE templateid=0
		OR templateid NOT IN (SELECT itemid FROM items);
UPDATE items
	SET valuemapid=NULL
	WHERE valuemapid=0
		OR valuemapid NOT IN (SELECT valuemapid from valuemaps);
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

-- add a first parameter {HOST.CONN} for external checks

UPDATE items
	SET key_ = SUBSTR(key_, 1, INSTR(key_, '[')) || '"{HOST.CONN}",' || SUBSTR(key_, INSTR(key_, '[') + 1)
	WHERE type IN (10)	-- EXTERNAL
		AND INSTR(key_, '[') <> 0;

UPDATE items
	SET key_ = key_ || '["{HOST.CONN}"]'
	WHERE type IN (10)	-- EXTERNAL
		AND INSTR(key_, '[') = 0;

-- convert simple check keys to a new form

CREATE FUNCTION zbx_key_exists(v_hostid IN number, new_key IN nvarchar2)
	RETURN number IS key_exists number(10);
	BEGIN
		SELECT COUNT(*) INTO key_exists FROM items WHERE hostid = v_hostid AND key_ = new_key;
		RETURN key_exists;
	END;
/

DECLARE
	v_itemid number(20);
	v_hostid number(20);
	v_key nvarchar2(255);
	new_key nvarchar2(255);
	pos number(10);

	CURSOR i_cur IS
		SELECT itemid,hostid,key_
			FROM items
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
BEGIN
	OPEN i_cur;

	LOOP
		FETCH i_cur INTO v_itemid, v_hostid, v_key;

		EXIT WHEN i_cur%NOTFOUND;

		new_key := 'net.tcp.service';
		pos := INSTR(v_key, '_perf');
		IF 0 <> pos THEN
			new_key := new_key || '.perf';
			v_key := SUBSTR(v_key, 1, pos - 1) || SUBSTR(v_key, pos + 5);
		END IF;
		new_key := new_key || '[';
		pos := INSTR(v_key, ',');
		IF 0 <> pos THEN
			new_key := new_key || '"' || SUBSTR(v_key, 1, pos - 1) || '"';
			v_key := SUBSTR(v_key, pos + 1);
		ELSE
			new_key := new_key || '"' || v_key || '"';
			v_key := '';
		END IF;
		IF 0 <> LENGTH(v_key) THEN
			new_key := new_key || ',,"' || v_key || '"';
		END IF;

		WHILE 0 <> zbx_key_exists(v_hostid, new_key || ']') LOOP
			new_key := new_key || ' ';
		END LOOP;

		new_key := new_key || ']';

		UPDATE items SET key_ = new_key WHERE itemid = v_itemid;
	END LOOP;

	CLOSE i_cur;
END;
/

DROP FUNCTION zbx_key_exists;

-- adding web.test.error[<web check>] items

DECLARE
	httptest_nodeid number(10);
	min_nodeid number(20);
	max_nodeid number(20);
	init_nodeid number(20);
	CURSOR node_cursor IS SELECT DISTINCT TRUNC(httptestid / 100000000000000) FROM httptest;

	max_itemid number(20);
	max_httptestitemid number(20);
	max_itemappid number(20);
BEGIN
	OPEN node_cursor;

	LOOP
		FETCH node_cursor INTO httptest_nodeid;
		EXIT WHEN node_cursor%NOTFOUND;

		min_nodeid := httptest_nodeid * 100000000000000;
		max_nodeid := min_nodeid + 99999999999999;
		init_nodeid := (httptest_nodeid * 1000 + httptest_nodeid) * 100000000000;

		SELECT MAX(itemid) INTO max_itemid FROM items WHERE itemid BETWEEN min_nodeid AND max_nodeid;
		IF max_itemid IS NULL THEN
			max_itemid := init_nodeid;
		END IF;
		EXECUTE IMMEDIATE 'CREATE SEQUENCE items_seq MINVALUE ' || (max_itemid + 1);

		SELECT MAX(httptestitemid) INTO max_httptestitemid FROM httptestitem WHERE httptestitemid BETWEEN min_nodeid AND max_nodeid;
		IF max_httptestitemid IS NULL THEN
			max_httptestitemid := init_nodeid;
		END IF;
		EXECUTE IMMEDIATE 'CREATE SEQUENCE httptestitem_seq MINVALUE ' || (max_httptestitemid + 1);

		SELECT MAX(itemappid) INTO max_itemappid FROM items_applications WHERE itemappid BETWEEN min_nodeid AND max_nodeid;
		IF max_itemappid IS NULL THEN
			max_itemappid := init_nodeid;
		END IF;
		EXECUTE IMMEDIATE 'CREATE SEQUENCE items_applications_seq MINVALUE ' || (max_itemappid + 1);

		EXECUTE IMMEDIATE 'INSERT INTO items (itemid, hostid, type, name, key_, value_type, units, delay, history, trends, status)
			SELECT items_seq.NEXTVAL, hostid, type, ''Last error message of scenario ''''$1'''''', ''web.test.error'' || SUBSTR(key_, INSTR(key_, ''['')), 1, '''', delay, history, 0, status
			FROM items
			WHERE type = 9
				AND key_ LIKE ''web.test.fail%''
				AND itemid BETWEEN ' || min_nodeid ||' AND ' || max_nodeid;

		EXECUTE IMMEDIATE 'INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type)
			SELECT httptestitem_seq.NEXTVAL, ht.httptestid, i.itemid, 4
			FROM httptest ht,applications a,items i
			WHERE ht.applicationid=a.applicationid
				AND a.hostid=i.hostid
				AND ''web.test.error['' || ht.name || '']'' = i.key_
				AND itemid BETWEEN ' || min_nodeid ||' AND ' || max_nodeid;

		EXECUTE IMMEDIATE 'INSERT INTO items_applications (itemappid, applicationid, itemid)
			SELECT items_applications_seq.NEXTVAL, ht.applicationid, hti.itemid
			FROM httptest ht, httptestitem hti
			WHERE ht.httptestid = hti.httptestid
				AND hti.type = 4
				AND itemid BETWEEN ' || min_nodeid ||' AND ' || max_nodeid;

		EXECUTE IMMEDIATE 'DROP SEQUENCE items_seq';
		EXECUTE IMMEDIATE 'DROP SEQUENCE httptestitem_seq';
		EXECUTE IMMEDIATE 'DROP SEQUENCE items_applications_seq';

	END LOOP;

	CLOSE node_cursor;
END;
/

DELETE FROM ids WHERE table_name IN ('items', 'httptestitem', 'items_applications');

---- Patching table `hosts`

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
ALTER TABLE hosts ADD jmx_disable_until number(10) DEFAULT '0' NOT NULL;
ALTER TABLE hosts ADD jmx_available number(10) DEFAULT '0' NOT NULL;
ALTER TABLE hosts ADD jmx_errors_from number(10) DEFAULT '0' NOT NULL;
ALTER TABLE hosts ADD jmx_error nvarchar2(128) DEFAULT '';
ALTER TABLE hosts ADD name nvarchar2(64) DEFAULT '';
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
UPDATE hosts SET name=host WHERE status in (0,1,3)	-- MONITORED, NOT_MONITORED, TEMPLATE
/
CREATE INDEX hosts_4 on hosts (name);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);
