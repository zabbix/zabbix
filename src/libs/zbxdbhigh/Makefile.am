SUBDIRS=
lib_LIBRARIES=libzbxdbhigh.a

if WITH_ODBC
ZBX_ODBC_SRC = odbc.c
ZBX_ODBC_INC = @ODBC_INCLUDE@
else
ZBX_ODBC_SRC = 
ZBX_ODBC_INC = 
endif

INCLUDES=-I@top_srcdir@/include @MYSQL_INCLUDE@ @PGSQL_INCLUDE@ @ORACLE_INCLUDE@ $(ZBX_ODBC_INC)

libzbxdbhigh_a_SOURCES=action.c graph.c item.c trigger.c host.c db.c $(ZBX_ODBC_SRC)
