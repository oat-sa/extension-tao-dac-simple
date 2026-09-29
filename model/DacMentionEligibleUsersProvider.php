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
use tao_models_classes_RoleService;
use tao_models_classes_UserService;

class DacMentionEligibleUsersProvider implements MentionEligibleUsersProviderInterface
{
    private RolePrivilegeRetriever $rolePrivilegeRetriever;
    private tao_models_classes_RoleService $roleService;
    private DataBaseAccess $dataBaseAccess;
    private tao_models_classes_UserService $userService;
    /** @var array<string, list<string>> */
    private array $eligibleUsersByResource = [];

    public function __construct(
        RolePrivilegeRetriever $rolePrivilegeRetriever,
        tao_models_classes_RoleService $roleService,
        DataBaseAccess $dataBaseAccess,
        tao_models_classes_UserService $userService
    ) {
        $this->rolePrivilegeRetriever = $rolePrivilegeRetriever;
        $this->roleService = $roleService;
        $this->dataBaseAccess = $dataBaseAccess;
        $this->userService = $userService;
    }

    /**
     * @param list<array{id: string, login: string, displayName: string}> $candidates
     * @return list<array{id: string, login: string, displayName: string}>
     */
    public function filterCandidatesForResource(string $resourceUri, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $identityUris = [];
        $identityUrisPerUser = [];

        foreach ($candidates as $candidate) {
            $userUri = isset($candidate['id']) ? trim((string) $candidate['id']) : '';
            if ($userUri === '' || isset($identityUrisPerUser[$userUri])) {
                continue;
            }

            $candidateIdentityUris = [$userUri => true];

            foreach ($this->userService->getUserRoles(new core_kernel_classes_Resource($userUri)) as $roleResource) {
                if (!$roleResource instanceof core_kernel_classes_Resource) {
                    continue;
                }

                $candidateIdentityUris[$roleResource->getUri()] = true;
            }

            $identityUrisPerUser[$userUri] = array_keys($candidateIdentityUris);

            foreach ($identityUrisPerUser[$userUri] as $identityUri) {
                $identityUris[$identityUri] = true;
            }
        }

        if ($identityUris === []) {
            return [];
        }

        $permissionsByResource = $this->dataBaseAccess->getPermissionsByUsersAndResources(
            array_keys($identityUris),
            [$resourceUri]
        );
        $permissionsByIdentity = $permissionsByResource[$resourceUri] ?? [];

        $filtered = [];
        foreach ($candidates as $candidate) {
            $userUri = isset($candidate['id']) ? trim((string) $candidate['id']) : '';
            if ($userUri === '' || !isset($identityUrisPerUser[$userUri])) {
                continue;
            }

            foreach ($identityUrisPerUser[$userUri] as $identityUri) {
                if (isset($permissionsByIdentity[$identityUri])) {
                    $filtered[] = $candidate;
                    break;
                }
            }
        }

        return $filtered;
    }

    public function getEligibleUserUris(string $resourceUri): ?array
    {
        if (array_key_exists($resourceUri, $this->eligibleUsersByResource)) {
            return $this->eligibleUsersByResource[$resourceUri];
        }

        $accessRights = $this->rolePrivilegeRetriever->retrieveByResourceIds([$resourceUri]);

        if ($accessRights === []) {
            $this->eligibleUsersByResource[$resourceUri] = [];

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

        $this->eligibleUsersByResource[$resourceUri] = array_keys($eligibleUsers);

        return $this->eligibleUsersByResource[$resourceUri];
    }
}
