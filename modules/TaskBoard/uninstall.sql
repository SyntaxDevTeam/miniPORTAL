DELETE role_permissions FROM role_permissions JOIN permissions ON permissions.id = role_permissions.permission_id WHERE permissions.name IN ('tasks.use', 'tasks.manage', 'tasks.publish');
DELETE FROM permissions WHERE name IN ('tasks.use', 'tasks.manage', 'tasks.publish');
DROP TABLE IF EXISTS task_board_notifications;
DROP TABLE IF EXISTS task_board_assignees;
DROP TABLE IF EXISTS task_board_history;
DROP TABLE IF EXISTS task_board_attachments;
DROP TABLE IF EXISTS task_board_comments;
DROP TABLE IF EXISTS task_board_tasks;
