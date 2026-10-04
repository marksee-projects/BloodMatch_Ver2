CREATE TABLE IF NOT EXISTS hospitals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    type ENUM('public', 'private') NOT NULL,
    municipality_name VARCHAR(120) NOT NULL,
    location_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_hospitals_location FOREIGN KEY (location_id) REFERENCES bataan_locations (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE blood_requests
    ADD COLUMN hospital_id INT UNSIGNED NULL AFTER facility_name,
    ADD CONSTRAINT fk_requests_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals (id) ON DELETE SET NULL ON UPDATE CASCADE;
