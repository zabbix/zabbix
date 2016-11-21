CREATE INDEX sessions_1 on sessions (userid, status);

alter table sessions modify sessionid               nvarchar2(32)           DEFAULT '';
