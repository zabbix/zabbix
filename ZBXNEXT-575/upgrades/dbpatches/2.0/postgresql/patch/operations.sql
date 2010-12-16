---- Patching table `opmessage`

CREATE TABLE opmessage (
	opmessageid              bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	default_msg              integer         DEFAULT '0'               NOT NULL,
	subject                  varchar(255)    DEFAULT ''                NOT NULL,
	message                  text            DEFAULT ''                NOT NULL,
	mediatypeid              bigint                                    NULL,
	PRIMARY KEY (opmessageid)
) with OIDS;
CREATE INDEX opmessage_1 on opmessage (operationid);
ALTER TABLE ONLY opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage ADD CONSTRAINT c_opmessage_3 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid);

CREATE TEMPORARY SEQUENCE opmessage_seq;

INSERT INTO opmessage (opmessageid, operationid, default_msg, subject, message)
	SELECT NEXTVAL('opmessage_seq'), operationid, default_msg, shortdata, longdata
		FROM operations
		WHERE operationtype IN (0);	-- OPERATION_TYPE_MESSAGE

DROP SEQUENCE opmessage_seq;

UPDATE opmessage
	SET mediatypeid = (
		SELECT mediatypeid
			FROM opmediatypes
			WHERE operationid = opmessage.operationid)
	WHERE operationid IN (
		SELECT omt.operationid
			FROM opmediatypes omt, media_type mt
			WHERE omt.mediatypeid = mt.mediatypeid);

UPDATE opmessage
	SET opmessageid = (operationid / 100000000000) * 100000000000 + opmessageid
	WHERE operationid >= 100000000000;

---- Patching table `opmessage_grp`

CREATE TABLE opmessage_grp (
	opmessage_grpid          bigint                                    NOT NULL,
	opmessageid              bigint                                    NOT NULL,
	usrgrpid                 bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_grpid)
) with OIDS;
CREATE INDEX opmessage_grp_1 on opmessage_grp (opmessageid);
ALTER TABLE ONLY opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (opmessageid) REFERENCES opmessage (opmessageid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);

CREATE TEMPORARY SEQUENCE opmessage_grp_seq;

INSERT INTO opmessage_grp (opmessage_grpid, opmessageid, usrgrpid)
	SELECT NEXTVAL('opmessage_grp_seq'), m.opmessageid, o.objectid
		FROM opmessage m, operations o
		WHERE m.operationid = o.operationid
			AND o.object IN (1)	-- OPERATION_OBJECT_GROUP
			AND o.objectid IN (SELECT usrgrpid FROM usrgrp);

DROP SEQUENCE opmessage_grp_seq;

UPDATE opmessage_grp
	SET opmessage_grpid = (opmessageid / 100000000000) * 100000000000 + opmessage_grpid
	WHERE opmessage_grpid >= 100000000000;

---- Patching table `opmessage_usr`

CREATE TABLE opmessage_usr (
	opmessage_usrid          bigint                                    NOT NULL,
	opmessageid              bigint                                    NOT NULL,
	userid                   bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_usrid)
) with OIDS;
CREATE INDEX opmessage_usr_1 on opmessage_usr (opmessageid);
ALTER TABLE ONLY opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (opmessageid) REFERENCES opmessage (opmessageid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid);

CREATE TEMPORARY SEQUENCE opmessage_usr_seq;

INSERT INTO opmessage_usr (opmessage_usrid, opmessageid, userid)
	SELECT NEXTVAL('opmessage_usr_seq'), m.opmessageid, o.objectid
		FROM opmessage m, operations o
		WHERE m.operationid = o.operationid
			AND o.object IN (0)	-- OPERATION_OBJECT_USER
			AND o.objectid IN (SELECT userid FROM users);

DROP SEQUENCE opmessage_usr_seq;

UPDATE opmessage_usr
	SET opmessage_usrid = (opmessageid / 100000000000) * 100000000000 + opmessage_usrid
	WHERE opmessage_usrid >= 100000000000;

---- Patching tables `opcommand_hst` and `opcommand_grp`

CREATE TABLE opcommand_hst (
	opcommand_hstid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	hostid                   bigint                                    NULL,
	command                  text            DEFAULT ''                NOT NULL,
	PRIMARY KEY (opcommand_hstid)
) with OIDS;
CREATE INDEX opcommand_hst_1 on opcommand_hst (operationid);
ALTER TABLE ONLY opcommand_hst ADD CONSTRAINT c_opcommand_hst_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opcommand_hst ADD CONSTRAINT c_opcommand_hst_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid);

CREATE TABLE opcommand_grp (
	opcommand_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	command                  text            DEFAULT ''                NOT NULL,
	PRIMARY KEY (opcommand_grpid)
) with OIDS;
CREATE INDEX opcommand_grp_1 on opcommand_grp (operationid);
ALTER TABLE ONLY opcommand_grp ADD CONSTRAINT c_opcommand_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opcommand_grp ADD CONSTRAINT c_opcommand_grp_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

-- creating temporary table `_opcommand`

CREATE TEMPORARY TABLE _opcommand (
	operationid bigint,
	longdata varchar(2048)
);

CREATE OR REPLACE FUNCTION unnest(anyarray) RETURNS SETOF anyelement
LANGUAGE SQL AS $$
	SELECT $1[i]
		FROM generate_series(array_lower($1, 1), array_upper($1, 1)) as i;
$$;

INSERT INTO _opcommand (operationid, longdata)
	SELECT operationid, unnest(string_to_array(longdata, '\n'))
		FROM operations
		WHERE operationtype = 1;

UPDATE _opcommand
	SET longdata = TRIM(TRAILING '\r' FROM longdata);

-- creating temporary table `_opcommand_hst`

CREATE TEMPORARY TABLE _opcommand_hst (
	operationid bigint,
	name varchar(64),
	longdata varchar(2048),
	hostid bigint
);

INSERT INTO _opcommand_hst (operationid, longdata)
	SELECT operationid, longdata
		FROM _opcommand
		WHERE POSITION(':' IN longdata) <> 0 AND (POSITION('#' IN longdata) = 0 OR POSITION(':' IN longdata) < POSITION('#' IN longdata));

UPDATE _opcommand_hst
	SET name = TRIM(BOTH ' ' FROM SUBSTRING(longdata FOR POSITION(':' IN longdata) - 1)),
		longdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FROM POSITION(':' IN longdata) + 1));

DELETE FROM _opcommand_hst
	WHERE name <> '{HOSTNAME}'
		AND NOT EXISTS (
			SELECT h.hostid
				FROM hosts h
				WHERE h.host = _opcommand_hst.name
					AND (h.hostid / 100000000000000) = (_opcommand_hst.operationid / 100000000000000));

UPDATE _opcommand_hst
	SET hostid = (
		SELECT MIN(h.hostid)
			FROM hosts h
			WHERE h.host = _opcommand_hst.name
				AND (h.hostid / 100000000000000) = (_opcommand_hst.operationid / 100000000000000))
	WHERE name <> '{HOSTNAME}';

CREATE TEMPORARY SEQUENCE opcommand_hst_seq;

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid, command)
	SELECT NEXTVAL('opcommand_hst_seq'), operationid, hostid, longdata
		FROM _opcommand_hst;

DROP SEQUENCE opcommand_hst_seq;

UPDATE opcommand_hst
	SET opcommand_hstid = (operationid / 100000000000) * 100000000000 + opcommand_hstid
	WHERE operationid >= 100000000000;

DROP TABLE _opcommand_hst;

-- creating temporary table `_opcommand_grp`

CREATE TEMPORARY TABLE _opcommand_grp (
	operationid bigint,
	name varchar(64),
	longdata varchar(2048),
	groupid bigint
);

INSERT INTO _opcommand_grp (operationid, longdata)
	SELECT operationid, longdata
		FROM _opcommand
		WHERE POSITION('#' IN longdata) <> 0 AND (POSITION(':' IN longdata) = 0 OR POSITION('#' IN longdata) < POSITION(':' IN longdata));

UPDATE _opcommand_grp
	SET name = TRIM(BOTH ' ' FROM SUBSTRING(longdata FOR POSITION('#' IN longdata) - 1)),
		longdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FROM POSITION('#' IN longdata) + 1));

DELETE FROM _opcommand_grp
	WHERE NOT EXISTS (
		SELECT g.groupid
			FROM groups g
			WHERE g.name = _opcommand_grp.name
				AND (g.groupid / 100000000000000) = (_opcommand_grp.operationid / 100000000000000));

UPDATE _opcommand_grp
	SET groupid = (
		SELECT MIN(g.groupid)
			FROM groups g
			WHERE g.name = _opcommand_grp.name
				AND (g.groupid / 100000000000000) = (_opcommand_grp.operationid / 100000000000000));

CREATE TEMPORARY SEQUENCE opcommand_grp_seq;

INSERT INTO opcommand_grp (opcommand_grpid, operationid, groupid, command)
	SELECT NEXTVAL('opcommand_grp_seq'), operationid, groupid, longdata
		FROM _opcommand_grp;

DROP SEQUENCE opcommand_grp_seq;

UPDATE opcommand_grp
	SET opcommand_grpid = (operationid / 100000000000) * 100000000000 + opcommand_grpid
	WHERE operationid >= 100000000000;

DROP TABLE _opcommand_grp;

SELECT * FROM opcommand_hst;
SELECT * FROM opcommand_grp;

DROP TABLE _opcommand;
DROP FUNCTION unnest(anyarray);

---- Patching table `opgroup`

CREATE TABLE opgroup (
	opgroupid                bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	PRIMARY KEY (opgroupid)
) with OIDS;
CREATE INDEX opgroup_1 on opgroup (operationid);
ALTER TABLE ONLY opgroup ADD CONSTRAINT c_opgroup_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opgroup ADD CONSTRAINT c_opgroup_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

CREATE TEMPORARY SEQUENCE opgroup_seq;

INSERT INTO opgroup (opgroupid, operationid, groupid)
	SELECT NEXTVAL('opgroup_seq'), operationid, objectid
		FROM operations
		WHERE operationtype IN (4,5);	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE

DROP SEQUENCE opgroup_seq;

UPDATE opgroup
	SET opgroupid = (operationid / 100000000000) * 100000000000 + opgroupid
	WHERE operationid >= 100000000000;

---- Patching table `optemplate`

CREATE TABLE optemplate (
	optemplateid             bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	templateid               bigint                                    NOT NULL,
	PRIMARY KEY (optemplateid)
) with OIDS;
CREATE INDEX optemplate_1 on optemplate (operationid);
ALTER TABLE ONLY optemplate ADD CONSTRAINT c_optemplate_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY optemplate ADD CONSTRAINT c_optemplate_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid);

CREATE TEMPORARY SEQUENCE optemplate_seq;

INSERT INTO optemplate (optemplateid, operationid, templateid)
	SELECT NEXTVAL('optemplate_seq'), operationid, objectid
		FROM operations
		WHERE operationtype IN (6,7);	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE

DROP SEQUENCE optemplate_seq;

UPDATE optemplate
	SET optemplateid = (operationid / 100000000000) * 100000000000 + optemplateid
	WHERE operationid >= 100000000000;

---- Patching table `operations`

ALTER TABLE operations ALTER operationid DROP DEFAULT,
		       ALTER actionid DROP DEFAULT,
		       DROP COLUMN object,
		       DROP COLUMN objectid,
		       DROP COLUMN shortdata,
		       DROP COLUMN longdata,
		       DROP COLUMN default_msg;
DELETE FROM operations WHERE NOT EXISTS (SELECT 1 FROM actions WHERE actions.actionid=operations.actionid);
ALTER TABLE ONLY operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;

---- Dropping table `opmediatypes`

DROP TABLE opmediatypes;
