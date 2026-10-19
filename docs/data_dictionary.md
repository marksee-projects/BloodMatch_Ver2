# BloodMatch data dictionary

Generated from **bloodmatch_test:3306**, 2026-10-19 21:26 Asia/Manila. **TESTED DISPOSABLE SCHEMA - see application snapshot for rollout status**. 38 physical base tables.

The active design uses six donation tables. `donation_credits`, `donation_report_holds`, and `compatibility_matrix` remain as cutover source snapshots, not current writers. After separately approved retirement: 35 active base tables, including `compatibility_pairs`.

See [rollout and test checkpoint](DONATION_CONSOLIDATION_032.md), [data dictionary](data_dictionary.md), and [SVG](erd.svg). PK = primary key; FK = enforced reference; UK is shown on a column only for single-column uniqueness. Composite keys are listed in the dictionary.

Current writers: completion service owns report/credit/hold transitions; proof service owns evidence; workflow repository appends report events; offer service owns accepted contexts and coordination events; correction service owns completed-record review requests. Restrictions remain a monotonic user anchor; fulfillment counts completed matches. Legacy confirmed reports may have no credit or occurrence.

## account_closures

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `user_id` | `bigint(20) unsigned` | NO |  |
| `closed_at` | `datetime` | NO |  |
| `erase_after` | `datetime` | NO |  |
| `profile_erased_at` | `datetime` | YES |  |

Indexes / uniqueness:

- `closure_due` INDEX (`erase_after`, `profile_erased_at`)
- `PRIMARY` UNIQUE (`user_id`)
- FK `account_closures_ibfk_1`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## audit_log

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `actor_id` | `bigint(20) unsigned` | YES |  |
| `action` | `varchar(80)` | NO |  |
| `target_type` | `varchar(60)` | YES |  |
| `target_id` | `varchar(64)` | YES |  |
| `context` | `longtext` | YES |  |
| `created_at` | `timestamp(3)` | NO |  |

Indexes / uniqueness:

- `idx_audit_action` INDEX (`action`)
- `idx_audit_actor` INDEX (`actor_id`)
- `idx_audit_created` INDEX (`created_at`)
- `idx_audit_target` INDEX (`target_type`, `target_id`)
- `idx_audit_target_created` INDEX (`target_type`, `target_id`, `created_at`)
- `PRIMARY` UNIQUE (`id`)
- FK `fk_audit_actor`: (actor_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- CHECK `context`: `json_valid(`context`)`

## auth_throttle

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `identifier` | `varchar(210)` | NO |  |
| `failed_count` | `int(10) unsigned` | NO |  |
| `locked_until` | `datetime` | YES |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`identifier`)

## bataan_locations

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `int(10) unsigned` | NO | auto_increment |
| `psgc_code` | `varchar(9)` | NO |  |
| `name` | `varchar(120)` | NO |  |
| `level` | `enum('municipality','barangay')` | NO |  |
| `municipality_code` | `varchar(9)` | NO |  |
| `municipality_name` | `varchar(120)` | NO |  |
| `latitude` | `decimal(9,6)` | YES |  |
| `longitude` | `decimal(9,6)` | YES |  |
| `is_active` | `tinyint(1)` | NO |  |
| `created_at` | `timestamp` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |

Indexes / uniqueness:

- `idx_location_active` INDEX (`is_active`)
- `idx_location_municipality` INDEX (`municipality_code`, `level`)
- `PRIMARY` UNIQUE (`id`)
- `uq_location_psgc` UNIQUE (`psgc_code`)

## blood_requests

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `requester_id` | `bigint(20) unsigned` | NO |  |
| `request_chapter_id` | `tinyint(3) unsigned` | YES |  |
| `required_blood_type` | `enum('A+','A-','B+','B-','AB+','AB-','O+','O-')` | NO |  |
| `quantity_units` | `tinyint(3) unsigned` | NO |  |
| `facility_name` | `varchar(150)` | NO |  |
| `hospital_id` | `int(10) unsigned` | YES |  |
| `latitude` | `decimal(9,6)` | YES |  |
| `longitude` | `decimal(9,6)` | YES |  |
| `urgency` | `enum('routine','urgent','emergency')` | NO |  |
| `needed_datetime` | `datetime` | NO |  |
| `status` | `enum('OPEN','FULFILLED','CANCELLED','EXPIRED')` | NO |  |
| `review_status` | `enum('not_required','pending_review')` | NO |  |
| `created_at` | `timestamp` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |
| `expired_at` | `datetime` | YES |  |
| `location_id` | `int(10) unsigned` | YES |  |
| `description` | `text` | YES |  |
| `renewed_from_id` | `bigint(20) unsigned` | YES |  |
| `closure_reason` | `varchar(50)` | YES |  |

Indexes / uniqueness:

- `fk_requests_hospital` INDEX (`hospital_id`)
- `fk_requests_location` INDEX (`location_id`)
- `idx_breq_chapter` INDEX (`request_chapter_id`)
- `idx_breq_chapter_status_created` INDEX (`request_chapter_id`, `status`, `created_at`)
- `idx_breq_expiry` INDEX (`status`, `needed_datetime`)
- `idx_breq_requester` INDEX (`requester_id`)
- `idx_breq_status` INDEX (`status`)
- `idx_breq_urgency` INDEX (`urgency`)
- `PRIMARY` UNIQUE (`id`)
- `uq_request_renewal` UNIQUE (`renewed_from_id`)
- FK `fk_breq_chapter`: (request_chapter_id) references `chapters` (id); DELETE RESTRICT; UPDATE CASCADE
- FK `fk_breq_requester`: (requester_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_requests_hospital`: (hospital_id) references `hospitals` (id); DELETE SET NULL; UPDATE CASCADE
- FK `fk_requests_location`: (location_id) references `bataan_locations` (id); DELETE SET NULL; UPDATE CASCADE
- FK `fk_request_renewal`: (renewed_from_id) references `blood_requests` (id); DELETE SET NULL; UPDATE RESTRICT
- CHECK `chk_breq_geo`: ``latitude` is null and `longitude` is null or `latitude` is not null and `longitude` is not null`
- CHECK `chk_request_positive_quantity`: ``quantity_units` > 0`

## chapters

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `tinyint(3) unsigned` | NO | auto_increment |
| `code` | `varchar(50)` | NO |  |
| `name` | `varchar(120)` | NO |  |
| `municipality` | `varchar(120)` | NO |  |
| `latitude` | `decimal(9,6)` | YES |  |
| `longitude` | `decimal(9,6)` | YES |  |
| `created_at` | `timestamp` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`id`)
- `uq_chapters_code` UNIQUE (`code`)

## chat_reports

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `reporter_id` | `bigint(20) unsigned` | YES |  |
| `conversation_id` | `bigint(20) unsigned` | YES |  |
| `reason` | `varchar(500)` | NO |  |
| `status` | `enum('open','under_review','resolved','dismissed')` | NO |  |
| `findings` | `varchar(2000)` | YES |  |
| `created_at` | `datetime` | NO |  |
| `resolved_at` | `datetime` | YES |  |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`id`)
- `reporter_id` INDEX (`reporter_id`)
- `report_queue` INDEX (`status`, `created_at`)
- FK `chat_reports_ibfk_1`: (reporter_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT

## chat_report_evidence

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `report_id` | `bigint(20) unsigned` | NO |  |
| `message_id` | `bigint(20) unsigned` | NO |  |
| `sender_id` | `bigint(20) unsigned` | YES |  |
| `body` | `text` | NO |  |
| `message_created_at` | `datetime(3)` | NO |  |
| `is_selected` | `tinyint(1)` | NO |  |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`id`)
- `report_evidence` INDEX (`report_id`, `message_id`)
- `uq_chat_evidence_message` UNIQUE (`report_id`, `message_id`)
- FK `chat_report_evidence_ibfk_1`: (report_id) references `chat_reports` (id); DELETE CASCADE; UPDATE RESTRICT

## chat_requests

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `user_low` | `bigint(20) unsigned` | NO |  |
| `user_high` | `bigint(20) unsigned` | NO |  |
| `sender_id` | `bigint(20) unsigned` | NO |  |
| `recipient_id` | `bigint(20) unsigned` | NO |  |
| `status` | `enum('pending','accepted','declined','cancelled')` | NO |  |
| `conversation_id` | `bigint(20) unsigned` | YES |  |
| `created_at` | `datetime` | NO |  |
| `updated_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `accepted_thread` INDEX (`conversation_id`, `status`)
- `chat_pair` UNIQUE (`user_low`, `user_high`)
- `incoming_requests` INDEX (`recipient_id`, `status`)
- `PRIMARY` UNIQUE (`id`)
- `sender_id` INDEX (`sender_id`)
- `user_high` INDEX (`user_high`)
- FK `chat_requests_ibfk_1`: (user_low) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `chat_requests_ibfk_2`: (user_high) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `chat_requests_ibfk_3`: (sender_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `chat_requests_ibfk_4`: (recipient_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- CHECK `CONSTRAINT_1`: ``user_low` < `user_high` and `user_low` = least(`sender_id`,`recipient_id`) and `user_high` = greatest(`sender_id`,`recipient_id`)`
- CHECK `CONSTRAINT_2`: ``status` = 'accepted' and `conversation_id` is not null or `status` <> 'accepted' and `conversation_id` is null`

## compatibility_matrix

**Retained source snapshot. Not authoritative after 032. Retirement requires separate approval.**

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `recipient_type` | `varchar(3)` | NO |  |
| `allowed_donor_types` | `text` | NO |  |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`recipient_type`)
- CHECK `chk_cmatrix_type`: ``recipient_type` in ('A+','A-','B+','B-','AB+','AB-','O+','O-')`

## compatibility_pairs

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `recipient_type` | `enum('A+','A-','B+','B-','AB+','AB-','O+','O-')` | NO |  |
| `donor_type` | `enum('A+','A-','B+','B-','AB+','AB-','O+','O-')` | NO |  |
| `preference_order` | `tinyint(3) unsigned` | NO |  |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`recipient_type`, `donor_type`)
- `uq_compatibility_order` UNIQUE (`recipient_type`, `preference_order`)
- CHECK `chk_compatibility_order`: ``preference_order` between 1 and 8`

## conversations

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `type` | `varchar(20)` | NO |  |
| `created_at` | `timestamp(3)` | NO |  |
| `updated_at` | `timestamp(3)` | NO | on update current_timestamp(3) |

Indexes / uniqueness:

- `idx_conversations_updated` INDEX (`updated_at`)
- `PRIMARY` UNIQUE (`id`)

## conversation_participants

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `conversation_id` | `bigint(20) unsigned` | NO |  |
| `user_id` | `bigint(20) unsigned` | NO |  |
| `last_read_at` | `datetime(3)` | YES |  |
| `created_at` | `timestamp(3)` | NO |  |
| `last_read_message_id` | `bigint(20) unsigned` | YES |  |

Indexes / uniqueness:

- `idx_participant_user` INDEX (`user_id`, `last_read_at`)
- `PRIMARY` UNIQUE (`id`)
- `uq_conversation_user` UNIQUE (`conversation_id`, `user_id`)
- FK `fk_part_conv`: (conversation_id) references `conversations` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_part_user`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## donation_chat_contexts

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `match_id` | `bigint(20) unsigned` | NO |  |
| `offer_cycle` | `int(10) unsigned` | NO |  |
| `conversation_id` | `bigint(20) unsigned` | YES |  |
| `accepted_by` | `bigint(20) unsigned` | YES |  |
| `accepted_at` | `datetime` | NO |  |
| `responded_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `accepted_by` INDEX (`accepted_by`)
- `idx_context_conversation` INDEX (`conversation_id`)
- `PRIMARY` UNIQUE (`id`)
- `uq_offer_chat_cycle` UNIQUE (`match_id`, `offer_cycle`)
- FK `donation_chat_contexts_ibfk_1`: (match_id) references `matches` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `donation_chat_contexts_ibfk_2`: (conversation_id) references `conversations` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `donation_chat_contexts_ibfk_3`: (accepted_by) references `users` (id); DELETE SET NULL; UPDATE RESTRICT

## donation_coordination_events

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `context_id` | `bigint(20) unsigned` | NO |  |
| `actor_id` | `bigint(20) unsigned` | YES |  |
| `actor_reference` | `bigint(20) unsigned` | NO |  |
| `action` | `varchar(32)` | NO |  |
| `report_version` | `int(10) unsigned` | NO |  |
| `created_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `actor_id` INDEX (`actor_id`)
- `idx_coordination_context` INDEX (`context_id`, `id`)
- `PRIMARY` UNIQUE (`id`)
- FK `donation_coordination_events_ibfk_1`: (context_id) references `donation_chat_contexts` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `donation_coordination_events_ibfk_2`: (actor_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT

## donation_correction_requests

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `report_id` | `bigint(20) unsigned` | NO |  |
| `content_version` | `int(10) unsigned` | NO |  |
| `actor_id` | `bigint(20) unsigned` | YES |  |
| `actor_reference` | `bigint(20) unsigned` | NO |  |
| `reason` | `varchar(500)` | NO |  |
| `created_at` | `datetime` | NO |  |
| `resolved_at` | `datetime` | YES |  |
| `resolved_by` | `bigint(20) unsigned` | YES |  |
| `resolution` | `varchar(500)` | YES |  |

Indexes / uniqueness:

- `actor_id` INDEX (`actor_id`)
- `idx_correction_unresolved` INDEX (`report_id`, `resolved_at`)
- `PRIMARY` UNIQUE (`id`)
- `resolved_by` INDEX (`resolved_by`)
- `uq_donation_correction` UNIQUE (`report_id`, `content_version`, `actor_reference`)
- FK `donation_correction_requests_ibfk_1`: (report_id) references `donation_reports` (id); DELETE RESTRICT; UPDATE RESTRICT
- FK `donation_correction_requests_ibfk_2`: (actor_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `donation_correction_requests_ibfk_3`: (resolved_by) references `users` (id); DELETE SET NULL; UPDATE RESTRICT

## donation_credits

**Retained source snapshot. Not authoritative after 032. Retirement requires separate approval.**

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `report_id` | `bigint(20) unsigned` | NO |  |
| `match_id` | `bigint(20) unsigned` | NO |  |
| `donor_id` | `bigint(20) unsigned` | NO |  |
| `occurred_at` | `datetime` | NO |  |
| `recorded_at` | `datetime` | NO |  |
| `completion_basis` | `varchar(24)` | NO |  |

Indexes / uniqueness:

- `idx_donation_credit_donor` INDEX (`donor_id`, `occurred_at`)
- `PRIMARY` UNIQUE (`report_id`)
- `uq_credit_donor_occurrence` UNIQUE (`donor_id`, `occurred_at`)
- `uq_donation_credit_match` UNIQUE (`match_id`)
- FK `fk_credit_donor`: (donor_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_credit_match`: (match_id) references `matches` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_credit_report`: (report_id) references `donation_reports` (id); DELETE CASCADE; UPDATE RESTRICT
- CHECK `CONSTRAINT_1`: ``completion_basis` in ('mutual','admin')`

## donation_reports

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `match_id` | `bigint(20) unsigned` | NO |  |
| `donor_id` | `bigint(20) unsigned` | NO |  |
| `report_note` | `varchar(500)` | YES |  |
| `rejection_reason` | `varchar(500)` | YES |  |
| `status` | `enum('PENDING','CONFIRMED','REJECTED')` | NO |  |
| `reported_at` | `datetime` | NO |  |
| `confirmed_by` | `bigint(20) unsigned` | YES |  |
| `confirmed_at` | `datetime` | YES |  |
| `created_at` | `timestamp` | NO |  |
| `review_evidence` | `varchar(500)` | YES |  |
| `occurred_at` | `datetime` | YES |  |
| `content_version` | `int(10) unsigned` | NO |  |
| `workflow_state` | `varchar(32)` | NO |  |
| `requester_response` | `varchar(24)` | YES |  |
| `acknowledged_by` | `bigint(20) unsigned` | YES |  |
| `acknowledged_at` | `datetime` | YES |  |
| `acknowledged_version` | `int(10) unsigned` | YES |  |
| `reviewed_at` | `datetime` | YES |  |
| `completion_basis` | `varchar(24)` | YES |  |
| `reminder_sent_at` | `datetime` | YES |  |
| `escalated_at` | `datetime` | YES |  |
| `offer_context_id` | `bigint(20) unsigned` | YES |  |
| `credit_recorded_at` | `datetime` | YES |  |
| `hold_started_at` | `datetime` | YES |  |
| `hold_released_at` | `datetime` | YES |  |
| `hold_released_by` | `bigint(20) unsigned` | YES |  |
| `hold_release_basis` | `varchar(500)` | YES |  |
| `hold_release_type` | `varchar(32)` | YES |  |
| `credited_donor_id` | `bigint(20) unsigned` | YES | STORED GENERATED = case when `credit_recorded_at` is not null then `donor_id` else NULL end |
| `credited_occurred_at` | `datetime` | YES | STORED GENERATED = case when `credit_recorded_at` is not null then `occurred_at` else NULL end |

Indexes / uniqueness:

- `fk_drep_confirmer` INDEX (`confirmed_by`)
- `fk_report_acknowledger` INDEX (`acknowledged_by`)
- `fk_report_hold_released_by` INDEX (`hold_released_by`)
- `fk_report_match_donor` INDEX (`match_id`, `donor_id`)
- `fk_report_offer_context` INDEX (`offer_context_id`)
- `idx_drep_donor` INDEX (`donor_id`)
- `idx_drep_match` INDEX (`match_id`)
- `idx_drep_status` INDEX (`status`)
- `idx_report_active_hold` INDEX (`donor_id`, `hold_released_at`, `hold_started_at`)
- `idx_report_followup` INDEX (`status`, `workflow_state`, `reported_at`)
- `PRIMARY` UNIQUE (`id`)
- `uq_report_credited_occurrence` UNIQUE (`credited_donor_id`, `credited_occurred_at`)
- `uq_report_match` UNIQUE (`match_id`)
- FK `fk_drep_confirmer`: (confirmed_by) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_drep_donor`: (donor_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_drep_match`: (match_id) references `matches` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_report_acknowledger`: (acknowledged_by) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_report_hold_released_by`: (hold_released_by) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_report_match_donor`: (match_id, donor_id) references `matches` (id, donor_id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_report_offer_context`: (offer_context_id) references `donation_chat_contexts` (id); DELETE SET NULL; UPDATE RESTRICT
- CHECK `chk_report_credit`: ``credit_recorded_at` is null or `status` = 'CONFIRMED' and `occurred_at` is not null and `completion_basis` is not null and `completion_basis` in ('mutual','admin')`
- CHECK `chk_report_hold`: `(`hold_started_at` is not null or `hold_released_at` is null and `hold_released_by` is null and `hold_release_basis` is null and `hold_release_type` is null) and (`hold_released_at` is null or `hold_released_at` >= `hold_started_at`)`
- CHECK `chk_report_response`: ``requester_response` is null or `requester_response` in ('received','did_not_donate','not_sure')`
- CHECK `chk_report_version`: ``content_version` >= 1`
- CHECK `chk_report_workflow`: ``workflow_state` in ('awaiting_requester','needs_review','clarification','completed','rejected')`

## donation_report_events

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `report_id` | `bigint(20) unsigned` | NO |  |
| `content_version` | `int(10) unsigned` | NO |  |
| `actor_id` | `bigint(20) unsigned` | YES |  |
| `actor_reference` | `bigint(20) unsigned` | YES |  |
| `event_type` | `varchar(48)` | NO |  |
| `basis` | `varchar(500)` | YES |  |
| `snapshot_json` | `longtext` | NO |  |
| `created_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `fk_report_event_actor` INDEX (`actor_id`)
- `idx_report_events` INDEX (`report_id`, `id`)
- `PRIMARY` UNIQUE (`id`)
- FK `fk_report_event_actor`: (actor_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_report_event_report`: (report_id) references `donation_reports` (id); DELETE CASCADE; UPDATE RESTRICT
- CHECK `CONSTRAINT_1`: `json_valid(`snapshot_json`)`

## donation_report_evidence

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `report_id` | `bigint(20) unsigned` | NO |  |
| `content_version` | `int(10) unsigned` | NO |  |
| `uploader_id` | `bigint(20) unsigned` | YES |  |
| `retired_version` | `int(10) unsigned` | YES |  |
| `stored_name` | `char(64)` | NO |  |
| `mime_type` | `varchar(40)` | NO |  |
| `original_ext` | `varchar(8)` | NO |  |
| `size_bytes` | `int(10) unsigned` | NO |  |
| `uploaded_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `idx_donation_proof_version` INDEX (`report_id`, `content_version`)
- `PRIMARY` UNIQUE (`id`)
- `uploader_id` INDEX (`uploader_id`)
- `uq_donation_proof_file` UNIQUE (`stored_name`)
- FK `donation_report_evidence_ibfk_1`: (report_id) references `donation_reports` (id); DELETE RESTRICT; UPDATE RESTRICT
- FK `donation_report_evidence_ibfk_2`: (uploader_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT

## donation_report_holds

**Retained source snapshot. Not authoritative after 032. Retirement requires separate approval.**

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `report_id` | `bigint(20) unsigned` | NO |  |
| `donor_id` | `bigint(20) unsigned` | NO |  |
| `started_at` | `datetime` | NO |  |
| `released_at` | `datetime` | YES |  |
| `released_by` | `bigint(20) unsigned` | YES |  |
| `release_basis` | `varchar(500)` | YES |  |
| `release_type` | `varchar(32)` | YES |  |

Indexes / uniqueness:

- `fk_report_hold_releaser` INDEX (`released_by`)
- `idx_report_hold_donor` INDEX (`donor_id`, `released_at`)
- `PRIMARY` UNIQUE (`report_id`)
- FK `fk_report_hold_donor`: (donor_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_report_hold_releaser`: (released_by) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_report_hold_report`: (report_id) references `donation_reports` (id); DELETE CASCADE; UPDATE RESTRICT

## donor_opportunities

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `request_id` | `bigint(20) unsigned` | NO |  |
| `donor_id` | `bigint(20) unsigned` | NO |  |
| `generation` | `int(10) unsigned` | NO |  |
| `notification_id` | `bigint(20) unsigned` | YES |  |
| `notified_at` | `datetime(6)` | NO |  |
| `due_at` | `datetime(6)` | NO |  |
| `status` | `enum('pending','answered','invalidated','acknowledged','superseded')` | NO |  |

Indexes / uniqueness:

- `fk_opportunity_notification` INDEX (`notification_id`)
- `idx_opportunity_donor` INDEX (`donor_id`, `status`, `due_at`)
- `idx_opportunity_due` INDEX (`status`, `due_at`, `id`)
- `PRIMARY` UNIQUE (`id`)
- `uq_opportunity_generation` UNIQUE (`request_id`, `donor_id`, `generation`)
- FK `fk_opportunity_donor`: (donor_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_opportunity_notification`: (notification_id) references `notifications` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_opportunity_request`: (request_id) references `blood_requests` (id); DELETE CASCADE; UPDATE RESTRICT

## donor_response_state

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `user_id` | `bigint(20) unsigned` | NO |  |
| `standby_started_at` | `datetime(6)` | YES |  |
| `consumed_through` | `datetime(6)` | YES |  |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`user_id`)
- FK `fk_response_state_user`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## email_attempts

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `mail_class` | `enum('account','normal','emergency')` | NO |  |
| `attempted_at` | `datetime(6)` | NO |  |

Indexes / uniqueness:

- `idx_email_attempts_window` INDEX (`attempted_at`, `mail_class`)
- `PRIMARY` UNIQUE (`id`)

## email_donor_reservations

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `attempt_id` | `bigint(20) unsigned` | NO |  |
| `donor_id` | `bigint(20) unsigned` | NO |  |
| `reserved_at` | `datetime(6)` | NO |  |
| `outcome` | `enum('outstanding','accepted','uncertain','released')` | NO |  |
| `updated_at` | `datetime(6)` | NO |  |

Indexes / uniqueness:

- `idx_donor_reservation_window` INDEX (`donor_id`, `reserved_at`, `outcome`)
- `PRIMARY` UNIQUE (`attempt_id`)
- FK `fk_donor_reservation_attempt`: (attempt_id) references `email_attempts` (id); DELETE RESTRICT; UPDATE RESTRICT

## email_outbox

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `notification_id` | `bigint(20) unsigned` | NO |  |
| `delivery_key` | `varchar(190)` | NO |  |
| `priority` | `enum('normal','emergency')` | NO |  |
| `status` | `enum('pending','leased','sent','failed','cancelled')` | NO |  |
| `attempts` | `smallint(5) unsigned` | NO |  |
| `next_attempt_at` | `datetime(6)` | NO |  |
| `lease_token` | `char(32)` | YES |  |
| `lease_expires_at` | `datetime(6)` | YES |  |
| `expires_at` | `datetime(6)` | NO |  |
| `last_error_code` | `varchar(60)` | YES |  |
| `cancel_reason` | `varchar(60)` | YES |  |
| `created_at` | `datetime(6)` | NO |  |
| `updated_at` | `datetime(6)` | NO |  |
| `sent_at` | `datetime(6)` | YES |  |

Indexes / uniqueness:

- `idx_email_outbox_due` INDEX (`status`, `priority`, `next_attempt_at`, `id`)
- `idx_email_outbox_lease` INDEX (`status`, `lease_expires_at`)
- `PRIMARY` UNIQUE (`id`)
- `uq_email_outbox_delivery` UNIQUE (`delivery_key`)
- `uq_email_outbox_notification` UNIQUE (`notification_id`)
- FK `fk_email_outbox_notification`: (notification_id) references `notifications` (id); DELETE CASCADE; UPDATE RESTRICT

## hospitals

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `int(10) unsigned` | NO | auto_increment |
| `name` | `varchar(150)` | NO |  |
| `type` | `enum('public','private')` | NO |  |
| `municipality_name` | `varchar(120)` | NO |  |
| `location_id` | `int(10) unsigned` | YES |  |
| `created_at` | `timestamp` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |

Indexes / uniqueness:

- `fk_hospitals_location` INDEX (`location_id`)
- `PRIMARY` UNIQUE (`id`)
- FK `fk_hospitals_location`: (location_id) references `bataan_locations` (id); DELETE SET NULL; UPDATE CASCADE

## matches

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `request_id` | `bigint(20) unsigned` | NO |  |
| `donor_id` | `bigint(20) unsigned` | NO |  |
| `generation` | `int(10) unsigned` | NO |  |
| `status` | `enum('POTENTIAL','NOTIFIED','RESPONDED','COMPLETED','CLOSED')` | NO |  |
| `distance_km` | `decimal(8,2)` | YES |  |
| `rank_score` | `decimal(10,4)` | YES |  |
| `created_at` | `timestamp` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |
| `responded_at` | `datetime` | YES |  |
| `offer_cycle` | `int(10) unsigned` | NO |  |

Indexes / uniqueness:

- `idx_matches_donor` INDEX (`donor_id`)
- `idx_matches_request_gen` INDEX (`request_id`, `generation`)
- `idx_matches_status` INDEX (`status`)
- `PRIMARY` UNIQUE (`id`)
- `uq_matches_request_donor` UNIQUE (`request_id`, `donor_id`)
- `uq_match_identity` UNIQUE (`id`, `donor_id`)
- FK `fk_matches_donor`: (donor_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_matches_request`: (request_id) references `blood_requests` (id); DELETE CASCADE; UPDATE RESTRICT

## member_documents

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `user_id` | `bigint(20) unsigned` | NO |  |
| `doc_type` | `enum('national_id','donor_card','parental_consent')` | NO |  |
| `stored_name` | `char(64)` | NO |  |
| `mime_type` | `varchar(100)` | NO |  |
| `original_ext` | `varchar(10)` | NO |  |
| `size_bytes` | `int(10) unsigned` | NO |  |
| `uploaded_at` | `timestamp` | NO |  |

Indexes / uniqueness:

- `idx_member_documents_user` INDEX (`user_id`)
- `idx_member_documents_user_type` INDEX (`user_id`, `doc_type`)
- `PRIMARY` UNIQUE (`id`)
- `uq_member_documents_stored_name` UNIQUE (`stored_name`)
- FK `fk_member_documents_user`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## messages

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `conversation_id` | `bigint(20) unsigned` | NO |  |
| `sender_id` | `bigint(20) unsigned` | NO |  |
| `body` | `text` | NO |  |
| `created_at` | `timestamp(3)` | NO |  |

Indexes / uniqueness:

- `fk_msg_sender` INDEX (`sender_id`)
- `idx_conv_messages` INDEX (`conversation_id`, `id`)
- `idx_messages_created` INDEX (`created_at`)
- `PRIMARY` UNIQUE (`id`)
- FK `fk_msg_conv`: (conversation_id) references `conversations` (id); DELETE CASCADE; UPDATE RESTRICT
- FK `fk_msg_sender`: (sender_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## notifications

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `user_id` | `bigint(20) unsigned` | NO |  |
| `type` | `varchar(60)` | NO |  |
| `title` | `varchar(150)` | NO |  |
| `body` | `varchar(500)` | NO |  |
| `related_type` | `varchar(40)` | YES |  |
| `related_id` | `bigint(20) unsigned` | YES |  |
| `dedup_key` | `varchar(120)` | YES |  |
| `generation` | `int(10) unsigned` | YES |  |
| `emailed_at` | `datetime` | YES |  |
| `read_at` | `datetime` | YES |  |
| `created_at` | `timestamp(3)` | NO |  |

Indexes / uniqueness:

- `idx_notifications_type` INDEX (`type`)
- `idx_notifications_user_read` INDEX (`user_id`, `read_at`)
- `PRIMARY` UNIQUE (`id`)
- `uq_notifications_dedup` UNIQUE (`dedup_key`, `generation`)
- FK `fk_notifications_user`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## password_resets

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `user_id` | `bigint(20) unsigned` | NO |  |
| `token_hash` | `char(64)` | NO |  |
| `expires_at` | `datetime` | NO |  |
| `used_at` | `datetime` | YES |  |
| `created_at` | `timestamp` | NO |  |

Indexes / uniqueness:

- `idx_password_resets_expires` INDEX (`expires_at`)
- `idx_password_resets_user` INDEX (`user_id`)
- `PRIMARY` UNIQUE (`id`)
- `uq_password_resets_token_hash` UNIQUE (`token_hash`)
- FK `fk_password_resets_user`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## pending_registrations

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `email` | `varchar(190)` | NO |  |
| `payload_json` | `longtext` | NO |  |
| `document_json` | `longtext` | YES |  |
| `code_hash` | `varchar(255)` | NO |  |
| `code_expires_at` | `datetime` | NO |  |
| `expires_at` | `datetime` | NO |  |
| `created_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `email` UNIQUE (`email`)
- `pending_expiry` INDEX (`expires_at`)
- `PRIMARY` UNIQUE (`id`)
- CHECK `CONSTRAINT_1`: `json_valid(`payload_json`)`
- CHECK `CONSTRAINT_2`: ``document_json` is null or json_valid(`document_json`)`

## schema_migrations

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `name` | `varchar(255)` | NO |  |
| `applied_at` | `timestamp` | NO |  |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`name`)

## system_settings

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `setting_key` | `varchar(80)` | NO |  |
| `value` | `varchar(255)` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |

Indexes / uniqueness:

- `PRIMARY` UNIQUE (`setting_key`)

## users

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `email` | `varchar(190)` | NO |  |
| `password_hash` | `varchar(255)` | YES |  |
| `first_name` | `varchar(50)` | NO |  |
| `middle_name` | `varchar(50)` | YES |  |
| `last_name` | `varchar(50)` | NO |  |
| `full_name` | `varchar(150)` | YES | STORED GENERATED = concat_ws(' ',`first_name`,nullif(`middle_name`,''),`last_name`) |
| `phone` | `varchar(30)` | YES |  |
| `role` | `enum('member','admin')` | NO |  |
| `chapter_id` | `tinyint(3) unsigned` | YES |  |
| `verification_status` | `enum('unverified','pending','verified','rejected')` | NO |  |
| `date_of_birth` | `date` | YES |  |
| `blood_type` | `enum('A+','A-','B+','B-','AB+','AB-','O+','O-')` | YES |  |
| `blood_type_source` | `enum('self_reported','donor_card')` | YES |  |
| `blood_type_verified` | `tinyint(1)` | NO |  |
| `donor_enrolled_at` | `datetime` | YES |  |
| `donor_availability` | `enum('available','unavailable','standby')` | YES |  |
| `last_verified_donation_at` | `datetime` | YES |  |
| `latitude` | `decimal(9,6)` | YES |  |
| `longitude` | `decimal(9,6)` | YES |  |
| `account_status` | `enum('active','deactivated')` | NO |  |
| `email_verified_at` | `datetime` | YES |  |
| `email_code_hash` | `varchar(255)` | YES |  |
| `email_code_expires_at` | `datetime` | YES |  |
| `deactivated_at` | `datetime` | YES |  |
| `created_at` | `timestamp` | NO |  |
| `updated_at` | `timestamp` | NO | on update current_timestamp() |
| `profile_picture` | `varchar(64)` | YES |  |
| `location_id` | `int(10) unsigned` | YES |  |

Indexes / uniqueness:

- `fk_users_location` INDEX (`location_id`)
- `idx_users_account_status` INDEX (`account_status`)
- `idx_users_availability` INDEX (`donor_availability`)
- `idx_users_chapter` INDEX (`chapter_id`)
- `idx_users_role` INDEX (`role`)
- `idx_users_verification` INDEX (`verification_status`)
- `PRIMARY` UNIQUE (`id`)
- `uq_users_email` UNIQUE (`email`)
- FK `fk_users_chapter`: (chapter_id) references `chapters` (id); DELETE RESTRICT; UPDATE CASCADE
- FK `fk_users_location`: (location_id) references `bataan_locations` (id); DELETE SET NULL; UPDATE CASCADE
- CHECK `chk_users_blood_source`: ``blood_type` is null and `blood_type_source` is null or `blood_type` is not null and `blood_type_source` is not null`
- CHECK `chk_users_blood_type`: ``blood_type` is null or `blood_type` in ('A+','A-','B+','B-','AB+','AB-','O+','O-')`
- CHECK `chk_users_blood_verified`: ``blood_type_verified` = 0 or `blood_type_verified` = 1 and `blood_type` is not null`
- CHECK `chk_users_deactivation`: ``account_status` = 'active' and `deactivated_at` is null or `account_status` = 'deactivated' and `deactivated_at` is not null`
- CHECK `chk_users_geo`: ``latitude` is null and `longitude` is null or `latitude` is not null and `longitude` is not null`

## user_presence

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `session_key` | `char(64)` | NO |  |
| `user_id` | `bigint(20) unsigned` | NO |  |
| `last_seen_at` | `datetime` | NO |  |

Indexes / uniqueness:

- `idx_presence_cleanup` INDEX (`last_seen_at`)
- `idx_presence_user_seen` INDEX (`user_id`, `last_seen_at`)
- `PRIMARY` UNIQUE (`session_key`)
- FK `fk_presence_user`: (user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## verification_decisions

| Column | Type | Nullable | Generated / extra |
|---|---|---|---|
| `id` | `bigint(20) unsigned` | NO | auto_increment |
| `target_user_id` | `bigint(20) unsigned` | NO |  |
| `officer_id` | `bigint(20) unsigned` | YES |  |
| `decision` | `enum('verified','rejected','re_review_requested','resubmitted')` | NO |  |
| `reason` | `varchar(500)` | YES |  |
| `created_at` | `timestamp(3)` | NO |  |

Indexes / uniqueness:

- `idx_verdec_officer` INDEX (`officer_id`)
- `idx_verdec_target` INDEX (`target_user_id`)
- `PRIMARY` UNIQUE (`id`)
- FK `fk_verdec_officer`: (officer_id) references `users` (id); DELETE SET NULL; UPDATE RESTRICT
- FK `fk_verdec_target`: (target_user_id) references `users` (id); DELETE CASCADE; UPDATE RESTRICT

## Consolidated report field meanings (032 targets only)

- `credit_recorded_at`: actual original credit recorded time, or NULL for uncredited claims/legacy confirmations. Independent of status.
- `credited_donor_id`, `credited_occurred_at`: generated nullable uniqueness keys; NULL for uncredited records.
- `hold_started_at`: original report hold start; NULL means no hold record existed.
- `hold_released_at`, `hold_released_by`, `hold_release_basis`, `hold_release_type`: independent release decision. Rejection alone never clears a hold. Reopening clears release fields while preserving the start; events preserve prior release history.
- Compatibility preference order preserves the former comma-list position exactly; it does not invent a new matching rule.

One report per match is enforced after 032. Correction requests are review cases, not automatic credit reversals. Multiple photo rows are retained separately, including prior versions. Current report snapshots and historic event JSON are intentional distinct time facts; operational relationships remain relational.
