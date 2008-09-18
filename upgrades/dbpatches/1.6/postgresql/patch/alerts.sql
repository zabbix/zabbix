CREATE TABLE alerts_tmp (
        alertid         bigint          DEFAULT '0'     NOT NULL,
        actionid                bigint          DEFAULT '0'     NOT NULL,
        eventid         bigint          DEFAULT '0'     NOT NULL,
        userid          bigint          DEFAULT '0'     NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        mediatypeid             bigint          DEFAULT '0'     NOT NULL,
        sendto          varchar(100)            DEFAULT ''      NOT NULL,
        subject         varchar(255)            DEFAULT ''      NOT NULL,
        message         text            DEFAULT ''      NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        retries         integer         DEFAULT '0'     NOT NULL,
        error           varchar(128)            DEFAULT ''      NOT NULL,
        nextcheck               integer         DEFAULT '0'     NOT NULL,
        esc_step                integer         DEFAULT '0'     NOT NULL,
        alerttype               integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (alertid)
) with OIDS;

insert into alerts_tmp (alertid,actionid,userid,clock,mediatypeid,sendto,subject,message,status,retries,error,nextcheck) select alertid,actionid,userid,clock,mediatypeid,sendto,subject,message,status,retries,error,nextcheck from alerts;
update alerts_tmp set status=3 where retries>=2;

drop table alerts;
alter table alerts_tmp rename to alerts;

CREATE INDEX alerts_1 on alerts (actionid);
CREATE INDEX alerts_2 on alerts (clock);
CREATE INDEX alerts_3 on alerts (eventid);
CREATE INDEX alerts_4 on alerts (status,retries);
CREATE INDEX alerts_5 on alerts (mediatypeid);
CREATE INDEX alerts_6 on alerts (userid);
