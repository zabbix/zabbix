if DBSCHEMA
DATABASE = postgresql
DB_EXTENSION = timescaledb

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	$(top_srcdir)/create/bin/gen_data.pl $(DATABASE) > data.sql

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql

$(DB_EXTENSION).sql:
	$(top_srcdir)/create/bin/gen_schema.pl $(DB_EXTENSION) > $(DB_EXTENSION).sql

history_pk_prepare.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl $(DATABASE) > history_pk_prepare.sql

tsdb_history_pk_upgrade_with_compression/history_pk.sql:
	mkdir -p tsdb_history_pk_upgrade_with_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history with_compression > tsdb_history_pk_upgrade_with_compression/history_pk.sql

tsdb_history_pk_upgrade_with_compression/history_pk_log.sql:
	mkdir -p tsdb_history_pk_upgrade_with_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_log with_compression > tsdb_history_pk_upgrade_with_compression/history_pk_log.sql

tsdb_history_pk_upgrade_with_compression/history_pk_str.sql:
	mkdir -p tsdb_history_pk_upgrade_with_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_str with_compression > tsdb_history_pk_upgrade_with_compression/history_pk_str.sql

tsdb_history_pk_upgrade_with_compression/history_pk_text.sql:
	mkdir -p tsdb_history_pk_upgrade_with_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_text with_compression > tsdb_history_pk_upgrade_with_compression/history_pk_text.sql

tsdb_history_pk_upgrade_with_compression/history_pk_uint.sql:
	mkdir -p tsdb_history_pk_upgrade_with_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_uint with_compression > tsdb_history_pk_upgrade_with_compression/history_pk_uint.sql

tsdb_history_pk_upgrade_no_compression/history_pk.sql:
	mkdir -p tsdb_history_pk_upgrade_no_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history > tsdb_history_pk_upgrade_no_compression/history_pk.sql

tsdb_history_pk_upgrade_no_compression/history_pk_log.sql:
	mkdir -p tsdb_history_pk_upgrade_no_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_log > tsdb_history_pk_upgrade_no_compression/history_pk_log.sql

tsdb_history_pk_upgrade_no_compression/history_pk_str.sql:
	mkdir -p tsdb_history_pk_upgrade_no_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_str > tsdb_history_pk_upgrade_no_compression/history_pk_str.sql

tsdb_history_pk_upgrade_no_compression/history_pk_text.sql:
	mkdir -p tsdb_history_pk_upgrade_no_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_text > tsdb_history_pk_upgrade_no_compression/history_pk_text.sql

tsdb_history_pk_upgrade_no_compression/history_pk_uint.sql:
	mkdir -p tsdb_history_pk_upgrade_no_compression && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_uint > tsdb_history_pk_upgrade_no_compression/history_pk_uint.sql

endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql \
	double.sql \
	$(DB_EXTENSION).sql \
	history_pk_prepare.sql \
	tsdb_history_pk_upgrade_with_compression/history_pk.sql \
	tsdb_history_pk_upgrade_with_compression/history_pk_log.sql \
	tsdb_history_pk_upgrade_with_compression/history_pk_str.sql \
	tsdb_history_pk_upgrade_with_compression/history_pk_text.sql \
	tsdb_history_pk_upgrade_with_compression/history_pk_uint.sql \
	tsdb_history_pk_upgrade_no_compression/history_pk.sql \
	tsdb_history_pk_upgrade_no_compression/history_pk_log.sql \
	tsdb_history_pk_upgrade_no_compression/history_pk_str.sql \
	tsdb_history_pk_upgrade_no_compression/history_pk_text.sql \
	tsdb_history_pk_upgrade_no_compression/history_pk_uint.sql

clean:
	rm -f schema.sql data.sql timescaledb.sql history_pk_prepare.sql
	rm -rf tsdb_history_pk_upgrade_with_compression tsdb_history_pk_upgrade_no_compression
