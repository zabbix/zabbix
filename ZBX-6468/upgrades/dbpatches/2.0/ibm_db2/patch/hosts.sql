---- Patching table `interfaces`

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
)
/
CREATE INDEX interface_1 on interface (hostid,type)
/
CREATE INDEX interface_2 on interface (ip,dns)
/
ALTER TABLE interface ADD CONSTRAINT c_interface_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/

-- Passive proxy interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000),
		hostid,1,0,ip,dns,useip,port
	FROM hosts
	WHERE status IN (6))
/

-- Zabbix Agent interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000),
		hostid,1,1,ip,dns,useip,port
	FROM hosts
	WHERE status IN (0,1))
/

-- SNMP interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000) + 1,
		hostid,1,2,ip,dns,useip,'161'
	FROM hosts
	WHERE status IN (0,1)
		AND EXISTS (SELECT DISTINCT i.hostid FROM items i WHERE i.hostid=hosts.hostid and i.type IN (1,4,6)))
/

-- IPMI interface
INSERT INTO interface (interfaceid,hostid,main,type,ip,dns,useip,port)
	(SELECT (hostid - ((hostid / 100000000000)*100000000000)) * 3 + ((hostid / 100000000000)*100000000000) + 2,
		hostid,1,3,'',ipmi_ip,0,ipmi_port
	FROM hosts
	WHERE status IN (0,1) AND useipmi=1)
/

---- Patching table `items`
ALTER TABLE items RENAME COLUMN description TO name
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN units SET DATA TYPE varchar(255)
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN lastlogsize SET DATA TYPE bigint
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN templateid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN templateid DROP NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN valuemapid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN valuemapid DROP NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD lastns integer NULL
/
REORG TABLE items
/
ALTER TABLE items ADD flags integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD filter varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD interfaceid bigint NULL
/
REORG TABLE items
/
ALTER TABLE items ADD port varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD description varchar(2048) WITH DEFAULT '' NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD inventory_link integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD lifetime varchar(64) WITH DEFAULT '30' NOT NULL
/
REORG TABLE items
/
UPDATE items
	SET templateid=NULL
	WHERE templateid=0
		OR templateid NOT IN (SELECT itemid FROM items)
/
UPDATE items
	SET valuemapid=NULL
	WHERE valuemapid=0
		OR valuemapid NOT IN (SELECT valuemapid from valuemaps)
/
UPDATE items SET units='Bps' WHERE type=9 AND units='bps'
/
DELETE FROM items WHERE hostid NOT IN (SELECT hostid FROM hosts)
/
CREATE INDEX items_5 on items (valuemapid)
/
ALTER TABLE items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
ALTER TABLE items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE
/
ALTER TABLE items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid)
/
ALTER TABLE items ADD CONSTRAINT c_items_4 FOREIGN KEY (interfaceid) REFERENCES interface (interfaceid)
/

UPDATE items SET port=snmp_port
/
ALTER TABLE items DROP COLUMN snmp_port
/
REORG TABLE items
/

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
	WHERE EXISTS(SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
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
		AND INSTR(key_, '[') <> 0
/

UPDATE items
	SET key_ = key_ || '["{HOST.CONN}"]'
	WHERE type IN (10)	-- EXTERNAL
		AND INSTR(key_, '[') = 0
/

-- convert simple check keys to a new form

CREATE TABLE t_keys (
	hostid bigint NOT NULL,
	key_ varchar(255) NOT NULL,
	PRIMARY KEY (hostid, key_)
)
/

CREATE FUNCTION zbx_convert_simple_checks(v_itemid bigint, v_hostid bigint, v_key varchar(255))
RETURNS varchar(255)
LANGUAGE SQL
BEGIN
	DECLARE new_key varchar(255);
	DECLARE pos integer;

	SET new_key = 'net.tcp.service';
	SET pos = INSTR(v_key, '_perf');
	IF 0 <> pos THEN
		SET new_key = new_key || '.perf';
		SET v_key = SUBSTR(v_key, 1, pos - 1) || SUBSTR(v_key, pos + 5);
	END IF;
	SET new_key = new_key || '[';
	SET pos = INSTR(v_key, ',');
	IF 0 <> pos THEN
		SET new_key = new_key || '"' || SUBSTR(v_key, 1, pos - 1) || '"';
		SET v_key = SUBSTR(v_key, pos + 1);
	ELSE
		SET new_key = new_key || '"' || v_key || '"';
		SET v_key = '';
	END IF;
	IF 0 <> LENGTH(v_key) THEN
		SET new_key = new_key || ',,"' || v_key || '"';
	END IF;

	WHILE 0 != (SELECT COUNT(*) FROM t_keys WHERE hostid = v_hostid AND key_ = new_key || ']') DO
		SET new_key = new_key || ' ';
	END WHILE;

	RETURN new_key || ']';
END
/

INSERT INTO t_keys
	SELECT hostid, key_
		FROM items
		WHERE key_ LIKE 'net.tcp.service[%'
/

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
			OR key_ LIKE 'tcp_perf,%')
/

DROP TABLE t_keys
/

DROP FUNCTION zbx_convert_simple_checks
/

ROLLBACK
/

-- adding web.test.error[<web check>] items

CREATE SEQUENCE items_seq AS bigint
/
CREATE SEQUENCE httptestitem_seq AS bigint
/
CREATE SEQUENCE items_applications_seq AS bigint
/

CREATE PROCEDURE zbx_add_web_test_error()
LANGUAGE SQL
BEGIN
	DECLARE max_itemid bigint;
	DECLARE max_httptestitemid bigint;
	DECLARE max_itemappid bigint;

	BEGIN
		DECLARE m_done integer DEFAULT 0;
		DECLARE m_not_found CONDITION FOR SQLSTATE '02000';
		DECLARE m_cur CURSOR FOR (SELECT MAX(itemid) FROM items);
		DECLARE CONTINUE HANDLER FOR m_not_found SET m_done = 1;

		OPEN m_cur;

		m_loop: LOOP
			FETCH m_cur INTO max_itemid;

			IF m_done = 1 THEN
				LEAVE m_loop;
			END IF;
		END LOOP m_loop;

		CLOSE m_cur;
	END;

	BEGIN
		DECLARE m_done integer DEFAULT 0;
		DECLARE m_not_found CONDITION FOR SQLSTATE '02000';
		DECLARE m_cur CURSOR FOR (SELECT MAX(httptestitemid) FROM httptestitem);
		DECLARE CONTINUE HANDLER FOR m_not_found SET m_done = 1;

		OPEN m_cur;

		m_loop: LOOP
			FETCH m_cur INTO max_httptestitemid;

			IF m_done = 1 THEN
				LEAVE m_loop;
			END IF;
		END LOOP m_loop;

		CLOSE m_cur;
	END;

	BEGIN
		DECLARE m_done integer DEFAULT 0;
		DECLARE m_not_found CONDITION FOR SQLSTATE '02000';
		DECLARE m_cur CURSOR FOR (SELECT MAX(itemappid) FROM items_applications);
		DECLARE CONTINUE HANDLER FOR m_not_found SET m_done = 1;

		OPEN m_cur;

		m_loop: LOOP
			FETCH m_cur INTO max_itemappid;

			IF m_done = 1 THEN
				LEAVE m_loop;
			END IF;
		END LOOP m_loop;

		CLOSE m_cur;
	END;

	IF max_itemid IS NULL OR max_httptestitemid IS NULL OR max_itemappid IS NULL THEN
		RETURN;
	END IF;

	BEGIN
		DECLARE v_httptestid bigint;
		DECLARE v_name varchar(64);
		DECLARE v_delay integer;
		DECLARE v_status integer;
		DECLARE v_applicationid bigint;
		DECLARE v_hostid bigint;
		DECLARE v_itemid bigint;
		DECLARE v_httptestitemid bigint;
		DECLARE v_itemappid bigint;
		DECLARE m_done integer DEFAULT 0;
		DECLARE m_not_found CONDITION FOR SQLSTATE '02000';
		DECLARE m_cur CURSOR FOR (
			SELECT ht.httptestid, ht.name, ht.delay, ht.status, a.applicationid, a.hostid
				FROM httptest ht,applications a
				WHERE ht.applicationid = a.applicationid
		);
		DECLARE CONTINUE HANDLER FOR m_not_found SET m_done = 1;

		OPEN m_cur;

		m_loop: LOOP
			FETCH m_cur INTO v_httptestid, v_name, v_delay, v_status, v_applicationid, v_hostid;

			IF m_done = 1 THEN
				LEAVE m_loop;
			END IF;

			SET v_itemid = max_itemid + (NEXTVAL FOR items_seq);
			SET v_httptestitemid = max_httptestitemid + (NEXTVAL FOR httptestitem_seq);
			SET v_itemappid = max_itemappid + (NEXTVAL FOR items_applications_seq);

			INSERT INTO items (itemid, hostid, type, name, key_, value_type, units, delay, history, trends, status)
				VALUES (v_itemid, v_hostid, 9, 'Last error message of scenario ''$1''', 'web.test.error[' || v_name || ']', 1, '', v_delay, 30, 0, v_status);

			INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type)
				VALUES (v_httptestitemid, v_httptestid, v_itemid, 4);

			INSERT INTO items_applications (itemappid, applicationid, itemid)
				VALUES (v_itemappid, v_applicationid, v_itemid);
		END LOOP m_loop;

		CLOSE m_cur;
	END;
END
/

CALL zbx_add_web_test_error
/

DROP PROCEDURE zbx_add_web_test_error
/

DROP SEQUENCE items_applications_seq
/
DROP SEQUENCE httptestitem_seq
/
DROP SEQUENCE items_seq
/

DELETE FROM ids WHERE table_name IN ('items', 'httptestitem', 'items_applications')
/

---- Patching table `hosts`

ALTER TABLE hosts ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN proxy_hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN proxy_hostid DROP NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN maintenanceid SET WITH DEFAULT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN maintenanceid DROP NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN ip
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN dns
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN port
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN useip
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN useipmi
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN ipmi_ip
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN ipmi_port
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN inbytes
/
REORG TABLE hosts
/
ALTER TABLE hosts DROP COLUMN outbytes
/
REORG TABLE hosts
/
ALTER TABLE hosts ADD jmx_disable_until integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ADD jmx_available integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ADD jmx_errors_from integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ADD jmx_error varchar(128) WITH DEFAULT '' NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ADD name varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE hosts
/
UPDATE hosts
	SET proxy_hostid=NULL
	WHERE proxy_hostid=0
		OR NOT EXISTS (SELECT 1 FROM hosts h WHERE h.hostid=hosts.proxy_hostid)
/
UPDATE hosts
	SET maintenanceid=NULL,
		maintenance_status=0,
		maintenance_type=0,
		maintenance_from=0
	WHERE maintenanceid=0
		OR NOT EXISTS (SELECT 1 FROM maintenances m WHERE m.maintenanceid=hosts.maintenanceid)
/
UPDATE hosts SET name=host WHERE status in (0,1,3)
/
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid)
/
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid)
/
