ALTER TABLE trends_uint ALTER COLUMN value_min TYPE numeric(20);
ALTER TABLE trends_uint ALTER COLUMN value_avg TYPE numeric(20);
ALTER TABLE trends_uint ALTER COLUMN value_max TYPE numeric(20);
ALTER TABLE trends_uint ALTER COLUMN value_min SET DEFAULT '0';
ALTER TABLE trends_uint ALTER COLUMN value_avg SET DEFAULT '0';
ALTER TABLE trends_uint ALTER COLUMN value_max SET DEFAULT '0';
ALTER TABLE trends_uint ALTER COLUMN value_min SET NOT NULL;
ALTER TABLE trends_uint ALTER COLUMN value_avg SET NOT NULL;
ALTER TABLE trends_uint ALTER COLUMN value_max SET NOT NULL;
