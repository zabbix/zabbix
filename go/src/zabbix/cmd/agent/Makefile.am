## Process this file with automake to produce Makefile.in
BUILD_TIME=`date +%H:%M:%S`
BUILD_DATE=`date +"%b %_d %Y"`
PKG=zabbix/pkg/version

all: build

build:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go build -ldflags="-X ${PKG}.titleMessage=zabbix_agentd -X '${PKG}.compileDate=${BUILD_DATE}' -X ${PKG}.compileTime=${BUILD_TIME} -X ${PKG}.compileOs=${ARCH} -X ${PKG}.compileArch=${build_cpu} -X ${PKG}.compileMode=daemon"

clean:
	go clean

install:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go install -ldflags="-X ${PKG}.titleMessage=zabbix_agentd -X '${PKG}.compileDate=${BUILD_DATE}' -X ${PKG}.compileTime=${BUILD_TIME} -X ${PKG}.compileOs=${ARCH} -X ${PKG}.compileArch=${build_cpu} -X ${PKG}.compileMode=daemon"

check:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go test

