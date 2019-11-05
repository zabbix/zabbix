## Process this file with automake to produce Makefile.in

clean:
	CGO_LDFLAGS="${CGO_LDFLAGS}" go clean

check:
	CGO_LDFLAGS="${CGO_LDFLAGS}" GOPATH="${GOPATH}" go test
