<?php
/**
 * MundophpBB Download Center - consolidated final migration.
 *
 * This migration replaces the long 1.0.64-1.0.99 incremental chain with a
 * single compatibility migration. It keeps upgrades safe by checking whether
 * schema/configuration elements already exist before changing them.
 */

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_99 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.99', '>=');
    }

    public static function depends_on()
    {
        return ['\\mundophpbb\\downloadcenter\\migrations\\v_1_0_0'];
    }

    public function update_data()
    {
        $modes = [
            'dashboard',
            'settings',
            'categories',
            'items',
            'pending',
            'files',
            'integrity',
            'diagnostics',
            'logs',
        ];

        return [
            ['custom', [[$this, 'ensure_current_version_column']]],
            ['custom', [[$this, 'ensure_final_config']]],

            ['permission.add', ['u_downloadcenter_view']],
            ['permission.add', ['u_downloadcenter_download']],
            ['permission.add', ['u_downloadcenter_submit']],
            ['permission.add', ['m_downloadcenter_approve']],
            ['permission.add', ['a_downloadcenter_manage']],
            ['permission.permission_set', ['GUESTS', 'u_downloadcenter_view', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_downloadcenter_view', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_downloadcenter_download', 'group']],
            ['permission.permission_set', ['REGISTERED', 'u_downloadcenter_submit', 'group']],
            ['permission.permission_set', ['GLOBAL_MODERATORS', 'm_downloadcenter_approve', 'group']],
            ['permission.permission_set', ['ADMINISTRATORS', 'u_downloadcenter_view', 'group']],
            ['permission.permission_set', ['ADMINISTRATORS', 'u_downloadcenter_download', 'group']],
            ['permission.permission_set', ['ADMINISTRATORS', 'u_downloadcenter_submit', 'group']],
            ['permission.permission_set', ['ADMINISTRATORS', 'm_downloadcenter_approve', 'group']],
            ['permission.permission_set', ['ADMINISTRATORS', 'a_downloadcenter_manage', 'group']],

            ['module.remove', [
                'acp',
                'ACP_DOWNLOADCENTER_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\downloadcenter\\acp\\main_module',
                    'modes' => $modes,
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_DOWNLOADCENTER_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\downloadcenter\\acp\\main_module',
                    'modes' => $modes,
                ],
            ]],

            ['custom', [[$this, 'mark_final_version']]],
        ];
    }

    public function ensure_current_version_column()
    {
        $table = $this->table_prefix . 'downloadcenter_items';

        if (!$this->db_tools->sql_column_exists($table, 'item_current_version_id'))
        {
            $this->db_tools->sql_column_add($table, 'item_current_version_id', ['UINT', 0]);
        }

        if (!$this->db_tools->sql_index_exists($table, 'current_version_id'))
        {
            $this->db_tools->sql_create_index($table, 'current_version_id', ['item_current_version_id']);
        }
    }

    public function ensure_final_config()
    {
        $defaults = [
            'mundophpbb_downloadcenter_enabled' => 1,
            'mundophpbb_downloadcenter_min_posts' => 0,
            'mundophpbb_downloadcenter_duplicate_window' => 3600,
            'mundophpbb_downloadcenter_support_forum_id' => 0,
            'mundophpbb_downloadcenter_allow_submissions' => 1,
            'mundophpbb_downloadcenter_view_access' => 'all',
            'mundophpbb_downloadcenter_download_access' => 'registered',
            'mundophpbb_downloadcenter_submit_access' => 'registered',
            'mundophpbb_downloadcenter_notifications_enabled' => 1,
            'mundophpbb_downloadcenter_public_per_page' => 12,
            'mundophpbb_downloadcenter_acp_per_page' => 20,
            'mundophpbb_downloadcenter_logs_per_page' => 50,
            'mundophpbb_downloadcenter_allowed_extensions' => 'zip,rar,7z,tar,gz,tgz,bz2,pdf,txt',
            'mundophpbb_downloadcenter_max_upload_mb' => '20',
            'mundophpbb_downloadcenter_permission_mode' => 'global',
            'mundophpbb_downloadcenter_show_public_stats' => 1,
            'mundophpbb_downloadcenter_feed_enabled' => 1,
            'mundophpbb_downloadcenter_rate_limit_count' => 0,
            'mundophpbb_downloadcenter_rate_limit_window' => 60,
        ];

        foreach ($defaults as $name => $value)
        {
            if (!isset($this->config[$name]))
            {
                $this->config->set($name, $value);
            }
        }
    }

    public function mark_final_version()
    {
        $this->config->set('mundophpbb_downloadcenter_version', '1.0.99');
    }
}
