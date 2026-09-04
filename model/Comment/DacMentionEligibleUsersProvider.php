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

namespace oat\taoDacSimple\model\Comment;

use core_kernel_classes_Resource;
use oat\generis\model\data\Ontology;
use oat\generis\model\GenerisRdf;
use oat\tao\model\user\MentionEligibleUsersProviderInterface;
use oat\taoDacSimple\model\PermissionProvider;
use oat\taoDacSimple\model\RolePrivilegeRetriever;
use tao_models_classes_UserService;

/**
 * Restricts mention candidates to users with effective READ on the resource (DAC).
 */
final class DacMentionEligibleUsersProvider implements MentionEligibleUsersProviderInterface
{
    private const EFFECTIVE_READ_PRIVILEGES = [
        PermissionProvider::PERMISSION_READ,
        PermissionProvider::PERMISSION_WRITE,
        PermissionProvider::PERMISSION_GRANT,
        'OWNER',
    ];

    private RolePrivilegeRetriever $rolePrivilegeRetriever;
    private Ontology $ontology;
    private tao_models_classes_UserService $userService;

    public function __construct(
        RolePrivilegeRetriever $rolePrivilegeRetriever,
        Ontology $ontology,
        tao_models_classes_UserService $userService
    ) {
        $this->rolePrivilegeRetriever = $rolePrivilegeRetriever;
        $this->ontology = $ontology;
        $this->userService = $userService;
    }

    /**
     * @return list<string>
     */
    public function getEligibleUserUris(string $resourceUri): ?array
    {
        $grants = $this->rolePrivilegeRetriever->retrieveByResourceIds([$resourceUri]);
        $userUris = [];
        $roleUris = [];
        $roleClass = $this->ontology->getClass(GenerisRdf::CLASS_ROLE);

        foreach ($grants as $subjectUri => $privileges) {
            if (!$this->hasEffectiveRead($privileges)) {
                continue;
            }

            $identity = $this->ontology->getResource($subjectUri);
            if ($identity->isInstanceOf($roleClass)) {
                $roleUris[] = $subjectUri;
            } else {
                $userUris[$subjectUri] = true;
            }
        }

        foreach ($roleUris as $roleUri) {
            $members = $this->userService->getAllUsers(
                [
                    'recursive' => true,
                    'like' => false,
                ],
                [
                    GenerisRdf::PROPERTY_USER_ROLES => $roleUri,
                ]
            );

            foreach ($members as $member) {
                if ($member instanceof core_kernel_classes_Resource) {
                    $userUris[$member->getUri()] = true;
                }
            }
        }

        return array_keys($userUris);
    }

    /**
     * @param list<string> $privileges
     */
    private function hasEffectiveRead(array $privileges): bool
    {
        return count(array_intersect($privileges, self::EFFECTIVE_READ_PRIVILEGES)) > 0;
    }
}
