## Process this file with automake to produce Makefile.in

export GOPATH := $(GOPATH):${CURDIR}/../..

all:
	go build -o bin/agent cmd/agent/agent.go

install: all
