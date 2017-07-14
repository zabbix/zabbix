## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	libs \
	zabbix_agent \
	zabbix_get \
	zabbix_sender \
	zabbix_proxy \
	zabbix_server \
	zabbix_java

if AGENT
COMMON_SUBDIRS = libs
else
if SERVER
COMMON_SUBDIRS = libs
else
if PROXY
COMMON_SUBDIRS = libs
endif
endif
endif

if AGENT
AGENT_SUBDIRS = \
	zabbix_agent \
	zabbix_get \
	zabbix_sender
endif

if SERVER
SERVER_SUBDIRS = \
	zabbix_server
endif

if PROXY
if SERVER
PROXY_SUBDIRS = \
	zabbix_proxy
else

PROXY_SUBDIRS = \
	zabbix_server/dbsyncer \
	zabbix_server/dbconfig \
	zabbix_server/discoverer \
	zabbix_server/httppoller \
	zabbix_server/pinger \
	zabbix_server/poller \
	zabbix_server/trapper \
	zabbix_server/selfmon \
	zabbix_server/snmptrapper \
	zabbix_server/vmware \
	zabbix_server/ipmi \
	zabbix_server/scripts \
	zabbix_proxy
	
endif
endif

if JAVA
JAVA_SUBDIRS = \
	zabbix_java
endif

SUBDIRS = \
	$(COMMON_SUBDIRS) \
	$(AGENT_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(PROXY_SUBDIRS) \
	$(JAVA_SUBDIRS)

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	mkdir $(distdir)/modules
	mkdir $(distdir)/modules/dummy
	cp $(srcdir)/modules/dummy/*.c $(distdir)/modules/dummy/
	cp $(srcdir)/modules/dummy/Makefile $(distdir)/modules/dummy/
	cp $(srcdir)/modules/dummy/README $(distdir)/modules/dummy/

## CUnit test support
TEST_COMMENT = "embedded unit tests - remove before commit"
TEST_LIBDIR = libs/zbxcunit
TEST_SUITESFILE = $(TEST_LIBDIR)/cu_modules.h
TEST_SRCFILES = `grep $(TEST_COMMENT) * -rl | grep "\.c$$"`
TEST_TESTFILES = `find -name *_test.c`
TEST_DECLARE = "ZBX_CU_DECLARE" 

$(TEST_SUITESFILE):
	echo 'const char *cu_modules[]={' > $(TEST_SUITESFILE)
	for file in $(TEST_TESTFILES); do								\
		if [ -f "$${file%_test.c}.c" ]; then							\
			echo "#include \"$${file##*/}\" //$(TEST_COMMENT)" >> "$${file%_test.c}.c"	;\
			grep $(TEST_DECLARE) $${file} | while read line; do				\
				funcname=$${line##*(}							;\
				echo "\"$${funcname%%)*}\"," >> $(TEST_SUITESFILE)			;\
			done										;\
		fi											;\
	done
	echo 'NULL};' >> $(TEST_SUITESFILE)

test: $(TEST_SUITESFILE) 
	$(MAKE) $(AM_MAKEFLAGS) CFLAGS="$(CFLAGS) -rdynamic -DZBX_CUNIT" CUNIT_LDADD="-lcunit"

clean-test:
	rm -rf $(TEST_SUITESFILE)
	for file in $(TEST_SRCFILES); do		\
		sed -i /$(TEST_COMMENT)/d $$file;	\
	done
 
clean-local: clean-test

.PHONY: test clean-test

