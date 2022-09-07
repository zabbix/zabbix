if DBSCHEMA
DATABASE = mysql

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	$(top_srcdir)/create/bin/gen_data.pl $(DATABASE) > data.sql

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql || (rm -rf schema.sql && exit 1)

history_pk_prepare.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl $(DATABASE) > history_pk_prepare.sql
endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql \
	double.sql \
	history_pk_prepare.sql

clean:
	rm -f schema.sql data.sql history_pk_prepare.sql
