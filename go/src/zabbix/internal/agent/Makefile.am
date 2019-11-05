## Process this file with automake to produce Makefile.in

SUBDIRS = \
	remotecontrol \
	resultcache \
	scheduler \
	serverlistener \
	serverconnector \
	statuslistener

clean:
	go clean

check:
	CGO_LDFLAGS="${CGO_LDFLAGS}" GOPATH="${GOPATH}" go test
