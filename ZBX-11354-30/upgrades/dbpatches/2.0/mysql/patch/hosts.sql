-- Patching table `interfaces`

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

-- Patching table `items`

ALTER TABLE items
	CHANGE COLUMN description name VARCHAR(255) NOT NULL DEFAULT '',
	MODIFY itemid bigint unsigned NOT NULL,
	MODIFY hostid bigint unsigned NOT NULL,
	MODIFY units varchar(255) DEFAULT '' NOT NULL,
	MODIFY lastlogsize bigint unsigned DEFAULT '0' NOT NULL,
	MODIFY templateid bigint unsigned NULL,
	MODIFY valuemapid bigint unsigned NULL,
	ADD lastns integer NULL,
	ADD flags integer DEFAULT '0' NOT NULL,
	ADD filter varchar(255) DEFAULT '' NOT NULL,
	ADD interfaceid bigint unsigned NULL,
	ADD port varchar(64) DEFAULT '' NOT NULL,
	ADD description text NOT NULL,
	ADD inventory_link integer DEFAULT '0' NOT NULL,
	ADD lifetime varchar(64) DEFAULT '30' NOT NULL;
CREATE TEMPORARY TABLE tmp_items_itemid (itemid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_items_itemid (itemid) (SELECT itemid FROM items);
UPDATE items
	SET templateid=NULL
	WHERE templateid=0
		OR templateid NOT IN (SELECT itemid FROM tmp_items_itemid);
DROP TABLE tmp_items_itemid;
UPDATE items
	SET valuemapid=NULL
	WHERE valuemapid=0
		OR valuemapid NOT IN (SELECT valuemapid FROM valuemaps);
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
	SET key_ = CONCAT(SUBSTR(key_, 1, INSTR(key_, '[')), '"{HOST.CONN}",', SUBSTR(key_, INSTR(key_, '[') + 1))
	WHERE type IN (10)	-- EXTERNAL
		AND INSTR(key_, '[') <> 0;

UPDATE items
	SET key_ = CONCAT(key_, '["{HOST.CONN}"]')
	WHERE type IN (10)	-- EXTERNAL
		AND INSTR(key_, '[') = 0;

-- convert simple check keys to a new form

DELIMITER $
CREATE FUNCTION zbx_convert_simple_checks(v_itemid bigint unsigned, v_hostid bigint unsigned, v_key varchar(255))
RETURNS varchar(255)
LANGUAGE SQL
DETERMINISTIC
BEGIN
	DECLARE new_key varchar(255);
	DECLARE pos integer;

	SET new_key = 'net.tcp.service';
	SET pos = INSTR(v_key, '_perf');
	IF 0 <> pos THEN
		SET new_key = CONCAT(new_key, '.perf');
		SET v_key = CONCAT(SUBSTR(v_key, 1, pos - 1), SUBSTR(v_key, pos + 5));
	END IF;
	SET new_key = CONCAT(new_key, '[');
	SET pos = INSTR(v_key, ',');
	IF 0 <> pos THEN
		SET new_key = CONCAT(new_key, '"', SUBSTR(v_key, 1, pos - 1), '"');
		SET v_key = SUBSTR(v_key, pos + 1);
	ELSE
		SET new_key = CONCAT(new_key, '"', v_key, '"');
		SET v_key = '';
	END IF;
	IF 0 <> LENGTH(v_key) THEN
		SET new_key = CONCAT(new_key, ',,"', v_key, '"');
	END IF;

	WHILE 0 != (SELECT COUNT(*) FROM items WHERE hostid = v_hostid AND key_ = CONCAT(new_key, ']')) DO
		SET new_key = CONCAT(new_key, ' ');
	END WHILE;

	RETURN CONCAT(new_key, ']');
END$
DELIMITER ;

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

DROP FUNCTION zbx_convert_simple_checks;

-- adding web.test.error[<web check>] items

DROP PROCEDURE IF EXISTS zbx_add_web_error_items;

DELIMITER $
CREATE PROCEDURE zbx_add_web_error_items()
LANGUAGE SQL
BEGIN
	DECLARE v_nodeid integer;
	DECLARE init_nodeid bigint unsigned;
	DECLARE minid, maxid bigint unsigned;
	DECLARE n_done integer DEFAULT 0;
	DECLARE n_cur CURSOR FOR (SELECT DISTINCT httptestid div 100000000000000 FROM httptest);
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET n_done = 1;

	OPEN n_cur;

	n_loop: LOOP
		FETCH n_cur INTO v_nodeid;

		IF n_done THEN
			LEAVE n_loop;
		END IF;

		SET minid = v_nodeid * 100000000000000;
		SET maxid = minid + 99999999999999;
		SET init_nodeid = (v_nodeid * 1000 + v_nodeid) * 100000000000;

		SET @itemid = (SELECT MAX(itemid) FROM items where itemid BETWEEN minid AND maxid);
		IF @itemid IS NULL THEN
			SET @itemid = init_nodeid;
		END IF;

		SET @httptestitemid = (SELECT MAX(httptestitemid) FROM httptestitem where httptestitemid BETWEEN minid AND maxid);
		IF @httptestitemid IS NULL THEN
			SET @httptestitemid = init_nodeid;
		END IF;

		SET @itemappid = (SELECT MAX(itemappid) FROM items_applications where itemappid BETWEEN minid AND maxid);
		IF @itemappid IS NULL THEN
			SET @itemappid = init_nodeid;
		END IF;

		INSERT INTO items (itemid, hostid, type, name, key_, value_type, units, delay, history, trends, status, params, description)
			SELECT @itemid := @itemid + 1, hostid, type, 'Last error message of scenario \'$1\'', CONCAT('web.test.error',
				SUBSTR(key_, LOCATE('[', key_))), 1, '', delay, history, 0, status, '', ''
				FROM items
				WHERE type = 9
					AND key_ LIKE 'web.test.fail%'
					AND itemid BETWEEN minid AND maxid;

		INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type)
			SELECT @httptestitemid := @httptestitemid + 1, ht.httptestid, i.itemid, 4
				FROM httptest ht,applications a,items i
				WHERE ht.applicationid=a.applicationid
					AND a.hostid=i.hostid
					AND CONCAT('web.test.error[', ht.name, ']') = i.key_
					AND i.itemid BETWEEN minid AND maxid;

		INSERT INTO items_applications (itemappid, applicationid, itemid)
			SELECT @itemappid := @itemappid + 1, ht.applicationid, hti.itemid
				FROM httptest ht, httptestitem hti
				WHERE ht.httptestid = hti.httptestid
					AND hti.type = 4
					AND hti.itemid BETWEEN minid AND maxid;

	END LOOP n_loop;

	CLOSE n_cur;
END$
DELIMITER ;

CALL zbx_add_web_error_items();
DROP PROCEDURE zbx_add_web_error_items;
DELETE FROM ids WHERE table_name IN ('items', 'httptestitem', 'items_applications');

-- Patching table `hosts`

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
		  DROP COLUMN outbytes,
		  ADD jmx_disable_until integer DEFAULT '0' NOT NULL,
		  ADD jmx_available integer DEFAULT '0' NOT NULL,
		  ADD jmx_errors_from integer DEFAULT '0' NOT NULL,
		  ADD jmx_error varchar(128) DEFAULT '' NOT NULL,
		  ADD name varchar(64) DEFAULT '' NOT NULL;
CREATE TEMPORARY TABLE tmp_hosts_hostid (hostid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_hosts_hostid (hostid) (SELECT hostid FROM hosts);
UPDATE hosts
	SET proxy_hostid=NULL
	WHERE proxy_hostid=0
		OR proxy_hostid NOT IN (SELECT hostid FROM tmp_hosts_hostid);
DROP TABLE tmp_hosts_hostid;
UPDATE hosts
	SET maintenanceid=NULL,
		maintenance_status=0,
		maintenance_type=0,
		maintenance_from=0
	WHERE maintenanceid=0
		OR maintenanceid NOT IN (SELECT maintenanceid FROM maintenances);
UPDATE hosts SET name=host WHERE status in (0,1,3);	-- MONITORED, NOT_MONITORED, TEMPLATE
CREATE INDEX hosts_4 on hosts (name);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);
