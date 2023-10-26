PATCHES = \
	  history_pk.sql \
	  history_pk_log.sql \
	  history_pk_text.sql \
	  history_pk_str.sql \
	  history_pk_uint.sql

if DBSCHEMA
all: $(PATCHES)

history_pk.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history without_compression > $@

history_pk_log.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_log without_compression > $@

history_pk_text.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_text without_compression > $@

history_pk_str.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_str without_compression > $@

history_pk_uint.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_uint without_compression > $@

clean:
	rm -f $(PATCHES)
endif

EXTRA_DIST = $(PATCHES)

.PHONY: all clean
