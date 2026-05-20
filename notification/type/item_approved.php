<?php

namespace mundophpbb\downloadcenter\notification\type;

class item_approved extends item_base
{
    public function get_type()
    {
        return 'mundophpbb.downloadcenter.notification.type.item_approved';
    }

    public function get_title()
    {
        return $this->user->lang('DOWNLOADCENTER_NOTIFICATION_ITEM_APPROVED', $this->item_name());
    }
}
