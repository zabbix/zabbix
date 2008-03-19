CREATE TABLE events_tmp (
        eventid         number(20)              DEFAULT '0'     NOT NULL,
        source          number(10)              DEFAULT '0'     NOT NULL,
        object          number(10)              DEFAULT '0'     NOT NULL,
        objectid                number(20)              DEFAULT '0'     NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        value           number(10)              DEFAULT '0'     NOT NULL,
        acknowledged            number(10)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (eventid)
);
CREATE INDEX events_1 on events_tmp (object,objectid,clock);
CREATE INDEX events_2 on events_tmp (clock);

insert into events select alarmid,0,0,triggerid,clock,value,acknowledged from alarms;
drop table alarms;
