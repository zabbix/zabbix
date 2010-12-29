---- Patching table `opmessage`

CREATE TABLE opmessage (
	opmessageid              bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	default_msg              integer         WITH DEFAULT '0'          NOT NULL,
	subject                  varchar(255)    WITH DEFAULT ''           NOT NULL,
	message                  varchar(2048)   WITH DEFAULT ''           NOT NULL,
	mediatypeid              bigint                                    NULL,
	PRIMARY KEY (opmessageid)
)
/
CREATE INDEX opmessage_1 on opmessage (operationid)
/
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid)
/

CREATE SEQUENCE opmessage_seq AS bigint
/

INSERT INTO opmessage (opmessageid, operationid, default_msg, subject, message)
	SELECT NEXTVAL FOR opmessage_seq, operationid, default_msg, shortdata, longdata
		FROM operations
		WHERE operationtype IN (0)	-- OPERATION_TYPE_MESSAGE
/

DROP SEQUENCE opmessage_seq
/

UPDATE opmessage
	SET mediatypeid = (
		SELECT mediatypeid
			FROM opmediatypes
			WHERE operationid = opmessage.operationid)
	WHERE operationid IN (
		SELECT omt.operationid
			FROM opmediatypes omt, media_type mt
			WHERE omt.mediatypeid = mt.mediatypeid)
/

UPDATE opmessage
	SET opmessageid = TRUNC(operationid / 100000000000) * 100000000000 + opmessageid
	WHERE operationid >= 100000000000
/

---- Patching table `opmessage_grp`

CREATE TABLE opmessage_grp (
	opmessage_grpid          bigint                                    NOT NULL,
	opmessageid              bigint                                    NOT NULL,
	usrgrpid                 bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_grpid)
)
/
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (opmessageid,usrgrpid)
/
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (opmessageid) REFERENCES opmessage (opmessageid) ON DELETE CASCADE
/
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid)
/

CREATE SEQUENCE opmessage_grp_seq AS bigint
/

INSERT INTO opmessage_grp (opmessage_grpid, opmessageid, usrgrpid)
	SELECT NEXTVAL FOR opmessage_grp_seq, m.opmessageid, o.objectid
		FROM opmessage m, operations o, usrgrp g
		WHERE m.operationid = o.operationid
			AND o.objectid = g.usrgrpid
			AND o.object IN (1)	-- OPERATION_OBJECT_GROUP
/

DROP SEQUENCE opmessage_grp_seq
/

UPDATE opmessage_grp
	SET opmessage_grpid = TRUNC(opmessageid / 100000000000) * 100000000000 + opmessage_grpid
	WHERE opmessage_grpid >= 100000000000
/

---- Patching table `opmessage_usr`

CREATE TABLE opmessage_usr (
	opmessage_usrid          bigint                                    NOT NULL,
	opmessageid              bigint                                    NOT NULL,
	userid                   bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_usrid)
)
/
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (opmessageid,userid)
/
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (opmessageid) REFERENCES opmessage (opmessageid) ON DELETE CASCADE
/
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid)
/

CREATE SEQUENCE opmessage_usr_seq
/

INSERT INTO opmessage_usr (opmessage_usrid, opmessageid, userid)
	SELECT NEXTVAL FOR opmessage_usr_seq, m.opmessageid, o.objectid
		FROM opmessage m, operations o, users u
		WHERE m.operationid = o.operationid
			AND o.objectid = u.userid
			AND o.object IN (0)	-- OPERATION_OBJECT_USER
/

DROP SEQUENCE opmessage_usr_seq
/

UPDATE opmessage_usr
	SET opmessage_usrid = TRUNC(opmessageid / 100000000000) * 100000000000 + opmessage_usrid
	WHERE opmessage_usrid >= 100000000000
/

---- Patching tables `opcommand_hst` and `opcommand_grp`

CREATE TABLE opcommand_hst (
	opcommand_hstid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	hostid                   bigint                                    NULL,
	command                  varchar(2048)   WITH DEFAULT ''           NOT NULL,
	PRIMARY KEY (opcommand_hstid)
)
/
CREATE INDEX opcommand_hst_1 on opcommand_hst (operationid)
/
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid)
/

CREATE TABLE opcommand_grp (
	opcommand_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	command                  varchar(2048)   WITH DEFAULT ''           NOT NULL,
	PRIMARY KEY (opcommand_grpid)
)
/
CREATE INDEX opcommand_grp_1 on opcommand_grp (operationid)
/
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_2 FOREIGN KEY (groupid) REFERENCES groups (groupid)
/

-- creating temporary table `tmp_opcommand`

CREATE TABLE tmp_opcommand (
	operationid bigint,
	longdata varchar(2048)
)
/

CREATE PROCEDURE split_commands()
LANGUAGE SQL
BEGIN
	DECLARE v_longdata	varchar(2048);
	DECLARE v_cur_string	varchar(2048);
	DECLARE v_operationid	bigint;
	DECLARE r_pos		integer;
	DECLARE l_pos		integer;
	DECLARE done		integer DEFAULT 0;
	DECLARE not_found	CONDITION FOR SQLSTATE '02000';
	DECLARE	op_cur		CURSOR FOR SELECT operationid, longdata FROM operations WHERE operationtype = 1;
	DECLARE			CONTINUE HANDLER FOR not_found SET done = 1;

	OPEN op_cur;

	read_loop: LOOP
		FETCH op_cur INTO v_operationid, v_longdata;

		IF done = 1 THEN
			LEAVE read_loop;
		END IF;

		SET r_pos = 1;
		SET l_pos = 1;

		LOOP
			SET r_pos = INSTR(v_longdata, CHR(10), l_pos);

			IF r_pos = 0 THEN
				SET v_cur_string = SUBSTR(v_longdata, l_pos);
			ELSE
				SET v_cur_string = SUBSTR(v_longdata, l_pos, r_pos - l_pos);
			END IF;

			SET v_cur_string = STRIP(v_cur_string, TRAILING, X'0D');

			IF LENGTH(v_cur_string) > 0 THEN
				INSERT INTO tmp_opcommand (operationid, longdata)
					VALUES (v_operationid, v_cur_string);
			END IF;

			IF r_pos = 0 THEN
				LEAVE read_loop;
			END IF;

			SET l_pos = r_pos + 1;
		END LOOP;
	END LOOP;

	CLOSE op_cur;
END
/

CALL split_commands()
/

DROP PROCEDURE split_commands()
/

-- creating temporary table `tmp_opcommand_hst`

CREATE TABLE tmp_opcommand_hst (
	operationid bigint,
	name varchar(64),
	longdata varchar(2048),
	hostid bigint
)
/

INSERT INTO tmp_opcommand_hst (operationid, longdata)
	SELECT operationid, longdata
		FROM tmp_opcommand
		WHERE INSTR(longdata, ':') <> 0 AND (INSTR(longdata, '#') = 0 OR INSTR(longdata, ':') < INSTR(longdata, '#'))
/

UPDATE tmp_opcommand_hst
	SET name = TRIM(SUBSTR(longdata, 1, INSTR(longdata, ':') - 1)),
		longdata = TRIM(SUBSTR(longdata, INSTR(longdata, ':') + 1))
/

DELETE FROM tmp_opcommand_hst
	WHERE name <> '{HOSTNAME}'
		AND NOT EXISTS (
			SELECT h.hostid
				FROM hosts h
				WHERE h.host = tmp_opcommand_hst.name
					AND TRUNC(h.hostid / 100000000000000) = TRUNC(tmp_opcommand_hst.operationid / 100000000000000))
/

UPDATE tmp_opcommand_hst
	SET hostid = (
		SELECT MIN(h.hostid)
			FROM hosts h
			WHERE h.host = tmp_opcommand_hst.name
				AND TRUNC(h.hostid / 100000000000000) = TRUNC(tmp_opcommand_hst.operationid / 100000000000000))
	WHERE name <> '{HOSTNAME}'
/

CREATE SEQUENCE opcommand_hst_seq AS bigint
/

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid, command)
	SELECT NEXTVAL FOR opcommand_hst_seq, operationid, hostid, longdata
		FROM tmp_opcommand_hst
/

DROP SEQUENCE opcommand_hst_seq
/

UPDATE opcommand_hst
	SET opcommand_hstid = TRUNC(operationid / 100000000000) * 100000000000 + opcommand_hstid
	WHERE operationid >= 100000000000
/

DROP TABLE tmp_opcommand_hst
/

-- creating temporary table `tmp_opcommand_grp`

CREATE TABLE tmp_opcommand_grp (
	operationid bigint,
	name varchar(64),
	longdata varchar(2048),
	groupid bigint
)
/

INSERT INTO tmp_opcommand_grp (operationid, longdata)
	SELECT operationid, longdata
		FROM tmp_opcommand
		WHERE INSTR(longdata, '#') <> 0 AND (INSTR(longdata, ':') = 0 OR INSTR(longdata, '#') < INSTR(longdata, ':'))
/

UPDATE tmp_opcommand_grp
	SET name = TRIM(SUBSTR(longdata, 1, INSTR(longdata, '#') - 1)),
		longdata = TRIM(SUBSTR(longdata, INSTR(longdata, '#') + 1))
/

DELETE FROM tmp_opcommand_grp
	WHERE NOT EXISTS (
		SELECT g.groupid
			FROM groups g
			WHERE g.name = tmp_opcommand_grp.name
				AND TRUNC(g.groupid / 100000000000000) = TRUNC(tmp_opcommand_grp.operationid / 100000000000000))
/

UPDATE tmp_opcommand_grp
	SET groupid = (
		SELECT MIN(g.groupid)
			FROM groups g
			WHERE g.name = tmp_opcommand_grp.name
				AND TRUNC(g.groupid / 100000000000000) = TRUNC(tmp_opcommand_grp.operationid / 100000000000000))
/

CREATE SEQUENCE opcommand_grp_seq AS bigint
/

INSERT INTO opcommand_grp (opcommand_grpid, operationid, groupid, command)
	SELECT NEXTVAL FOR opcommand_grp_seq, operationid, groupid, longdata
		FROM tmp_opcommand_grp
/

DROP SEQUENCE opcommand_grp_seq
/

UPDATE opcommand_grp
	SET opcommand_grpid = TRUNC(operationid / 100000000000) * 100000000000 + opcommand_grpid
	WHERE operationid >= 100000000000
/

DROP TABLE tmp_opcommand_grp
/
DROP TABLE tmp_opcommand
/

---- Patching table `opgroup`

CREATE TABLE opgroup (
	opgroupid                bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	PRIMARY KEY (opgroupid)
)
/
CREATE UNIQUE INDEX opgroup_1 ON opgroup (operationid,groupid)
/
ALTER TABLE opgroup ADD CONSTRAINT c_opgroup_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opgroup ADD CONSTRAINT c_opgroup_2 FOREIGN KEY (groupid) REFERENCES groups (groupid)
/

CREATE SEQUENCE opgroup_seq AS bigint
/

INSERT INTO opgroup (opgroupid, operationid, groupid)
	SELECT NEXTVAL FOR opgroup_seq, o.operationid, o.objectid
		FROM operations o, groups g
		WHERE o.objectid = g.groupid
			AND o.operationtype IN (4,5)	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE
/

DROP SEQUENCE opgroup_seq
/

UPDATE opgroup
	SET opgroupid = TRUNC(operationid / 100000000000) * 100000000000 + opgroupid
	WHERE operationid >= 100000000000
/

---- Patching table `optemplate`

CREATE TABLE optemplate (
	optemplateid             bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	templateid               bigint                                    NOT NULL,
	PRIMARY KEY (optemplateid)
)
/
CREATE UNIQUE INDEX optemplate_1 ON optemplate (operationid,templateid)
/
ALTER TABLE optemplate ADD CONSTRAINT c_optemplate_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE optemplate ADD CONSTRAINT c_optemplate_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid)
/

CREATE SEQUENCE optemplate_seq AS bigint
/

INSERT INTO optemplate (optemplateid, operationid, templateid)
	SELECT NEXTVAL FOR optemplate_seq, o.operationid, o.objectid
		FROM operations o, hosts h
		WHERE o.objectid = h.hostid
			AND o.operationtype IN (6,7)	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE
/

DROP SEQUENCE optemplate_seq
/

UPDATE optemplate
	SET optemplateid = TRUNC(operationid / 100000000000) * 100000000000 + optemplateid
	WHERE operationid >= 100000000000
/

---- Patching table `operations`

ALTER TABLE operations ALTER COLUMN operationid SET WITH DEFAULT NULL
/
REORG TABLE operations
/
ALTER TABLE operations ALTER COLUMN actionid SET WITH DEFAULT NULL
/
REORG TABLE operations
/
ALTER TABLE operations DROP COLUMN object
/
REORG TABLE operations
/
ALTER TABLE operations DROP COLUMN objectid
/
REORG TABLE operations
/
ALTER TABLE operations DROP COLUMN shortdata
/
REORG TABLE operations
/
ALTER TABLE operations DROP COLUMN longdata
/
REORG TABLE operations
/
ALTER TABLE operations DROP COLUMN default_msg
/
REORG TABLE operations
/
DELETE FROM operations WHERE actionid NOT IN (SELECT actionid FROM actions)
/
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE
/
REORG TABLE operations
/

---- Dropping table `opmediatypes`

DROP TABLE opmediatypes
/
