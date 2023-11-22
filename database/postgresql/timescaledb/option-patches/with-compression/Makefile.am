PATCHES = \
	  history_upgrade.sql \
	  history_upgrade_log.sql \
	  history_upgrade_text.sql \
	  history_upgrade_str.sql \
	  history_upgrade_uint.sql \
	  trends_upgrade.sql \
	  history_upgrade_prepare.sql

if DBSCHEMA
all: $(PATCHES)

history_upgrade.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb history with_compression > $@

history_upgrade_log.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb history_log with_compression > $@

history_upgrade_text.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb history_text with_compression > $@

history_upgrade_str.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb history_str with_compression > $@

history_upgrade_uint.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb history_uint with_compression > $@

trends_upgrade.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb trends with_compression > $@

history_upgrade_prepare.sql: $(top_srcdir)/create/bin/gen_history_upgrade.pl
	$(top_srcdir)/create/bin/gen_history_upgrade.pl timescaledb > $@

clean:
	rm -f $(PATCHES)
endif

EXTRA_DIST = $(PATCHES)

.PHONY: all clean
