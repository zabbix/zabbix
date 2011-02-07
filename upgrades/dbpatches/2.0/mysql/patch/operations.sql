---- Patching table `opmessage`

CREATE TABLE opmessage (
	operationid              bigint unsigned                           NOT NULL,
	default_msg              integer         DEFAULT '0'               NOT NULL,
	subject                  varchar(255)    DEFAULT ''                NOT NULL,
	message                  text                                      NOT NULL,
	mediatypeid              bigint unsigned                           NULL,
	PRIMARY KEY (operationid)
) ENGINE=InnoDB;
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid);

INSERT INTO opmessage (operationid, default_msg, subject, message)
	SELECT operationid, default_msg, shortdata, longdata
		FROM operations
		WHERE operationtype IN (0);	-- OPERATION_TYPE_MESSAGE

UPDATE opmessage
	SET mediatypeid = (
		SELECT mediatypeid
			FROM opmediatypes
			WHERE operationid = opmessage.operationid)
	WHERE operationid IN (
		SELECT omt.operationid
			FROM opmediatypes omt, media_type mt
			WHERE omt.mediatypeid = mt.mediatypeid);

---- Patching table `opmessage_grp`

CREATE TABLE opmessage_grp (
	opmessage_grpid          bigint unsigned                           NOT NULL,
	operationid              bigint unsigned                           NOT NULL,
	usrgrpid                 bigint unsigned                           NOT NULL,
	PRIMARY KEY (opmessage_grpid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (operationid,usrgrpid);
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);

SET @opmessage_grpid := 0;
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid)
	SELECT @opmessage_grpid := @opmessage_grpid + 1, o.operationid, o.objectid
		FROM operations o, usrgrp g
		WHERE o.objectid = g.usrgrpid
			AND o.object IN (1);	-- OPERATION_OBJECT_GROUP

UPDATE opmessage_grp
	SET opmessage_grpid = (operationid div 100000000000) * 100000000000 + opmessage_grpid
	WHERE opmessage_grpid >= 100000000000;

---- Patching table `opmessage_usr`

CREATE TABLE opmessage_usr (
	opmessage_usrid          bigint unsigned                           NOT NULL,
	operationid              bigint unsigned                           NOT NULL,
	userid                   bigint unsigned                           NOT NULL,
	PRIMARY KEY (opmessage_usrid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (operationid,userid);
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid);

SET @opmessage_usrid := 0;
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid)
	SELECT @opmessage_usrid := @opmessage_usrid + 1, o.operationid, o.objectid
		FROM operations o, users u
		WHERE o.objectid = u.userid
			AND o.object IN (0);	-- OPERATION_OBJECT_USER

UPDATE opmessage_usr
	SET opmessage_usrid = (operationid div 100000000000) * 100000000000 + opmessage_usrid
	WHERE opmessage_usrid >= 100000000000;

---- Patching tables `opcommand_hst` and `opcommand_grp`

-- creating temporary tables

DROP PROCEDURE IF EXISTS split_commands;
DROP TABLE IF EXISTS _opcommand_hst;
DROP TABLE IF EXISTS _opcommand_grp;

CREATE TEMPORARY TABLE _opcommand_hst (
	operationid bigint unsigned,
	name varchar(64),
	longdata varchar(2048),
	hostid bigint unsigned
) ENGINE=MEMORY;

CREATE TEMPORARY TABLE _opcommand_grp (
	operationid bigint unsigned,
	name varchar(64),
	longdata varchar(2048),
	groupid bigint unsigned
) ENGINE=MEMORY;

DELIMITER /
CREATE PROCEDURE split_commands ()
BEGIN
	DECLARE done INT DEFAULT 0;
	DECLARE l_pos, r_pos, h_pos, g_pos INT;
	DECLARE _operationid bigint unsigned DEFAULT 0;
	DECLARE _longdata text DEFAULT '';
	DECLARE cur_string varchar(2048);
	DECLARE op_cur CURSOR FOR (SELECT operationid, longdata FROM operations WHERE operationtype = 1);
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

	OPEN op_cur;

	read_loop: LOOP
		FETCH op_cur INTO _operationid, _longdata;

		IF done THEN
			LEAVE read_loop;
		END IF;

		SET r_pos = 1;
		SET l_pos = 1;

		WHILE r_pos > 0 DO
			SET r_pos = LOCATE('\n', _longdata, l_pos);

			IF r_pos = 0 THEN
				SET cur_string = SUBSTRING(_longdata, l_pos);
			ELSE
				SET cur_string = SUBSTRING(_longdata, l_pos, r_pos - l_pos);
			END IF;

			SET cur_string = TRIM(TRAILING '\r' FROM cur_string);
			SET cur_string = TRIM(cur_string);

			IF CHAR_LENGTH(cur_string) <> 0 THEN
				SET h_pos = LOCATE(':', cur_string);
				SET g_pos = LOCATE('#', cur_string);

				IF h_pos <> 0 AND (g_pos = 0 OR h_pos < g_pos) THEN
					INSERT INTO _opcommand_hst
						VALUES (_operationid, TRIM(SUBSTRING(cur_string, 1, h_pos - 1)), TRIM(SUBSTRING(cur_string, h_pos + 1)), NULL);
				END IF;

				IF g_pos <> 0 AND (h_pos = 0 OR g_pos < h_pos) THEN
					INSERT INTO _opcommand_grp
						VALUES (_operationid, TRIM(SUBSTRING(cur_string, 1, g_pos - 1)), TRIM(SUBSTRING(cur_string, g_pos + 1)), NULL);
				END IF;
			END IF;

			SET l_pos = r_pos + 1;
		END WHILE;
	END LOOP read_loop;

	CLOSE op_cur;
END
/
DELIMITER ;

CALL split_commands;

CREATE TABLE opcommand_hst (
	opcommand_hstid          bigint unsigned                           NOT NULL,
	operationid              bigint unsigned                           NOT NULL,
	hostid                   bigint unsigned                           NULL,
	command                  text                                      NOT NULL,
	PRIMARY KEY (opcommand_hstid)
) ENGINE=InnoDB;
CREATE INDEX opcommand_hst_1 ON opcommand_hst (operationid);
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid);

DELETE FROM _opcommand_hst
	WHERE name <> '{HOSTNAME}'
		AND NOT EXISTS (
			SELECT h.hostid
				FROM hosts h
				WHERE h.host = _opcommand_hst.name
					AND (h.hostid div 100000000000000) = (_opcommand_hst.operationid div 100000000000000));

UPDATE _opcommand_hst
	SET hostid = (
		SELECT MIN(h.hostid)
			FROM hosts h
			WHERE h.host = _opcommand_hst.name
				AND (h.hostid div 100000000000000) = (_opcommand_hst.operationid div 100000000000000))
	WHERE name <> '{HOSTNAME}';

SET @opcommand_hstid := 0;
INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid, command)
	SELECT @opcommand_hstid := @opcommand_hstid + 1, operationid, hostid, longdata
		FROM _opcommand_hst;

UPDATE opcommand_hst
	SET opcommand_hstid = (operationid div 100000000000) * 100000000000 + opcommand_hstid
	WHERE operationid >= 100000000000;

CREATE TABLE opcommand_grp (
	opcommand_grpid          bigint unsigned                           NOT NULL,
	operationid              bigint unsigned                           NOT NULL,
	groupid                  bigint unsigned                           NOT NULL,
	command                  text                                      NOT NULL,
	PRIMARY KEY (opcommand_grpid)
) ENGINE=InnoDB;
CREATE INDEX opcommand_grp_1 ON opcommand_grp (operationid);
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

DELETE FROM _opcommand_grp
	WHERE NOT EXISTS (
		SELECT g.groupid
			FROM groups g
			WHERE g.name = _opcommand_grp.name
				AND (g.groupid div 100000000000000) = (_opcommand_grp.operationid div 100000000000000));

UPDATE _opcommand_grp
	SET groupid = (
		SELECT MIN(g.groupid)
			FROM groups g
			WHERE g.name = _opcommand_grp.name
				AND (g.groupid div 100000000000000) = (_opcommand_grp.operationid div 100000000000000));

SET @opcommand_grpid := 0;
INSERT INTO opcommand_grp (opcommand_grpid, operationid, groupid, command)
	SELECT @opcommand_grpid := @opcommand_grpid + 1, operationid, groupid, longdata
		FROM _opcommand_grp;

UPDATE opcommand_grp
	SET opcommand_grpid = (operationid div 100000000000) * 100000000000 + opcommand_grpid
	WHERE operationid >= 100000000000;

DROP PROCEDURE split_commands;
DROP TABLE _opcommand_hst;
DROP TABLE _opcommand_grp;

---- Patching table `opgroup`

CREATE TABLE opgroup (
	opgroupid                bigint unsigned                           NOT NULL,
	operationid              bigint unsigned                           NOT NULL,
	groupid                  bigint unsigned                           NOT NULL,
	PRIMARY KEY (opgroupid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX opgroup_1 ON opgroup (operationid,groupid);
ALTER TABLE opgroup ADD CONSTRAINT c_opgroup_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opgroup ADD CONSTRAINT c_opgroup_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

SET @opgroupid := 0;
INSERT INTO opgroup (opgroupid, operationid, groupid)
	SELECT @opgroupid := @opgroupid + 1, o.operationid, o.objectid
		FROM operations o, groups g
		WHERE o.objectid = g.groupid
			AND o.operationtype IN (4,5);	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE

UPDATE opgroup
	SET opgroupid = (operationid div 100000000000) * 100000000000 + opgroupid
	WHERE operationid >= 100000000000;

---- Patching table `optemplate`

CREATE TABLE optemplate (
	optemplateid             bigint unsigned                           NOT NULL,
	operationid              bigint unsigned                           NOT NULL,
	templateid               bigint unsigned                           NOT NULL,
	PRIMARY KEY (optemplateid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX optemplate_1 ON optemplate (operationid,templateid);
ALTER TABLE optemplate ADD CONSTRAINT c_optemplate_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE optemplate ADD CONSTRAINT c_optemplate_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid);

SET @optemplateid := 0;
INSERT INTO optemplate (optemplateid, operationid, templateid)
	SELECT @optemplateid := @optemplateid + 1, o.operationid, o.objectid
		FROM operations o, hosts h
		WHERE o.objectid = h.hostid
			AND o.operationtype IN (6,7);	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE

UPDATE optemplate
	SET optemplateid = (operationid div 100000000000) * 100000000000 + optemplateid
	WHERE operationid >= 100000000000;

---- Patching table `operations`

ALTER TABLE operations MODIFY operationid bigint unsigned NOT NULL,
		       MODIFY actionid bigint unsigned NOT NULL,
		       MODIFY esc_step_from integer NOT NULL DEFAULT '1',
		       DROP COLUMN object,
		       DROP COLUMN objectid,
		       DROP COLUMN shortdata,
		       DROP COLUMN longdata,
		       DROP COLUMN default_msg;
DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;

---- Dropping table `opmediatypes`

DROP TABLE opmediatypes;
