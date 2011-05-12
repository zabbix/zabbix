CREATE TABLE httptest_tmp (
        httptestid              number(20)              DEFAULT '0'     NOT NULL,
        name            varchar2(64)            DEFAULT ''      ,
        applicationid           number(20)              DEFAULT '0'     NOT NULL,
        lastcheck               number(10)              DEFAULT '0'     NOT NULL,
        nextcheck               number(10)              DEFAULT '0'     NOT NULL,
        curstate                number(10)              DEFAULT '0'     NOT NULL,
        curstep         number(10)              DEFAULT '0'     NOT NULL,
        lastfailedstep          number(10)              DEFAULT '0'     NOT NULL,
        delay           number(10)              DEFAULT '60'    NOT NULL,
        status          number(10)              DEFAULT '0'     NOT NULL,
        macros          varchar2(2048)          DEFAULT ''      ,
        agent           varchar2(255)           DEFAULT ''      ,
        time            number(20,4)            DEFAULT '0'     NOT NULL,
        error           varchar2(255)           DEFAULT ''      ,
        PRIMARY KEY (httptestid)
);

insert into httptest_tmp select * from httptest;
drop table httptest;
alter table httptest_tmp rename to httptest;

CREATE INDEX httptest_httptest_1 on httptest (applicationid);
CREATE INDEX httptest_2 on httptest (name);
CREATE INDEX httptest_3 on httptest (status);
