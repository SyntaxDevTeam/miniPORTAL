UPDATE homepage_sections
SET
    title = 'Code that powers communities.',
    content_html = '<p>We build server plugins, Discord automation, mobile apps and modular web systems that can grow without rewriting everything from scratch.</p>',
    button_label = 'Explore projects'
WHERE section_key = 'top';

UPDATE homepage_sections
SET
    eyebrow = '01 / Featured work',
    title = 'Independent projects. One shared quality standard.',
    content_html = '<p>Each product is a separate module, built on proven foundations.</p>'
WHERE section_key = 'projects';

UPDATE homepage_sections
SET
    eyebrow = '02 / Technology',
    title = 'We choose tools for the problem.',
    content_html = '<p>No unnecessary abstraction, with a focus on security and maintainability.</p>'
WHERE section_key = 'stack';

UPDATE homepage_sections
SET
    eyebrow = 'Contact and support',
    title = 'Let us stay in touch.',
    content_html = '<p>Choose the best channel for the topic: Discord for quick contact, GitHub for code and issues, or e-mail for direct conversation.</p>'
WHERE section_key = 'contact';

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET
    item.content = 'A moderation system for Paper and Folia: punishments, action history, permissions and API.',
    item.button_label = 'Ask about this project'
WHERE section.section_key = 'projects' AND item.title = 'PunisherX';

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET
    item.content = 'A shared library for messages, configuration, logging and integrations.',
    item.button_label = 'View the foundations'
WHERE section.section_key = 'projects' AND item.title = 'SyntaxCore';

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET
    item.content = 'A Discord bot connecting community economy, tasks, shop and a web panel.',
    item.button_label = 'Explore features'
WHERE section.section_key = 'projects' AND item.title IN ('Econizer', 'Econify');

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET
    item.content = 'Plain PHP, swappable themes, local ACL and independent content modules.',
    item.button_label = 'Discover the system'
WHERE section.section_key = 'projects' AND item.title = 'miniPORTAL';

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET item.label = 'SERVERS', item.content = 'Kotlin, Adventure and modern server environments.'
WHERE section.section_key = 'stack' AND item.title = 'Paper & Folia';

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET item.label = 'AUTOMATION', item.content = 'Bots, federated sign-in, ACL and API integrations.'
WHERE section.section_key = 'stack' AND item.title = 'Discord & OAuth';

UPDATE homepage_section_items AS item
JOIN homepage_sections AS section ON section.id = item.section_id
SET item.content = 'PHP 8.4+, Medoo, MySQL and a swappable Theme layer.'
WHERE section.section_key = 'stack' AND item.title = 'PHP & CrudApp';
