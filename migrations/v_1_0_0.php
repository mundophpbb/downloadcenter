<?php

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.0', '>=');
    }

    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v330\v330'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'downloadcenter_categories' => [
                    'COLUMNS' => [
                        'category_id'      => ['UINT', null, 'auto_increment'],
                        'category_name'    => ['VCHAR:255', ''],
                        'category_desc'    => ['TEXT', ''],
                        'category_slug'    => ['VCHAR:255', ''],
                        'category_order'   => ['UINT', 0],
                        'category_enabled' => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'category_id',
                    'KEYS' => [
                        'category_slug'  => ['INDEX', 'category_slug'],
                        'category_order' => ['INDEX', 'category_order'],
                    ],
                ],

                $this->table_prefix . 'downloadcenter_items' => [
                    'COLUMNS' => [
                        'item_id'         => ['UINT', null, 'auto_increment'],
                        'category_id'     => ['UINT', 0],
                        'user_id'         => ['UINT', 0],
                        'topic_id'        => ['UINT', 0],
                        'item_name'       => ['VCHAR:255', ''],
                        'item_slug'       => ['VCHAR:255', ''],
                        'item_short_desc' => ['VCHAR:500', ''],
                        'item_desc'       => ['MTEXT_UNI', ''],
                        'item_icon'       => ['VCHAR:255', ''],
                        'item_enabled'    => ['BOOL', 1],
                        'item_approved'   => ['BOOL', 1],
                        'item_downloads'  => ['UINT', 0],
                        'item_created'    => ['TIMESTAMP', 0],
                        'item_updated'    => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'item_id',
                    'KEYS' => [
                        'category_id' => ['INDEX', 'category_id'],
                        'item_slug'   => ['INDEX', 'item_slug'],
                        'user_id'     => ['INDEX', 'user_id'],
                        'topic_id'    => ['INDEX', 'topic_id'],
                        'approved'    => ['INDEX', 'item_approved'],
                        'enabled'     => ['INDEX', 'item_enabled'],
                    ],
                ],

                $this->table_prefix . 'downloadcenter_versions' => [
                    'COLUMNS' => [
                        'version_id'        => ['UINT', null, 'auto_increment'],
                        'item_id'           => ['UINT', 0],
                        'version_number'    => ['VCHAR:64', ''],
                        'phpbb_version'     => ['VCHAR:255', ''],
                        'php_version'       => ['VCHAR:255', ''],
                        'version_changelog' => ['MTEXT_UNI', ''],
                        'download_type'     => ['VCHAR:20', 'external'],
                        'download_url'      => ['TEXT', ''],
                        'download_file'     => ['VCHAR:255', ''],
                        'file_size'         => ['VCHAR:64', ''],
                        'version_downloads' => ['UINT', 0],
                        'version_enabled'   => ['BOOL', 1],
                        'version_created'   => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'version_id',
                    'KEYS' => [
                        'item_id'         => ['INDEX', 'item_id'],
                        'version_enabled' => ['INDEX', 'version_enabled'],
                    ],
                ],

                $this->table_prefix . 'downloadcenter_downloads' => [
                    'COLUMNS' => [
                        'download_id'   => ['UINT', null, 'auto_increment'],
                        'item_id'       => ['UINT', 0],
                        'version_id'    => ['UINT', 0],
                        'user_id'       => ['UINT', 0],
                        'user_ip'       => ['VCHAR:45', ''],
                        'download_time' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'download_id',
                    'KEYS' => [
                        'item_id'       => ['INDEX', 'item_id'],
                        'version_id'    => ['INDEX', 'version_id'],
                        'user_id'       => ['INDEX', 'user_id'],
                        'download_time' => ['INDEX', 'download_time'],
                    ],
                ],

                $this->table_prefix . 'downloadcenter_logs' => [
                    'COLUMNS' => [
                        'log_id'      => ['UINT', null, 'auto_increment'],
                        'user_id'     => ['UINT', 0],
                        'username'    => ['VCHAR:255', ''],
                        'item_id'     => ['UINT', 0],
                        'version_id'  => ['UINT', 0],
                        'log_action'  => ['VCHAR:64', ''],
                        'log_message' => ['MTEXT_UNI', ''],
                        'user_ip'     => ['VCHAR:45', ''],
                        'log_time'    => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => [
                        'user_id'    => ['INDEX', 'user_id'],
                        'item_id'    => ['INDEX', 'item_id'],
                        'version_id' => ['INDEX', 'version_id'],
                        'action'     => ['INDEX', 'log_action'],
                        'log_time'   => ['INDEX', 'log_time'],
                    ],
                ],

                $this->table_prefix . 'downloadcenter_screenshots' => [
                    'COLUMNS' => [
                        'screenshot_id' => ['UINT', null, 'auto_increment'],
                        'item_id'       => ['UINT', 0],
                        'image_file'    => ['VCHAR:255', ''],
                        'image_caption' => ['VCHAR:255', ''],
                        'image_order'   => ['UINT', 0],
                        'image_created' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'screenshot_id',
                    'KEYS' => [
                        'item_id'     => ['INDEX', 'item_id'],
                        'image_order' => ['INDEX', 'image_order'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'downloadcenter_screenshots',
                $this->table_prefix . 'downloadcenter_logs',
                $this->table_prefix . 'downloadcenter_downloads',
                $this->table_prefix . 'downloadcenter_versions',
                $this->table_prefix . 'downloadcenter_items',
                $this->table_prefix . 'downloadcenter_categories',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_downloadcenter_version', '1.0.64']],
            ['config.add', ['mundophpbb_downloadcenter_enabled', 1]],
            ['config.add', ['mundophpbb_downloadcenter_min_posts', 0]],
            ['config.add', ['mundophpbb_downloadcenter_duplicate_window', 3600]],
            ['config.add', ['mundophpbb_downloadcenter_support_forum_id', 0]],
            ['config.add', ['mundophpbb_downloadcenter_allow_submissions', 1]],
            ['config.add', ['mundophpbb_downloadcenter_require_rules_accept', 1]],
            ['config.add', ['mundophpbb_downloadcenter_rules_url', '']],
            ['config.add', ['mundophpbb_downloadcenter_view_access', 'all']],
            ['config.add', ['mundophpbb_downloadcenter_download_access', 'registered']],
            ['config.add', ['mundophpbb_downloadcenter_submit_access', 'registered']],
            ['config.add', ['mundophpbb_downloadcenter_notifications_enabled', 1]],
            ['config.add', ['mundophpbb_downloadcenter_public_per_page', 12]],
            ['config.add', ['mundophpbb_downloadcenter_acp_per_page', 20]],
            ['config.add', ['mundophpbb_downloadcenter_logs_per_page', 50]],
            ['config.add', ['mundophpbb_downloadcenter_allowed_extensions', 'zip,rar,7z,tar,gz,tgz,bz2,pdf,txt']],
            ['config.add', ['mundophpbb_downloadcenter_max_upload_mb', '20']],

            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_DOWNLOADCENTER_TITLE'
            ]],

            ['module.add', [
                'acp',
                'ACP_DOWNLOADCENTER_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\downloadcenter\\acp\\main_module',
                    'modes' => [
                        'dashboard',
                        'settings',
                        'categories',
                        'items',
                        'pending',
                        'files',
                        'logs',
                        'diagnostics',
                        'integrity',
                    ],
                ],
            ]],
        ];
    }
}
