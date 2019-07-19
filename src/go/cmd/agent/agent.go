package main

import "go/internal/log"

func main() {
	log.Open(log.Console, log.Debug, "/tmp/zabbix_agent.log")
	log.Infof("Zabbix Go agent")
}
