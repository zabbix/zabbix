## Process this file with automake to produce Makefile.in

EXTRA_DIST = .

BUILD_TIME=`date +%H:%M:%S`
BUILD_DATE=`date +"%b %_d %Y"`
GOOS=`go env GOOS`
GOARCH=`go env GOARCH`
PKG=zabbix.com/pkg/version

GOLDFLAGS = -X '${PKG}.compileDate=${BUILD_DATE}'
GOLDFLAGS += -X ${PKG}.compileTime=${BUILD_TIME}
GOLDFLAGS += -X ${PKG}.compileOs=${GOOS}
GOLDFLAGS += -X ${PKG}.compileArch=${GOARCH}

AGENT_GOLDFLAGS = ${GOLDFLAGS}
AGENT_GOLDFLAGS += -X main.confDefault=${AGENT2_CONFIG_FILE}
AGENT_GOLDFLAGS += -X main.applicationName=zabbix_agent2

WEBSERVICE_GOLDFLAGS = ${GOLDFLAGS}
WEBSERVICE_GOLDFLAGS += -X main.applicationName=zabbix_web_service

dist_sysconf_DATA =
TARGETS =
INSTALL_TARGETS =

if AGENT2
TARGETS += zabbix.com/cmd/zabbix_agent2
INSTALL_TARGETS += install-zabbix.com/cmd/zabbix_agent2
DBGTARGETS = zabbix.com/cmd/mock_server
dist_sysconf_DATA += conf/zabbix_agent2.conf
endif

if WEBSERVICE
TARGETS += zabbix.com/cmd/zabbix_web_service
INSTALL_TARGETS += install-zabbix.com/cmd/zabbix_web_service
dist_sysconf_DATA += conf/zabbix_web_service.conf
endif

PLUGIN_CFG_POSTGRES = postgres.conf
PLUGIN_CFG_ORACLE = oracle.conf
PLUGIN_CFG_CEPH = ceph.conf
PLUGIN_CFG_MYSQL = mysql.conf
PLUGIN_CFG_MEMCACHED = memcached.conf
PLUGIN_CFG_MONGODB = mongodb.conf
PLUGIN_CFG_REDIS = redis.conf
PLUGIN_CFG_SMART = smart.conf
PLUGIN_CFG_DOCKER = docker.conf

all: build

zabbix.com/cmd/zabbix_agent2:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go build -ldflags="${AGENT_GOLDFLAGS}" -o bin zabbix.com/cmd/zabbix_agent2

zabbix.com/cmd/zabbix_web_service:
	go build -ldflags="${WEBSERVICE_GOLDFLAGS}" -o bin zabbix.com/cmd/zabbix_web_service

build: ${TARGETS}

clean:
	go clean ./...
	rm -f bin/zabbix_agent2 bin/mock_server

install-zabbix.com/cmd/zabbix_agent2:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" GOBIN=${GOBIN} \
		go install -ldflags="${AGENT_GOLDFLAGS}" ${TARGETS}
	$(MKDIR_P) "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@"
	test -f "$(DESTDIR)@AGENT2_CONFIG_FILE@" || cp "conf/zabbix_agent2.conf" "$(DESTDIR)@AGENT2_CONFIG_FILE@"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_POSTGRES)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_POSTGRES)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_POSTGRES)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_ORACLE)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_ORACLE)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_ORACLE)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_CEPH)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_CEPH)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_CEPH)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_MYSQL)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_MYSQL)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_MYSQL)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_MEMCACHED)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_MEMCACHED)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_MEMCACHED)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_MONGODB)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_MONGODB)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_MONGODB)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_REDIS)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_REDIS)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_REDIS)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_SMART)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_SMART)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_SMART)"
	test -f "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_DOCKER)" || cp "conf/zabbix_agent2.d/plugins.d/$(PLUGIN_CFG_DOCKER)" "$(DESTDIR)@AGENT2_CONFIG_PLUGINS_PATH@/$(PLUGIN_CFG_DOCKER)"

install-zabbix.com/cmd/zabbix_web_service:
	GOBIN=${GOBIN} go install -ldflags="${WEBSERVICE_GOLDFLAGS}" zabbix.com/cmd/zabbix_web_service

install-exec-local: ${INSTALL_TARGETS}

check:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go test ./...

