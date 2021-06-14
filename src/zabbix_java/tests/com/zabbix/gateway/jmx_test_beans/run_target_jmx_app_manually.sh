#!/bin/sh

java -Dcom.sun.management.jmxremote \
	-Dcom.sun.management.jmxremote.port=1617 \
	-Djavax.net.ssl.keyStore=$PWD/k_tools/cert1/SimpleAgent.keystore \
	-Djavax.net.ssl.keyStorePassword=kuseruser \
	-Djavax.net.ssl.trustStore=$PWD/k_tools/cert1/SimpleAgent.truststore \
	-Djavax.net.ssl.trustStorePassword=tuseruser \
	-Dcom.sun.management.jmxremote.authenticate=true \
	-Dcom.sun.management.jmxremote.access.file=$PWD/jmxremote.access \
	-Dcom.sun.management.jmxremote.password.file=$PWD/jmxremote.password \
	-Dcom.sun.management.jmxremote.ssl=false \
	-Dcom.sun.management.jmxremote.registry.ssl=true \
	-Djavax.net.debug=all \
	SimpleAgent
