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
TARGETDIR = zabbix_server
TESTSOURCE = $(TARGETDIR)/zabbix_server_test_impl.c
TESTENTRY = $(TARGETDIR)/server.c
REPORTPATH = ${CURDIR}/$(TARGETDIR)/reports
SRCFILES = `grep $(TEST_COMMENT) * -rl | grep "\.c$$"`
TESTFILES = `find -name *_test.c`

$(TESTSOURCE):
	mkdir $(TARGETDIR)/reports
	echo "#include \"zbxcunit.h\"" >> $(TESTSOURCE)
	echo 'void run_cu_tests(){' >> $(TESTSOURCE)
	echo 'CU_set_output_filename("$(REPORTPATH)/$(TARGETDIR)");' >> $(TESTSOURCE)
	echo 'CU_automated_run_tests();' >> $(TESTSOURCE)
	echo 'CU_list_tests_to_file();}' >> $(TESTSOURCE)
	echo 'void test_entry_cu(const char *suites) {' >> $(TESTSOURCE)
	echo 'int run_all = 0, suite_count = 0;' >> $(TESTSOURCE)
	echo 'printf("suites: %s\n", suites);' >> $(TESTSOURCE)
	echo 'if (0 == strcmp(suites, "all")) run_all = 1;' >> $(TESTSOURCE)
	echo 'if (CUE_SUCCESS != CU_initialize_registry()){' >> $(TESTSOURCE)
	echo 'fprintf(stderr, "Error while initializing CUnit registry: %s\n", (char *)CU_get_error_msg);' >> $(TESTSOURCE)
	echo 'goto out;}' >> $(TESTSOURCE)
	for file in $(TESTFILES); do								\
		echo "#include \"$${file##*/}\" //$(TEST_COMMENT)" >> "$${file%_test.c}.c"	;\
		funcname=$${file##*/} 								;\
		echo "if (1 == run_all || NULL != strstr(suites, \"$${funcname%.c}\")) {zbx_cu_init_$${funcname%.c}(); suite_count++;}" >> $(TESTSOURCE) 	;\
	done
	echo 'if (0 == suite_count) {printf("No matching test suite.\n"); goto out;}' >> $(TESTSOURCE)
	echo 'run_cu_tests();' >> $(TESTSOURCE)
	echo 'printf("CUnit tests completed.\n");' >> $(TESTSOURCE)
	echo 'out:' >> $(TESTSOURCE)
	echo 'CU_cleanup_registry();' >> $(TESTSOURCE)
	echo 'CU_get_error();' >> $(TESTSOURCE)
	echo 'exit(0);}' >> $(TESTSOURCE)
	
	echo "#include \"$(notdir $(TESTSOURCE))\" //$(TEST_COMMENT)" >> "$(TESTENTRY)"

test: $(TESTSOURCE) 
	$(MAKE) $(AM_MAKEFLAGS) TESTFLAGS=-DTEST_ENTRY_CU=test_entry_cu

clean-test:
	rm -rf $(TESTSOURCE)
	rm -rf $(TARGETDIR)/reports
	for file in $(SRCFILES); do			\
		sed -i /$(TEST_COMMENT)/d $$file;	\
	done
 
clean-local: clean-test


.PHONY: test clean-test prepare-test
