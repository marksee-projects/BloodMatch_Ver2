-- Preserve the oldest ID and every request's facility association.
CREATE TEMPORARY TABLE hospital_canonical AS
SELECT name,municipality_name,MIN(id) AS canonical_id FROM hospitals GROUP BY name,municipality_name;
START TRANSACTION;
UPDATE blood_requests r JOIN hospitals h ON h.id=r.hospital_id
JOIN hospital_canonical c ON c.name=h.name AND c.municipality_name=h.municipality_name
SET r.hospital_id=c.canonical_id,r.updated_at=r.updated_at WHERE r.hospital_id<>c.canonical_id;
DELETE h FROM hospitals h JOIN hospital_canonical c ON c.name=h.name AND c.municipality_name=h.municipality_name
WHERE h.id<>c.canonical_id;
COMMIT;
DROP TEMPORARY TABLE hospital_canonical;
ALTER TABLE hospitals ADD UNIQUE KEY uq_hospital_name_municipality (name,municipality_name);
