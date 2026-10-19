-- Only through retire_donation_sources.php after verified full backup and writer fencing.
-- Authoritative state already lives in donation_reports and compatibility_pairs.
DROP TABLE donation_credits, donation_report_holds, compatibility_matrix;
