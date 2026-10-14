# BloodMatch entity relationship diagram

Generated from the actual `bloodmatch` schema on port 3306, 14 October 2026 19:07 Asia/Manila. This snapshot includes all 24 base tables; account count does not change its relationships.

**Import repair pending:** these tables have no primary key: `conversations`, `conversation_participants`, `messages`. Chat foreign keys are also absent in this snapshot. Run the reviewed maintenance and regenerate this file before submitting the final ERD.

[Open the SVG diagram](erd.svg). PK = primary key; FK = enforced foreign key; UK = single-column unique key. Nullable FK fields permit an absent parent.

```mermaid
erDiagram
    users |o--o{ audit_log : "actor_id"
    hospitals |o--o{ blood_requests : "hospital_id"
    bataan_locations |o--o{ blood_requests : "location_id"
    users ||--o{ blood_requests : "requester_id"
    chapters |o--o{ blood_requests : "request_chapter_id"
    users |o--o{ donation_reports : "confirmed_by"
    users ||--o{ donation_reports : "donor_id"
    matches ||--o{ donation_reports : "match_id"
    users ||--o{ donor_opportunities : "donor_id"
    notifications |o--o{ donor_opportunities : "notification_id"
    blood_requests ||--o{ donor_opportunities : "request_id"
    users ||--o| donor_response_state : "user_id"
    email_attempts ||--o| email_donor_reservations : "attempt_id"
    notifications ||--o| email_outbox : "notification_id"
    bataan_locations |o--o{ hospitals : "location_id"
    users ||--o{ matches : "donor_id"
    blood_requests ||--o{ matches : "request_id"
    users ||--o{ member_documents : "user_id"
    users ||--o{ notifications : "user_id"
    users ||--o{ password_resets : "user_id"
    chapters |o--o{ users : "chapter_id"
    bataan_locations |o--o{ users : "location_id"
    users |o--o{ verification_decisions : "officer_id"
    users ||--o{ verification_decisions : "target_user_id"
    audit_log {
        BIGINT id PK
        BIGINT actor_id FK "nullable"
        VARCHAR action
        VARCHAR target_type "nullable"
        VARCHAR target_id "nullable"
        LONGTEXT context "nullable"
        TIMESTAMP created_at
    }
    auth_throttle {
        VARCHAR identifier PK
        INT failed_count
        DATETIME locked_until "nullable"
        TIMESTAMP updated_at
    }
    bataan_locations {
        INT id PK
        VARCHAR psgc_code UK
        VARCHAR name
        ENUM level
        VARCHAR municipality_code
        VARCHAR municipality_name
        DECIMAL latitude "nullable"
        DECIMAL longitude "nullable"
        TINYINT is_active
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }
    blood_requests {
        BIGINT id PK
        BIGINT requester_id FK
        TINYINT request_chapter_id FK "nullable"
        ENUM required_blood_type
        TINYINT quantity_units
        VARCHAR facility_name
        INT hospital_id FK "nullable"
        DECIMAL latitude "nullable"
        DECIMAL longitude "nullable"
        ENUM urgency
        DATETIME needed_datetime
        ENUM status
        ENUM review_status
        TIMESTAMP created_at
        TIMESTAMP updated_at
        DATETIME expired_at "nullable"
        INT location_id FK "nullable"
        TEXT description "nullable"
    }
    chapters {
        TINYINT id PK
        VARCHAR code UK
        VARCHAR name
        VARCHAR municipality
        DECIMAL latitude "nullable"
        DECIMAL longitude "nullable"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }
    compatibility_matrix {
        VARCHAR recipient_type PK
        TEXT allowed_donor_types
    }
    conversations {
        BIGINT id
        VARCHAR type
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }
    conversation_participants {
        BIGINT id
        BIGINT conversation_id
        BIGINT user_id
        DATETIME last_read_at "nullable"
        TIMESTAMP created_at
    }
    donation_reports {
        BIGINT id PK
        BIGINT match_id FK
        BIGINT donor_id FK
        VARCHAR report_note "nullable"
        VARCHAR rejection_reason "nullable"
        ENUM status
        DATETIME reported_at
        BIGINT confirmed_by FK "nullable"
        DATETIME confirmed_at "nullable"
        TIMESTAMP created_at
    }
    donor_opportunities {
        BIGINT id PK
        BIGINT request_id FK
        BIGINT donor_id FK
        INT generation
        BIGINT notification_id FK "nullable"
        DATETIME notified_at
        DATETIME due_at
        ENUM status
    }
    donor_response_state {
        BIGINT user_id PK,FK
        DATETIME standby_started_at "nullable"
        DATETIME consumed_through "nullable"
    }
    email_attempts {
        BIGINT id PK
        ENUM mail_class
        DATETIME attempted_at
    }
    email_donor_reservations {
        BIGINT attempt_id PK,FK
        BIGINT donor_id
        DATETIME reserved_at
        ENUM outcome
        DATETIME updated_at
    }
    email_outbox {
        BIGINT id PK
        BIGINT notification_id UK,FK
        VARCHAR delivery_key UK
        ENUM priority
        ENUM status
        SMALLINT attempts
        DATETIME next_attempt_at
        CHAR lease_token "nullable"
        DATETIME lease_expires_at "nullable"
        DATETIME expires_at
        VARCHAR last_error_code "nullable"
        VARCHAR cancel_reason "nullable"
        DATETIME created_at
        DATETIME updated_at
        DATETIME sent_at "nullable"
    }
    hospitals {
        INT id PK
        VARCHAR name
        ENUM type
        VARCHAR municipality_name
        INT location_id FK "nullable"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }
    matches {
        BIGINT id PK
        BIGINT request_id FK
        BIGINT donor_id FK
        INT generation
        ENUM status
        DECIMAL distance_km "nullable"
        DECIMAL rank_score "nullable"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }
    member_documents {
        BIGINT id PK
        BIGINT user_id FK
        ENUM doc_type
        CHAR stored_name UK
        VARCHAR mime_type
        VARCHAR original_ext
        INT size_bytes
        TIMESTAMP uploaded_at
    }
    messages {
        BIGINT id
        BIGINT conversation_id
        BIGINT sender_id
        TEXT body
        TIMESTAMP created_at
    }
    notifications {
        BIGINT id PK
        BIGINT user_id FK
        VARCHAR type
        VARCHAR title
        VARCHAR body
        VARCHAR related_type "nullable"
        BIGINT related_id "nullable"
        VARCHAR dedup_key "nullable"
        INT generation "nullable"
        DATETIME emailed_at "nullable"
        DATETIME read_at "nullable"
        TIMESTAMP created_at
    }
    password_resets {
        BIGINT id PK
        BIGINT user_id FK
        CHAR token_hash UK
        DATETIME expires_at
        DATETIME used_at "nullable"
        TIMESTAMP created_at
    }
    schema_migrations {
        VARCHAR name PK
        TIMESTAMP applied_at
    }
    system_settings {
        VARCHAR setting_key PK
        VARCHAR value
        TIMESTAMP updated_at
    }
    users {
        BIGINT id PK
        VARCHAR email UK
        VARCHAR password_hash "nullable"
        VARCHAR first_name
        VARCHAR middle_name "nullable"
        VARCHAR last_name
        VARCHAR full_name "nullable; generated"
        VARCHAR phone "nullable"
        ENUM role
        TINYINT chapter_id FK "nullable"
        ENUM verification_status
        DATE date_of_birth "nullable"
        ENUM blood_type "nullable"
        ENUM blood_type_source "nullable"
        TINYINT blood_type_verified
        DATETIME donor_enrolled_at "nullable"
        ENUM donor_availability "nullable"
        DATETIME last_verified_donation_at "nullable"
        DECIMAL latitude "nullable"
        DECIMAL longitude "nullable"
        ENUM account_status
        DATETIME email_verified_at "nullable"
        VARCHAR email_code_hash "nullable"
        DATETIME email_code_expires_at "nullable"
        DATETIME deactivated_at "nullable"
        TIMESTAMP created_at
        TIMESTAMP updated_at
        VARCHAR profile_picture "nullable"
        INT location_id FK "nullable"
    }
    verification_decisions {
        BIGINT id PK
        BIGINT target_user_id FK
        BIGINT officer_id FK "nullable"
        ENUM decision
        VARCHAR reason "nullable"
        TIMESTAMP created_at
    }
```

## Relationships not enforced by foreign keys

- `notifications.related_type` / `related_id` and `audit_log.target_type` / `target_id` are polymorphic application references.
- `email_donor_reservations.donor_id` is a deliberate accounting reference without a user FK; cleanup explicitly handles removed donors.
- `auth_throttle.identifier` contains opaque throttle keys, rather than an enforced user relationship.
- `compatibility_matrix.allowed_donor_types` stores the fixed reference compatibility list used by the matching service.

## Chat repair target

The intended relationship is `users -> conversation_participants -> conversations -> messages`, with a separate sender FK from `messages` to `users`. Chat IDs require primary keys and automatic generation; participant pairs require uniqueness. `last_read_at` must be DATETIME(3). The exporter includes these edges only after the actual database has them.

No tables or columns were removed to simplify this diagram. See [the database review and execution guide](CHAT_DATABASE_NOTIFICATION_HANDOFF.md).
