CREATE TABLE httptestitem (
	httptestitemid          number(20)              DEFAULT '0'     NOT NULL,
	httptestid              number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (httptestitemid)
);
CREATE UNIQUE INDEX httptestitem_httptestitem_1 on httptestitem (httptestid,itemid);
