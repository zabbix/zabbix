## Process this file with automake to produce Makefile.in

SUBDIRS = \
	scheduler \
	remotecontrol \
	resultcache \
	serverlistener \
	serverconnector

clean:
	go clean

check:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go test

