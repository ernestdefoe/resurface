<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'discussion.buoyBump' => Group::MEMBER_ID,
    'discussion.buoyBumpAny' => Group::MODERATOR_ID,
]);
