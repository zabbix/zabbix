PATCHES = \
	  history_upgrade_prepare.sql

if DBSCHEMA
all: $(PATCHES)

history_upgrade_prepare.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl postgresql > $@

clean:
	rm -f history_upgrade_prepare.sql
endif

EXTRA_DIST = $(PATCHES)

.PHONY: all clean
