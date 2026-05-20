<?php

namespace mundophpbb\downloadcenter\notification\type;

class item_pending extends item_base
{
    public function get_type()
    {
        return 'mundophpbb.downloadcenter.notification.type.item_pending';
    }

    public function get_title()
    {
        return $this->user->lang('DOWNLOADCENTER_NOTIFICATION_ITEM_PENDING', $this->item_name());
    }

    public function get_url()
    {
        return $this->build_frontend_url('downloadcenter');
    }
}
