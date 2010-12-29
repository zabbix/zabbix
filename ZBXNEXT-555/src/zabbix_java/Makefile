JAR = sbin/ZabbixJavaProxy.jar

jar: build
	javac -d build/src src/com/zabbix/proxy/*.java
	javac -d build/tests -classpath build/src:$(CLASSPATH) tests/com/zabbix/proxy/*.java
	jar cfm $(JAR) MANIFEST.MF -C build/src .

build:
	mkdir -p build/src
	mkdir -p build/tests

run:
	java -jar $(JAR)

test:
	java -classpath build/tests:$(JAR):$(CLASSPATH) com.zabbix.proxy.AllTestRunner

clean:
	rm -f $(JAR)
	rm -rf build/src/*
	rm -rf build/tests/*
