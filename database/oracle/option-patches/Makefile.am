PATCHES = \
	  history_upgrade_prepare.sql \
	  items_nvarchar_prepare.sql

if DBSCHEMA
all: $(PATCHES)

history_upgrade_prepare.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl oracle > $@
endif

EXTRA_DIST = $(PATCHES)

clean:
	rm -f history_upgrade_prepare.sql

.PHONY: all clean
