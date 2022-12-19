## Process this file with automake to produce Makefile.in

if DBSCHEMA
dbschema.c: $(top_srcdir)/create/src/schema.tmpl
	$(top_srcdir)/create/bin/gen_schema.pl c > dbschema.c

clean:
	rm -f dbschema.c dbschema.o libzbxdbschema.a
endif

noinst_LIBRARIES = libzbxdbschema.a

libzbxdbschema_a_SOURCES = \
	dbschema.c
