if DBSCHEMA
DATABASE = sqlite3

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql || (rm -rf schema.sql && exit 1)

clean:
	rm -f schema.sql
endif

EXTRA_DIST = \
	schema.sql

