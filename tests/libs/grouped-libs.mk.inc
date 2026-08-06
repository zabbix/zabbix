# This file exists to safely use linker grouping flags:
#     -Wl,--start-group ... -Wl,--end-group
#
# Problem:
# Automake does not allow linker flags like --start-group/--end-group
# inside *_LDADD variables and will fail during automake processing if
# they are present directly in Makefile.am or included .am/.include files.
#
# Why grouping is needed:
# Zabbix static libraries have many cyclic dependencies. Without
# --start-group/--end-group, the linker may fail with undefined references
# depending on library order.
#
# Solution:
# We generate the grouped linker flags at *make runtime* using:
#     @echo "... --start-group $(LIBS) --end-group" > generated.mk
#
# This works because:
# - Automake never sees the flags (they are generated later)
# - The linker still receives properly grouped libraries

agent_DEPS = \
	$(ZBX_COMMON_LIBS) \
	$(ZBX_AGENT_LIBS)

server_DEPS = \
	$(ZBX_COMMON_LIBS) \
	$(ZBX_SERVER_PROXY_LIBS) \
	$(ZBX_SERVER_LIBS) \
	$(ZBX_IPMI_LIB)

proxy_DEPS = \
	$(ZBX_COMMON_LIBS) \
	$(ZBX_SERVER_PROXY_LIBS) \
	$(ZBX_PROXY_LIBS) \
	$(ZBX_IPMI_LIB)

GROUPED_LIBS_MK = grouped-libs-generated.mk
CLEANFILES = $(GROUPED_LIBS_MK)

$(GROUPED_LIBS_MK): Makefile
	@echo "AGENT_GROUPED_LIBS := -Wl,--start-group $(agent_DEPS) -Wl,--end-group" > $@
	@echo "SERVER_GROUPED_LIBS := -Wl,--start-group $(server_DEPS) -Wl,--end-group" >> $@
	@echo "PROXY_GROUPED_LIBS := -Wl,--start-group $(proxy_DEPS) -Wl,--end-group" >> $@

-include $(GROUPED_LIBS_MK)
