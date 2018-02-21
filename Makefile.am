## Process this file with automake to produce Makefile.in

ACLOCAL_AMFLAGS = -I m4

SUBDIRS = \
	src \
	database \
	man \
	misc \
	upgrades

EXTRA_DIST = \
	bin \
	build \
	frontends \
	include \
	conf \
	sass

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	@zabbix_revision=`svn info $(top_srcdir)|sed -n -e "/Last Changed Rev:/s/.*: \([[0-9]]*\)/\1/p"`; \
	cat $(top_distdir)/include/version.h|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > $(top_distdir)/include/version.h.new; \
	mv $(top_distdir)/include/version.h.new $(top_distdir)/include/version.h; \
	cat $(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > \
		$(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java.new; \
	mv $(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java.new \
		$(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java

	rm -rf `find $(top_distdir) -name ".svn"`
	rm -f $(top_distdir)/include/config.h
	rm -f $(top_distdir)/frontends/php/conf/zabbix.conf.php
	rm -rf $(top_distdir)/frontends/php/tests
	rm -f $(top_distdir)/sass/*.html
	rm -rf $(top_distdir)/sass/img_source

if DBSCHEMA
dbschema_ibm_db2:
	cd database/ibm_db2 && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_mysql:
	cd database/mysql && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_oracle:
	cd database/oracle && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_postgresql:
	cd database/postgresql && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_sqlite3:
	cd database/sqlite3 && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_c:
	cd src/libs/zbxdbhigh && \
	$(MAKE) $(AM_MAKEFLAGS) dbschema.c

dbschema: dbschema_ibm_db2 dbschema_mysql dbschema_oracle dbschema_postgresql dbschema_sqlite3 dbschema_c
endif

gettext:
	frontends/php/locale/make_mo.sh

css_theme_blue:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/screen.scss frontends/php/styles/blue-theme.css
	cp frontends/php/styles/blue-theme.css sass/stylesheets/screen.css

css_theme_dark:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/dark-theme.scss frontends/php/styles/dark-theme.css
	cp frontends/php/styles/dark-theme.css sass/stylesheets/dark-theme.css

css_hc_light:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/hc-light.scss frontends/php/styles/hc-light.css
	cp frontends/php/styles/hc-light.css sass/stylesheets/hc-light.css

css_hc_dark:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/hc-dark.scss frontends/php/styles/hc-dark.css
	cp frontends/php/styles/hc-dark.css sass/stylesheets/hc-dark.css

css_icons:
	cp sass/img/icon-sprite.svg frontends/php/img/
	cp sass/img/browser-sprite.png frontends/php/img/
	cp sass/apple-touch-icon-120x120-precomposed.png frontends/php/img/
	cp sass/apple-touch-icon-152x152-precomposed.png frontends/php/img/
	cp sass/apple-touch-icon-180x180-precomposed.png frontends/php/img/
	cp sass/apple-touch-icon-76x76-precomposed.png frontends/php/img/
	cp sass/ms-tile-144x144.png frontends/php/img/
	cp sass/touch-icon-192x192.png frontends/php/img/
	cp sass/favicon.ico frontends/php/

css: css_theme_blue css_theme_dark css_hc_light css_hc_dark css_icons

## CUnit test support
TESTDIR = src

test:
	cd $(TESTDIR) && $(MAKE) $(AM_MAKEFLAGS) test

## cmocka test support
if ZBXCMOCKA
WRAP_DB_FUNCS = \
	-Wl,--wrap=zbx_db_vselect \
	-Wl,--wrap=zbx_db_fetch \
	-Wl,--wrap=__zbx_DBexecute \
	-Wl,--wrap=DBbegin \
	-Wl,--wrap=DBcommit \
	-Wl,--wrap=DBexecute_multiple_query \
	-Wl,--wrap=DBfree_result

WRAP_IO_FUNCS = \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=fgets

WRAP_FS_FUNCS = \
	-Wl,--wrap=opendir \
	-Wl,--wrap=readdir \
	-Wl,--wrap=stat \
	-Wl,--wrap=__xstat

WRAP_EXIT = \
	-Wl,--wrap=exit

COMMON_WRAP_FUNCS = \
	$(WRAP_DB_FUNCS) \
	$(WRAP_IO_FUNCS) \
	$(WRAP_FS_FUNCS) \
	$(WRAP_EXIT)

tests:
	$(MAKE) $(AM_MAKEFLAGS) && \
	cd tests && \
	$(MAKE) $(AM_MAKEFLAGS) LDFLAGS="$(LDFLAGS) $(COMMON_WRAP_FUNCS)" LIBS="$(LIBS) -lcmocka -lyaml" && \
	./tests_run.pl
endif

.PHONY: test tests
