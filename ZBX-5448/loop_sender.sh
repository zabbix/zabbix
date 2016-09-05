COUNTER=0
while [  $COUNTER -lt 10000 ]; do
./bin/zabbix_sender -z 127.0.0.1 -p 10052 -s "test server" -k trap -o $COUNTER           
./bin/zabbix_sender -z 127.0.0.1 -p 10052 -s "test server" -k trap2 -o $COUNTER
let COUNTER=COUNTER+1 
done
