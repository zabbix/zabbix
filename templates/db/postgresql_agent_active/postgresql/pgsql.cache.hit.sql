SELECT round(sum(blks_hit)*100/sum(blks_hit+blks_read), 2)
FROM pg_stat_database
