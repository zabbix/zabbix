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

all: class
	$(JAVAC) -d class/src -classpath $(LIB) src/com/zabbix/proxy/*.java
	$(JAR) cf $(ZJP) -C class/src .

test: class
	$(JAVAC) -d class/tests -classpath class/src:$(JUNIT) tests/com/zabbix/proxy/*.java
	java -classpath class/tests:$(LIB):$(ZJP):$(JUNIT) com.zabbix.proxy.AllTestRunner

class:
	mkdir -p class/src
	mkdir -p class/tests

install: all
	mkdir -p $(sbindir)/zabbix_java/bin
	mkdir -p $(sbindir)/zabbix_java/lib
	cp startup.sh shutdown.sh $(sbindir)/zabbix_java
	if test ! -f $(sbindir)/zabbix_java/settings.sh; then cp settings.sh $(sbindir)/zabbix_java; fi
	cp $(ZJP) $(sbindir)/zabbix_java/bin
	cp lib/*.jar $(sbindir)/zabbix_java/lib
	if test ! -f $(sbindir)/zabbix_java/lib/logback.xml; then cp lib/logback.xml $(sbindir)/zabbix_java/lib; fi
	if test ! -f $(sbindir)/zabbix_java/lib/logback-console.xml; then cp lib/logback-console.xml $(sbindir)/zabbix_java/lib; fi

clean:
	rm -f $(ZJP)
	rm -rf class/src/*
	rm -rf class/tests/*
