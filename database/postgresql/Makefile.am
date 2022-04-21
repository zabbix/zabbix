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

tsdb/history_pk.sql:
	mkdir -p tsdb && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history > tsdb/history_pk.sql

tsdb/history_pk_log.sql:
	mkdir -p tsdb && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_log > tsdb/history_pk_log.sql

tsdb/history_pk_str.sql:
	mkdir -p tsdb && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_str > tsdb/history_pk_str.sql

tsdb/history_pk_text.sql:
	mkdir -p tsdb && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_text > tsdb/history_pk_text.sql

tsdb/history_pk_uint.sql:
	mkdir -p tsdb && \
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_uint > tsdb/history_pk_uint.sql
endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql \
	double.sql \
	$(DB_EXTENSION).sql \
	history_pk_prepare.sql \
	tsdb/history_pk.sql \
	tsdb/history_pk_log.sql \
	tsdb/history_pk_str.sql \
	tsdb/history_pk_text.sql \
	tsdb/history_pk_uint.sql

clean:
	rm -f schema.sql data.sql timescaledb.sql history_pk_prepare.sql
	rm -rf tsdb
