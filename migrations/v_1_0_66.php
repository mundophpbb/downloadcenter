<?php

namespace mundophpbb\downloadcenter\migrations;

class v_1_0_66 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_downloadcenter_version'])
            && version_compare($this->config['mundophpbb_downloadcenter_version'], '1.0.66', '>=');
    }

    public static function depends_on()
    {
        return ['\\mundophpbb\\downloadcenter\\migrations\\v_1_0_65'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['mundophpbb_downloadcenter_version', '1.0.66']],
        ];
    }
}
