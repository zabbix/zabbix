RENAME history TO history_old;
CREATE TABLE history (
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    BINARY_DOUBLE   DEFAULT '0.0000'          NOT NULL,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
	CONSTRAINT PK_HISTORY PRIMARY KEY (itemid,clock,ns)
);

RENAME history_uint TO history_uint_old;
CREATE TABLE history_uint (
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    number(20)      DEFAULT '0'               NOT NULL,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
	CONSTRAINT PK_HISTORY_UINT PRIMARY KEY (itemid,clock,ns)
);

RENAME history_str TO history_str_old;
CREATE TABLE history_str (
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    nvarchar2(255)  DEFAULT ''                ,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
	CONSTRAINT PK_HISTORY_STR PRIMARY KEY (itemid,clock,ns)
);

RENAME history_log TO history_log_old;
CREATE TABLE history_log (
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	timestamp                number(10)      DEFAULT '0'               NOT NULL,
	source                   nvarchar2(64)   DEFAULT ''                ,
	severity                 number(10)      DEFAULT '0'               NOT NULL,
	value                    nclob           DEFAULT ''                ,
	logeventid               number(10)      DEFAULT '0'               NOT NULL,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
	CONSTRAINT PK_HISTORY_LOG PRIMARY KEY (itemid,clock,ns)
);

RENAME history_text TO history_text_old;
CREATE TABLE history_text (
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    nclob           DEFAULT ''                ,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
	CONSTRAINT PK_HISTORY_TEXT PRIMARY KEY (itemid,clock,ns)
);

