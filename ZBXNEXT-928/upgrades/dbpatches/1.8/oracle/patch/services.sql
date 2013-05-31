CREATE INDEX services_1 on services (triggerid);

alter table services modify name            nvarchar2(128)          DEFAULT '';

