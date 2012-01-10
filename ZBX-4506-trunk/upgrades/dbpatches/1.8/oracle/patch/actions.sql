CREATE INDEX actions_1 on actions (eventsource,status);

alter table actions modify name            nvarchar2(255)          DEFAULT '';
alter table actions modify def_shortdata           nvarchar2(255)          DEFAULT '';
alter table actions modify def_longdata            nvarchar2(2048)         DEFAULT '';
alter table actions modify r_shortdata             nvarchar2(255)          DEFAULT '';
alter table actions modify r_longdata              nvarchar2(2048)         DEFAULT '';

