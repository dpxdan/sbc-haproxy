INSERT INTO cron_settings (name, command, exec_interval, status, file_path, creation_date, last_modified_date)
VALUES (
    'Cleanup Channels',
    'minutes',
    15,
    0,
    'wget --no-check-certificate -O - -q {BASE_URL}CleanupChannels/index/',
    NOW(),
    NOW()
);

INSERT INTO `system` (`id`, `name`, `display_name`, `value`, `field_type`, `comment`, `timestamp`, `reseller_id`, `is_display`, `group_title`, `sub_group`, `field_rules`)
VALUES (NULL, 'cleanup_channels', 'Cleanup Channels', '21600', 'default_system_input', 'Cleanup channels interval (in seconds).', '2019-05-24 19:03:37', 0, 0, 'calls', 'General', '');
