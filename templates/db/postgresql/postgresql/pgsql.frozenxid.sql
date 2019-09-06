WITH T AS (
	SELECT
		age(relfrozenxid),
		current_setting('autovacuum_freeze_max_age')::integer fma
	FROM pg_class
	WHERE relkind IN ('r', 't'))
SELECT row_to_json(T2)
FROM (
	SELECT extract(epoch FROM now())::integer ts,
	(
		SELECT ((1 - max(age)::double precision / current_setting('autovacuum_freeze_max_age')::integer) * 100)::numeric(9,6)
		FROM T
		WHERE age < fma
	) prc_before_av,
	(
		SELECT ((1 - max(age)::double precision / -((1 << 31) + 1)) * 100)::numeric(9,6)
		FROM T
	) prc_before_stop
) T2
