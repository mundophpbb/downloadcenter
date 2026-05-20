<?php
/**
 * MundophpBB Download Center - v1.0.100
 * Restore rules URL configuration and fix rules page request handling.
 */

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_100 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.100', '>=');
    }

    public static function depends_on()
    {
        return ['\mundophpbb\downloadcenter\migrations\v_1_0_99'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_downloadcenter_rules_url', '']],
            ['config.add', ['mundophpbb_downloadcenter_rules_topic_id', 0]],
            ['config.update', ['mundophpbb_downloadcenter_version', '1.0.100']],
        ];
    }
}
