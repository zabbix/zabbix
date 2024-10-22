PATCHES = double.sql

EXTRA_DIST = $(PATCHES)

if DBSCHEMA
GENPATCHES = history_pk_prepare.sql

CLEANFILES  = $(GENPATCHES)
EXTRA_DIST += $(GENPATCHES)


all: $(GENPATCHES)

history_pk_prepare.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl postgresql > $@

.PHONY: all
endif
