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
 * Copyright (c) 2014-2023 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

declare(strict_types=1);

namespace oat\taoDacSimple\model;

use common_persistence_SqlPersistence;
use oat\oatbox\event\EventManager;
use oat\oatbox\service\ConfigurableService;
use oat\taoDacSimple\model\Command\ChangeAccessCommand;
use oat\taoDacSimple\model\event\DacAddedEvent;
use oat\taoDacSimple\model\event\DacChangedEvent;
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

    private $writeChunkSize = 1000;

    private $persistence;

    public function setWriteChunkSize(int $size): void
    {
        $this->writeChunkSize = $size;
    }

    /**
     * @return array [
     *     '{resourceId}' => [
     *          '{userId}' => ['GRANT'],
     *     ]
     * ]
     */
    public function getPermissionsByUsersAndResources(array $userIds, array $resourceIds): array
    {
        if (empty($resourceIds) || empty($userIds)) {
            return [];
        }

        // phpcs:disable Generic.Files.LineLength
        $results = $this->fetchQuery(
            sprintf(
                'SELECT resource_id, user_id, privilege FROM data_privileges WHERE resource_id IN (%s) AND user_id IN (%s)',
                implode(',', array_fill(0, count($resourceIds), '?')),
                implode(',', array_fill(0, count($userIds), '?'))
            ),
            [
                ...$resourceIds,
                ...$userIds
            ]
        );
        // phpcs:disable Generic.Files.LineLength

        $data = array_fill_keys($resourceIds, []);

        foreach ($results as $result) {
            $data[$result[self::COLUMN_RESOURCE_ID]][$result[self::COLUMN_USER_ID]][] = $result[self::COLUMN_PRIVILEGE];
        }

        return $data;
    }

    /**
     * Allow to grant/revoke access for several users and resources
     */
    public function changeAccess(ChangeAccessCommand $command): void
    {
        $persistence = $this->getPersistence();

        $persistence->transactional(function () use ($command, $persistence): void {
            $removed = [];

            foreach ($command->getUserIdsToRevokePermissions() as $userId) {
                $permissions = $command->getUserPermissionsToRevoke($userId);

                foreach ($permissions as $permission) {
                    $resourceIds = $command->getResourceIdsByUserAndPermissionToRevoke($userId, $permission);

                    if (!empty($resourceIds)) {
                        foreach (array_chunk($resourceIds, $this->writeChunkSize) as $batch) {
                            // phpcs:disable Generic.Files.LineLength
                            $persistence->exec(
                                sprintf(
                                    'DELETE FROM data_privileges WHERE user_id = ? AND privilege = ? AND resource_id IN (%s)',
                                    implode(',', array_fill(0, count($batch), '?')),
                                ),
                                array_merge([$userId, $permission], $batch)
                            );
                            // phpcs:enable Generic.Files.LineLength
                        }

                        foreach ($resourceIds as $resourceId) {
                            $this->addEventValue($removed, $userId, $resourceId, $permission);
                        }
                    }
                }
            }

            $insert = [];
            $added = [];

            foreach ($command->getResourceIdsToGrant() as $resourceId) {
                foreach (PermissionProvider::ALLOWED_PERMISSIONS as $permission) {
                    $usersIds = $command->getUserIdsToGrant($resourceId, $permission);

                    foreach ($usersIds as $userId) {
                        $insert[] = [
                            'user_id' => $userId,
                            'resource_id' => $resourceId,
                            'privilege' => $permission,
                        ];

                        $this->addEventValue($added, $userId, $resourceId, $permission);
                    }
                }
            }

            $this->insertPermissions($insert);

            if (!empty($added) || !empty($removed)) {
                $this->getEventManager()->trigger(new DacChangedEvent($added, $removed));
            }
        });
    }

    /**
     * Retrieve info on users having privileges on a set of resources
     *
     * @return array [
     *     [
     *         '{resourceId}',
     *         '{userId}',
     *         '{privilege}'
     *     ]
     * ]
     */
    public function getUsersWithPermissions(array $resourceIds): array
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
     * @return array [
     *      '{resourceId}' => ['READ', 'WRITE'],
     *  ]
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

    /**
     * @return array [
     *     '{resourceId}' => [
     *         '{userId}' => ['READ', 'WRITE'],
     *     ]
     * ]
     */
    public function getResourcesPermissions(array $resourceIds): array
    {
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
     * @deprecated Please use $this::changeAccess()
     */
    public function addPermissions(string $user, string $resourceId, array $rights): void
    {
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
    }

    /**
     * Get the permissions to resource
     *
     * @return array [
     *     '{userId}' => [
     *          'READ',
     *          'WRITE',
     *     ]
     * ]
     */
    public function getResourcePermissions(string $resourceId): array
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
     * @deprecated Please use $this::changeAccess()
     */
    public function removePermissions(string $user, string $resourceId, array $rights): void
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
    }

    /**
     * Completely remove all permissions to any user for the resourceIds
     *
     * @deprecated Please use $this::changeAccess()
     */
    public function removeAllPermissions(array $resourceIds): void
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
    }

    /**
     * Filter users\roles that have permissions
     *
     * @return array [
     *     '{userId}' => '{userId}',
     *     '{userId}' => '{userId}',
     * ]
     */
    public function checkPermissions(array $userIds): array
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

    public function removeTables(): void
    {
        $persistence = $this->getPersistence();
        $schema = $persistence->getDriver()->getSchemaManager()->createSchema();
        $fromSchema = clone $schema;
        $schema->dropTable(self::TABLE_PRIVILEGES_NAME);
        $queries = $persistence->getPlatform()->getMigrateSchemaSql($fromSchema, $schema);
        foreach ($queries as $query) {
            $persistence->exec($query);
        }
    }

    public function createTables(): void
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

    private function getEventManager(): EventManager
    {
        return $this->getServiceLocator()->get(EventManager::SERVICE_ID);
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

    private function fetchQuery(string $query, array $params): array
    {
        return $this->getPersistence()
            ->query($query, $params)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws Throwable
     */
    private function insertPermissions(array $insert): void
    {
        if (!empty($insert)) {
            $persistence = $this->getPersistence();

            foreach (array_chunk($insert, $this->writeChunkSize) as $batch) {
                $persistence->insertMultiple(self::TABLE_PRIVILEGES_NAME, $batch);
            }
        }
    }

    private function addEventValue(array &$eventData, string $userId, string $resourceId, string $permission): void
    {
        $key = $userId . $resourceId;

        if (array_key_exists($key, $eventData)) {
            $eventData[$key]['privileges'][] = $permission;

            return;
        }

        $eventData[$key] = [
            'userId' => $userId,
            'resourceId' => $resourceId,
            'privileges' => [$permission],
        ];
    }
}
