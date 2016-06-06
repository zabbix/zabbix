CREATE TABLE httpstepitem (
	httpstepitemid          number(20)              DEFAULT '0'     NOT NULL,
	httpstepid              number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (httpstepitemid)
);
CREATE UNIQUE INDEX httpstepitem_httpstepitem_1 on httpstepitem (httpstepid,itemid);
