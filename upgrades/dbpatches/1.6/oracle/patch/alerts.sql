CREATE TABLE alerts_tmp (
        alertid         number(20)              DEFAULT '0'     NOT NULL,
        actionid                number(20)              DEFAULT '0'     NOT NULL,
        eventid         number(20)              DEFAULT '0'     NOT NULL,
        userid          number(20)              DEFAULT '0'     NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        mediatypeid             number(20)              DEFAULT '0'     NOT NULL,
        sendto          varchar2(100)           DEFAULT ''      ,
        subject         varchar2(255)           DEFAULT ''      ,
        message         varchar2(2048)          DEFAULT ''      ,
        status          number(10)              DEFAULT '0'     NOT NULL,
        retries         number(10)              DEFAULT '0'     NOT NULL,
        error           varchar2(128)           DEFAULT ''      ,
        nextcheck               number(10)              DEFAULT '0'     NOT NULL,
        esc_step                number(10)              DEFAULT '0'     NOT NULL,
        alerttype               number(10)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (alertid)
);

insert into alerts_tmp select a.alertid,a.actionid,e.eventid,a.userid,a.clock,a.mediatypeid,a.sendto,a.subject,a.message,a.status,a.retries,a.error,a.nextcheck,0,0 from alerts a, events e where a.triggerid=e.objectid and e.object=0;

drop table alerts;
alter table alerts_tmp rename to alerts;
update alerts set status=3 where retries>=2;

CREATE INDEX alerts_1 on alerts (actionid);
CREATE INDEX alerts_2 on alerts (clock);
CREATE INDEX alerts_3 on alerts (eventid);
CREATE INDEX alerts_4 on alerts (status,retries);
CREATE INDEX alerts_5 on alerts (mediatypeid);
CREATE INDEX alerts_6 on alerts (userid);
