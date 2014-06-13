A lot of Zabbix components are highly integrated and hard to separate for unit
tests. Because of that the idea is to embedd unit tests in Zabbix binaries 
(server, proxy, agent) with a help of prepare script.

The embedding is done by including unit tests at the end of corresponding
source files and redefining the daemon entry functions with a test runner 
functions.

The unit tests are embedded in three target binaries - zabbix_server, 
zabbix_proxy and zabbix_agentd.

The unit test sources are kept in target specific directory (zabbix_server, 
zabbix_proxy, zabbix_agentd) and have the same directory structure as Zabbix
source files. 

When preparing Zabbix sources for unit tests the prepare script does the 
following:
 * scan the directories for unit tests
 * append/insert unit test source includes in corresponding Zabbix source files
 * copy the test runner sources template file from test directory to target
   source directory
 * generate test initialization and runner functions and append them to the 
   copied source templates
 * set LIBS/CFLAGS variables and run ./configure

Directory structure:
  ./zbxcunit.h               // the common include file for unit tests
  ./zabbix_server_cu.c       // server test runner template
  ./zabbix_proxy_cu.c        // proxy test runner template  
  ./zabbix_agent_cu.c        // server test  runner template

  ./zabbix_server/libs/zbxcomms/comms.c         // server comms unit tests
  ./zabbix_server/libs/zbxdbcache/valuecache.c  // server value cache unit tests

  ./zabbix_proxy/libs/zbxcomms/comms.c          // proxy comms unit tests

  ./zabbix_agent/zabbix_agent/logfiles.c        // agent log file unit tests

The test examples are based on CUnit, but any other C unit testing framework
could be used instead.


The prepare script forwards all command line parameters to configure except
the internal options:
  --revert          - revert the changes to the Zabbix source. Must be done 
                      before commiting any changes to svn.
  --skip-configure  - prepare the sources, but don't invoke configure
  --testsrc=<path>  - set unit test source directory
  --report=<path>   - set report output directory for automated tests
  --mode=<mode>     - basic - run tests and generate output in console
                    - automated - run tests and generate report files

