ALTER TABLE trends RENAME COLUMN value_min TO zbx_old_tmp1;
ALTER TABLE trends RENAME COLUMN value_avg TO zbx_old_tmp2;
ALTER TABLE trends RENAME COLUMN value_max TO zbx_old_tmp3;
ALTER TABLE trends
	ADD (value_min BINARY_DOUBLE DEFAULT '0.0000' NOT NULL,
		value_avg BINARY_DOUBLE DEFAULT '0.0000' NOT NULL,
		value_max BINARY_DOUBLE DEFAULT '0.0000' NOT NULL);
UPDATE trends
	SET value_min=zbx_old_tmp1,
		value_avg=zbx_old_tmp2,
		value_max=zbx_old_tmp3;
ALTER TABLE trends DROP COLUMN zbx_old_tmp1;
ALTER TABLE trends DROP COLUMN zbx_old_tmp2;
ALTER TABLE trends DROP COLUMN zbx_old_tmp3;
ALTER TABLE history RENAME COLUMN value TO zbx_old_tmp1;
ALTER TABLE history ADD value BINARY_DOUBLE DEFAULT '0.0000' NOT NULL;
UPDATE history SET value=zbx_old_tmp1;
ALTER TABLE history DROP COLUMN zbx_old_tmp1;
