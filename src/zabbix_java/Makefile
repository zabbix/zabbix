JAR = sbin/ZabbixJavaProxy.jar

LIB := lib/org-json-2010-12-28.jar
LIB := $(LIB):lib/logback-core-0.9.27.jar
LIB := $(LIB):lib/logback-classic-0.9.27.jar
LIB := $(LIB):lib/slf4j-api-1.6.1.jar

jar: build
	javac -d build/src -classpath $(LIB) src/com/zabbix/proxy/*.java
	javac -d build/tests -classpath build/src:$(CLASSPATH) tests/com/zabbix/proxy/*.java
	jar cfm $(JAR) MANIFEST.MF -C build/src .

build:
	mkdir -p build/src
	mkdir -p build/tests

run:
	java -classpath $(LIB):$(JAR) com.zabbix.proxy.JavaProxy

test:
	java -classpath build/tests:$(JAR):$(CLASSPATH) com.zabbix.proxy.AllTestRunner

clean:
	rm -f $(JAR)
	rm -rf build/src/*
	rm -rf build/tests/*
