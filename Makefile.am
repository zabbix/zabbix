## Process this file with automake to produce Makefile.in

ACLOCAL_AMFLAGS = -I m4

SUBDIRS = \
	include \
	src \
	database \
	man \
	misc

EXTRA_DIST = \
	README.md \
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
	rm -f $(top_distdir)/include/common/config.h
	rm -f $(top_distdir)/include/common/config.h.in~
	rm -f $(top_distdir)/ui/conf/zabbix.conf.php
	rm -rf $(top_distdir)/ui/tests
	rm -rf $(top_distdir)/sass/img_source

if DBSCHEMA
dbschema_mysql:
	cd database/mysql && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql && \
	$(MAKE) $(AM_MAKEFLAGS) -C option-patches

dbschema_postgresql:
	cd database/postgresql && \
	$(MAKE) $(AM_MAKEFLAGS) data.sql && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql && \
	$(MAKE) $(AM_MAKEFLAGS) -C option-patches

dbschema_timescaledb:
	cd database/postgresql/timescaledb && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql && \
	$(MAKE) $(AM_MAKEFLAGS) -C option-patches

dbschema_sqlite3:
	cd database/sqlite3 && \
	$(MAKE) $(AM_MAKEFLAGS) schema.sql

dbschema_c:
	cd src/libs/zbxdbschema && \
	$(MAKE) $(AM_MAKEFLAGS) dbschema.c

dbschema: dbschema_mysql dbschema_postgresql dbschema_timescaledb dbschema_sqlite3 dbschema_c
endif

gettext:
	ui/locale/make_mo.sh

css_theme_blue:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/blue-theme.scss ui/assets/styles/blue-theme.css

css_theme_blue_classic:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/blue-classic-theme.scss ui/assets/styles/blue-classic-theme.css

css_theme_dark:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/dark-theme.scss ui/assets/styles/dark-theme.css

css_theme_dark_classic:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/dark-classic-theme.scss ui/assets/styles/dark-classic-theme.css

css_hc_light:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/hc-light.scss ui/assets/styles/hc-light.css

css_hc_dark:
	sass --no-cache --sourcemap=none sass/stylesheets/sass/hc-dark.scss ui/assets/styles/hc-dark.css

css_images:
	cp sass/img/browser-sprite.png ui/assets/img/
	cp sass/apple-touch-icon-120x120-precomposed.png ui/assets/img/
	cp sass/apple-touch-icon-152x152-precomposed.png ui/assets/img/
	cp sass/apple-touch-icon-180x180-precomposed.png ui/assets/img/
	cp sass/apple-touch-icon-76x76-precomposed.png ui/assets/img/
	cp sass/ms-tile-144x144.png ui/assets/img/
	cp sass/touch-icon-192x192.png ui/assets/img/
	cp sass/favicon.ico ui/

icons_build:
	cd sass/icons && \
	npm run generate
	cp sass/icons/dist/_icons.scss sass/stylesheets/sass/base/
	cp sass/icons/dist/zabbix-icons.svg ui/assets/fonts/
	cp sass/icons/dist/zabbix-icons.ttf ui/assets/fonts/
	cp sass/icons/dist/zabbix-icons.woff ui/assets/fonts/
	cp sass/icons/dist/zabbix-icons.woff2 ui/assets/fonts/

icons_cleanup:
	cd sass/icons && \
	npm run cleanup

css: css_theme_blue css_theme_blue_classic css_theme_dark css_theme_dark_classic css_hc_light css_hc_dark css_images

icons: icons_build css icons_cleanup


## list of modules which should be built with testing suite
MODULES = \
	dummy

modules_build:
	-@for module in $(MODULES); do \
		$(MAKE) -C src/modules/$$module; \
	done

modules_clean:
	-@for module in $(MODULES); do \
		$(MAKE) -C src/modules/$$module; \
	done

## cmocka test support
if TESTS
WRAP_DB_FUNCS = \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_vselect \
	-Wl,--wrap=zbx_db_select_n \
	-Wl,--wrap=zbx_db_execute \
	-Wl,--wrap=zbx_db_begin \
	-Wl,--wrap=zbx_db_commit \
	-Wl,--wrap=zbx_db_execute_multiple_query

WRAP_IO_FUNCS = \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=fgets \
	-Wl,--wrap=fstat \
	-Wl,--wrap=open \
	-Wl,--wrap=lseek \
	-Wl,--wrap=close

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
	-Wl,--wrap=connect \
	-Wl,--wrap=poll

COMMON_WRAP_FUNCS = \
	$(WRAP_DB_FUNCS) \
	$(WRAP_IO_FUNCS) \
	$(WRAP_FS_FUNCS) \
	$(WRAP_EXIT) \
	$(WRAP_COMM_FUNCS)

tests_build: modules_build
	$(MAKE) $(AM_MAKEFLAGS) && \
	cd tests && \
	$(MAKE) $(AM_MAKEFLAGS) LDFLAGS="$(LDFLAGS) $(COMMON_WRAP_FUNCS)" LIBS="$(LIBS)"

tests: tests_build
	export LD_LIBRARY_PATH=$$LD_LIBRARY_PATH:$(CMOCKA_LIBRARY_PATH):$(YAML_LIBRARY_PATH); \
	tests/tests_run.pl

clean: clean-recursive modules_clean build_test_zbxcommon_clean
	cd tests && $(MAKE) clean
endif

build_test_zbxcommon:
	$(MAKE) $(AM_MAKEFLAGS) && \
	cd tests/test_zbxcommon && \
	$(MAKE) $(AM_MAKEFLAGS)

build_test_zbxcommon_clean:
	cd tests/test_zbxcommon && $(MAKE) clean

sbom: sbom-ui

sbom-ui: ui/sbom.json

ui/sbom.json:
	(cd ${@D} && \
		composer CycloneDX:make-sbom --omit=dev --no-interaction \
		--spec-version=1.4 \
		--output-reproducible --output-format=JSON --output-file="${@F}")

ui/sbom.xml:
	(cd ${@D} && \
		composer CycloneDX:make-sbom --omit=dev --no-interaction \
		--spec-version=1.4 \
		--output-reproducible --output-format=XML --output-file="${@F}")

.PHONY: test tests clean modules_build modules_clean sbom sbom-ui
