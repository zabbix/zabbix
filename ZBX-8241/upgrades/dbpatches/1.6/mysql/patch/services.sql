alter table services modify         goodsla         double(16,4)            DEFAULT '99.9'  NOT NULL;
CREATE INDEX services_1 on services (triggerid);
