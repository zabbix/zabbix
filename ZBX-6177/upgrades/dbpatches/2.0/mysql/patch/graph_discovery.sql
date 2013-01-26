CREATE TABLE graph_discovery (
	graphdiscoveryid         bigint unsigned                           NOT NULL,
	graphid                  bigint unsigned                           NOT NULL,
	parent_graphid           bigint unsigned                           NOT NULL,
	name                     varchar(128)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (graphdiscoveryid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX graph_discovery_1 on graph_discovery (graphid,parent_graphid);
ALTER TABLE graph_discovery ADD CONSTRAINT c_graph_discovery_1 FOREIGN KEY (graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE graph_discovery ADD CONSTRAINT c_graph_discovery_2 FOREIGN KEY (parent_graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
