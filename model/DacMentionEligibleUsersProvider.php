<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model;

use core_kernel_classes_Resource;
use oat\tao\model\user\MentionEligibleUsersProviderInterface;

class DacMentionEligibleUsersProvider implements MentionEligibleUsersProviderInterface
{
    private RolePrivilegeRetriever $rolePrivilegeRetriever;
    private \tao_models_classes_RoleService $roleService;

    public function __construct(
        RolePrivilegeRetriever $rolePrivilegeRetriever,
        ?\tao_models_classes_RoleService $roleService = null
    ) {
        $this->rolePrivilegeRetriever = $rolePrivilegeRetriever;
        $this->roleService = $roleService ?? \tao_models_classes_RoleService::singleton();
    }

    public function getEligibleUserUris(string $resourceUri): ?array
    {
        $accessRights = $this->rolePrivilegeRetriever->retrieveByResourceIds([$resourceUri]);

        if ($accessRights === []) {
            return [];
        }

        $eligibleUsers = [];

        foreach (array_keys($accessRights) as $identityUri) {
            $usersFromRole = $this->roleService->getUsers(new core_kernel_classes_Resource($identityUri));

            if ($usersFromRole !== []) {
                foreach ($usersFromRole as $userUri) {
                    $eligibleUsers[$userUri] = true;
                }

                continue;
            }

            $eligibleUsers[$identityUri] = true;
        }

        return array_keys($eligibleUsers);
    }
}
