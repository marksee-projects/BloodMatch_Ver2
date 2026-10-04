INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Bataan General Hospital and Medical Center (BGHMC)', 'public', 'City of Balanga', id FROM bataan_locations WHERE municipality_name = 'City of Balanga' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Jose C. Payumo Jr. Memorial Hospital', 'public', 'Dinalupihan', id FROM bataan_locations WHERE municipality_name = 'Dinalupihan' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Orani District Hospital', 'public', 'Orani', id FROM bataan_locations WHERE municipality_name = 'Orani' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Mariveles Mental Wellness and General Hospital (MMWGH)', 'public', 'Mariveles', id FROM bataan_locations WHERE municipality_name = 'Mariveles' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Mariveles District Hospital', 'public', 'Mariveles', id FROM bataan_locations WHERE municipality_name = 'Mariveles' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Bagac Community and Medicare Hospital', 'public', 'Bagac', id FROM bataan_locations WHERE municipality_name = 'Bagac' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Hermosa Municipal Hospital', 'public', 'Hermosa', id FROM bataan_locations WHERE municipality_name = 'Hermosa' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Isaac & Catalina Medical Center (ICMC)', 'private', 'City of Balanga', id FROM bataan_locations WHERE municipality_name = 'City of Balanga' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Bataan Doctors Hospital and Medical Center', 'private', 'City of Balanga', id FROM bataan_locations WHERE municipality_name = 'City of Balanga' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'St. Joseph Hospital and Medical Center', 'private', 'City of Balanga', id FROM bataan_locations WHERE municipality_name = 'City of Balanga' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Centro Medico de Santisimo Rosario', 'private', 'City of Balanga', id FROM bataan_locations WHERE municipality_name = 'City of Balanga' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Bataan Peninsula Medical Center (BPMC)', 'private', 'Dinalupihan', id FROM bataan_locations WHERE municipality_name = 'Dinalupihan' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Orion St. Michael Hospital', 'private', 'Orion', id FROM bataan_locations WHERE municipality_name = 'Orion' AND level = 'municipality' LIMIT 1;

INSERT INTO hospitals (name, type, municipality_name, location_id)
SELECT 'Mount Samat Medical Center', 'private', 'Pilar', id FROM bataan_locations WHERE municipality_name = 'Pilar' AND level = 'municipality' LIMIT 1;
