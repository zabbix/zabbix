ALTER TABLE graphs RENAME COLUMN yaxismin TO zbx_old_tmp1;
ALTER TABLE graphs RENAME COLUMN yaxismax TO zbx_old_tmp2;
ALTER TABLE graphs RENAME COLUMN percent_left TO zbx_old_tmp3;
ALTER TABLE graphs RENAME COLUMN percent_right TO zbx_old_tmp4;
ALTER TABLE graphs
	ADD (yaxismin BINARY_DOUBLE DEFAULT '0' NOT NULL,
		yaxismax BINARY_DOUBLE DEFAULT '100' NOT NULL,
		percent_left BINARY_DOUBLE DEFAULT '0' NOT NULL,
		percent_right BINARY_DOUBLE DEFAULT '0' NOT NULL);
UPDATE graphs
	SET yaxismin=zbx_old_tmp1,
		yaxismax=zbx_old_tmp2,
		percent_left=zbx_old_tmp3,
		percent_right=zbx_old_tmp4;
ALTER TABLE graphs DROP COLUMN zbx_old_tmp1;
ALTER TABLE graphs DROP COLUMN zbx_old_tmp2;
ALTER TABLE graphs DROP COLUMN zbx_old_tmp3;
ALTER TABLE graphs DROP COLUMN zbx_old_tmp4;
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
ALTER TABLE services RENAME COLUMN goodsla TO zbx_old_tmp1;
ALTER TABLE services ADD goodsla BINARY_DOUBLE DEFAULT '99.9' NOT NULL;
UPDATE services SET goodsla=zbx_old_tmp1;
ALTER TABLE services DROP COLUMN zbx_old_tmp1;
ALTER TABLE history RENAME COLUMN value TO zbx_old_tmp1;
ALTER TABLE history ADD value BINARY_DOUBLE DEFAULT '0.0000' NOT NULL;
UPDATE history SET value=zbx_old_tmp1;
ALTER TABLE history DROP COLUMN zbx_old_tmp1;
