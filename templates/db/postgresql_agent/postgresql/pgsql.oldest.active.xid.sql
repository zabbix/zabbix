SELECT
	greatest (max(age (backend_xmin)), max(age (backend_xid)))
FROM
	pg_catalog.pg_stat_activity;
