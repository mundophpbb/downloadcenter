<?php
/**
 * MundophpBB Download Center - v1.0.101
 * Add experimental phpBB attachment-backed local uploads.
 */

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_101 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.101', '>=');
    }

    public static function depends_on()
    {
        return ['\mundophpbb\downloadcenter\migrations\v_1_0_100'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_downloadcenter_use_phpbb_attachments', 0]],
            ['config.update', ['mundophpbb_downloadcenter_version', '1.0.101']],
        ];
    }
}
