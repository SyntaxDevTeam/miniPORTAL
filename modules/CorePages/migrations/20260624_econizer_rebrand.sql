UPDATE homepage_sections
SET content_html = REPLACE(content_html, 'Econify', 'Econizer')
WHERE content_html LIKE '%Econify%';

UPDATE homepage_section_items
SET
    label = REPLACE(label, 'Econify', 'Econizer'),
    title = REPLACE(title, 'Econify', 'Econizer'),
    content = REPLACE(content, 'Econify', 'Econizer'),
    button_label = REPLACE(button_label, 'Econify', 'Econizer'),
    button_url = REPLACE(button_url, '/econify', '/econizer')
WHERE label LIKE '%Econify%'
   OR title LIKE '%Econify%'
   OR content LIKE '%Econify%'
   OR button_label LIKE '%Econify%'
   OR button_url LIKE '%/econify%';

UPDATE core_pages
SET
    title = REPLACE(title, 'Econify', 'Econizer'),
    eyebrow = REPLACE(eyebrow, 'Econify', 'Econizer'),
    summary = REPLACE(summary, 'Econify', 'Econizer'),
    content = REPLACE(content, 'Econify', 'Econizer'),
    meta_description = REPLACE(meta_description, 'Econify', 'Econizer')
WHERE title LIKE '%Econify%'
   OR eyebrow LIKE '%Econify%'
   OR summary LIKE '%Econify%'
   OR content LIKE '%Econify%'
   OR meta_description LIKE '%Econify%';
