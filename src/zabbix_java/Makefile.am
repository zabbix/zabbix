## Process this file with automake to produce Makefile.in

EXTRA_DIST = \
	bin \
	lib \
	src \
	settings.sh \
	shutdown.sh \
	startup.sh

ZJG = bin/zabbix-java-gateway-$(VERSION).jar
LIB = lib/android-json-4.3_r3.1.jar:lib/logback-core-1.2.9.jar:lib/logback-classic-1.2.9.jar:lib/slf4j-api-1.7.32.jar
JUNIT = tests/junit-4.8.2.jar

ZJG_DEST = $(DESTDIR)$(sbindir)/zabbix_java

all: $(ZJG)

$(ZJG): class src/com/zabbix/gateway/*.java
	$(JAVAC) -d class/src -classpath $(LIB) src/com/zabbix/gateway/*.java
	$(JAR) cf $(ZJG) -C class/src .

test: class
	echo "badger useruser" > tests/com/zabbix/gateway/jmx_test_beans/jmxremote.password
	chmod 600 tests/com/zabbix/gateway/jmx_test_beans/jmxremote.password
	$(JAVAC) tests/com/zabbix/gateway/jmx_test_beans/*.java
	$(JAVAC) -d class/tests -classpath class/src:$(JUNIT) tests/com/zabbix/gateway/*.java
	java -classpath class/tests:$(LIB):$(ZJG):$(JUNIT) com.zabbix.gateway.AllTestRunner

class:
	$(MKDIR_P) class/src
	$(MKDIR_P) class/tests

install: all
	$(MKDIR_P) "$(ZJG_DEST)/bin"
	$(MKDIR_P) "$(ZJG_DEST)/lib"
	cp startup.sh shutdown.sh "$(ZJG_DEST)"
	if test ! -f "$(ZJG_DEST)/settings.sh"; then cp settings.sh "$(ZJG_DEST)"; fi
	cp $(ZJG) "$(ZJG_DEST)/bin"
	cp lib/*.jar "$(ZJG_DEST)/lib"
	if test ! -f "$(ZJG_DEST)/lib/logback.xml"; then cp lib/logback.xml "$(ZJG_DEST)/lib"; fi
	if test ! -f "$(ZJG_DEST)/lib/logback-console.xml"; then cp lib/logback-console.xml "$(ZJG_DEST)/lib"; fi

clean:
	rm -f tests/com/zabbix/gateway/jmx_test_beans/*.class
	rm -f bin/*
	rm -f $(ZJG)
	rm -f tests/com/zabbix/gateway/jmx_test_beans/*.class
	rm -f bin/*
	rm -rf class/src/*
	rm -rf class/tests/*
