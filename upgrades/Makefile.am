## Process this file with automake to produce Makefile.in

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	mkdir $(distdir)/dbpatches
	mkdir $(distdir)/dbpatches/1.6
	mkdir $(distdir)/dbpatches/1.6/mysql
	mkdir $(distdir)/dbpatches/1.6/oracle
	mkdir $(distdir)/dbpatches/1.6/postgresql
	cat $(srcdir)/dbpatches/1.6/mysql/patch/* >$(distdir)/dbpatches/1.6/mysql/patch.sql
	cat $(srcdir)/dbpatches/1.6/oracle/patch/* >$(distdir)/dbpatches/1.6/oracle/patch.sql
	cat $(srcdir)/dbpatches/1.6/postgresql/patch/* >$(distdir)/dbpatches/1.6/postgresql/patch.sql
