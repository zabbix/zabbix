CREATE TABLE nodes (
	nodeid          number(10)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT '0'     ,
	timezone                number(10)              DEFAULT '0'     NOT NULL,
	ip              varchar2(15)            DEFAULT ''      ,
	port            number(10)              DEFAULT '10051' NOT NULL,
	slave_history           number(10)              DEFAULT '30'    NOT NULL,
	slave_trends            number(10)              DEFAULT '365'   NOT NULL,
	event_lastid            number(20)              DEFAULT '0'     NOT NULL,
	history_lastid          number(20)              DEFAULT '0'     NOT NULL,
	history_str_lastid              number(20)              DEFAULT '0'     NOT NULL,
	history_uint_lastid             number(20)              DEFAULT '0'     NOT NULL,
	nodetype                number(10)              DEFAULT '0'     NOT NULL,
	masterid                number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (nodeid)
);
