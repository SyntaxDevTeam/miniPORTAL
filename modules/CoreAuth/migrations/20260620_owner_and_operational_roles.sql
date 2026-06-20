INSERT IGNORE INTO roles (name, label, is_system) VALUES
    ('owner', 'Owner', 1),
    ('maintainer', 'Maintainer', 1),
    ('auditor', 'Audytor', 1),
    ('support', 'Support', 1);

UPDATE roles SET label = 'Redaktor', is_system = 1 WHERE name = 'editor';
UPDATE roles SET label = 'Administrator', is_system = 1 WHERE name = 'administrator';

INSERT IGNORE INTO permissions (name, label) VALUES
    ('*', 'Pełny dostęp właściciela');

DELETE role_permissions
FROM role_permissions
JOIN roles ON roles.id = role_permissions.role_id
WHERE roles.name IN ('owner', 'administrator', 'maintainer', 'editor', 'auditor', 'support');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = '*'
WHERE roles.name = 'owner';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'administrator' AND permissions.name <> '*';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN (
    'admin.access', 'users.view', 'users.manage', 'roles.view', 'logs.view',
    'modules.view', 'modules.toggle', 'database.view', 'settings.manage',
    'projects.view', 'projects.manage', 'builds.view', 'builds.manage', 'team.manage'
)
WHERE roles.name = 'maintainer';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN (
    'admin.access',
    'pages.view', 'pages.create', 'pages.edit', 'pages.publish',
    'articles.view', 'articles.create', 'articles.edit', 'articles.publish',
    'wikipedia.view', 'wikipedia.create', 'wikipedia.edit', 'wikipedia.publish',
    'projects.view', 'projects.manage', 'team.manage',
    'plugin_translator.use', 'plugin_translator.review'
)
WHERE roles.name = 'editor';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN (
    'admin.access', 'pages.view', 'articles.view', 'users.view', 'roles.view',
    'logs.view', 'modules.view', 'database.view', 'projects.view', 'builds.view',
    'wikipedia.view'
)
WHERE roles.name = 'auditor';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN (
    'admin.access', 'pages.view', 'articles.view', 'users.view', 'projects.view',
    'builds.view', 'wikipedia.view', 'wikipedia.create', 'wikipedia.edit',
    'plugin_translator.use', 'plugin_translator.review'
)
WHERE roles.name = 'support';

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT candidate.user_id, owner_role.id
FROM (
    SELECT users.id AS user_id
    FROM users
    JOIN user_roles ON user_roles.user_id = users.id
    JOIN roles ON roles.id = user_roles.role_id
    WHERE users.status = 'active' AND roles.name = 'administrator'
    ORDER BY users.created_at ASC, users.id ASC
    LIMIT 1
) AS candidate
JOIN roles AS owner_role ON owner_role.name = 'owner';

DELETE administrator_assignment
FROM user_roles AS administrator_assignment
JOIN roles AS administrator_role
  ON administrator_role.id = administrator_assignment.role_id
 AND administrator_role.name = 'administrator'
JOIN user_roles AS owner_assignment
  ON owner_assignment.user_id = administrator_assignment.user_id
JOIN roles AS owner_role
  ON owner_role.id = owner_assignment.role_id
 AND owner_role.name = 'owner';
