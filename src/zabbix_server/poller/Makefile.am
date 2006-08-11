SUBDIRS=

if WITH_ODBC
ODBC_INC = @ODBC_INCLUDE@
ODBC_LIB = @ODBC_LIBS@
ODBC_LDF = @ODBC_LDFLAGS@
else
ODBC_INC = 
ODBC_LIB = 
ODBC_LDF = 
endif

INCLUDES=-I@top_srcdir@/include @MYSQL_INCLUDE@ @ORACLE_INCLUDE@ @SNMP_INCLUDE@ $(ODBC_INC)

lib_LIBRARIES=libzbxpoller.a
libzbxpoller_a_SOURCES=checks_agent.c checks_internal.c checks_db.c checks_simple.c checks_snmp.c checks_aggregate.c poller.c
