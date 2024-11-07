SELECT date_part('epoch', now() - pg_postmaster_start_time())::int
