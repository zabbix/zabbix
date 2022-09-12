if DBSCHEMA
DATABASE = postgresql
DB_EXTENSION = timescaledb

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	$(top_srcdir)/create/bin/gen_data.pl $(DATABASE) > data.sql

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql || (rm -rf schema.sql && exit 1)

$(DB_EXTENSION).sql:
	$(top_srcdir)/create/bin/gen_schema.pl $(DB_EXTENSION) > $(DB_EXTENSION).sql

gen_history_pk = $(top_srcdir)/create/bin/gen_history_pk.pl
history_pk_prepare.sql: $(gen_history_pk)
	$(gen_history_pk) $(DATABASE) > history_pk_prepare.sql

$(tsdb_wcompr_dir)/history_pk.sql:
	mkdir -p $(tsdb_wcompr_dir) && \
	$(gen_history_pk) timescaledb history with_compression > $(tsdb_wcompr_dir)/history_pk.sql

$(tsdb_wcompr_dir)/history_pk_log.sql:
	mkdir -p $(tsdb_wcompr_dir) && \
	$(gen_history_pk) timescaledb history_log with_compression > $(tsdb_wcompr_dir)/history_pk_log.sql

$(tsdb_wcompr_dir)/history_pk_str.sql:
	mkdir -p $(tsdb_wcompr_dir) && \
	$(gen_history_pk) timescaledb history_str with_compression > $(tsdb_wcompr_dir)/history_pk_str.sql

$(tsdb_wcompr_dir)/history_pk_text.sql:
	mkdir -p $(tsdb_wcompr_dir) && \
	$(gen_history_pk) timescaledb history_text with_compression > $(tsdb_wcompr_dir)/history_pk_text.sql

$(tsdb_wcompr_dir)/history_pk_uint.sql:
	mkdir -p $(tsdb_wcompr_dir) && \
	$(gen_history_pk) timescaledb history_uint with_compression > $(tsdb_wcompr_dir)/history_pk_uint.sql

$(tsdb_nocompr_dir)/history_pk.sql:
	mkdir -p $(tsdb_nocompr_dir) && \
	$(gen_history_pk) timescaledb history > $(tsdb_nocompr_dir)/history_pk.sql

$(tsdb_nocompr_dir)/history_pk_log.sql:
	mkdir -p $(tsdb_nocompr_dir) && \
	$(gen_history_pk) timescaledb history_log > $(tsdb_nocompr_dir)/history_pk_log.sql

$(tsdb_nocompr_dir)/history_pk_str.sql:
	mkdir -p $(tsdb_nocompr_dir) && \
	$(gen_history_pk) timescaledb history_str > $(tsdb_nocompr_dir)/history_pk_str.sql

$(tsdb_nocompr_dir)/history_pk_text.sql:
	mkdir -p $(tsdb_nocompr_dir) && \
	$(gen_history_pk) timescaledb history_text > $(tsdb_nocompr_dir)/history_pk_text.sql

$(tsdb_nocompr_dir)/history_pk_uint.sql:
	mkdir -p $(tsdb_nocompr_dir) && \
	$(gen_history_pk) timescaledb history_uint > $(tsdb_nocompr_dir)/history_pk_uint.sql

endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql \
	double.sql \
	$(DB_EXTENSION).sql \
	history_pk_prepare.sql \
	$(tsdb_wcompr_dir)/history_pk.sql \
	$(tsdb_wcompr_dir)/history_pk_log.sql \
	$(tsdb_wcompr_dir)/history_pk_str.sql \
	$(tsdb_wcompr_dir)/history_pk_text.sql \
	$(tsdb_wcompr_dir)/history_pk_uint.sql \
	$(tsdb_nocompr_dir)/history_pk.sql \
	$(tsdb_nocompr_dir)/history_pk_log.sql \
	$(tsdb_nocompr_dir)/history_pk_str.sql \
	$(tsdb_nocompr_dir)/history_pk_text.sql \
	$(tsdb_nocompr_dir)/history_pk_uint.sql

clean:
	rm -f schema.sql data.sql timescaledb.sql history_pk_prepare.sql
	rm -rf tsdb_history_pk_upgrade_with_compression tsdb_history_pk_upgrade_no_compression
