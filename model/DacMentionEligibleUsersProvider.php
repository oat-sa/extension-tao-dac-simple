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

use common_Logger;
use core_kernel_classes_Resource;
use oat\tao\model\user\MentionEligibleUsersProviderInterface;
use tao_models_classes_UserService;

class DacMentionEligibleUsersProvider implements MentionEligibleUsersProviderInterface
{
    private DataBaseAccess $dataBaseAccess;
    private tao_models_classes_UserService $userService;

    public function __construct(
        DataBaseAccess $dataBaseAccess,
        tao_models_classes_UserService $userService
    ) {
        $this->dataBaseAccess = $dataBaseAccess;
        $this->userService = $userService;
    }

    /**
     * @param list<array{id: string, login: string, displayName: string}> $candidates
     * @return list<array{id: string, login: string, displayName: string}>
     */
    public function filterCandidatesForResource(string $resourceUri, array $candidates): array
    {
        common_Logger::d(sprintf(
            '[DacMentionEligibleUsersProvider] Start filter: resource=%s candidates=%d',
            $resourceUri,
            count($candidates)
        ));

        if ($candidates === []) {
            common_Logger::d('[DacMentionEligibleUsersProvider] Filter result: candidates=0, allowed=0');
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
            common_Logger::d('[DacMentionEligibleUsersProvider] Filter result: identities=0, allowed=0');
            return [];
        }

        $permissionsByResource = $this->dataBaseAccess->getPermissionsByUsersAndResources(
            array_keys($identityUris),
            [$resourceUri]
        );
        $permissionsByIdentity = $permissionsByResource[$resourceUri] ?? [];
        common_Logger::d(sprintf(
            '[DacMentionEligibleUsersProvider] ACL lookup: identities=%d matched-identities=%d',
            count($identityUris),
            count($permissionsByIdentity)
        ));

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

        common_Logger::d(sprintf(
            '[DacMentionEligibleUsersProvider] Filter result: candidates=%d allowed=%d',
            count($candidates),
            count($filtered)
        ));

        return $filtered;
    }
}
