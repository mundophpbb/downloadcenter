<?php

namespace mundophpbb\downloadcenter\controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class download_controller
{
    protected $config;
    protected $helper;
    protected $user;
    protected $auth;
    protected $db;
    protected $root_path;
    protected $php_ext;

    public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, $root_path, $php_ext)
    {
        $this->config = $config;
        $this->helper = $helper;
        $this->user = $user;
        $this->auth = $auth;
        $this->db = $db;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
    }

    public function download($version_id)
    {
        $this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

        if (empty($this->config['mundophpbb_downloadcenter_enabled']))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
        }

        if (!$this->access_allowed(isset($this->config['mundophpbb_downloadcenter_download_access']) ? $this->config['mundophpbb_downloadcenter_download_access'] : 'registered'))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_DOWNLOAD'));
        }

        $min_posts = (int) $this->config['mundophpbb_downloadcenter_min_posts'];
        if ($min_posts > 0 && (int) $this->user->data['user_posts'] < $min_posts)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_MIN_POSTS_REQUIRED', $min_posts));
        }

        $sql = 'SELECT v.*, i.item_id, i.item_name, i.item_enabled, i.item_approved
            FROM ' . $this->table('downloadcenter_versions') . ' v
            INNER JOIN ' . $this->table('downloadcenter_items') . ' i ON i.item_id = v.item_id
            WHERE v.version_id = ' . (int) $version_id . '
                AND v.version_enabled = 1
                AND i.item_enabled = 1
                AND i.item_approved = 1';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_VERSION_NOT_FOUND'));
        }

        if ($row['download_type'] === 'external')
        {
            if (empty($row['download_url']))
            {
                trigger_error($this->user->lang('DOWNLOADCENTER_FILE_NOT_AVAILABLE'));
            }

            $this->register_download((int) $row['item_id'], (int) $row['version_id']);
            return new RedirectResponse($row['download_url']);
        }

        $file = $this->local_file_path($row['download_file']);
        if (!$row['download_file'] || !is_file($file))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_FILE_NOT_AVAILABLE'));
        }

        $this->register_download((int) $row['item_id'], (int) $row['version_id']);

        $response = new BinaryFileResponse($file);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($file));

        return $response;
    }


    public function screenshot($screenshot_id)
    {
        $this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

        if (empty($this->config['mundophpbb_downloadcenter_enabled']))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
        }

        if (!$this->access_allowed(isset($this->config['mundophpbb_downloadcenter_view_access']) ? $this->config['mundophpbb_downloadcenter_view_access'] : 'all'))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
        }

        $sql = 'SELECT s.*, i.item_enabled, i.item_approved
            FROM ' . $this->table('downloadcenter_screenshots') . ' s
            INNER JOIN ' . $this->table('downloadcenter_items') . ' i ON i.item_id = s.item_id
            WHERE s.screenshot_id = ' . (int) $screenshot_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_NOT_FOUND'));
        }

        if ((!$row['item_enabled'] || !$row['item_approved']) && !$this->is_admin())
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_NOT_FOUND'));
        }

        $file = $this->screenshot_file_path($row['image_file']);
        if (!$row['image_file'] || !is_file($file))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_NOT_FOUND'));
        }

        $response = new BinaryFileResponse($file);
        $mime = function_exists('mime_content_type') ? mime_content_type($file) : '';
        if ($mime)
        {
            $response->headers->set('Content-Type', $mime);
        }
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($file));

        return $response;
    }



    public function item_image($file_name)
    {
        $this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

        if (empty($this->config['mundophpbb_downloadcenter_enabled']))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
        }

        if (!$this->access_allowed(isset($this->config['mundophpbb_downloadcenter_view_access']) ? $this->config['mundophpbb_downloadcenter_view_access'] : 'all'))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
        }

        $file_name = basename((string) $file_name);
        if ($file_name === '')
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_ITEM_IMAGE_NOT_FOUND'));
        }

        $file = $this->item_image_file_path($file_name);
        if (!is_file($file))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_ITEM_IMAGE_NOT_FOUND'));
        }

        $response = new BinaryFileResponse($file);
        $mime = function_exists('mime_content_type') ? mime_content_type($file) : '';
        if ($mime)
        {
            $response->headers->set('Content-Type', $mime);
        }
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($file));

        return $response;
    }

    protected function screenshot_file_path($file_name)
    {
        return $this->root_path . 'files/mundophpbb/downloadcenter/screenshots/' . basename((string) $file_name);
    }

    protected function item_image_file_path($file_name)
    {
        return $this->root_path . 'files/mundophpbb/downloadcenter/item_images/' . basename((string) $file_name);
    }


    protected function local_file_path($file_name)
    {
        return $this->root_path . 'files/mundophpbb/downloadcenter/' . basename((string) $file_name);
    }

    protected function access_allowed($mode)
    {
        switch ($mode)
        {
            case 'admin':
                return $this->is_admin();

            case 'registered':
                return (int) $this->user->data['user_id'] !== ANONYMOUS;

            case 'all':
            default:
                return true;
        }
    }

    protected function is_admin()
    {
        return ((int) $this->user->data['user_type'] === USER_FOUNDER) || $this->auth->acl_get('a_board');
    }

    protected function register_download($item_id, $version_id)
    {
        $now = time();
        $user_id = (int) $this->user->data['user_id'];
        $user_ip = $this->user->ip;
        $duplicate_window = isset($this->config['mundophpbb_downloadcenter_duplicate_window']) ? (int) $this->config['mundophpbb_downloadcenter_duplicate_window'] : 3600;
        $window = $now - max(0, $duplicate_window);

        $sql = 'SELECT download_id
            FROM ' . $this->table('downloadcenter_downloads') . '
            WHERE version_id = ' . (int) $version_id . '
                AND user_id = ' . $user_id . '
                AND user_ip = \'' . $this->db->sql_escape($user_ip) . '\'
                AND download_time >= ' . (int) $window;
        $result = $this->db->sql_query_limit($sql, 1);
        $recent = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($recent)
        {
            return;
        }

        $sql_ary = [
            'item_id'       => $item_id,
            'version_id'    => $version_id,
            'user_id'       => $user_id,
            'user_ip'       => $user_ip,
            'download_time' => $now,
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table('downloadcenter_downloads') . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
        $this->db->sql_query('UPDATE ' . $this->table('downloadcenter_versions') . ' SET version_downloads = version_downloads + 1 WHERE version_id = ' . (int) $version_id);
        $this->db->sql_query('UPDATE ' . $this->table('downloadcenter_items') . ' SET item_downloads = item_downloads + 1 WHERE item_id = ' . (int) $item_id);
        $this->add_log('download', $this->user->lang('DOWNLOADCENTER_LOG_DOWNLOAD_REGISTERED', (string) $version_id), $item_id, $version_id);
    }

    protected function add_log($action, $message, $item_id = 0, $version_id = 0)
    {
        $sql_ary = [
            'user_id' => (int) $this->user->data['user_id'],
            'username' => isset($this->user->data['username']) ? (string) $this->user->data['username'] : '',
            'item_id' => (int) $item_id,
            'version_id' => (int) $version_id,
            'log_action' => (string) $action,
            'log_message' => (string) $message,
            'user_ip' => (string) $this->user->ip,
            'log_time' => time(),
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table('downloadcenter_logs') . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
    }

    protected function table($name)
    {
        global $table_prefix;
        return $table_prefix . $name;
    }
}
