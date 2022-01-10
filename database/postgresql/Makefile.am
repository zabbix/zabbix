if DBSCHEMA
DATABASE = postgresql
DB_EXTENSION = timescaledb

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	$(top_srcdir)/create/bin/gen_data.pl $(DATABASE) > data.sql

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql

$(DB_EXTENSION).sql:
	$(top_srcdir)/create/bin/gen_schema.pl $(DB_EXTENSION) > $(DB_EXTENSION).sql
endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql \
	double.sql \
	$(DB_EXTENSION).sql \
	history_pk_prepare.sql
