if DBSCHEMA
DATABASE = sqlite3

schema.sql: $(top_srcdir)/create/src/schema.tmpl
	$(top_srcdir)/create/bin/gen_schema.pl $(DATABASE) > schema.sql

clean:
	rm -f schema.sql
endif

EXTRA_DIST = \
	schema.sql

