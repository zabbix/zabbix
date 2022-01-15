## Process this file with automake to produce Makefile.in

ACLOCAL_AMFLAGS = -I m4

SUBDIRS = \
	src \
	database \
	man \
	misc

EXTRA_DIST = \
	bin \
	build \
	ui \
	include \
	conf \
	sass

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
# File revision number must be numeric (Git commit hash cannot be used).
# Therefore to make it numeric and meaningful it is artificially composed from:
#    - branch (development or release),
#    - type (alpha, beta, rc or release),
#    - number of alpha, beta or rc.
# 'branch' expression tries to find out is it a development branch or release branch.
#      Result is encoded as: 1 - dev branch, release branch or error occurred, 2 - tag.
# 'type_name' expression tries to find out what type of release it is.
#      Expected result is: "alpha", "beta", "rc" or "" (empty string).
# 'type_num' expression encodes 'type_name' as numeric value:
#      1 - alpha, 2 - beta, 3 - rc, 4 - release, 0 - unknown.
# 'type_count' expression tries to find out number of "alpha", "beta" or "rc" (e.g. 1 from "rc1").
	@branch=`(git symbolic-ref -q HEAD > /dev/null && echo 1) || (git tag -l --points-at HEAD| grep "."| grep -q -v "-" && echo 2) || echo 1`; \
	type_name=`cat $(top_distdir)/include/version.h| sed -n -e '/ZABBIX_VERSION_RC/s/.*"\([a-z]*\)[0-9]*"/\1/p'`; \
	type_num=`(test "x$$type_name" = "xalpha" && echo "1") || echo ""`; \
	type_num=`(test -z $$type_num && test "x$$type_name" = "xbeta" && echo "2") || echo "$$type_num"`; \
	type_num=`(test -z $$type_num && test "x$$type_name" = "xrc" && echo "3") || echo "$$type_num"`; \
	type_num=`(test -z $$type_num && test -z $$type_name && echo "4") || echo "$$type_num"`; \
	type_num=`(test -z $$type_num && echo "0") || echo "$$type_num"`; \
	type_count=`cat $(top_distdir)/include/version.h|sed -n -e '/ZABBIX_VERSION_RC/s/.*"[a-z]*\([0-9]*\)"/\1/p'`; \
	type_count=`printf '%02d' $$type_count`; \
	zabbix_revision=`git rev-parse --short HEAD`; \
	cat $(top_distdir)/include/version.h|sed "s/{ZABBIX_RC_NUM}/$$branch$$type_num$$type_count/g"| \
	sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > $(top_distdir)/include/version.h.new; \
	mv $(top_distdir)/include/version.h.new $(top_distdir)/include/version.h; \
	cat $(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > \
		$(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java.new; \
	mv $(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java.new \
		$(top_distdir)/src/zabbix_java/src/com/zabbix/gateway/GeneralInformation.java; \
	cat $(top_distdir)/src/go/pkg/version/version.go|sed "s/{ZABBIX_RC_NUM}/$$branch$$type_num$$type_count/g"| \
	sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" > $(top_distdir)/src/go/pkg/version/version.go.new; \
	mv $(top_distdir)/src/go/pkg/version/version.go.new $(top_distdir)/src/go/pkg/version/version.go

	rm -rf `find $(top_distdir) -name ".gitignore" -o -name ".gitattributes"`
	rm -f $(top_distdir)/include/config.h
	rm -f $(top_distdir)/include/config.h.in~
	rm -f $(top_distdir)/ui/conf/zabbix.conf.php
	rm -rf $(top_distdir)/ui/tests
	rm -rf $(top_distdir)/sass/img_source

if DBSCHEMA
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
	$(MAKE) $(AM_MAKEFLAGS) schema.sql && \
	$(MAKE) $(AM_MAKEFLAGS) timescaledb.sql

dbschema_sqlite3:
	cd database/sqlite3 && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_c:
	cd src/libs/zbxdbhigh && \
	$(MAKE) $(AM_MAKEFLAGS) dbschema.c

dbschema: dbschema_mysql dbschema_oracle dbschema_postgresql dbschema_sqlite3 dbschema_c
endif

gettext:
	ui/locale/make_mo.sh

css_theme_blue:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/screen.scss ui/assets/styles/blue-theme.css
	cp ui/assets/styles/blue-theme.css sass/stylesheets/screen.css

css_theme_dark:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/dark-theme.scss ui/assets/styles/dark-theme.css
	cp ui/assets/styles/dark-theme.css sass/stylesheets/dark-theme.css

css_hc_light:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/hc-light.scss ui/assets/styles/hc-light.css
	cp ui/assets/styles/hc-light.css sass/stylesheets/hc-light.css

css_hc_dark:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/hc-dark.scss ui/assets/styles/hc-dark.css
	cp ui/assets/styles/hc-dark.css sass/stylesheets/hc-dark.css

css_icons:
	cp sass/img/icon-sprite.svg ui/assets/img/
	cp sass/img/browser-sprite.png ui/assets/img/
	cp sass/apple-touch-icon-120x120-precomposed.png ui/assets/img/
	cp sass/apple-touch-icon-152x152-precomposed.png ui/assets/img/
	cp sass/apple-touch-icon-180x180-precomposed.png ui/assets/img/
	cp sass/apple-touch-icon-76x76-precomposed.png ui/assets/img/
	cp sass/ms-tile-144x144.png ui/assets/img/
	cp sass/touch-icon-192x192.png ui/assets/img/
	cp sass/favicon.ico ui/

css: css_theme_blue css_theme_dark css_hc_light css_hc_dark css_icons

## cmocka test support
if ZBXCMOCKA
WRAP_DB_FUNCS = \
	-Wl,--wrap=zbx_db_vselect \
	-Wl,--wrap=zbx_db_select_n \
	-Wl,--wrap=zbx_db_fetch \
	-Wl,--wrap=__zbx_DBexecute \
	-Wl,--wrap=DBbegin \
	-Wl,--wrap=DBcommit \
	-Wl,--wrap=DBexecute_multiple_query \
	-Wl,--wrap=DBfree_result

WRAP_IO_FUNCS = \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=fgets \
	-Wl,--wrap=open

WRAP_FS_FUNCS = \
	-Wl,--wrap=opendir \
	-Wl,--wrap=readdir \
	-Wl,--wrap=__xstat \
	-Wl,--wrap=__fxstat \
	-Wl,--wrap=stat

WRAP_EXIT = \
	-Wl,--wrap=exit

WRAP_COMM_FUNCS = \
	-Wl,--wrap=read \
	-Wl,--wrap=connect

WRAP_LOG = \
	-Wl,--wrap=__zbx_zabbix_log

COMMON_WRAP_FUNCS = \
	$(WRAP_DB_FUNCS) \
	$(WRAP_IO_FUNCS) \
	$(WRAP_FS_FUNCS) \
	$(WRAP_EXIT) \
	$(WRAP_COMM_FUNCS) \
	$(WRAP_LOG)

tests_build:
	$(MAKE) $(AM_MAKEFLAGS) && \
	cd tests && \
	$(MAKE) $(AM_MAKEFLAGS) LDFLAGS="$(LDFLAGS) $(COMMON_WRAP_FUNCS)" LIBS="$(LIBS) -lcmocka -lyaml"

tests: tests_build
	tests/tests_run.pl

clean: clean-recursive
	cd tests && $(MAKE) clean
endif

.PHONY: test tests clean
