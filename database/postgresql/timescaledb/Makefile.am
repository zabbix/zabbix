SUBDIRS = option-patches

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl timescaledb > schema.sql || (rm -rf schemq.sql && exit 1)

clean: clean-recursive
	rm -f schema.sql

EXTRA_DIST = schema.sql

.PHONY: clean
