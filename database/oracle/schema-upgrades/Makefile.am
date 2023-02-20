PATCHES = \
	  history_pk_prepare.sql

all: $(PATCHES)

history_pk_prepare.sql: $(top_srcdir)/create/bin/gen_history_pk.pl
	$(top_srcdir)/create/bin/gen_history_pk.pl oracle > $@

EXTRA_DIST = double.sql \
	     history_pk_prepare.sql

clean:
	rm -f history_pk_prepare.sql

.PHONY: all clean
