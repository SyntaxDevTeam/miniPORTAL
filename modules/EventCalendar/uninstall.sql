DROP TABLE IF EXISTS event_calendar_notifications;
DROP TABLE IF EXISTS event_calendar_history;
DROP TABLE IF EXISTS event_calendar_tasks;
DROP TABLE IF EXISTS event_calendar_events;
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name IN ('events.view','events.manage','events.publish'));
DELETE FROM permissions WHERE name IN ('events.view','events.manage','events.publish');
