alter table items modify units           nvarchar2(255);
ALTER TABLE items ADD lastns number(10) NULL;

UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
