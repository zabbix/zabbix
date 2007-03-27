## Process this file with automake to produce Makefile.in

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	mkdir $(distdir)/dbpatches
	mkdir $(distdir)/dbpatches/1.4
	mkdir $(distdir)/dbpatches/1.4/mysql
	mkdir $(distdir)/dbpatches/1.4/oracle
	mkdir $(distdir)/dbpatches/1.4/postgresql
	cat $(srcdir)/dbpatches/1.4/mysql/patch/* >$(distdir)/dbpatches/1.4/mysql/patch.sql
	cat $(srcdir)/dbpatches/1.4/oracle/patch/* >$(distdir)/dbpatches/1.4/oracle/patch.sql
	cat $(srcdir)/dbpatches/1.4/postgresql/patch/* >$(distdir)/dbpatches/1.4/postgresql/patch.sql
