INSERT INTO modules_config
    (module_id, version, status, is_protected, data_preserved, installed_at, created_at, updated_at)
SELECT
    'econizer', version, status, is_protected, data_preserved, installed_at, created_at, updated_at
FROM modules_config
WHERE module_id = 'econify'
  AND NOT EXISTS (
      SELECT 1 FROM modules_config AS current_module WHERE current_module.module_id = 'econizer'
  );

UPDATE module_migrations
SET module_id = 'econizer'
WHERE module_id = 'econify';

DELETE FROM modules_config
WHERE module_id = 'econify';

UPDATE system_settings
SET setting_value = REPLACE(
    REPLACE(setting_value, 'Econify', 'Econizer'),
    'econify',
    'econizer'
)
WHERE setting_key IN ('public_navigation', 'dashboard_widgets');

UPDATE auth_events
SET event_type = REPLACE(event_type, 'econify', 'econizer')
WHERE event_type LIKE 'econify%';

UPDATE auth_events_archive
SET event_type = REPLACE(event_type, 'econify', 'econizer')
WHERE event_type LIKE 'econify%';
