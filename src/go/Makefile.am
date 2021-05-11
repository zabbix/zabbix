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

install-zabbix.com/cmd/zabbix_web_service:
	GOBIN=${GOBIN} go install -ldflags="${WEBSERVICE_GOLDFLAGS}" zabbix.com/cmd/zabbix_web_service

install-exec-local: ${INSTALL_TARGETS}

check:
	CGO_CFLAGS="${CGO_CFLAGS}" CGO_LDFLAGS="${CGO_LDFLAGS}" go test ./...

