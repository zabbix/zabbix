ALTER TABLE items MODIFY units VARCHAR(255) DEFAULT '' NOT NULL;
ALTER TABLE items ADD lastns integer NULL;

UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
