<?php

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_69 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.69', '>=');
    }

    public static function depends_on()
    {
        return ['\\mundophpbb\\downloadcenter\\migrations\\v_1_0_68'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_downloadcenter_require_rules_accept', 1]],
            ['config.add', ['mundophpbb_downloadcenter_rules_url', '']],
            ['config.update', ['mundophpbb_downloadcenter_version', '1.0.69']],
        ];
    }
}
