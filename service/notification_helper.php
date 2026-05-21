<?php

namespace mundophpbb\downloadcenter\service;

class notification_helper
{
    protected $db;
    protected $user;
    protected $table_prefix;

    public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix)
    {
        $this->db = $db;
        $this->user = $user;
        $this->table_prefix = $table_prefix;
    }

    public function notify_author_status($author_id, $item_id, $item_name, $approved)
    {
        $author_id = (int) $author_id;
        if ($author_id <= 1)
        {
            return;
        }

        $type = $approved ? 'mundophpbb.downloadcenter.notification.type.item_approved' : 'mundophpbb.downloadcenter.notification.type.item_unapproved';
        $this->add_board_notification($type, $author_id, $item_id, [
            'item_id' => (int) $item_id,
            'item_name' => (string) $item_name,
            'approved' => (bool) $approved,
        ]);
    }

    public function notify_pending_item($item_id, $item_name, $author_id = 0)
    {
        $admin_ids = $this->get_admin_user_ids((int) $author_id);
        foreach ($admin_ids as $admin_id)
        {
            $this->add_board_notification('mundophpbb.downloadcenter.notification.type.item_pending', $admin_id, (int) $item_id, [
                'item_id' => (int) $item_id,
                'item_name' => (string) $item_name,
                'author_id' => (int) $author_id,
            ]);
        }
    }

    public function purge_item_notifications($item_id)
    {
        $type_ids = $this->get_type_ids([
            'mundophpbb.downloadcenter.notification.type.item_approved',
            'mundophpbb.downloadcenter.notification.type.item_unapproved',
            'mundophpbb.downloadcenter.notification.type.item_pending',
        ]);

        if (empty($type_ids))
        {
            return;
        }

        $sql = 'DELETE FROM ' . $this->table_prefix . 'notifications
            WHERE item_id = ' . (int) $item_id . '
                AND ' . $this->db->sql_in_set('notification_type_id', $type_ids);
        $this->db->sql_query($sql);
    }

    protected function add_board_notification($type_name, $user_id, $item_id, array $data)
    {
        $type_id = $this->ensure_type($type_name);
        if ($type_id <= 0 || (int) $user_id <= 1)
        {
            return;
        }

        $sql_ary = [
            'notification_type_id' => (int) $type_id,
            'item_id' => (int) $item_id,
            'item_parent_id' => 0,
            'user_id' => (int) $user_id,
            'notification_read' => 0,
            'notification_time' => time(),
            'notification_data' => serialize($data),
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'notifications ' . $this->db->sql_build_array('INSERT', $sql_ary));
    }

    protected function ensure_type($type_name)
    {
        $table = $this->table_prefix . 'notification_types';
        $sql = 'SELECT notification_type_id
            FROM ' . $table . "
            WHERE notification_type_name = '" . $this->db->sql_escape($type_name) . "'";
        $result = $this->db->sql_query($sql);
        $type_id = (int) $this->db->sql_fetchfield('notification_type_id');
        $this->db->sql_freeresult($result);

        if ($type_id > 0)
        {
            return $type_id;
        }

        $sql_ary = [
            'notification_type_name' => (string) $type_name,
            'notification_type_enabled' => 1,
        ];
        $this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

        return (int) $this->db->sql_nextid();
    }

    protected function get_type_ids(array $type_names)
    {
        $ids = [];
        $escaped = [];
        foreach ($type_names as $type_name)
        {
            $escaped[] = (string) $type_name;
        }

        if (empty($escaped))
        {
            return $ids;
        }

        $sql = 'SELECT notification_type_id
            FROM ' . $this->table_prefix . 'notification_types
            WHERE ' . $this->db->sql_in_set('notification_type_name', $escaped);
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['notification_type_id'];
        }
        $this->db->sql_freeresult($result);

        return $ids;
    }

    protected function get_admin_user_ids($exclude_user_id = 0)
    {
        $ids = [];
        $users_table = defined('USERS_TABLE') ? USERS_TABLE : $this->table_prefix . 'users';
        $groups_table = defined('GROUPS_TABLE') ? GROUPS_TABLE : $this->table_prefix . 'groups';
        $user_group_table = defined('USER_GROUP_TABLE') ? USER_GROUP_TABLE : $this->table_prefix . 'user_group';

        $sql = 'SELECT DISTINCT u.user_id
            FROM ' . $users_table . ' u
            LEFT JOIN ' . $user_group_table . ' ug ON ug.user_id = u.user_id AND ug.user_pending = 0
            LEFT JOIN ' . $groups_table . " g ON g.group_id = ug.group_id
            WHERE (u.user_type = " . (defined('USER_FOUNDER') ? USER_FOUNDER : 3) . " OR g.group_name = 'ADMINISTRATORS')
                AND u.user_id <> " . (int) $exclude_user_id;
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            if ((int) $row['user_id'] > 1)
            {
                $ids[(int) $row['user_id']] = (int) $row['user_id'];
            }
        }
        $this->db->sql_freeresult($result);

        return array_values($ids);
    }
}
