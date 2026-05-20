<?php

namespace mundophpbb\downloadcenter\notification\type;

abstract class item_base extends \phpbb\notification\type\base
{
    public static $notification_option = [
        'group' => 'NOTIFICATION_GROUP_MISCELLANEOUS',
        'lang' => 'DOWNLOADCENTER_NOTIFICATION_OPTION',
    ];

    public static function get_item_id($data)
    {
        return (int) $data['item_id'];
    }

    public static function get_item_parent_id($data)
    {
        return 0;
    }

    public function find_users_for_notification($data, $options = [])
    {
        return [];
    }

    public function users_to_query()
    {
        return [];
    }

    public function get_url()
    {
        $item_id = (int) $this->get_data('item_id');
        return $this->build_frontend_url('downloadcenter/item/' . $item_id);
    }

    protected function build_frontend_url($path)
    {
        $path = ltrim((string) $path, '/');

        if (function_exists('generate_board_url'))
        {
            return generate_board_url() . '/app.php/' . $path;
        }

        return 'app.php/' . $path;
    }

    public function get_email_template()
    {
        return false;
    }

    public function get_email_template_variables()
    {
        return [];
    }

    protected function item_name()
    {
        return (string) $this->get_data('item_name');
    }
}
