DROP TABLE IF EXISTS build_retention_settings;
DROP TABLE IF EXISTS build_artifact_compatible_platforms;
DROP TABLE IF EXISTS build_artifact_game_versions;
DROP TABLE IF EXISTS build_artifacts;
DROP TABLE IF EXISTS build_releases;
DROP TABLE IF EXISTS build_explorer_project_settings;
DROP TABLE IF EXISTS project_builds;
DELETE role_permissions FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name LIKE 'builds.%';
DELETE FROM permissions WHERE name LIKE 'builds.%';
