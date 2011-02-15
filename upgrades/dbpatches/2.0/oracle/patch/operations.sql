---- Patching table `opmessage`

CREATE TABLE opmessage (
	operationid              number(20)                                NOT NULL,
	default_msg              number(10)      DEFAULT '0'               NOT NULL,
	subject                  nvarchar2(255)  DEFAULT ''                ,
	message                  nclob           DEFAULT ''                ,
	mediatypeid              number(20)                                NULL,
	PRIMARY KEY (operationid)
);
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid);

INSERT INTO opmessage (operationid, default_msg, subject, message)
	SELECT operationid, default_msg, shortdata, longdata
		FROM operations
		WHERE operationtype IN (0)	-- OPERATION_TYPE_MESSAGE
/

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
	opmessage_grpid          number(20)                                NOT NULL,
	operationid              number(20)                                NOT NULL,
	usrgrpid                 number(20)                                NOT NULL,
	PRIMARY KEY (opmessage_grpid)
);
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (operationid,usrgrpid);
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);

CREATE SEQUENCE opmessage_grp_seq;

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid)
	SELECT opmessage_grp_seq.NEXTVAL, o.operationid, o.objectid
		FROM operations o, usrgrp g
		WHERE o.objectid=g.usrgrpid
			AND o.object IN (1)	-- OPERATION_OBJECT_GROUP
/

DROP SEQUENCE opmessage_grp_seq;

UPDATE opmessage_grp
	SET opmessage_grpid = TRUNC(operationid / 100000000000) * 100000000000 + opmessage_grpid
	WHERE operationid >= 100000000000;

---- Patching table `opmessage_usr`

CREATE TABLE opmessage_usr (
	opmessage_usrid          number(20)                                NOT NULL,
	operationid              number(20)                                NOT NULL,
	userid                   number(20)                                NOT NULL,
	PRIMARY KEY (opmessage_usrid)
);
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (operationid,userid);
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid);

CREATE SEQUENCE opmessage_usr_seq;

INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid)
	SELECT opmessage_usr_seq.NEXTVAL, o.operationid, o.objectid
		FROM operations o, users u
		WHERE o.objectid = u.userid
			AND o.object IN (0)	-- OPERATION_OBJECT_USER
/

DROP SEQUENCE opmessage_usr_seq;

UPDATE opmessage_usr
	SET opmessage_usrid = TRUNC(operationid / 100000000000) * 100000000000 + opmessage_usrid
	WHERE operationid >= 100000000000;

---- Patching tables `opcommand_hst` and `opcommand_grp`

CREATE TABLE opcommand_hst (
	opcommand_hstid          number(20)                                NOT NULL,
	operationid              number(20)                                NOT NULL,
	hostid                   number(20)                                NULL,
	command                  nclob           DEFAULT ''                ,
	PRIMARY KEY (opcommand_hstid)
);
CREATE INDEX opcommand_hst_1 on opcommand_hst (operationid);
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid);

CREATE TABLE opcommand_grp (
	opcommand_grpid          number(20)                                NOT NULL,
	operationid              number(20)                                NOT NULL,
	groupid                  number(20)                                NOT NULL,
	command                  nclob           DEFAULT ''                ,
	PRIMARY KEY (opcommand_grpid)
);
CREATE INDEX opcommand_grp_1 on opcommand_grp (operationid);
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

-- creating temporary table `tmp_opcommand`

CREATE TABLE tmp_opcommand (
	operationid number(20),
	longdata nvarchar2(2048)
);

DECLARE
	CURSOR op_cur IS SELECT operationid, longdata FROM operations WHERE operationtype = 1;
	v_longdata	nvarchar2(2048);
	v_cur_string	nvarchar2(2048);
	v_operationid	number(20);
	r_pos		number;
	l_pos		number;
BEGIN
	OPEN op_cur;

	LOOP
		FETCH op_cur INTO v_operationid, v_longdata;

		EXIT WHEN op_cur%NOTFOUND;

		r_pos := 1;
		l_pos := 1;

		LOOP
			r_pos := INSTR(v_longdata, CHR(10), l_pos);

			IF r_pos = 0 THEN
				v_cur_string := SUBSTR(v_longdata, l_pos);
			ELSE
				v_cur_string := SUBSTR(v_longdata, l_pos, r_pos - l_pos);
			END IF;

			v_cur_string := RTRIM(v_cur_string, CHR(13));

			IF LENGTH(v_cur_string) > 0 THEN
				INSERT INTO tmp_opcommand (operationid, longdata)
					VALUES (v_operationid, v_cur_string);
			END IF;

			EXIT WHEN r_pos = 0;

			l_pos := r_pos + 1;
		END LOOP;
	END LOOP;

	CLOSE op_cur;
END;
/

-- creating temporary table `tmp_opcommand_hst`

CREATE TABLE tmp_opcommand_hst (
	operationid number(20),
	name nvarchar2(64),
	longdata nvarchar2(2048),
	hostid number(20)
);

INSERT INTO tmp_opcommand_hst (operationid, longdata)
	SELECT operationid, longdata
		FROM tmp_opcommand
		WHERE INSTR(longdata, ':') <> 0 AND (INSTR(longdata, '#') = 0 OR INSTR(longdata, ':') < INSTR(longdata, '#'));

UPDATE tmp_opcommand_hst
	SET name = TRIM(SUBSTR(longdata, 1, INSTR(longdata, ':') - 1)),
		longdata = TRIM(SUBSTR(longdata, INSTR(longdata, ':') + 1));

DELETE FROM tmp_opcommand_hst
	WHERE name <> '{HOSTNAME}'
		AND NOT EXISTS (
			SELECT h.hostid
				FROM hosts h
				WHERE h.host = tmp_opcommand_hst.name
					AND TRUNC(h.hostid / 100000000000000) = TRUNC(tmp_opcommand_hst.operationid / 100000000000000));

UPDATE tmp_opcommand_hst
	SET hostid = (
		SELECT MIN(h.hostid)
			FROM hosts h
			WHERE h.host = tmp_opcommand_hst.name
				AND TRUNC(h.hostid / 100000000000000) = TRUNC(tmp_opcommand_hst.operationid / 100000000000000))
	WHERE name <> '{HOSTNAME}';

CREATE SEQUENCE opcommand_hst_seq;

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid, command)
	SELECT opcommand_hst_seq.NEXTVAL, operationid, hostid, longdata
		FROM tmp_opcommand_hst;

DROP SEQUENCE opcommand_hst_seq;

UPDATE opcommand_hst
	SET opcommand_hstid = TRUNC(operationid / 100000000000) * 100000000000 + opcommand_hstid
	WHERE operationid >= 100000000000;

DROP TABLE tmp_opcommand_hst;

-- creating temporary table `tmp_opcommand_grp`

CREATE TABLE tmp_opcommand_grp (
	operationid number(20),
	name nvarchar2(64),
	longdata nvarchar2(2048),
	groupid number(20)
);

INSERT INTO tmp_opcommand_grp (operationid, longdata)
	SELECT operationid, longdata
		FROM tmp_opcommand
		WHERE INSTR(longdata, '#') <> 0 AND (INSTR(longdata, ':') = 0 OR INSTR(longdata, '#') < INSTR(longdata, ':'));

UPDATE tmp_opcommand_grp
	SET name = TRIM(SUBSTR(longdata, 1, INSTR(longdata, '#') - 1)),
		longdata = TRIM(SUBSTR(longdata, INSTR(longdata, '#') + 1));

DELETE FROM tmp_opcommand_grp
	WHERE NOT EXISTS (
		SELECT g.groupid
			FROM groups g
			WHERE g.name = tmp_opcommand_grp.name
				AND TRUNC(g.groupid / 100000000000000) = TRUNC(tmp_opcommand_grp.operationid / 100000000000000));

UPDATE tmp_opcommand_grp
	SET groupid = (
		SELECT MIN(g.groupid)
			FROM groups g
			WHERE g.name = tmp_opcommand_grp.name
				AND TRUNC(g.groupid / 100000000000000) = TRUNC(tmp_opcommand_grp.operationid / 100000000000000));

CREATE SEQUENCE opcommand_grp_seq;

INSERT INTO opcommand_grp (opcommand_grpid, operationid, groupid, command)
	SELECT opcommand_grp_seq.NEXTVAL, operationid, groupid, longdata
		FROM tmp_opcommand_grp;

DROP SEQUENCE opcommand_grp_seq;

UPDATE opcommand_grp
	SET opcommand_grpid = TRUNC(operationid / 100000000000) * 100000000000 + opcommand_grpid
	WHERE operationid >= 100000000000;

DROP TABLE tmp_opcommand_grp;
DROP TABLE tmp_opcommand;

---- Patching table `opgroup`

CREATE TABLE opgroup (
	opgroupid                number(20)                                NOT NULL,
	operationid              number(20)                                NOT NULL,
	groupid                  number(20)                                NOT NULL,
	PRIMARY KEY (opgroupid)
);
CREATE UNIQUE INDEX opgroup_1 ON opgroup (operationid,groupid);
ALTER TABLE opgroup ADD CONSTRAINT c_opgroup_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE opgroup ADD CONSTRAINT c_opgroup_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

CREATE SEQUENCE opgroup_seq;

INSERT INTO opgroup (opgroupid, operationid, groupid)
	SELECT opgroup_seq.NEXTVAL, o.operationid, o.objectid
		FROM operations o, groups g
		WHERE o.objectid = g.groupid
			AND o.operationtype IN (4,5)	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE
/

DROP SEQUENCE opgroup_seq;

UPDATE opgroup
	SET opgroupid = TRUNC(operationid / 100000000000) * 100000000000 + opgroupid
	WHERE operationid >= 100000000000;

---- Patching table `optemplate`

CREATE TABLE optemplate (
	optemplateid             number(20)                                NOT NULL,
	operationid              number(20)                                NOT NULL,
	templateid               number(20)                                NOT NULL,
	PRIMARY KEY (optemplateid)
);
CREATE UNIQUE INDEX optemplate_1 ON optemplate (operationid,templateid);
ALTER TABLE optemplate ADD CONSTRAINT c_optemplate_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE optemplate ADD CONSTRAINT c_optemplate_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid);

CREATE SEQUENCE optemplate_seq;

INSERT INTO optemplate (optemplateid, operationid, templateid)
	SELECT optemplate_seq.NEXTVAL, o.operationid, o.objectid
		FROM operations o, hosts h
		WHERE o.objectid = h.hostid
			AND o.operationtype IN (6,7)	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE
/

DROP SEQUENCE optemplate_seq;

UPDATE optemplate
	SET optemplateid = TRUNC(operationid / 100000000000) * 100000000000 + optemplateid
	WHERE operationid >= 100000000000;

---- Patching table `operations`

ALTER TABLE operations MODIFY operationid DEFAULT NULL;
ALTER TABLE operations MODIFY actionid DEFAULT NULL;
ALTER TABLE operations MODIFY esc_step_from DEFAULT '1';
ALTER TABLE operations DROP COLUMN object;
ALTER TABLE operations DROP COLUMN objectid;
ALTER TABLE operations DROP COLUMN shortdata;
ALTER TABLE operations DROP COLUMN longdata;
ALTER TABLE operations DROP COLUMN default_msg;
DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;

---- Dropping table `opmediatypes`

DROP TABLE opmediatypes;
