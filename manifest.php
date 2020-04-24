<?php

use oat\taoDacSimple\scripts\update\Updater;

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
 * Copyright (c) 2014-2020 (original work) Open Assessment Technologies SA;
 */

use oat\taoDacSimple\scripts\install\SetupDataAccess;
use oat\taoDacSimple\scripts\install\RegisterAction;
use oat\taoDacSimple\controller\AdminAccessController;
use oat\taoDacSimple\scripts\uninstall\RemoveDataAccess;

return [
    'name' => 'taoDacSimple',
    'label' => 'extension-tao-dac-simple',
    'description' => 'extension that allows admin to give access to some resources to other people',
    'license' => 'GPL-2.0',
    'version' => '6.6.1',
    'author' => 'Open Assessment Technologies SA',
    'requires' => [
       'taoBackOffice' => '>=3.0.0',
       'generis' => '>=12.15.0',
       'tao' => '>=40.9.0'
    ],
    // for compatibility
    'dependencies' => ['tao', 'taoItems'],
    'managementRole' => 'http://www.tao.lu/Ontologies/generis.rdf#taoDacSimpleManager',
    'acl' => [
        ['grant', 'http://www.tao.lu/Ontologies/generis.rdf#taoDacSimpleManager', ['ext' => 'taoDacSimple']],
        ['grant', 'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemsManagerRole', AdminAccessController::class],
        ['grant', 'http://www.tao.lu/Ontologies/TAOTest.rdf#TestsManagerRole', AdminAccessController::class]
    ],
    'install' => [
        'php' => [
            SetupDataAccess::class,
            RegisterAction::class
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
    ]
];
