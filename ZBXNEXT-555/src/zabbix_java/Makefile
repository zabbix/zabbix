JAR = sbin/ZabbixJavaProxy.jar

jar: cls
	javac -d cls src/com/zabbix/proxy/*.java
	jar cfm $(JAR) MANIFEST.MF -C cls .

cls:
	mkdir -p cls

run:
	java -jar $(JAR)

clean:
	rm -f $(JAR)
	rm -f cls/com/zabbix/proxy/*.class
