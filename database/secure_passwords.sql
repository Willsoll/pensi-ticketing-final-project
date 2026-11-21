-- Rotate staff passwords after importing the demo database.
BEGIN;
UPDATE users SET password = '$2y$12$gronLRj6nw.meBmVSxAYjOMFqvLe7HX2IvlyBa4YD72NGkd6JVcnW' WHERE email = 'admin.eo@eventpro.id';
UPDATE users SET password = '$2y$12$9/HFwtmdPWPSks6ETJotae3hre5nyTM0CNEKV/p9k8DxxPFttfElW' WHERE email = 'finance@eventpro.id';
UPDATE users SET password = '$2y$12$BHRnxbGpgtowaHaHV/A7Gu7ZYQuJoVeEdsAsi1zGWZgG/TRe44CtS' WHERE email = 'gate@eventpro.id';
COMMIT;
