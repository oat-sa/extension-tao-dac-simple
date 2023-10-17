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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2014-2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

namespace oat\taoDacSimple\model;

use common_persistence_SqlPersistence;
use core_kernel_classes_Resource;
use oat\generis\model\OntologyRdf;
use oat\generis\model\OntologyRdfs;
use oat\oatbox\event\EventManager;
use oat\oatbox\service\ConfigurableService;
use oat\taoDacSimple\model\event\DacAddedEvent;
use oat\taoDacSimple\model\event\DacRemovedEvent;
use oat\generis\persistence\PersistenceManager;
use PDO;
use Throwable;

/**
 * Class to handle the storage and retrieval of permissions
 *
 * @author Antoine Robin <antoine.robin@vesperiagroup.com>
 * @author Joel Bout <joel@taotesting.com>
 */
class DataBaseAccess extends ConfigurableService
{
    public const SERVICE_ID = 'taoDacSimple/DataBaseAccess';

    public const OPTION_PERSISTENCE = 'persistence';
    public const OPTION_FETCH_USER_PERMISSIONS_CHUNK_SIZE = 'fetch_user_permissions_chunk_size';

    public const COLUMN_USER_ID = 'user_id';
    public const COLUMN_RESOURCE_ID = 'resource_id';
    public const COLUMN_PRIVILEGE = 'privilege';
    public const TABLE_PRIVILEGES_NAME = 'data_privileges';
    public const INDEX_RESOURCE_ID = 'data_privileges_resource_id_index';

    private $insertChunkSize = 20000;

    private $persistence;

    public function setInsertChunkSize(int $size): void
    {
        $this->insertChunkSize = $size;
    }

    /**
     * @return EventManager
     */
    protected function getEventManager()
    {
        return $this->getServiceLocator()->get(EventManager::SERVICE_ID);
    }

    public function getParentClassesIds(string $resourceUri): array
    {
        $query = <<<'SQL'
WITH RECURSIVE statements_tree AS (
    SELECT
        r.object
    FROM statements r
    WHERE r.subject = ?
      AND r.predicate IN (?, ?)
    UNION ALL
    SELECT
        s.object
    FROM statements s
        JOIN statements_tree st
            ON s.subject = st.object
                   AND s.predicate IN (?, ?)
                   AND s.object NOT IN (?, ?, ?, ?)
)
SELECT object FROM statements_tree;
SQL;

        return array_column(
            $this->fetchQuery(
                $query,
                [
                    $resourceUri,
                    OntologyRdfs::RDFS_SUBCLASSOF,
                    OntologyRdf::RDF_TYPE,
                    OntologyRdfs::RDFS_SUBCLASSOF,
                    OntologyRdf::RDF_TYPE,
                    'http://www.tao.lu/Ontologies/TAO.rdf#AssessmentContentObject',
                    'http://www.tao.lu/Ontologies/TAO.rdf#TAOObject',
                    'http://www.tao.lu/Ontologies/generis.rdf#generis_Ressource',
                    'http://www.w3.org/2000/01/rdf-schema#Resource',
                ]
            ),
            'object'
        );
    }

    public function getClassesResources(array $classesIds): array
    {
        $inQuery = implode(',', array_fill(0, count($classesIds), '?'));
        $query = "SELECT subject, object FROM statements WHERE predicate IN (?, ?) AND object IN ($inQuery)";

        $results = $this->fetchQuery(
            $query,
            [
                OntologyRdfs::RDFS_SUBCLASSOF,
                OntologyRdf::RDF_TYPE,
                ...$classesIds,
            ]
        );

        $classesResources = [];

        foreach ($results as $result) {
            $classesResources[$result['object']][] = $result['subject'];
        }

        return $classesResources;
    }

    public function getResourceTree(core_kernel_classes_Resource $resource): array
    {
        $query = <<<'SQL'
WITH RECURSIVE statements_tree AS (
    SELECT
        r.subject,
        r.predicate,
        1 as level
    FROM statements r
    WHERE r.subject = ?
      AND r.predicate IN (?, ?)
    UNION ALL
    SELECT
        s.subject,
        s.predicate,
        level + 1
    FROM statements s
        JOIN statements_tree st
            ON s.object = st.subject
)
SELECT subject, predicate, level FROM statements_tree;
SQL;

        return $this->fetchQuery(
            $query,
            [
                $resource->getUri(),
                OntologyRdfs::RDFS_SUBCLASSOF,
                OntologyRdf::RDF_TYPE,
            ]
        );
    }

    public function getPermissionsByUsersAndResources(array $userIds, array $resourceIds): array
    {
        // Permissions for an empty set of resources must be an empty array
        if (empty($resourceIds)) {
            return [];
        }

        $inQueryResources = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryUsers = implode(',', array_fill(0, count($userIds), '?'));

        $query = <<<SQL
SELECT
    resource_id,
    user_id,
    privilege
FROM data_privileges
WHERE resource_id IN ($inQueryResources)
  AND user_id IN ($inQueryUsers)
SQL;

        $results = $this->fetchQuery($query, [...$resourceIds, ...$userIds]);

        // If resource doesn't have permission don't return null
        $data = array_fill_keys($resourceIds, []);

        foreach ($results as $result) {
            $data[$result[self::COLUMN_RESOURCE_ID]][$result[self::COLUMN_USER_ID]][] = $result[self::COLUMN_PRIVILEGE];
        }

        return $data;
    }

    public function addResourcesPermissions(array $resources): void
    {
        $insert = [];

        foreach ($resources as $resource) {
            foreach ($resource['permissions']['add'] as $userId => $addPermissions) {
                if (empty($addPermissions)) {
                    continue;
                }

                foreach ($addPermissions as $permission) {
                    $insert[] = [
                        self::COLUMN_USER_ID => $userId,
                        self::COLUMN_RESOURCE_ID => $resource['id'],
                        self::COLUMN_PRIVILEGE => $permission,
                    ];
                }
            }
        }

        $this->insertPermissions($insert);

        foreach ($insert as $inserted) {
            $this->getEventManager()->trigger(
                new DacAddedEvent(
                    $inserted[self::COLUMN_USER_ID],
                    $inserted[self::COLUMN_RESOURCE_ID],
                    (array) $inserted[self::COLUMN_PRIVILEGE]
                )
            );
        }
    }

    public function removeResourcesPermissions(array $resources): void
    {
        $groupedRemove = [];
        $eventsData = [];

        foreach ($resources as $resource) {
            foreach ($resource['permissions']['remove'] as $userId => $removePermissions) {
                if (empty($removePermissions)) {
                    continue;
                }

                $idString = implode($removePermissions);

                $groupedRemove[$userId][$idString]['resources'][] = $resource['id'];
                $groupedRemove[$userId][$idString]['privileges'] = $removePermissions;

                $eventsData[] = [
                    'userId' => $userId,
                    'resourceId' => $resource['id'],
                    'privileges' => $removePermissions,
                ];
            }
        }

        foreach ($groupedRemove as $userId => $priviligeGroups) {
            foreach ($priviligeGroups as $privilegeGroup) {
                $inQueryPrivilege = implode(',', array_fill(0, count($privilegeGroup['privileges']), ' ? '));
                $inQueryResources = implode(',', array_fill(0, count($privilegeGroup['resources']), ' ? '));

                $query = <<<SQL
DELETE
FROM data_privileges
WHERE resource_id IN ($inQueryResources)
  AND privilege IN ($inQueryPrivilege)
  AND user_id = ?
SQL;

                $params = array_merge(
                    array_values($privilegeGroup['resources']),
                    array_values($privilegeGroup['privileges']),
                    [$userId]
                );

                $this->getPersistence()->exec($query, $params);
            }
        }

        foreach ($eventsData as $eventData) {
            $this->getEventManager()->trigger(new DacRemovedEvent(
                $eventData['userId'],
                $eventData['resourceId'],
                $eventData['privileges']
            ));
        }
    }

    public function addReadAccess(array $addAccessList): bool
    {
        if (empty($addAccessList)) {
            return true;
        }

        $values = [];

        try {
            foreach ($addAccessList as $resourceId => $usersIds) {
                foreach ($usersIds as $userId) {
                    $values[] = [
                        'user_id' => $userId,
                        'resource_id' => $resourceId,
                        'privilege' => PermissionProvider::PERMISSION_READ
                    ];
                }
            }

            $this->insertPermissions($values);

            return true;
        } catch (Throwable $exception) {
            $this->logError('Error when adding READ access: ' . $exception->getMessage());

            return false;
        }
    }

    public function revokeAccess(array $revokeAccessList): bool
    {
        if (empty($revokeAccessList)) {
            return true;
        }

        $persistence = $this->getPersistence();

        try {
            $persistence->transactional(static function () use ($revokeAccessList, $persistence): void {
                foreach ($revokeAccessList as $resourceId => $usersIds) {
                    $inQueryUsers = implode(',', array_fill(0, count($usersIds), ' ? '));

                    $persistence->exec(
                        "DELETE FROM data_privileges WHERE resource_id = ? AND user_id IN ($inQueryUsers)",
                        [
                            $resourceId,
                            ...$usersIds,
                        ]
                    );
                }
            });

            return true;
        } catch (Throwable $exception) {
            $this->logError('Error when revoking access: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Retrieve info on users having privileges on a set of resources
     *
     * @param array $resourceIds IDs of resources to fetch privileges for
     * @return array A list of rows containing resource ID, user ID and privilege name
     */
    public function getUsersWithPermissions($resourceIds)
    {
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $query = sprintf(
            'SELECT %s, %s, %s FROM %s WHERE %s IN (%s)',
            self::COLUMN_RESOURCE_ID,
            self::COLUMN_USER_ID,
            self::COLUMN_PRIVILEGE,
            self::TABLE_PRIVILEGES_NAME,
            self::COLUMN_RESOURCE_ID,
            $inQuery
        );

        return $this->fetchQuery($query, $resourceIds);
    }

    /**
     * Get the permissions for a list of resources and users
     *
     * @access public
     * @param array $userIds
     * @param array $resourceIds
     * @return array
     */
    public function getPermissions(array $userIds, array $resourceIds): array
    {
        // Permissions for an empty set of resources must be an empty array
        if (!count($resourceIds)) {
            return [];
        }

        $inQueryResource = implode(',', array_fill(0, count($resourceIds), '?'));
        $inQueryUser = implode(',', array_fill(0, count($userIds), '?'));
        $query = sprintf(
            'SELECT %s, %s FROM %s WHERE %s IN (%s) AND %s IN (%s)',
            self::COLUMN_RESOURCE_ID,
            self::COLUMN_PRIVILEGE,
            self::TABLE_PRIVILEGES_NAME,
            self::COLUMN_RESOURCE_ID,
            $inQueryResource,
            self::COLUMN_USER_ID,
            $inQueryUser
        );

        $params = array_merge(array_values($resourceIds), array_values($userIds));

        //If resource doesn't have permission don't return null
        $returnValue = array_fill_keys($resourceIds, []);

        $results = $this->fetchQuery($query, $params);
        foreach ($results as $result) {
            $returnValue[$result[self::COLUMN_RESOURCE_ID]][] = $result[self::COLUMN_PRIVILEGE];
        }
        return $returnValue;
    }

    public function getResourcesPermissions(array $resourceIds)
    {
        // Return an empty array for resources not having permissions data
        $grants = array_fill_keys($resourceIds, []);

        foreach ($this->getUsersWithPermissions($resourceIds) as $entry) {
            $grants[$entry[self::COLUMN_RESOURCE_ID]][$entry[self::COLUMN_USER_ID]][]
                = $entry[self::COLUMN_PRIVILEGE];
        }

        return $grants;
    }

    /**
     * Add permissions of a user to a resource
     *
     * @access public
     * @param string $user
     * @param string $resourceId
     * @param array $rights
     *
     * @return bool
     */
    public function addPermissions($user, $resourceId, $rights)
    {
        // Add an ACL item for each user URI, resource ID and privilege combination
        foreach ($rights as $privilege) {
            $this->getPersistence()->insert(
                self::TABLE_PRIVILEGES_NAME,
                [
                    self::COLUMN_USER_ID => $user,
                    self::COLUMN_RESOURCE_ID => $resourceId,
                    self::COLUMN_PRIVILEGE => $privilege
                ]
            );
        }

        $this->getEventManager()->trigger(new DacAddedEvent(
            $user,
            $resourceId,
            (array)$rights
        ));

        return true;
    }

    /**
     * Add batch permissions
     *
     * @access public
     * @param array $permissionData
     * @return void
     * @throws Throwable
     */
    public function addMultiplePermissions(array $permissionData)
    {
        $insert = [];
        foreach ($permissionData as $permissionItem) {
            foreach ($permissionItem['permissions'] as $userId => $privilegeIds) {
                if (!empty($privilegeIds)) {
                    foreach ($privilegeIds as $privilegeId) {
                        $insert [] = [
                            self::COLUMN_USER_ID     => $userId,
                            self::COLUMN_RESOURCE_ID => $permissionItem['resource']->getUri(),
                            self::COLUMN_PRIVILEGE   => $privilegeId
                        ];
                    }
                }
            }
        }

        $this->insertPermissions($insert);

        foreach ($insert as $inserted) {
            $this->getEventManager()->trigger(new DacAddedEvent(
                $inserted[self::COLUMN_USER_ID],
                $inserted[self::COLUMN_RESOURCE_ID],
                (array)$inserted[self::COLUMN_PRIVILEGE]
            ));
        }
    }

    /**
     * Get the permissions to resource
     *
     * @access public
     * @param string $resourceId
     * @return array
     */
    public function getResourcePermissions($resourceId)
    {
        $grants = [];
        $query = sprintf(
            'SELECT %s, %s FROM %s WHERE %s = ?',
            self::COLUMN_USER_ID,
            self::COLUMN_PRIVILEGE,
            self::TABLE_PRIVILEGES_NAME,
            self::COLUMN_RESOURCE_ID
        );

        foreach ($this->fetchQuery($query, [$resourceId]) as $entry) {
            $grants[$entry[self::COLUMN_USER_ID]][] = $entry[self::COLUMN_PRIVILEGE];
        }

        return $grants;
    }

    /**
     * remove permissions to a resource for a user
     *
     * @access public
     * @param string $user
     * @param string $resourceId
     * @param array $rights
     * @return boolean
     */
    public function removePermissions($user, $resourceId, $rights)
    {
        //get all entries that match (user,resourceId) and remove them
        $inQueryPrivilege = implode(',', array_fill(0, count($rights), ' ? '));
        $query = sprintf(
            'DELETE FROM %s WHERE %s = ? AND %s IN (%s) AND %s = ?',
            self::TABLE_PRIVILEGES_NAME,
            self::COLUMN_RESOURCE_ID,
            self::COLUMN_PRIVILEGE,
            $inQueryPrivilege,
            self::COLUMN_USER_ID
        );

        $params = array_merge([$resourceId], array_values($rights), [$user]);
        $this->getPersistence()->exec($query, $params);

        $this->getEventManager()->trigger(new DacRemovedEvent(
            $user,
            $resourceId,
            $rights
        ));

        return true;
    }

    /**
     * Remove batch permissions
     *
     * @access public
     * @param array $data
     * @return void
     */
    public function removeMultiplePermissions(array $data)
    {
        $groupedRemove = [];
        $eventsData = [];

        foreach ($data as $permissionItem) {
            $resource = &$permissionItem['resource'];

            foreach ($permissionItem['permissions'] as $userId => $privilegeIds) {
                if (!empty($privilegeIds)) {
                    $idString = implode($privilegeIds);

                    $groupedRemove[$userId][$idString]['resources'][] = $resource->getUri();
                    $groupedRemove[$userId][$idString]['privileges'] = $privilegeIds;

                    $eventsData[] = [
                        'userId' => $userId,
                        'resourceId' => $resource->getUri(),
                        'privileges' => $privilegeIds
                    ];
                }
            }
        }
        foreach ($groupedRemove as $userId => $resources) {
            foreach ($resources as $permissions) {
                $inQueryPrivilege = implode(',', array_fill(0, count($permissions['privileges']), ' ? '));
                $inQueryResources = implode(',', array_fill(0, count($permissions['resources']), ' ? '));
                $query = sprintf(
                    'DELETE FROM %s WHERE %s IN (%s) AND %s IN (%s) AND %s = ?',
                    self::TABLE_PRIVILEGES_NAME,
                    self::COLUMN_RESOURCE_ID,
                    $inQueryResources,
                    self::COLUMN_PRIVILEGE,
                    $inQueryPrivilege,
                    self::COLUMN_USER_ID
                );

                $params = array_merge(
                    array_values($permissions['resources']),
                    array_values($permissions['privileges']),
                    [$userId]
                );

                $this->getPersistence()->exec($query, $params);
            }
        }

        foreach ($eventsData as $eventData) {
            $this->getEventManager()->trigger(new DacRemovedEvent(
                $eventData['userId'],
                $eventData['resourceId'],
                $eventData['privileges']
            ));
        }
    }

    /**
     * Remove all permissions from a resource
     *
     * @access public
     * @param array $resourceIds
     * @return boolean
     */
    public function removeAllPermissions($resourceIds)
    {
        //get all entries that match (resourceId) and remove them
        $inQuery = implode(',', array_fill(0, count($resourceIds), ' ? '));
        $query = sprintf(
            'DELETE FROM %s WHERE %s IN (%s)',
            self::TABLE_PRIVILEGES_NAME,
            self::COLUMN_RESOURCE_ID,
            $inQuery
        );

        $this->getPersistence()->exec($query, $resourceIds);
        foreach ($resourceIds as $resourceId) {
            $this->getEventManager()->trigger(new DacRemovedEvent('-', $resourceId, ['-']));
        }
        return true;
    }

    /**
     * Filter users\roles that have no permissions
     *
     * @access public
     * @param array $userIds
     * @return array
     */
    public function checkPermissions($userIds)
    {
        $chunks = array_chunk($userIds, $this->getOption(self::OPTION_FETCH_USER_PERMISSIONS_CHUNK_SIZE, 20));
        $existingUsers = [];

        foreach ($chunks as $chunkUserIds) {
            $inQueryUser = implode(',', array_fill(0, count($chunkUserIds), ' ? '));
            $query = sprintf(
                'SELECT %s FROM %s WHERE %s IN (%s) GROUP BY %s',
                self::COLUMN_USER_ID,
                self::TABLE_PRIVILEGES_NAME,
                self::COLUMN_USER_ID,
                $inQueryUser,
                self::COLUMN_USER_ID
            );
            $results = $this->fetchQuery($query, array_values($chunkUserIds));
            foreach ($results as $result) {
                $existingUsers[$result[self::COLUMN_USER_ID]] = $result[self::COLUMN_USER_ID];
            }
        }

        return $existingUsers;
    }

    /**
     * @return common_persistence_SqlPersistence
     */
    private function getPersistence()
    {
        if (!$this->persistence) {
            $this->persistence = $this->getServiceLocator()->get(PersistenceManager::SERVICE_ID)
                ->getPersistenceById($this->getOption(self::OPTION_PERSISTENCE));
        }
        return $this->persistence;
    }


    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    private function fetchQuery($query, $params)
    {
        $statement = $this->getPersistence()->query($query, $params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTables()
    {
        $schemaManager = $this->getPersistence()->getDriver()->getSchemaManager();
        $schema = $schemaManager->createSchema();
        $fromSchema = clone $schema;
        $table = $schema->createtable(self::TABLE_PRIVILEGES_NAME);
        $table->addColumn(self::COLUMN_USER_ID, 'string', ['notnull' => null, 'length' => 255]);
        $table->addColumn(self::COLUMN_RESOURCE_ID, 'string', ['notnull' => null, 'length' => 255]);
        $table->addColumn(self::COLUMN_PRIVILEGE, 'string', ['notnull' => null, 'length' => 255]);
        $table->setPrimaryKey([self::COLUMN_USER_ID, self::COLUMN_RESOURCE_ID, self::COLUMN_PRIVILEGE]);
        $table->addIndex([self::COLUMN_RESOURCE_ID], self::INDEX_RESOURCE_ID);

        $queries = $this->getPersistence()->getPlatform()->getMigrateSchemaSql($fromSchema, $schema);
        foreach ($queries as $query) {
            $this->getPersistence()->exec($query);
        }
    }


    public function removeTables()
    {
        $persistence = $this->getPersistence();
        $schema = $persistence->getDriver()->getSchemaManager()->createSchema();
        $fromSchema = clone $schema;
        $table = $schema->dropTable(self::TABLE_PRIVILEGES_NAME);
        $queries = $persistence->getPlatform()->getMigrateSchemaSql($fromSchema, $schema);
        foreach ($queries as $query) {
            $persistence->exec($query);
        }
    }

    /**
     * @throws Throwable
     */
    private function insertPermissions(array $insert): void
    {
        if (empty($insert)) {
            return;
        }

        $logger = $this->getLogger();
        $insertCount = count($insert);
        $persistence = $this->getPersistence();

        $persistence->transactional(function () use ($insert, $logger, $insertCount, $persistence) {
            foreach (array_chunk($insert, $this->insertChunkSize) as $index => $batch) {
                $logger->debug(
                    'Processing chunk {index}/{total} with {items} ACL entries',
                    [
                        'index' => $index + 1,
                        'total' => ceil($insertCount / $this->insertChunkSize),
                        'items' => count($batch)
                    ]
                );

                $persistence->insertMultiple(self::TABLE_PRIVILEGES_NAME, $batch);
            }
        });
    }
}
