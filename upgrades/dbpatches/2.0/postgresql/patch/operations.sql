---- Patching table `opmessage`

CREATE TABLE opmessage (
	operationid              bigint                                    NOT NULL,
	default_msg              integer         DEFAULT '0'               NOT NULL,
	subject                  varchar(255)    DEFAULT ''                NOT NULL,
	message                  text            DEFAULT ''                NOT NULL,
	mediatypeid              bigint                                    NULL,
	PRIMARY KEY (operationid)
) with OIDS;
ALTER TABLE ONLY opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage ADD CONSTRAINT c_opmessage_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid);

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
	opmessage_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	usrgrpid                 bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_grpid)
) with OIDS;
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (operationid,usrgrpid);
ALTER TABLE ONLY opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);

CREATE SEQUENCE opmessage_grp_seq;

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid)
	SELECT NEXTVAL('opmessage_grp_seq'), o.operationid, o.objectid
		FROM operations o, usrgrp g
		WHERE o.objectid = g.usrgrpid
			AND o.object IN (1);	-- OPERATION_OBJECT_GROUP

DROP SEQUENCE opmessage_grp_seq;

UPDATE opmessage_grp
	SET opmessage_grpid = (operationid / 100000000000) * 100000000000 + opmessage_grpid
	WHERE opmessage_grpid >= 100000000000;

---- Patching table `opmessage_usr`

CREATE TABLE opmessage_usr (
	opmessage_usrid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	userid                   bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_usrid)
) with OIDS;
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (operationid,userid);
ALTER TABLE ONLY opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid);

CREATE SEQUENCE opmessage_usr_seq;

INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid)
	SELECT NEXTVAL('opmessage_usr_seq'), o.operationid, o.objectid
		FROM operations o, users u
		WHERE o.objectid = u.userid
			AND o.object IN (0);	-- OPERATION_OBJECT_USER

DROP SEQUENCE opmessage_usr_seq;

UPDATE opmessage_usr
	SET opmessage_usrid = (operationid / 100000000000) * 100000000000 + opmessage_usrid
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

-- creating temporary table `tmp_opcommand`

CREATE TABLE tmp_opcommand (
	operationid bigint,
	longdata varchar(2048)
);

CREATE OR REPLACE FUNCTION unnest(anyarray) RETURNS SETOF anyelement
LANGUAGE SQL AS $$
	SELECT $1[i]
		FROM generate_series(array_lower($1, 1), array_upper($1, 1)) as i;
$$;

INSERT INTO tmp_opcommand (operationid, longdata)
	SELECT operationid, unnest(string_to_array(longdata, '\n'))
		FROM operations
		WHERE operationtype = 1;

UPDATE tmp_opcommand
	SET longdata = TRIM(TRAILING '\r' FROM longdata);

-- creating temporary table `tmp_opcommand_hst`

CREATE TABLE tmp_opcommand_hst (
	operationid bigint,
	name varchar(64),
	longdata varchar(2048),
	hostid bigint
);

INSERT INTO tmp_opcommand_hst (operationid, longdata)
	SELECT operationid, longdata
		FROM tmp_opcommand
		WHERE POSITION(':' IN longdata) <> 0 AND (POSITION('#' IN longdata) = 0 OR POSITION(':' IN longdata) < POSITION('#' IN longdata));

UPDATE tmp_opcommand_hst
	SET name = TRIM(BOTH ' ' FROM SUBSTRING(longdata FOR POSITION(':' IN longdata) - 1)),
		longdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FROM POSITION(':' IN longdata) + 1));

DELETE FROM tmp_opcommand_hst
	WHERE name <> '{HOSTNAME}'
		AND NOT EXISTS (
			SELECT h.hostid
				FROM hosts h
				WHERE h.host = tmp_opcommand_hst.name
					AND (h.hostid / 100000000000000) = (tmp_opcommand_hst.operationid / 100000000000000));

UPDATE tmp_opcommand_hst
	SET hostid = (
		SELECT MIN(h.hostid)
			FROM hosts h
			WHERE h.host = tmp_opcommand_hst.name
				AND (h.hostid / 100000000000000) = (tmp_opcommand_hst.operationid / 100000000000000))
	WHERE name <> '{HOSTNAME}';

CREATE SEQUENCE opcommand_hst_seq;

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid, command)
	SELECT NEXTVAL('opcommand_hst_seq'), operationid, hostid, longdata
		FROM tmp_opcommand_hst;

DROP SEQUENCE opcommand_hst_seq;

UPDATE opcommand_hst
	SET opcommand_hstid = (operationid / 100000000000) * 100000000000 + opcommand_hstid
	WHERE operationid >= 100000000000;

DROP TABLE tmp_opcommand_hst;

-- creating temporary table `tmp_opcommand_grp`

CREATE TABLE tmp_opcommand_grp (
	operationid bigint,
	name varchar(64),
	longdata varchar(2048),
	groupid bigint
);

INSERT INTO tmp_opcommand_grp (operationid, longdata)
	SELECT operationid, longdata
		FROM tmp_opcommand
		WHERE POSITION('#' IN longdata) <> 0 AND (POSITION(':' IN longdata) = 0 OR POSITION('#' IN longdata) < POSITION(':' IN longdata));

UPDATE tmp_opcommand_grp
	SET name = TRIM(BOTH ' ' FROM SUBSTRING(longdata FOR POSITION('#' IN longdata) - 1)),
		longdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FROM POSITION('#' IN longdata) + 1));

DELETE FROM tmp_opcommand_grp
	WHERE NOT EXISTS (
		SELECT g.groupid
			FROM groups g
			WHERE g.name = tmp_opcommand_grp.name
				AND (g.groupid / 100000000000000) = (tmp_opcommand_grp.operationid / 100000000000000));

UPDATE tmp_opcommand_grp
	SET groupid = (
		SELECT MIN(g.groupid)
			FROM groups g
			WHERE g.name = tmp_opcommand_grp.name
				AND (g.groupid / 100000000000000) = (tmp_opcommand_grp.operationid / 100000000000000));

CREATE SEQUENCE opcommand_grp_seq;

INSERT INTO opcommand_grp (opcommand_grpid, operationid, groupid, command)
	SELECT NEXTVAL('opcommand_grp_seq'), operationid, groupid, longdata
		FROM tmp_opcommand_grp;

DROP SEQUENCE opcommand_grp_seq;

UPDATE opcommand_grp
	SET opcommand_grpid = (operationid / 100000000000) * 100000000000 + opcommand_grpid
	WHERE operationid >= 100000000000;

DROP TABLE tmp_opcommand_grp;
DROP TABLE tmp_opcommand;
DROP FUNCTION unnest(anyarray);

---- Patching table `opgroup`

CREATE TABLE opgroup (
	opgroupid                bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	PRIMARY KEY (opgroupid)
) with OIDS;
CREATE UNIQUE INDEX opgroup_1 ON opgroup (operationid,groupid);
ALTER TABLE ONLY opgroup ADD CONSTRAINT c_opgroup_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opgroup ADD CONSTRAINT c_opgroup_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

CREATE SEQUENCE opgroup_seq;

INSERT INTO opgroup (opgroupid, operationid, groupid)
	SELECT NEXTVAL('opgroup_seq'), o.operationid, o.objectid
		FROM operations o, groups g
		WHERE o.objectid = g.groupid
			AND o.operationtype IN (4,5);	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE

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
CREATE UNIQUE INDEX optemplate_1 ON optemplate (operationid,templateid);
ALTER TABLE ONLY optemplate ADD CONSTRAINT c_optemplate_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY optemplate ADD CONSTRAINT c_optemplate_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid);

CREATE SEQUENCE optemplate_seq;

INSERT INTO optemplate (optemplateid, operationid, templateid)
	SELECT NEXTVAL('optemplate_seq'), o.operationid, o.objectid
		FROM operations o, hosts h
		WHERE o.objectid = h.hostid
			AND o.operationtype IN (6,7);	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE

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
