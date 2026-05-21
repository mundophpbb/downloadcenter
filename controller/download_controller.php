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

        if (!$this->can_download())
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
            INNER JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
            WHERE v.version_id = ' . (int) $version_id . '
                AND v.version_enabled = 1
                AND i.item_enabled = 1
                AND i.item_approved = 1
                AND c.category_enabled = 1';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_VERSION_NOT_FOUND'));
        }

        if ($this->is_rate_limited((int) $row['version_id']))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_RATE_LIMITED'));
        }

        if ($row['download_type'] === 'external')
        {
            $external_url = trim((string) $row['download_url']);
            if ($external_url === '' || !preg_match('#^https?://#i', $external_url) || !filter_var($external_url, FILTER_VALIDATE_URL))
            {
                trigger_error($this->user->lang('DOWNLOADCENTER_FILE_NOT_AVAILABLE'));
            }

            $this->register_download((int) $row['item_id'], (int) $row['version_id']);
            $response = new RedirectResponse($external_url);
            $this->apply_no_store_headers($response);

            return $response;
        }

        $attachment = $this->get_phpbb_attachment_file($row['download_file']);
        $file = $attachment ? $attachment['path'] : $this->local_file_path($row['download_file']);
        if (!$row['download_file'] || !is_file($file))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_FILE_NOT_AVAILABLE'));
        }

        $this->register_download((int) $row['item_id'], (int) $row['version_id']);
        if ($attachment)
        {
            $this->increment_phpbb_attachment_downloads((int) $attachment['attach_id']);
        }

        $response = new BinaryFileResponse($file);
        $this->prepare_binary_response($response, $file);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $download_filename = $attachment ? (string) $attachment['real_filename'] : $this->download_filename($row, $file);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $download_filename,
            $this->ascii_filename_fallback($download_filename)
        );
        $this->apply_private_download_headers($response);

        return $response;
    }


    public function screenshot($screenshot_id)
    {
        $this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

        if (empty($this->config['mundophpbb_downloadcenter_enabled']))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
        }

        if (!$this->can_view())
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
        }

        $sql = 'SELECT s.*, i.item_enabled, i.item_approved, c.category_enabled
            FROM ' . $this->table('downloadcenter_screenshots') . ' s
            INNER JOIN ' . $this->table('downloadcenter_items') . ' i ON i.item_id = s.item_id
            INNER JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
            WHERE s.screenshot_id = ' . (int) $screenshot_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_NOT_FOUND'));
        }

        if ((!$row['item_enabled'] || !$row['item_approved'] || !$row['category_enabled']) && !$this->is_admin())
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_NOT_FOUND'));
        }

        $file = $this->screenshot_file_path($row['image_file']);
        if (!$row['image_file'] || !is_file($file))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_NOT_FOUND'));
        }

        $response = new BinaryFileResponse($file);
        $this->prepare_binary_response($response, $file);
        $mime = $this->safe_mime_type($file, 'image/jpeg');
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($file),
            $this->ascii_filename_fallback(basename($file))
        );
        $this->apply_private_asset_headers($response);

        return $response;
    }



    public function item_image($file_name)
    {
        $this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

        if (empty($this->config['mundophpbb_downloadcenter_enabled']))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
        }

        if (!$this->can_view())
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
        $this->prepare_binary_response($response, $file);
        $mime = $this->safe_mime_type($file, 'image/jpeg');
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($file),
            $this->ascii_filename_fallback(basename($file))
        );
        $this->apply_private_asset_headers($response);

        return $response;
    }

    protected function prepare_binary_response(BinaryFileResponse $response, $file)
    {
        if (method_exists($response, 'setAutoEtag'))
        {
            $response->setAutoEtag();
        }

        if (method_exists($response, 'setAutoLastModified'))
        {
            $response->setAutoLastModified();
        }

        if (is_file($file))
        {
            $response->headers->set('Content-Length', (string) filesize($file));
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Accept-Ranges', 'bytes');
    }

    protected function apply_private_download_headers($response)
    {
        $this->apply_no_store_headers($response);
        $response->headers->set('X-Download-Options', 'noopen');
    }

    protected function apply_private_asset_headers($response)
    {
        $response->headers->set('Cache-Control', 'private, max-age=3600, must-revalidate');
        $response->headers->set('Pragma', '');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
    }

    protected function apply_no_store_headers($response)
    {
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
    }

    protected function download_filename(array $row, $file)
    {
        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        $name = isset($row['item_name']) ? (string) $row['item_name'] : 'download';
        $version = isset($row['version_number']) ? (string) $row['version_number'] : '';

        $base = trim($name . ($version !== '' ? '-' . $version : ''));
        $base = $this->clean_filename_part($base);

        if ($base === '')
        {
            $base = 'download';
        }

        return $extension !== '' ? $base . '.' . $extension : $base;
    }

    protected function clean_filename_part($value)
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $value = preg_replace('#[\\/\?%\*:|"<>]+#u', '-', $value);
        $value = preg_replace('#\s+#u', ' ', $value);
        $value = trim($value, " .\t\n\r\0\x0B-_");

        return $value;
    }

    protected function ascii_filename_fallback($filename)
    {
        $filename = $this->clean_filename_part($filename);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = $extension !== '' ? substr($filename, 0, -(strlen($extension) + 1)) : $filename;

        if (function_exists('iconv'))
        {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
            if ($converted !== false)
            {
                $base = $converted;
            }
        }

        $base = preg_replace('#[^A-Za-z0-9._-]+#', '-', $base);
        $base = trim($base, '.-_');
        if ($base === '')
        {
            $base = 'download';
        }

        $extension = preg_replace('#[^A-Za-z0-9]+#', '', (string) $extension);

        return $extension !== '' ? $base . '.' . strtolower($extension) : $base;
    }

    protected function safe_mime_type($file, $fallback = 'application/octet-stream')
    {
        $mime = function_exists('mime_content_type') ? @mime_content_type($file) : '';
        if (!is_string($mime) || $mime === '')
        {
            return $fallback;
        }

        if (strpos($mime, "\n") !== false || strpos($mime, "\r") !== false)
        {
            return $fallback;
        }

        return $mime;
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

    protected function get_phpbb_attachment_file($file_name)
    {
        if (!defined('ATTACHMENTS_TABLE') || !preg_match('/^attach:(\d+)$/', (string) $file_name, $matches))
        {
            return false;
        }

        $sql = 'SELECT attach_id, physical_filename, real_filename, filesize, mimetype
            FROM ' . ATTACHMENTS_TABLE . '
            WHERE attach_id = ' . (int) $matches[1];
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return false;
        }

        $upload_path = isset($this->config['upload_path']) ? (string) $this->config['upload_path'] : 'files';
        $row['path'] = $this->root_path . trim($upload_path, '/\\') . '/' . basename((string) $row['physical_filename']);

        return $row;
    }

    protected function increment_phpbb_attachment_downloads($attach_id)
    {
        if (!defined('ATTACHMENTS_TABLE') || $attach_id <= 0)
        {
            return;
        }

        $this->db->sql_query('UPDATE ' . ATTACHMENTS_TABLE . '
            SET download_count = download_count + 1
            WHERE attach_id = ' . (int) $attach_id);
    }


    protected function effective_access_mode($config_key, $default, $respect_view_access = true)
    {
        $mode = isset($this->config[$config_key]) ? (string) $this->config[$config_key] : $default;
        $mode = $this->normalise_access_mode($mode, $default);

        if ($respect_view_access)
        {
            $view_mode = isset($this->config['mundophpbb_downloadcenter_view_access']) ? (string) $this->config['mundophpbb_downloadcenter_view_access'] : 'all';
            $mode = $this->most_restrictive_access_mode($view_mode, $mode);
        }

        return $mode;
    }

    protected function normalise_access_mode($mode, $default = 'registered')
    {
        return in_array($mode, ['all', 'registered', 'admin'], true) ? $mode : $default;
    }

    protected function most_restrictive_access_mode($first, $second)
    {
        $weights = ['all' => 0, 'registered' => 1, 'admin' => 2];
        $first = $this->normalise_access_mode($first, 'all');
        $second = $this->normalise_access_mode($second, 'registered');

        return ($weights[$first] >= $weights[$second]) ? $first : $second;
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

    protected function use_acl_permissions()
    {
        return isset($this->config['mundophpbb_downloadcenter_permission_mode']) && $this->config['mundophpbb_downloadcenter_permission_mode'] === 'acl';
    }

    protected function can_view()
    {
        if ($this->use_acl_permissions())
        {
            return $this->is_admin() || $this->auth->acl_get('u_downloadcenter_view');
        }

        return $this->access_allowed(isset($this->config['mundophpbb_downloadcenter_view_access']) ? $this->config['mundophpbb_downloadcenter_view_access'] : 'all');
    }

    protected function can_download()
    {
        $min_posts = (int) $this->config['mundophpbb_downloadcenter_min_posts'];

        if ($this->use_acl_permissions())
        {
            return $this->can_view()
                && ($this->is_admin() || $this->auth->acl_get('u_downloadcenter_download'))
                && ($min_posts <= 0 || (int) $this->user->data['user_posts'] >= $min_posts);
        }

        return $this->access_allowed($this->effective_access_mode('mundophpbb_downloadcenter_download_access', 'registered', true))
            && ($min_posts <= 0 || (int) $this->user->data['user_posts'] >= $min_posts);
    }

    protected function is_admin()
    {
        return ((int) $this->user->data['user_type'] === USER_FOUNDER) || $this->auth->acl_get('a_board') || $this->auth->acl_get('a_downloadcenter_manage');
    }

    protected function is_rate_limited($version_id)
    {
        $limit = isset($this->config['mundophpbb_downloadcenter_rate_limit_count']) ? (int) $this->config['mundophpbb_downloadcenter_rate_limit_count'] : 0;
        if ($limit <= 0 || $this->is_admin())
        {
            return false;
        }

        $window = isset($this->config['mundophpbb_downloadcenter_rate_limit_window']) ? max(10, (int) $this->config['mundophpbb_downloadcenter_rate_limit_window']) : 60;
        $since = time() - $window;
        $user_id = (int) $this->user->data['user_id'];
        $user_ip = $this->db->sql_escape((string) $this->user->ip);

        $sql = 'SELECT COUNT(*) AS total
            FROM ' . $this->table('downloadcenter_downloads') . '
            WHERE download_time >= ' . (int) $since . '
                AND (user_id = ' . $user_id . " OR user_ip = '" . $user_ip . "')";
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        return $total >= $limit;
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
