<?php

namespace oat\taoDacSimple\model\tasks;

use common_exception_MissingParameter;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use oat\oatbox\event\EventManager;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\tao\model\taskQueue\Task\TaskAwareInterface;
use oat\tao\model\taskQueue\Task\TaskAwareTrait;
use oat\taoDacSimple\model\event\DacAffectedUsersEvent;
use oat\taoDacSimple\model\event\DacRootAddedEvent;
use oat\taoDacSimple\model\event\DacRootRemovedEvent;

class PostChangePermissionsTask extends AbstractAction implements TaskAwareInterface, JsonSerializable
{
    use TaskAwareTrait;

    public const PARAM_RESOURCE_ID = 'resourceId';
    public const PARAM_PERMISSIONS_DELTA = 'permissionsDelta';
    public const PARAM_IS_RECURSIVE = 'isRecursive';
    private const MANDATORY_PARAMS = [
        self::PARAM_RESOURCE_ID,
        self::PARAM_PERMISSIONS_DELTA,
        self::PARAM_IS_RECURSIVE,
    ];

    public function __invoke($params): Report
    {
        $this->validateParams($params);

        try {
            $reports = [];
            $eventManager = $this->getEventManager();
            $permissionsDelta = $params[self::PARAM_PERMISSIONS_DELTA];

            // TODO Collect all users and do in a single event
            foreach ($permissionsDelta['add'] as $userId => $permissions) {
                $eventManager->trigger(new DacRootAddedEvent($userId, $params[self::PARAM_RESOURCE_ID], $permissions));
            }

            $reports[] = Report::createInfo('Required permissions successfully added to parent classes');

            // TODO Collect all users and do in a single event
            foreach ($permissionsDelta['remove'] as $userId => $permissions) {
                $eventManager->trigger(new DacRootRemovedEvent($userId, $params[self::PARAM_RESOURCE_ID], $permissions));
            }

            $reports[] = Report::createInfo('Not necessary permissions successfully removed from parent classes');

            $eventManager->trigger(
                new DacAffectedUsersEvent(
                    array_keys($permissionsDelta['add']),
                    array_keys($permissionsDelta['remove'])
                )
            );

            $reports[] = Report::createInfo('Affected users successfully updated');

            $eventManager->trigger(
                new DataAccessControlChangedEvent(
                    $params[self::PARAM_RESOURCE_ID],
                    $permissionsDelta,
                    $params[self::PARAM_IS_RECURSIVE]
                )
            );

            return Report::createSuccess('Success', null, $reports);
        } catch (Exception $exception) {
            $errMessage = sprintf('Error: %s', $exception->getMessage());
            $this->getLogger()->error($errMessage);

            return Report::createError($errMessage);
        }
    }

    public function jsonSerialize(): string
    {
        return __CLASS__;
    }

    private function validateParams(array $params): void
    {
        foreach (self::MANDATORY_PARAMS as $param) {
            if (!isset($params[$param])) {
                throw new common_exception_MissingParameter(
                    sprintf(
                        'Missing parameter `%s` in %s',
                        $param,
                        self::class
                    )
                );
            }
        }

        $permissionsDelta = $params[self::PARAM_PERMISSIONS_DELTA];

        if (
            !array_key_exists('add', $permissionsDelta)
            || !array_key_exists('remove', $permissionsDelta)
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Parameter "%s" must contain "add" and "remove" keys',
                    self::PARAM_PERMISSIONS_DELTA
                )
            );
        }
    }

    private function getEventManager(): EventManager
    {
        return $this->getServiceManager()->getContainer()->get(EventManager::SERVICE_ID);
    }
}