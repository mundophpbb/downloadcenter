<?php
/**
 * Permissões da Central de Downloads.
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

$lang = array_merge($lang, [
    'ACL_U_DOWNLOADCENTER_VIEW' => 'Pode visualizar a Central de Downloads',
    'ACL_U_DOWNLOADCENTER_DOWNLOAD' => 'Pode baixar arquivos da Central de Downloads',
    'ACL_U_DOWNLOADCENTER_SUBMIT' => 'Pode enviar itens para a Central de Downloads',
    'ACL_M_DOWNLOADCENTER_APPROVE' => 'Pode aprovar itens da Central de Downloads',
    'ACL_A_DOWNLOADCENTER_MANAGE' => 'Pode administrar a Central de Downloads',
]);
