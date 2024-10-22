SUBDIRS = option-patches

if DBSCHEMA
GENSQLFILES = schema.sql

CLEANFILES = $(GENSQLFILES)
EXTRA_DIST = $(GENSQLFILES)

all: $(GENSQLFILES)

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl timescaledb > $@ || (rm -f $@ && exit 1)

.PHONY: all
endif
