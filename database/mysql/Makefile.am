SUBDIRS = option-patches

SQLFILES = images.sql

EXTRA_DIST = images.sql

if DBSCHEMA
GENSQLFILES = data.sql schema.sql

CLEANFILES  = $(GENSQLFILES)
EXTRA_DIST += $(GENSQLFILES)


all: $(GENSQLFILES)

data.sql: $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates-aa.tmpl $(top_srcdir)/create/src/templates-ab.tmpl $(top_srcdir)/create/src/templates-ac.tmpl $(top_srcdir)/create/src/dashboards.tmpl $(top_srcdir)/create/bin/gen_data.pl
	cat $(top_srcdir)/create/src/data.tmpl $(top_srcdir)/create/src/templates-*.tmpl $(top_srcdir)/create/src/dashboards.tmpl | $(top_srcdir)/create/bin/gen_data.pl mysql > $@

schema.sql: $(top_srcdir)/create/src/schema.tmpl $(top_srcdir)/create/bin/gen_schema.pl
	$(top_srcdir)/create/bin/gen_schema.pl mysql > $@

.PHONY: all
endif
