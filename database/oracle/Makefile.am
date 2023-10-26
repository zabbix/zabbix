SUBDIRS = option-patches

if DBSCHEMA
DATABASE = oracle

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	$(top_srcdir)/create/bin/gen_data.pl $(DATABASE) > data.sql

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql || (rm -rf schema.sql && exit 1)

clean: clean-recursive
	rm -f schema.sql data.sql
endif

EXTRA_DIST = \
	data.sql \
	images.sql \
	schema.sql

.PHONY: clean
