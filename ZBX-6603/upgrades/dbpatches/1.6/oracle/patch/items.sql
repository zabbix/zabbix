alter table items add ipmi_sensor varchar2(128) DEFAULT '';
CREATE INDEX items_4 on items (templateid);
