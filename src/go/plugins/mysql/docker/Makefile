MAKEFILE_DIR := $(dir $(abspath $(lastword $(MAKEFILE_LIST))))
COMPOSE_MYSQL_5.7 = $(MAKEFILE_DIR)mysql_5.7/docker-compose.yaml
COMPOSE_MYSQL_8.3 = $(MAKEFILE_DIR)mysql_8.3/docker-compose.yaml
COMPOSE_MYSQL_9.3 = $(MAKEFILE_DIR)mysql_9.3/docker-compose.yaml
COMPOSE_MARIADB_11.8 = $(MAKEFILE_DIR)mariadb_11.8/docker-compose.yaml
COMPOSE_PERCONA_8.0 = $(MAKEFILE_DIR)percona_8.0/docker-compose.yaml


.PHONY: buildup-mysql-5.7 buildup-mysql-8.3 buildup-mysql-9.3 buildup-mariadb_11.8 buildup-percona-8.0 stop down help
buildup-mysql-5.7: stop ## Build (if needed) and start MySQL v5.7 master and replica containers (old syntax for replication query used).
	docker compose -f $(COMPOSE_MYSQL_5.7) up -d --build mysql-5.7-setup

buildup-mysql-8.3: stop ## Build (if needed) and start MySQL v8.3 master and replica containers (new syntax for replication query used).
	docker compose -f $(COMPOSE_MYSQL_8.3) up -d --build mysql-8.3-setup

buildup-mysql-9.3: stop  ## Build (if needed) and start MySQL v9.3 master and replica containers (new syntax for replication query used).
	docker compose -f $(COMPOSE_MYSQL_9.3) up -d --build mysql-9.3-setup

buildup-mariadb-11.8: stop ## Build (if needed) and start MariaDB v11.8 master and replica containers (new syntax for replication query used).
	docker compose -f $(COMPOSE_MARIADB_11.8) up -d --build mariadb-11.8-setup

buildup-percona-8.0: stop ## Build (if needed) and start Percona v8.0 master and replica containers new syntax for replication query used).
	docker compose -f $(COMPOSE_PERCONA_8.0) up -d --build percona-8.0-setup

stop: ## Stop all containers
	docker compose -f $(COMPOSE_MYSQL_5.7) stop
	docker compose -f $(COMPOSE_MYSQL_8.3) stop
	docker compose -f $(COMPOSE_MYSQL_9.3) stop
	docker compose -f $(COMPOSE_MARIADB_11.8) stop
	docker compose -f $(COMPOSE_PERCONA_8.0) stop

down: ## Stop and delete all containers
	docker compose -f $(COMPOSE_MYSQL_5.7) down -v
	docker compose -f $(COMPOSE_MYSQL_8.3) down -v
	docker compose -f $(COMPOSE_MYSQL_9.3) down -v
	docker compose -f $(COMPOSE_MARIADB_11.8) down -v
	docker compose -f $(COMPOSE_PERCONA_8.0) down -v

help: ## Show available targets
	@awk -F':.*##' '/^[a-zA-Z0-9_.-]+:.*##/ {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
