-- Post-donation restriction is cooldown only. Preserve its duration and anchors.
UPDATE system_settings SET value='0' WHERE setting_key='standby_hours';
