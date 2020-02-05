<?php

use oat\taoDacSimple\model\PermissionServiceFactory;
use oat\taoDacSimple\model\SyncPermissionsStrategy;

return new PermissionServiceFactory(
    [
        PermissionServiceFactory::OPTION_SAVE_STRATEGY => SyncPermissionsStrategy::class
    ]
);
