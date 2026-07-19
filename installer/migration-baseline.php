<?php

declare(strict_types=1);

return array (
  'core' =>
  array (
    '20260614_module_manager.sql' => '383038f0e160d020593d9f21194d72b8756f2a549da9c6b5007d50105c58d16a',
    '20260615_module_lifecycle.sql' => '061db2b82550b83f88080680cc7f7d89f64f3fbc7d9ef20b1e0fdd948af9ac88',
    '20260615_system_settings.sql' => 'c24f36c148ce8aaec81114bd5d4f4fcc85bf205360db8b85d00e89ba7bee8824',
    '20260616_audit_archive.sql' => 'e817676e01f98aac047a3bde5b69b78b40a2270486b9a5d479ee1120a9d7841f',
    '20260617_system_settings_text.sql' => 'f06b0fa4cc132fd24c3861f028c11d284468ef6f9d5600a7fd7a26acf132b42b',
    '20260624_econizer_module_identity.sql' => 'e6c0ffafdfa67b175870582c59c38e87cf2d7b60e5861fb33da49ed8950e7160',
  ),
  'modules' =>
  array (
    'articles' =>
    array (
      '20260614_content_formats.sql' => '8cf217a562250822b679c74f9c79131b944fab5951e9ae2245f0f2dd9ccbe8c9',
    ),
    'build_explorer' =>
    array (
      '20260619_local_artifact_upload.sql' => 'cf1bdb3b0c557eed634b61dcdd0da4f0ca04e3ec36541ed8954f14952e6a2aa1',
      '20260620_ci_build_history.sql' => '7a22e16b49b418672d365675a990516793abae8ae57de6b771449ffb01918e8e',
    ),
    'core_auth' =>
    array (
      '20260615_roles_and_user_approval.sql' => 'c4a2022af25a7d3febe63e8e97069f3357be1e51e0fc5cf14d1322780aa8a18b',
      '20260615_system_logs_permission.sql' => 'dbd7adf01c4feb09d884d2e4484294409679aec402bb1a19b53eca786a507e3b',
      '20260617_database_view_permission.sql' => 'ee56a79d852e9ba52bbc0e07c81c59d4c791f8b2383cd083fa6779cf16abc386',
      '20260620_owner_and_operational_roles.sql' => '2046f785565f34d95263a7bb6890577fcf647308a57cabf03328197c408a05c5',
    ),
    'core_pages' =>
    array (
      '20260613_homepage_section_items.sql' => '0d35bb2368e2532ac66380dea381a1c29fa9a61edb58664c91bb8ed3191a8a0c',
      '20260613_homepage_sections.sql' => '2d584a5e4bfba0da55de72c7803cd5f50a9ee7e541270ac8a0fd946f4f61f9d6',
      '20260614_contact_layout.sql' => '15d076e1115f5a9dbb8c958299ffed3664fb2a3c3067f63b63759c3a0abc1666',
      '20260614_content_formats.sql' => '1f79e000c51ed00d245ead8c1f00e771fa1fc980cd80cb3759e065c546ad2184',
      '20260614_page_documents.sql' => '6b42024dd987a4059dfd9645e59aa4580a8acee5bfa5f1c8bab17ea372fb774b',
      '20260614_page_eyebrow.sql' => '90920d51405f7417dd40cdfe31f1e65b3312bba5d182e022b6732b89a70975ba',
      '20260621_homepage_hero_acrostic.sql' => '659e8617d07d730a03588d4b7c4df24209c987438800bd36196e7ccc3e254464',
      '20260621_miniportal_project_page.sql' => '88b0985bcfe3c0d80d817b3b95683da122bd445b86d7a9bae7ae79372f8fc95e',
      '20260624_econizer_rebrand.sql' => 'd481dca4fa2ea725faad88e3984b8815f73a5196c802243f4f5a11781fb1326d',
      '20260629_public_english_defaults.sql' => 'cacb178ae8e7b5b01e7cd1a445c9c18b05e84d52832fe6dc2536f357716279de',
      '20260705_miniportal_legal_pages.sql' => '42d726f028ba4857516e2b3feeefaba4986d32b78825d7595a120aa131c1b53a',
      '20260715_homepage_multiple_buttons.sql' => 'f932d4fe79f832afb001b975e2780ac1e6ee5404e5f68facb2a6639f61640836',
    ),
    'database_manager' =>
    array (
      '20260617_database_manage_permission.sql' => '542dc12fb8466b304fbef277cba25646e8d74628fb050a00fe8898af9c010636',
    ),
    'media_library' =>
    array (
      '20260702_tinify_usage.sql' => 'af840b07e6f509b1864edb0883896599c837d6b9c5ec66c0c8921a8cabfc82e8',
    ),
    'plugin_translator' =>
    array (
      '20260618_public_translation_workflow.sql' => 'e60051f0fc5defa8d5099a399a65171c9f72e38f851272dea0299ad586be9beb',
      '20260618_translation_language_and_ux.sql' => '2f5fb764506e0ef06294613f4b0cb6783379f0641348cfe24c39971633f7f41a',
      '20260619_translation_page_link_and_manager_actions.sql' => '9afb6cbea25758ba1baaea60959bd57aa165bbff7f0d39c86afdb7af186ae1c0',
      '20260619_translation_project_catalog.sql' => '41cf657359dc41eddfa9121afa2cd48b541f5bc1bd1ef3a3284183cd52728d52',
    ),
    'projects' =>
    array (
      '20260620_remove_required_summary.sql' => '6e67fb51e99250a446401be0fe63a6fbd440a991810f4a4f0861dcb734ff6f05',
      '20260702_project_integration_fields.sql' => '9e44d39e185fc1ca1cb0af8f2d039160a02d6f411b3e7edd4c93094ad30ba18f',
    ),
    'remote_terminal' =>
    array (
    ),
    'system_admin' =>
    array (
    ),
    'team' =>
    array (
      '20260704_rich_public_profiles.sql' => '5fcd818a3275340d8ae5e8a528f207bc5f69c499c75e02df037fafc8a0afeaf4',
    ),
    'uptime' =>
    array (
      '20260710_heartbeat_monitors.sql' => '3c10431315ecb81f4e2c6cde731eb6ad5bcc2d35580ff8b50d8d3c74396dc2ea',
    ),
    'user_profile' =>
    array (
    ),
    'widgets' =>
    array (
      '20260629_widget_content_format.sql' => '959b991fe2b6ee3ae79637311d3b9c771ba830bf359ed0318af39e1b49bebeab',
      '20260629_widget_uptime_type.sql' => '9b27ece582c90a5541717a05937a8b33e9e816a6742013b4cf50db4be501527d',
      '20260710_terminal_english_copy.sql' => 'c462a2a92fcf168aaec701aff3a90a42a5a09801b907925428dbdc42e5eb37de',
      '20260710_widget_definitions.sql' => '46240906ad20bd6fd176ecb912382340387aa1aa0017610f6ab9f6817e608848',
    ),
    'wikipedia' =>
    array (
    ),
  ),
);
