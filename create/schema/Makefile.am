## Process this file with automake to produce Makefile.in

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	$(srcdir)/gen.pl mysql > $(distdir)/mysql.sql
	$(srcdir)/gen.pl postgresql > $(distdir)/postgresql.sql
	$(srcdir)/gen.pl sqlite > $(distdir)/sqlite.sql
	$(srcdir)/gen.pl oracle > $(distdir)/oracle.sql
	$(srcdir)/gen.pl c >$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	echo -e "\n#ifdef HAVE_MYSQL\nconst char *db_schema= {\"\\" >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	cat $(distdir)/mysql.sql|sed -e 's/\t\t*/ /g' -e 's/$$/\\/' >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#elif HAVE_POSTGRESQL\nconst char *db_schema = {\"\\" >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	cat $(distdir)/postgresql.sql|sed -e 's/\t\t*/ /g' -e 's/$$/\\/' >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#elif HAVE_ORACLE\nconst char *db_schema = {\"\\" >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	cat $(distdir)/oracle.sql|sed -e 's/\t\t*/ /g' -e 's/$$/\\/' >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#elif HAVE_SQLITE3\nconst char *db_schema = {\"\\"  >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	cat $(distdir)/sqlite.sql|sed -e 's/\t\t*/ /g' -e 's/$$/\\/' >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#endif /* HAVE_SQLITE3 */\n" >>$(top_distdir)/src/libs/zbxdbhigh/dbschema.c
