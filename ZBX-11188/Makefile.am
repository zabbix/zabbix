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
	conf

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	@zabbix_revision=`svn info|grep "Last Changed Rev"|awk '{print $$4;}'`; \
	cat $(top_distdir)/include/version.h|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > $(top_distdir)/include/version.h.new; \
	mv $(top_distdir)/include/version.h.new $(top_distdir)/include/version.h; \
	cat $(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > \
		$(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java.new; \
	mv $(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java.new \
		$(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java

	rm -rf `find $(top_distdir) -name ".svn"`
	rm -f $(top_distdir)/create/data/data_test.sql
	rm -f $(top_distdir)/include/config.h
	rm -f $(top_distdir)/frontends/php/conf/zabbix.conf.php
	rm -rf $(top_distdir)/frontends/php/tests

dbschema_ibm_db2:
	perl create/bin/gen_data.pl ibm_db2 > database/ibm_db2/data.sql
	perl create/bin/gen_schema.pl ibm_db2 > database/ibm_db2/schema.sql

dbschema_mysql:
	perl create/bin/gen_data.pl mysql > database/mysql/data.sql
	perl create/bin/gen_schema.pl mysql > database/mysql/schema.sql

dbschema_oracle:
	perl create/bin/gen_data.pl oracle > database/oracle/data.sql
	perl create/bin/gen_schema.pl oracle > database/oracle/schema.sql

dbschema_postgresql:
	perl create/bin/gen_data.pl postgresql > database/postgresql/data.sql
	perl create/bin/gen_schema.pl postgresql > database/postgresql/schema.sql

dbschema_sqlite3:
	perl create/bin/gen_data.pl sqlite3 > database/sqlite3/data.sql
	perl create/bin/gen_schema.pl sqlite3 > database/sqlite3/schema.sql

dbschema_c:
	perl create/bin/gen_schema.pl c > src/libs/zbxdbhigh/dbschema.c

dbschema: dbschema_ibm_db2 dbschema_mysql dbschema_oracle dbschema_postgresql dbschema_sqlite3 dbschema_c

gettext:
	frontends/php/locale/make_mo.sh

css_theme_blue:
	sass --no-cache --sourcemap=none --style=compressed sass/stylesheets/sass/screen.scss frontends/php/styles/blue-theme.css
	cp frontends/php/styles/blue-theme.css sass/stylesheets/screen.css

css_theme_dark:
	sass --no-cache --sourcemap=none --style=compressed sass/stylesheets/sass/dark-theme.scss frontends/php/styles/dark-theme.css
	cp frontends/php/styles/dark-theme.css sass/stylesheets/dark-theme.css

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

css: css_theme_blue css_theme_dark css_icons
