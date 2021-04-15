<?php

declare(strict_types=1);

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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoDacSimple\model;

use oat\oatbox\service\ConfigurableService;

class RolePrivilegeRetriever extends ConfigurableService
{
    /**
     * Sample data to be returned:
     * [
     *    'http://www.tao.lu/Ontologies/TAO.rdf#BackOfficeRole' => [
     *        'GRANT',
     *        'READ',
     *        'WRITE'
     *    ],
     *    'http://www.tao.lu/Ontologies/TAO.rdf#ItemAuthor' => [
     *        'GRANT',
     *        'READ'
     *    ],
     * ]
     */
    public function retrieveByResourceIds(array $resourceIds): array
    {
        $results = $this->getDataBaseAccess()
            ->getUsersWithPermissions($resourceIds);

        $permissions = [];

        foreach ($results as $result) {
            $user = $result['user_id'];

            if (!isset($permissions[$user])) {
                $permissions[$user] = [];
            }

            $permissions[$user][] = $result['privilege'];
        }

        return $permissions;
    }

    private function getDataBaseAccess(): DataBaseAccess
    {
        return $this->getServiceLocator()->get(DataBaseAccess::SERVICE_ID);
    }
}
