SELECT
	row_to_json (T)
FROM
	(
		SELECT
			CASE
				WHEN pg_is_in_recovery () THEN 0
				ELSE pg_wal_lsn_diff (pg_current_wal_lsn (), '0/00000000')
			END AS WRITE,
			CASE
				WHEN NOT pg_is_in_recovery () THEN 0
				ELSE pg_wal_lsn_diff (pg_last_wal_receive_lsn (), '0/00000000')
			END AS RECEIVE,
			count(*)
		FROM
			pg_ls_waldir () AS COUNT
	) T
