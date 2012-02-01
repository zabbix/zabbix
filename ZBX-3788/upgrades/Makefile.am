## Process this file with automake to produce Makefile.in

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	mkdir $(distdir)/dbpatches
	mkdir $(distdir)/dbpatches/1.6
	mkdir $(distdir)/dbpatches/1.8
	mkdir $(distdir)/dbpatches/1.6/mysql
	mkdir $(distdir)/dbpatches/1.6/oracle
	mkdir $(distdir)/dbpatches/1.6/postgresql
	mkdir $(distdir)/dbpatches/1.8/mysql
	mkdir $(distdir)/dbpatches/1.8/oracle
	mkdir $(distdir)/dbpatches/1.8/postgresql
	cat $(srcdir)/dbpatches/1.6/mysql/patch/* >$(distdir)/dbpatches/1.6/mysql/patch.sql
	cat $(srcdir)/dbpatches/1.6/oracle/patch/* >$(distdir)/dbpatches/1.6/oracle/patch.sql
	cat $(srcdir)/dbpatches/1.6/postgresql/patch/* >$(distdir)/dbpatches/1.6/postgresql/patch.sql
	cat $(srcdir)/dbpatches/1.8/mysql/patch/* >$(distdir)/dbpatches/1.8/mysql/patch.sql
	cat $(srcdir)/dbpatches/1.8/oracle/patch/* >$(distdir)/dbpatches/1.8/oracle/patch.sql
	cat $(srcdir)/dbpatches/1.8/postgresql/patch/* >$(distdir)/dbpatches/1.8/postgresql/patch.sql
	cp $(srcdir)/dbpatches/1.8/mysql/upgrade $(distdir)/dbpatches/1.8/mysql/
