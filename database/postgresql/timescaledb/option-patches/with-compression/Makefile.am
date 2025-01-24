if DBSCHEMA
GENPATCHES = history_pk.sql history_pk_log.sql history_pk_str.sql \
	     history_pk_text.sql history_pk_uint.sql

CLEANFILES = $(GENPATCHES)
EXTRA_DIST = $(GENPATCHES)


all: $(GENPATCHES)

history_pk.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history with_compression > $@

history_pk_log.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_log with_compression > $@

history_pk_text.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_str with_compression > $@

history_pk_str.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_text with_compression > $@

history_pk_uint.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl timescaledb history_uint with_compression > $@

.PHONY: all
endif
