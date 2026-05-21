<?php

namespace mundophpbb\downloadcenter\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\\mundophpbb\\downloadcenter\\acp\\main_module',
            'title'    => 'ACP_DOWNLOADCENTER_TITLE',
            'language' => 'info_acp_downloadcenter',
            'modes'    => [
                'dashboard' => [
                    'title' => 'ACP_DOWNLOADCENTER_DASHBOARD',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'diagnostics' => [
                    'title' => 'ACP_DOWNLOADCENTER_DIAGNOSTICS',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'integrity' => [
                    'title' => 'ACP_DOWNLOADCENTER_INTEGRITY',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'settings' => [
                    'title' => 'ACP_DOWNLOADCENTER_SETTINGS',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'categories' => [
                    'title' => 'ACP_DOWNLOADCENTER_CATEGORIES',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'items' => [
                    'title' => 'ACP_DOWNLOADCENTER_ITEMS',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'pending' => [
                    'title' => 'ACP_DOWNLOADCENTER_PENDING_ITEMS',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'files' => [
                    'title' => 'ACP_DOWNLOADCENTER_FILES',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
                'logs' => [
                    'title' => 'ACP_DOWNLOADCENTER_LOGS',
                    'auth'  => 'ext_mundophpbb/downloadcenter && acl_a_board',
                    'cat'   => ['ACP_DOWNLOADCENTER_TITLE'],
                ],
            ],
        ];
    }
}
