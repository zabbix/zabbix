alter table actions add esc_period    number(10)     DEFAULT '0' NOT NULL;
alter table actions add def_shortdata varchar2(255)  DEFAULT '';
alter table actions add def_longdata  varchar2(2048) DEFAULT '';
alter table actions add recovery_msg  number(10)     DEFAULT '0' NOT NULL;
alter table actions add r_shortdata   varchar2(255)  DEFAULT '';
alter table actions add r_longdata    varchar2(2048) DEFAULT '';

CREATE INDEX actions_1 on actions (eventsource,status);
