<?php

use oat\taoDacSimple\model\PermissionsServiceFactory;
use oat\taoDacSimple\model\SyncPermissionsStrategy;

return new PermissionsServiceFactory(
    [
        PermissionsServiceFactory::OPTION_SAVE_STRATEGY => SyncPermissionsStrategy::class
    ]
);
