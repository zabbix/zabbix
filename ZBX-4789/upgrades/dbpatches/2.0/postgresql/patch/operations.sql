CREATE TABLE t_operations (
	operationid		bigint,
	new_operationid		bigint,
	actionid		bigint,
	operationtype		integer,
	object			integer,
	objectid		bigint,
	shortdata		varchar(255),
	longdata		text,
	esc_period		integer,
	esc_step_from		integer,
	esc_step_to		integer,
	default_msg		integer,
	evaltype		integer,
	mediatypeid		bigint,
	is_host			integer,
	hostid			bigint,
	groupid			bigint
);

CREATE TABLE t_opconditions (
	operationid		bigint,
	conditiontype		integer,
	operator		integer,
	value			varchar(255)
);

CREATE OR REPLACE FUNCTION zbx_unnest(anyarray) RETURNS SETOF anyelement
LANGUAGE SQL AS $$
	SELECT $1[i]
		FROM generate_series(array_lower($1, 1), array_upper($1, 1)) as i;
$$;

CREATE SEQUENCE operations_seq;

INSERT INTO t_operations
	SELECT o.operationid, NEXTVAL('operations_seq'), o.actionid, o.operationtype, o.object, o.objectid, o.shortdata,
			CASE WHEN operationtype = 1 THEN zbx_unnest(string_to_array(o.longdata, CHR(10))) ELSE o.longdata END,
			o.esc_period, o.esc_step_from, o.esc_step_to, o.default_msg, o.evaltype, omt.mediatypeid,
			NULL, NULL, NULL
		FROM actions a, operations o
			LEFT JOIN opmediatypes omt ON omt.operationid=o.operationid
		WHERE a.actionid=o.actionid;

DROP SEQUENCE operations_seq;

DROP FUNCTION zbx_unnest(anyarray);

INSERT INTO t_opconditions
	SELECT operationid, conditiontype, operator, value FROM opconditions;

UPDATE t_operations
	SET new_operationid = (operationid / 100000000000) * 100000000000 + new_operationid
	WHERE operationid >= 100000000000;

UPDATE t_operations
	SET is_host = 1,
		shortdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FOR POSITION(':' IN longdata) - 1)),
		longdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FROM POSITION(':' IN longdata) + 1))
	WHERE operationtype IN (1)	-- OPERATION_TYPE_COMMAND
		AND POSITION(':' IN longdata) <> 0
		AND (POSITION('#' IN longdata) = 0 OR POSITION(':' IN longdata) < POSITION('#' IN longdata));

UPDATE t_operations
	SET is_host = 0,
		shortdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FOR POSITION('#' IN longdata) - 1)),
		longdata = TRIM(BOTH ' ' FROM SUBSTRING(longdata FROM POSITION('#' IN longdata) + 1))
	WHERE operationtype IN (1)	-- OPERATION_TYPE_COMMAND
		AND POSITION('#' IN longdata) <> 0
		AND (POSITION(':' IN longdata) = 0 OR POSITION('#' IN longdata) < POSITION(':' IN longdata));

UPDATE t_operations
	SET longdata = TRIM(TRAILING CHR(13) FROM longdata)
	WHERE operationtype IN (1);	-- OPERATION_TYPE_COMMAND

UPDATE t_operations
	SET hostid = (
		SELECT MIN(h.hostid)
			FROM hosts h
			WHERE h.host = t_operations.shortdata
				AND (h.hostid / 100000000000000) = (t_operations.operationid / 100000000000000))
	WHERE operationtype IN (1)	-- OPERATION_TYPE_COMMAND
		AND is_host = 1
		AND shortdata <> '{HOSTNAME}';

UPDATE t_operations
	SET groupid = (
		SELECT MIN(g.groupid)
			FROM groups g
			WHERE g.name = t_operations.shortdata
				AND (g.groupid / 100000000000000) = (t_operations.operationid / 100000000000000))
	WHERE operationtype IN (1)	-- OPERATION_TYPE_COMMAND
		AND is_host = 0;

UPDATE t_operations
	SET mediatypeid = NULL
	WHERE NOT EXISTS (SELECT 1 FROM media_type mt WHERE mt.mediatypeid = t_operations.mediatypeid);

UPDATE t_operations
	SET objectid = NULL
	WHERE operationtype = 0		-- OPERATION_TYPE_MESSAGE
		AND object = 0		-- OPERATION_OBJECT_USER
		AND NOT EXISTS (SELECT 1 FROM users u WHERE u.userid = t_operations.objectid);

UPDATE t_operations
	SET objectid = NULL
	WHERE operationtype = 0		-- OPERATION_TYPE_MESSAGE
		AND object = 1		-- OPERATION_OBJECT_GROUP
		AND NOT EXISTS (SELECT 1 FROM usrgrp g WHERE g.usrgrpid = t_operations.objectid);

DELETE FROM t_operations
	WHERE operationtype IN (4,5)	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE
		AND NOT EXISTS (SELECT 1 FROM groups g WHERE g.groupid = t_operations.objectid);

DELETE FROM t_operations
	WHERE operationtype IN (6,7)	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE
		AND NOT EXISTS (SELECT 1 FROM hosts h WHERE h.hostid = t_operations.objectid);

DROP TABLE operations;
DROP TABLE opmediatypes;
DROP TABLE opconditions;

CREATE TABLE operations (
	operationid              bigint                                    NOT NULL,
	actionid                 bigint                                    NOT NULL,
	operationtype            integer         DEFAULT '0'               NOT NULL,
	esc_period               integer         DEFAULT '0'               NOT NULL,
	esc_step_from            integer         DEFAULT '1'               NOT NULL,
	esc_step_to              integer         DEFAULT '1'               NOT NULL,
	evaltype                 integer         DEFAULT '0'               NOT NULL,
	PRIMARY KEY (operationid)
);
CREATE INDEX operations_1 ON operations (actionid);
ALTER TABLE ONLY operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;

CREATE TABLE opmessage (
	operationid              bigint                                    NOT NULL,
	default_msg              integer         DEFAULT '0'               NOT NULL,
	subject                  varchar(255)    DEFAULT ''                NOT NULL,
	message                  text            DEFAULT ''                NOT NULL,
	mediatypeid              bigint                                    NULL,
	PRIMARY KEY (operationid)
);
ALTER TABLE ONLY opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage ADD CONSTRAINT c_opmessage_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid);

CREATE TABLE opmessage_grp (
	opmessage_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	usrgrpid                 bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_grpid)
);
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (operationid,usrgrpid);
ALTER TABLE ONLY opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);

CREATE TABLE opmessage_usr (
	opmessage_usrid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	userid                   bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_usrid)
);
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (operationid,userid);
ALTER TABLE ONLY opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid);

CREATE TABLE opcommand (
	operationid              bigint                                    NOT NULL,
	type                     integer         DEFAULT '0'               NOT NULL,
	scriptid                 bigint                                    NULL,
	execute_on               integer         DEFAULT '0'               NOT NULL,
	port                     varchar(64)     DEFAULT ''                NOT NULL,
	authtype                 integer         DEFAULT '0'               NOT NULL,
	username                 varchar(64)     DEFAULT ''                NOT NULL,
	password                 varchar(64)     DEFAULT ''                NOT NULL,
	publickey                varchar(64)     DEFAULT ''                NOT NULL,
	privatekey               varchar(64)     DEFAULT ''                NOT NULL,
	command                  text            DEFAULT ''                NOT NULL,
	PRIMARY KEY (operationid)
);
ALTER TABLE ONLY opcommand ADD CONSTRAINT c_opcommand_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opcommand ADD CONSTRAINT c_opcommand_2 FOREIGN KEY (scriptid) REFERENCES scripts (scriptid);

CREATE TABLE opcommand_hst (
	opcommand_hstid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	hostid                   bigint                                    NULL,
	PRIMARY KEY (opcommand_hstid)
);
CREATE INDEX opcommand_hst_1 ON opcommand_hst (operationid);
ALTER TABLE ONLY opcommand_hst ADD CONSTRAINT c_opcommand_hst_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opcommand_hst ADD CONSTRAINT c_opcommand_hst_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid);

CREATE TABLE opcommand_grp (
	opcommand_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	PRIMARY KEY (opcommand_grpid)
);
CREATE INDEX opcommand_grp_1 ON opcommand_grp (operationid);
ALTER TABLE ONLY opcommand_grp ADD CONSTRAINT c_opcommand_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opcommand_grp ADD CONSTRAINT c_opcommand_grp_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

CREATE TABLE opgroup (
	opgroupid                bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	PRIMARY KEY (opgroupid)
);
CREATE UNIQUE INDEX opgroup_1 ON opgroup (operationid,groupid);
ALTER TABLE ONLY opgroup ADD CONSTRAINT c_opgroup_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opgroup ADD CONSTRAINT c_opgroup_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);

CREATE TABLE optemplate (
	optemplateid             bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	templateid               bigint                                    NOT NULL,
	PRIMARY KEY (optemplateid)
);
CREATE UNIQUE INDEX optemplate_1 ON optemplate (operationid,templateid);
ALTER TABLE ONLY optemplate ADD CONSTRAINT c_optemplate_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY optemplate ADD CONSTRAINT c_optemplate_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid);

CREATE TABLE opconditions (
	opconditionid            bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	conditiontype            integer         DEFAULT '0'               NOT NULL,
	operator                 integer         DEFAULT '0'               NOT NULL,
	value                    varchar(255)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (opconditionid)
);
CREATE INDEX opconditions_1 ON opconditions (operationid);
ALTER TABLE ONLY opconditions ADD CONSTRAINT c_opconditions_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;

CREATE SEQUENCE opconditions_seq;
CREATE SEQUENCE opmessage_grp_seq;
CREATE SEQUENCE opmessage_usr_seq;
CREATE SEQUENCE opcommand_grp_seq;
CREATE SEQUENCE opcommand_hst_seq;
CREATE SEQUENCE opgroup_seq;
CREATE SEQUENCE optemplate_seq;

INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype)
	SELECT new_operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype
		FROM t_operations;

INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid)
	SELECT new_operationid, default_msg, shortdata, longdata, mediatypeid
		FROM t_operations
		WHERE operationtype IN (0);		-- OPERATION_TYPE_MESSAGE

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid)
	SELECT NEXTVAL('opmessage_grp_seq'), o.new_operationid, o.objectid
		FROM t_operations o, usrgrp g
		WHERE o.objectid = g.usrgrpid
			AND o.operationtype IN (0)	-- OPERATION_TYPE_MESSAGE
			AND o.object IN (1);		-- OPERATION_OBJECT_GROUP

UPDATE opmessage_grp
	SET opmessage_grpid = (operationid / 100000000000) * 100000000000 + opmessage_grpid
	WHERE operationid >= 100000000000;

INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid)
	SELECT NEXTVAL('opmessage_usr_seq'), o.new_operationid, o.objectid
		FROM t_operations o, users u
		WHERE o.objectid = u.userid
			AND o.operationtype IN (0)	-- OPERATION_TYPE_MESSAGE
			AND o.object IN (0);		-- OPERATION_OBJECT_USER

UPDATE opmessage_usr
	SET opmessage_usrid = (operationid / 100000000000) * 100000000000 + opmessage_usrid
	WHERE operationid >= 100000000000;

INSERT INTO opcommand (operationid, command)
	SELECT new_operationid, longdata
		FROM t_operations
		WHERE operationtype IN (1);		-- OPERATION_TYPE_COMMAND

UPDATE opcommand
	SET type = 1,
		command = TRIM(SUBSTRING(command, 5))
	WHERE SUBSTRING(command, 1, 4) = 'IPMI';

INSERT INTO opcommand_grp (opcommand_grpid, operationid, groupid)
	SELECT NEXTVAL('opcommand_grp_seq'), new_operationid, groupid
		FROM t_operations
		WHERE operationtype IN (1)		-- OPERATION_TYPE_COMMAND
			AND is_host = 0;

UPDATE opcommand_grp
	SET opcommand_grpid = (operationid / 100000000000) * 100000000000 + opcommand_grpid
	WHERE operationid >= 100000000000;

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid)
	SELECT NEXTVAL('opcommand_hst_seq'), new_operationid, hostid
		FROM t_operations
		WHERE operationtype IN (1)		-- OPERATION_TYPE_COMMAND
			AND is_host = 1
			AND (hostid IS NOT NULL OR shortdata = '{HOSTNAME}');

UPDATE opcommand_hst
	SET opcommand_hstid = (operationid / 100000000000) * 100000000000 + opcommand_hstid
	WHERE operationid >= 100000000000;

INSERT INTO opgroup (opgroupid, operationid, groupid)
	SELECT NEXTVAL('opgroup_seq'), o.new_operationid, o.objectid
		FROM t_operations o, groups g
		WHERE o.objectid = g.groupid
			AND o.operationtype IN (4,5);	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE

UPDATE opgroup
	SET opgroupid = (operationid / 100000000000) * 100000000000 + opgroupid
	WHERE operationid >= 100000000000;

INSERT INTO optemplate (optemplateid, operationid, templateid)
	SELECT NEXTVAL('optemplate_seq'), o.new_operationid, o.objectid
		FROM t_operations o, hosts h
		WHERE o.objectid = h.hostid
			AND o.operationtype IN (6,7);	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE

UPDATE optemplate
	SET optemplateid = (operationid / 100000000000) * 100000000000 + optemplateid
	WHERE operationid >= 100000000000;

INSERT INTO opconditions
	SELECT NEXTVAL('opconditions_seq'), o.new_operationid, c.conditiontype, c.operator, c.value
		FROM t_opconditions c, t_operations o
		WHERE c.operationid = o.operationid;

UPDATE opconditions
	SET opconditionid = (operationid / 100000000000) * 100000000000 + opconditionid
	WHERE operationid >= 100000000000;

DROP SEQUENCE optemplate_seq;
DROP SEQUENCE opgroup_seq;
DROP SEQUENCE opcommand_hst_seq;
DROP SEQUENCE opcommand_grp_seq;
DROP SEQUENCE opmessage_usr_seq;
DROP SEQUENCE opmessage_grp_seq;
DROP SEQUENCE opconditions_seq;

DROP TABLE t_operations;
DROP TABLE t_opconditions;

DELETE FROM ids WHERE table_name IN ('operations', 'opconditions', 'opmediatypes');
