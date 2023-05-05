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
 * Copyright (c) 2014-2022 (original work) Open Assessment Technologies SA;
 */

use oat\taoDacSimple\model\Copy\ServiceProvider\CopyServiceProvider;
use oat\taoDacSimple\scripts\install\AttachEventHandler;
use oat\taoDacSimple\scripts\update\Updater;
use oat\taoDacSimple\scripts\install\SetupDataAccess;
use oat\taoDacSimple\scripts\install\RegisterAction;
use oat\taoDacSimple\controller\AdminAccessController;
use oat\taoDacSimple\scripts\uninstall\RemoveDataAccess;

return [
    'name' => 'taoDacSimple',
    'label' => 'extension-tao-dac-simple',
    'description' => 'extension that allows admin to give access to some resources to other people',
    'license' => 'GPL-2.0',
    'author' => 'Open Assessment Technologies SA',
    'managementRole' => 'http://www.tao.lu/Ontologies/generis.rdf#taoDacSimpleManager',
    'acl' => [
        ['grant', 'http://www.tao.lu/Ontologies/generis.rdf#taoDacSimpleManager', ['ext' => 'taoDacSimple']],
        ['grant', 'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemsManagerRole', AdminAccessController::class],
        ['grant', 'http://www.tao.lu/Ontologies/TAOTest.rdf#TestsManagerRole', AdminAccessController::class]
    ],
    'install' => [
        'php' => [
            SetupDataAccess::class,
            RegisterAction::class,
            AttachEventHandler::class
        ],
        'rdf' => [
            __DIR__ . '/model/ontology/dac.rdf',
        ],
    ],
    'uninstall' => [
        'php' => [
            RemoveDataAccess::class
        ]
    ],
    'update' => Updater::class,
    'routes' => [
        '/taoDacSimple' => 'oat\\taoDacSimple\\controller'
    ],
    'constants' => [
        # views directory
        'DIR_VIEWS' => __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR,

        #BASE URL (usually the domain root)
        'BASE_URL' => ROOT_URL . 'taoDacSimple/',
    ],
    'containerServiceProviders' => [
        CopyServiceProvider::class
    ]
];
