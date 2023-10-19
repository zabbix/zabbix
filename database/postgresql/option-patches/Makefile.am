PATCHES = \
	  history_pk_prepare.sql

if DBSCHEMA
all: $(PATCHES)

history_pk_prepare.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl postgresql > $@
endif

EXTRA_DIST = $(PATCHES)

clean:
	rm -f history_pk_prepare.sql

.PHONY: all clean
