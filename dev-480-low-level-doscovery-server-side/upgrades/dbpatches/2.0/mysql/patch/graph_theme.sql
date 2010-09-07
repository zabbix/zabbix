ALTER TABLE graph_theme MODIFY graphthemeid bigint unsigned NOT NULL,
			CHANGE noneworktimecolor nonworktimecolor varchar(6) DEFAULT 'CCCCCC' NOT NULL;
