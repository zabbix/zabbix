alter table items add ipmi_sensor             varchar(128)            DEFAULT ''      NOT NULL;
CREATE INDEX items_4 on items (templateid);
