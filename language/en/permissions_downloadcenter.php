<?php
/**
 * Download Center permissions.
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

$lang = array_merge($lang, [
    'ACL_U_DOWNLOADCENTER_VIEW' => 'Can view the Download Center',
    'ACL_U_DOWNLOADCENTER_DOWNLOAD' => 'Can download files from the Download Center',
    'ACL_U_DOWNLOADCENTER_SUBMIT' => 'Can submit items to the Download Center',
    'ACL_M_DOWNLOADCENTER_APPROVE' => 'Can approve Download Center items',
    'ACL_A_DOWNLOADCENTER_MANAGE' => 'Can manage the Download Center',
]);
