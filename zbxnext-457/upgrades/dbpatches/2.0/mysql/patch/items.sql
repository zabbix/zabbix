ALTER TABLE items CHANGE units units VARCHAR(255) DEFAULT '' NOT NULL;

UPDATE items SET units='Bps' WHERE type=9 AND units='bps';