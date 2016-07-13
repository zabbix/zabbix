## Process this file with automake to produce Makefile.in

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	mkdir $(distdir)/dbpatches
	mkdir $(distdir)/dbpatches/1.6
	mkdir $(distdir)/dbpatches/1.8
	mkdir $(distdir)/dbpatches/2.0
	mkdir $(distdir)/dbpatches/1.6/mysql
	mkdir $(distdir)/dbpatches/1.6/oracle
	mkdir $(distdir)/dbpatches/1.6/postgresql
	mkdir $(distdir)/dbpatches/1.8/mysql
	mkdir $(distdir)/dbpatches/1.8/oracle
	mkdir $(distdir)/dbpatches/1.8/postgresql
	mkdir $(distdir)/dbpatches/2.0/ibm_db2
	mkdir $(distdir)/dbpatches/2.0/mysql
	mkdir $(distdir)/dbpatches/2.0/oracle
	mkdir $(distdir)/dbpatches/2.0/postgresql
	cat $(srcdir)/dbpatches/1.6/mysql/patch/* >$(distdir)/dbpatches/1.6/mysql/patch.sql
	cat $(srcdir)/dbpatches/1.6/oracle/patch/* >$(distdir)/dbpatches/1.6/oracle/patch.sql
	cat $(srcdir)/dbpatches/1.6/postgresql/patch/* >$(distdir)/dbpatches/1.6/postgresql/patch.sql
	cat $(srcdir)/dbpatches/1.8/mysql/patch/* >$(distdir)/dbpatches/1.8/mysql/patch.sql
	cat $(srcdir)/dbpatches/1.8/oracle/patch/* >$(distdir)/dbpatches/1.8/oracle/patch.sql
	cat $(srcdir)/dbpatches/1.8/postgresql/patch/* >$(distdir)/dbpatches/1.8/postgresql/patch.sql
	cp $(srcdir)/dbpatches/1.8/mysql/upgrade $(distdir)/dbpatches/1.8/mysql/
	cat $(srcdir)/dbpatches/2.0/ibm_db2/patch/* >$(distdir)/dbpatches/2.0/ibm_db2/patch.sql
	cat $(srcdir)/dbpatches/2.0/mysql/patch/* >$(distdir)/dbpatches/2.0/mysql/patch.sql
	cat $(srcdir)/dbpatches/2.0/oracle/patch/* >$(distdir)/dbpatches/2.0/oracle/patch.sql
	cat $(srcdir)/dbpatches/2.0/postgresql/patch/* >$(distdir)/dbpatches/2.0/postgresql/patch.sql
	cp $(srcdir)/dbpatches/2.0/mysql/upgrade $(distdir)/dbpatches/2.0/mysql/
	cp $(srcdir)/dbpatches/2.0/ibm_db2/rc4_rc5.sql $(distdir)/dbpatches/2.0/ibm_db2/
	cp $(srcdir)/dbpatches/2.0/mysql/rc4_rc5.sql $(distdir)/dbpatches/2.0/mysql/
	cp $(srcdir)/dbpatches/2.0/oracle/rc4_rc5.sql $(distdir)/dbpatches/2.0/oracle/
	cp $(srcdir)/dbpatches/2.0/postgresql/rc4_rc5.sql $(distdir)/dbpatches/2.0/postgresql/
