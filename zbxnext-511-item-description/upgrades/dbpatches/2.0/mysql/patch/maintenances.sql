ALTER TABLE maintenances
	MODIFY maintenanceid bigint unsigned NOT NULL,
	MODIFY description TEXT NOT NULL DEFAULT '';
