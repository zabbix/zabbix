# ====== DEFINE GROUPS ONCE ======

agent_DEPS = \
        $(ZBX_COMMON_LIBS) \
        $(ZBX_AGENT_LIBS)

server_DEPS = \
        $(ZBX_COMMON_LIBS) \
        $(ZBX_SERVER_RPOXY_LIBS) \
        $(ZBX_SERVER_LIBS) \
        $(ZBX_IPMI_LIB)

proxy_DEPS = \
        $(ZBX_COMMON_LIBS) \
        $(ZBX_SERVER_RPOXY_LIBS) \
        $(ZBX_PROXY_LIBS) \
        $(ZBX_IPMI_LIB)

GROUPED_LIBS_MK = grouped-libs-generated.mk

$(GROUPED_LIBS_MK): Makefile
	@echo "AGENT_GROUPED_LIBS := -Wl,--start-group $(agent_DEPS) -Wl,--end-group" > $@
	@echo "SERVER_GROUPED_LIBS := -Wl,--start-group $(server_DEPS) -Wl,--end-group" >> $@
	@echo "PROXY_GROUPED_LIBS := -Wl,--start-group $(proxy_DEPS) -Wl,--end-group" >> $@

# include generated variables
-include $(GROUPED_LIBS_MK)
