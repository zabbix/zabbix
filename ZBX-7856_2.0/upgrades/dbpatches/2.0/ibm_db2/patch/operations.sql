CREATE TABLE t_operations (
	operationid		bigint,
	actionid		bigint,
	operationtype		integer,
	object			integer,
	objectid		bigint,
	shortdata		varchar(255),
	longdata		varchar(2048),
	esc_period		integer,
	esc_step_from		integer,
	esc_step_to		integer,
	default_msg		integer,
	evaltype		integer,
	mediatypeid		bigint
)
/

CREATE TABLE t_opconditions (
	operationid		bigint,
	conditiontype		integer,
	operator		integer,
	value			varchar(255)
)
/

INSERT INTO t_operations
	SELECT o.operationid, o.actionid, o.operationtype, o.object, o.objectid, o.shortdata, o.longdata,
			o.esc_period, o.esc_step_from, o.esc_step_to, o.default_msg, o.evaltype, omt.mediatypeid
		FROM actions a, operations o
			LEFT JOIN opmediatypes omt ON omt.operationid=o.operationid
		WHERE a.actionid=o.actionid
/

INSERT INTO t_opconditions
	SELECT operationid, conditiontype, operator, value FROM opconditions
/

UPDATE t_operations
	SET mediatypeid = NULL
	WHERE NOT EXISTS (SELECT 1 FROM media_type mt WHERE mt.mediatypeid = t_operations.mediatypeid)
/

UPDATE t_operations
	SET objectid = NULL
	WHERE operationtype = 0		-- OPERATION_TYPE_MESSAGE
		AND object = 0		-- OPERATION_OBJECT_USER
		AND NOT EXISTS (SELECT 1 FROM users u WHERE u.userid = t_operations.objectid)
/

UPDATE t_operations
	SET objectid = NULL
	WHERE operationtype = 0		-- OPERATION_TYPE_MESSAGE
		AND object = 1		-- OPERATION_OBJECT_GROUP
		AND NOT EXISTS (SELECT 1 FROM usrgrp g WHERE g.usrgrpid = t_operations.objectid)
/

DELETE FROM t_operations
	WHERE operationtype IN (4,5)	-- OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE
		AND NOT EXISTS (SELECT 1 FROM groups g WHERE g.groupid = t_operations.objectid)
/

DELETE FROM t_operations
	WHERE operationtype IN (6,7)	-- OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE
		AND NOT EXISTS (SELECT 1 FROM hosts h WHERE h.hostid = t_operations.objectid)
/

DROP TABLE operations
/
DROP TABLE opmediatypes
/
DROP TABLE opconditions
/

CREATE TABLE operations (
	operationid              bigint                                    NOT NULL,
	actionid                 bigint                                    NOT NULL,
	operationtype            integer         WITH DEFAULT '0'          NOT NULL,
	esc_period               integer         WITH DEFAULT '0'          NOT NULL,
	esc_step_from            integer         WITH DEFAULT '1'          NOT NULL,
	esc_step_to              integer         WITH DEFAULT '1'          NOT NULL,
	evaltype                 integer         WITH DEFAULT '0'          NOT NULL,
	PRIMARY KEY (operationid)
)
/
CREATE INDEX operations_1 ON operations (actionid)
/
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE
/

CREATE TABLE opmessage (
	operationid              bigint                                    NOT NULL,
	default_msg              integer         WITH DEFAULT '0'          NOT NULL,
	subject                  varchar(255)    WITH DEFAULT ''           NOT NULL,
	message                  varchar(2048)   WITH DEFAULT ''           NOT NULL,
	mediatypeid              bigint                                    NULL,
	PRIMARY KEY (operationid)
)
/
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opmessage ADD CONSTRAINT c_opmessage_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid)
/

CREATE TABLE opmessage_grp (
	opmessage_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	usrgrpid                 bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_grpid)
)
/
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (operationid,usrgrpid)
/
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opmessage_grp ADD CONSTRAINT c_opmessage_grp_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid)
/

CREATE TABLE opmessage_usr (
	opmessage_usrid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	userid                   bigint                                    NOT NULL,
	PRIMARY KEY (opmessage_usrid)
)
/
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (operationid,userid)
/
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opmessage_usr ADD CONSTRAINT c_opmessage_usr_2 FOREIGN KEY (userid) REFERENCES users (userid)
/

CREATE TABLE opcommand (
	operationid              bigint                                    NOT NULL,
	type                     integer         WITH DEFAULT '0'          NOT NULL,
	scriptid                 bigint                                    NULL,
	execute_on               integer         WITH DEFAULT '0'          NOT NULL,
	port                     varchar(64)     WITH DEFAULT ''           NOT NULL,
	authtype                 integer         WITH DEFAULT '0'          NOT NULL,
	username                 varchar(64)     WITH DEFAULT ''           NOT NULL,
	password                 varchar(64)     WITH DEFAULT ''           NOT NULL,
	publickey                varchar(64)     WITH DEFAULT ''           NOT NULL,
	privatekey               varchar(64)     WITH DEFAULT ''           NOT NULL,
	command                  varchar(2048)   WITH DEFAULT ''           NOT NULL,
	PRIMARY KEY (operationid)
)
/
ALTER TABLE opcommand ADD CONSTRAINT c_opcommand_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opcommand ADD CONSTRAINT c_opcommand_2 FOREIGN KEY (scriptid) REFERENCES scripts (scriptid)
/

CREATE TABLE opcommand_hst (
	opcommand_hstid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	hostid                   bigint                                    NULL,
	PRIMARY KEY (opcommand_hstid)
)
/
CREATE INDEX opcommand_hst_1 ON opcommand_hst (operationid)
/
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opcommand_hst ADD CONSTRAINT c_opcommand_hst_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid)
/

CREATE TABLE opcommand_grp (
	opcommand_grpid          bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	groupid                  bigint                                    NOT NULL,
	PRIMARY KEY (opcommand_grpid)
)
/
CREATE INDEX opcommand_grp_1 ON opcommand_grp (operationid)
/
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
ALTER TABLE opcommand_grp ADD CONSTRAINT c_opcommand_grp_2 FOREIGN KEY (groupid) REFERENCES groups (groupid)
/

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

CREATE TABLE opconditions (
	opconditionid            bigint                                    NOT NULL,
	operationid              bigint                                    NOT NULL,
	conditiontype            integer         WITH DEFAULT '0'          NOT NULL,
	operator                 integer         WITH DEFAULT '0'          NOT NULL,
	value                    varchar(255)    WITH DEFAULT ''           NOT NULL,
	PRIMARY KEY (opconditionid)
)
/
CREATE INDEX opconditions_1 ON opconditions (operationid)
/
ALTER TABLE opconditions ADD CONSTRAINT c_opconditions_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/

CREATE SEQUENCE opconditions_seq AS bigint
/

CREATE PROCEDURE zbx_convert_operations()
LANGUAGE SQL
BEGIN
	DECLARE v_nodeid integer;
	DECLARE minid, maxid bigint;
	DECLARE new_operationid bigint;
	DECLARE new_opmessage_grpid bigint;
	DECLARE new_opmessage_usrid bigint;
	DECLARE new_opgroupid bigint;
	DECLARE new_optemplateid bigint;
	DECLARE new_opcommand_hstid bigint;
	DECLARE new_opcommand_grpid bigint;
	DECLARE n_done integer DEFAULT 0;
	DECLARE n_not_found CONDITION FOR SQLSTATE '02000';
	DECLARE n_cur CURSOR FOR (SELECT DISTINCT TRUNC(operationid / 100000000000000) FROM t_operations);
	DECLARE CONTINUE HANDLER FOR n_not_found SET n_done = 1;

	OPEN n_cur;

	n_loop: LOOP
		FETCH n_cur INTO v_nodeid;

		IF n_done = 1 THEN
			LEAVE n_loop;
		END IF;

		SET minid = v_nodeid * 100000000000000;
		SET maxid = minid + 99999999999999;
		SET new_operationid = minid;
		SET new_opmessage_grpid = minid;
		SET new_opmessage_usrid = minid;
		SET new_opgroupid = minid;
		SET new_optemplateid = minid;
		SET new_opcommand_hstid = minid;
		SET new_opcommand_grpid = minid;

		BEGIN
			DECLARE v_operationid bigint;
			DECLARE v_actionid bigint;
			DECLARE v_operationtype integer;
			DECLARE v_esc_period integer;
			DECLARE v_esc_step_from integer;
			DECLARE v_esc_step_to integer;
			DECLARE v_evaltype integer;
			DECLARE v_default_msg integer;
			DECLARE v_shortdata varchar(255);
			DECLARE v_longdata varchar(2048);
			DECLARE v_mediatypeid bigint;
			DECLARE v_object integer;
			DECLARE v_objectid bigint;
			DECLARE l_pos, r_pos, h_pos, g_pos integer;
			DECLARE cur_string varchar(2048);
			DECLARE v_host, v_group varchar(64);
			DECLARE v_hostid, v_groupid bigint;
			DECLARE o_done integer DEFAULT 0;
			DECLARE o_not_found CONDITION FOR SQLSTATE '02000';
			DECLARE o_cur CURSOR FOR (
				SELECT operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to,
						evaltype, default_msg, shortdata, longdata, mediatypeid, object, objectid
					FROM t_operations
					WHERE operationid BETWEEN minid AND maxid);
			DECLARE CONTINUE HANDLER FOR o_not_found SET o_done = 1;

			OPEN o_cur;

			o_loop: LOOP
				FETCH o_cur INTO v_operationid, v_actionid, v_operationtype, v_esc_period, v_esc_step_from,
						v_esc_step_to, v_evaltype, v_default_msg, v_shortdata, v_longdata,
						v_mediatypeid, v_object, v_objectid;

				IF o_done = 1 THEN
					LEAVE o_loop;
				END IF;

				IF v_operationtype IN (0) THEN			-- OPERATION_TYPE_MESSAGE
					SET new_operationid = new_operationid + 1;

					INSERT INTO operations (operationid, actionid, operationtype, esc_period,
							esc_step_from, esc_step_to, evaltype)
						VALUES (new_operationid, v_actionid, v_operationtype, v_esc_period,
							v_esc_step_from, v_esc_step_to, v_evaltype);

					INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid)
						VALUES (new_operationid, v_default_msg, v_shortdata, v_longdata, v_mediatypeid);

					IF v_object = 0 AND v_objectid IS NOT NULL THEN	-- OPERATION_OBJECT_USER
						SET new_opmessage_usrid = new_opmessage_usrid + 1;

						INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid)
							VALUES (new_opmessage_usrid, new_operationid, v_objectid);
					END IF;

					IF v_object = 1 AND v_objectid IS NOT NULL THEN	-- OPERATION_OBJECT_GROUP
						SET new_opmessage_grpid = new_opmessage_grpid + 1;

						INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid)
							VALUES (new_opmessage_grpid, new_operationid, v_objectid);
					END IF;

					INSERT INTO opconditions
						SELECT minid + (NEXTVAL FOR opconditions_seq), new_operationid, conditiontype,
								operator, value
							FROM t_opconditions
							WHERE operationid = v_operationid;
				ELSEIF v_operationtype IN (1) THEN		-- OPERATION_TYPE_COMMAND
					SET r_pos = 1;
					SET l_pos = 1;

					WHILE r_pos > 0 DO
						SET r_pos = INSTR(v_longdata, CHR(10), l_pos);

						IF r_pos = 0 THEN
							SET cur_string = SUBSTR(v_longdata, l_pos);
						ELSE
							SET cur_string = SUBSTR(v_longdata, l_pos, r_pos - l_pos);
						END IF;

						SET cur_string = STRIP(cur_string, TRAILING, X'0D');
						SET cur_string = TRIM(cur_string);

						IF LENGTH(cur_string) <> 0 THEN
							SET h_pos = INSTR(cur_string, ':');
							SET g_pos = INSTR(cur_string, '#');

							IF h_pos <> 0 OR g_pos <> 0 THEN
								SET new_operationid = new_operationid + 1;

								INSERT INTO operations (operationid, actionid, operationtype,
										esc_period, esc_step_from, esc_step_to, evaltype)
								VALUES (new_operationid, v_actionid, v_operationtype, v_esc_period,
										v_esc_step_from, v_esc_step_to, v_evaltype);

								INSERT INTO opconditions
									SELECT minid + (NEXTVAL FOR opconditions_seq),
											new_operationid, conditiontype,
											operator, value
										FROM t_opconditions
										WHERE operationid = v_operationid;

								IF h_pos <> 0 AND (g_pos = 0 OR h_pos < g_pos) THEN
									INSERT INTO opcommand (operationid, command)
										VALUES (new_operationid, TRIM(SUBSTR(cur_string, h_pos + 1)));

									SET v_host = TRIM(SUBSTR(cur_string, 1, h_pos - 1));

									IF v_host = '{HOSTNAME}' THEN
										SET new_opcommand_hstid = new_opcommand_hstid + 1;

										INSERT INTO opcommand_hst
											VALUES (new_opcommand_hstid, new_operationid, NULL);
									ELSE
										SET v_hostid = (
											SELECT MIN(hostid)
												FROM hosts
												WHERE host = v_host
													AND TRUNC(hostid / 100000000000000) = v_nodeid);

										IF v_hostid IS NOT NULL THEN
											SET new_opcommand_hstid = new_opcommand_hstid + 1;

											INSERT INTO opcommand_hst
												VALUES (new_opcommand_hstid, new_operationid, v_hostid);
										END IF;
									END IF;
								END IF;

								IF g_pos <> 0 AND (h_pos = 0 OR g_pos < h_pos) THEN
									INSERT INTO opcommand (operationid, command)
										VALUES (new_operationid, TRIM(SUBSTR(cur_string, g_pos + 1)));

									SET v_group = TRIM(SUBSTR(cur_string, 1, g_pos - 1));

									SET v_groupid = (
										SELECT MIN(groupid)
											FROM groups
											WHERE name = v_group
												AND TRUNC(groupid / 100000000000000) = v_nodeid);

									IF v_groupid IS NOT NULL THEN
										SET new_opcommand_grpid = new_opcommand_grpid + 1;

										INSERT INTO opcommand_grp
											VALUES (new_opcommand_grpid, new_operationid, v_groupid);
									END IF;
								END IF;
							END IF;
						END IF;

						SET l_pos = r_pos + 1;
					END WHILE;
				ELSEIF v_operationtype IN (2, 3, 8, 9) THEN	-- OPERATION_TYPE_HOST_(ADD, REMOVE, ENABLE, DISABLE)
					SET new_operationid = new_operationid + 1;

					INSERT INTO operations (operationid, actionid, operationtype)
						VALUES (new_operationid, v_actionid, v_operationtype);
				ELSEIF v_operationtype IN (4, 5) THEN		-- OPERATION_TYPE_GROUP_(ADD, REMOVE)
					SET new_operationid = new_operationid + 1;

					INSERT INTO operations (operationid, actionid, operationtype)
						VALUES (new_operationid, v_actionid, v_operationtype);

					SET new_opgroupid = new_opgroupid + 1;

					INSERT INTO opgroup (opgroupid, operationid, groupid)
						VALUES (new_opgroupid, new_operationid, v_objectid);
				ELSEIF v_operationtype IN (6, 7) THEN		-- OPERATION_TYPE_TEMPLATE_(ADD, REMOVE)
					SET new_operationid = new_operationid + 1;

					INSERT INTO operations (operationid, actionid, operationtype)
						VALUES (new_operationid, v_actionid, v_operationtype);

					SET new_optemplateid = new_optemplateid + 1;

					INSERT INTO optemplate (optemplateid, operationid, templateid)
						VALUES (new_optemplateid, new_operationid, v_objectid);
				END IF;
			END LOOP o_loop;

			CLOSE o_cur;
		END;
	END LOOP n_loop;

	CLOSE n_cur;
END
/

CALL zbx_convert_operations
/

DROP SEQUENCE opconditions_seq
/

DROP TABLE t_operations
/
DROP TABLE t_opconditions
/
DROP PROCEDURE zbx_convert_operations
/

UPDATE opcommand
	SET type = 1, command = TRIM(SUBSTR(command, 5))
	WHERE SUBSTR(command, 1, 4) = 'IPMI'
/

DELETE FROM ids WHERE table_name IN ('operations', 'opconditions', 'opmediatypes')
/
