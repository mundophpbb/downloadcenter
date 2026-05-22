<?php
/**
 * MundophpBB Download Center - v1.0.102
 * Link phpBB attachment-backed downloads to support topics.
 */

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_102 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.102', '>=');
    }

    public static function depends_on()
    {
        return ['\mundophpbb\downloadcenter\migrations\v_1_0_101'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['mundophpbb_downloadcenter_version', '1.0.102']],
        ];
    }
}
