#!/bin/bash

VALIDITY="36500"
KEYALG="RSA"
TARGET_APP="SimpleAgent"
MONITOR_APP="junit_test"
KEYSTORE_PASS="kuseruser"
TRUSTSTORE_PASS="tuseruser"
D_NAME="CN=Zabbix, OU=Dev, O=JMX_Test, L=Riga, S=VI, C=LA"
SAN="ip:127.0.0.1,dns:localhost"

CUR_DIR=$(pwd)

for TARGET_DIR in cert1 cert2
do
	cd "$CUR_DIR/$TARGET_DIR"

	keytool -genkeypair -alias $TARGET_APP -keyalg $KEYALG -validity $VALIDITY -keystore "${TARGET_APP}.keystore" -storepass $KEYSTORE_PASS -keypass $KEYSTORE_PASS -dname "${D_NAME}" -ext "SAN=${SAN}"
	keytool -genkeypair -alias $TARGET_APP -keyalg $KEYALG -validity $VALIDITY -keystore "${TARGET_APP}.truststore" -storepass $TRUSTSTORE_PASS -keypass $TRUSTSTORE_PASS -dname "${D_NAME}" -ext "SAN=${SAN}"
	keytool -genkeypair -alias $MONITOR_APP -keyalg $KEYALG -validity $VALIDITY -keystore "${MONITOR_APP}.keystore" -storepass $KEYSTORE_PASS -keypass $KEYSTORE_PASS -dname "${D_NAME}" -ext "SAN=${SAN}"
	keytool -genkeypair -alias $MONITOR_APP -keyalg $KEYALG -validity $VALIDITY -keystore "${MONITOR_APP}.truststore" -storepass $TRUSTSTORE_PASS -keypass $TRUSTSTORE_PASS -dname "${D_NAME}" -ext "SAN=${SAN}"
	keytool -export -alias $TARGET_APP -keystore "${TARGET_APP}.keystore" -file "${TARGET_APP}.cer" -storepass $KEYSTORE_PASS
	keytool -export -alias $MONITOR_APP -keystore "${MONITOR_APP}.keystore" -file "${MONITOR_APP}.cer" -storepass $KEYSTORE_PASS
	keytool -import -alias $MONITOR_APP -file "${MONITOR_APP}.cer" -keystore "${TARGET_APP}.truststore" -storepass $TRUSTSTORE_PASS -noprompt
	keytool -import -alias $TARGET_APP -file "${TARGET_APP}.cer" -keystore "${MONITOR_APP}.truststore" -storepass $TRUSTSTORE_PASS -noprompt

	cd "$CUR_DIR"
done
