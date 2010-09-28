## Process this file with automake to produce Makefile.in

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	$(srcdir)/gen.pl mysql > $(distdir)/mysql.sql
	$(srcdir)/gen.pl postgresql > $(distdir)/postgresql.sql
	$(srcdir)/gen.pl sqlite > $(distdir)/sqlite.sql
	$(srcdir)/gen.pl oracle > $(distdir)/oracle.sql
	$(srcdir)/gen.pl c > $(top_distdir)/include/dbsync.h

