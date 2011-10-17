## Process this file with automake to produce Makefile.in

EXTRA_DIST = \
	bin \
	lib \
	src \
	Makefile.am \
	settings.sh \
	shutdown.sh \
	startup.sh

ZJP = bin/zabbix-java-proxy-$(VERSION).jar
LIB = lib/org-json-2010-12-28.jar:lib/logback-core-0.9.27.jar:lib/logback-classic-0.9.27.jar:lib/slf4j-api-1.6.1.jar
JUNIT = tests/junit-4.8.2.jar

ZJP_DEST = $(DESTDIR)$(sbindir)/zabbix_java

all: $(ZJP)

$(ZJP): class src/com/zabbix/proxy/*.java
	$(JAVAC) -d class/src -classpath $(LIB) src/com/zabbix/proxy/*.java
	$(JAR) cf $(ZJP) -C class/src .

test: class
	$(JAVAC) -d class/tests -classpath class/src:$(JUNIT) tests/com/zabbix/proxy/*.java
	java -classpath class/tests:$(LIB):$(ZJP):$(JUNIT) com.zabbix.proxy.AllTestRunner

class:
	$(MKDIR_P) class/src
	$(MKDIR_P) class/tests

install: all
	$(MKDIR_P) "$(ZJP_DEST)/bin"
	$(MKDIR_P) "$(ZJP_DEST)/lib"
	cp startup.sh shutdown.sh "$(ZJP_DEST)"
	if test ! -f "$(ZJP_DEST)/settings.sh"; then cp settings.sh "$(ZJP_DEST)"; fi
	cp $(ZJP) "$(ZJP_DEST)/bin"
	cp lib/*.jar "$(ZJP_DEST)/lib"
	if test ! -f "$(ZJP_DEST)/lib/logback.xml"; then cp lib/logback.xml "$(ZJP_DEST)/lib"; fi
	if test ! -f "$(ZJP_DEST)/lib/logback-console.xml"; then cp lib/logback-console.xml "$(ZJP_DEST)/lib"; fi

clean:
	rm -f $(ZJP)
	rm -rf class/src/*
	rm -rf class/tests/*
