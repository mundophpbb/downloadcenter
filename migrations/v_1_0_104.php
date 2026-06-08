<?php
/**
 * MundophpBB Download Center - v1.0.104
 * Strip 4-byte Unicode characters before saving text on utf8/utf8mb3 databases.
 */

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_104 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.104', '>=');
    }

    public static function depends_on()
    {
        return ['\\mundophpbb\\downloadcenter\\migrations\\v_1_0_103'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['mundophpbb_downloadcenter_version', '1.0.104']],
        ];
    }
}
