if DBSCHEMA
DATABASE = postgresql
TIMESCALEDB_CREATE_EXTENSION = timescaledb_create_extension
TIMESCALEDB_CONFIGURE = timescaledb_configure

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	$(top_srcdir)/create/bin/gen_data.pl $(DATABASE) > data.sql

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql

$(TIMESCALEDB_CREATE_EXTENSION).sql:
	$(top_srcdir)/create/bin/gen_schema.pl $(TIMESCALEDB_CREATE_EXTENSION) > $(TIMESCALEDB_CREATE_EXTENSION).sql

$(TIMESCALEDB_CONFIGURE).sql:
	$(top_srcdir)/create/bin/gen_schema.pl $(TIMESCALEDB_CONFIGURE) > $(TIMESCALEDB_CONFIGURE).sql
endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql \
	double.sql \
	$(TIMESCALEDB_CREATE_EXTENSION).sql \
	$(TIMESCALEDB_CONFIGURE).sql

.PHONY: $(TIMESCALEDB_CREATE_EXTENSION).sql $(TIMESCALEDB_CONFIGURE).sql
