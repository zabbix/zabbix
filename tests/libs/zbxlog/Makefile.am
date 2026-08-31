include ../Makefile.include

if SERVER
noinst_PROGRAMS = \
	zbx_log_dispatch_formatted

COMMON_SRC = \
	../../zbxmocktest.h

COMMON_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

ZBX_LOG_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_log_dispatch_formatted_SOURCES = \
	zbx_log_dispatch_formatted.c \
	$(COMMON_SRC)

zbx_log_dispatch_formatted_LDADD = $(ZBX_LOG_LIBS)


zbx_log_dispatch_formatted_LDADD += @SERVER_LIBS@

zbx_log_dispatch_formatted_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=close \
	-Wl,--wrap=exit \
	-Wl,--wrap=fclose \
	-Wl,--wrap=opendir \
	-Wl,--wrap=fgets \
	-Wl,--wrap=__xstat \
	-Wl,--wrap=__fxstat \
	-Wl,--wrap=read \
	-Wl,--wrap=connect \
	-Wl,--wrap=poll \
	-Wl,--wrap=readdir \
	-Wl,--wrap=open \
	-Wl,--wrap=fopen \
	-Wl,--wrap=stat \
	-Wl,--wrap=lseek \
	-Wl,--wrap=fstat \
	-Wl,--wrap=zbx_error

zbx_log_dispatch_formatted_CFLAGS = $(COMMON_FLAGS)

endif
