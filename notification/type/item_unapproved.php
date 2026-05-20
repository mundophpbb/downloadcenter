<?php

namespace mundophpbb\downloadcenter\notification\type;

class item_unapproved extends item_base
{
    public function get_type()
    {
        return 'mundophpbb.downloadcenter.notification.type.item_unapproved';
    }

    public function get_title()
    {
        return $this->user->lang('DOWNLOADCENTER_NOTIFICATION_ITEM_UNAPPROVED', $this->item_name());
    }

    public function get_url()
    {
        $item_id = (int) $this->get_data('item_id');
        return $this->build_frontend_url('downloadcenter/mine/edit/' . $item_id);
    }
}
