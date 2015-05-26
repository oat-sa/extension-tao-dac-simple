<?php use oat\tao\helpers\Template;?>
<div class="permission-container flex-container-full">
    <?php
        $userData = get_data('userData');
    ?>
    <h1><?= __('Access Permissions for')?> <em><?= get_data('label')?></em></h1>

    <form action="<?=_url('savePermissions')?>" method="POST" class="grid-container">
        <input type="hidden" name="resource_id" id="resource_id" value="<?= get_data('uri')?>">
        <table class="matrix" id="permissions-table">
            <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th><?= __('Type');?></th>
                    <?php foreach (get_data('privileges') as $privilegeLabel):?>
                        <th><?= $privilegeLabel?></th>
                    <?php endforeach;?>
                    <th><?= __('Actions')?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (get_data('userPrivileges') as $userUri => $privileges):?>
                <tr>
                    <td><?= $userData[$userUri]['label']?></td>
                    <td>
                        <?= $userData[$userUri]['isRole'] ? 'role' : 'user' ?>
                        <input type="hidden" name="users[<?= $userUri?>][type]" value="<?=  $userData[$userUri]['isRole'] ? 'role' : 'user'?>">
                    </td>
                    <?php foreach (get_data('privileges') as $privilege => $privilegeLabel):?>
                        <td>
                            <label class="tooltip">
                                <input type="checkbox" class="privilege-<?= $privilege?>" name="users[<?= $userUri?>][<?= $privilege?>]" value="1" <?= (in_array($privilege, $privileges)) ? 'checked' : '' ?>>
                                <span class="icon-checkbox"></span>
                            </label>
                        </td>
                    <?php endforeach;?>
                    <td>
                        <button type="button" class="small delete_permission tooltip btn-info" data-acl-user="<?= $userUri?>" data-acl-type="<?= $userData[$userUri]['isRole'] ? 'role' : 'user'?>" data-acl-label="<?= $userData[$userUri]['label']?>" >
                            <span class="icon-bin"></span><?= __('Remove')?>
                        </button>
                    </td>
                </tr>
                <?php endforeach;?>
            </tbody>
        </table>
        <div class="grid-row">
            <div class="col-3">
                <input type="text" id="add-user" style="width:100%" placeholder="<?= __('Add user(s)') ?>"
                       data-url="<?= _url('search', 'Search', 'tao') ?>"
                       data-ontology="http://www.tao.lu/Ontologies/TAO.rdf#User"
                       data-params-root="params" />
            </div>
            <div class="col-3">
                <input type="text" id="add-role" style="width:100%" placeholder="<?= __('Add role(s)') ?>"
                       data-url="<?= _url('search', 'Search', 'tao') ?>"
                       data-ontology="http://www.tao.lu/Ontologies/generis.rdf#ClassRole"
                       data-params-root="params" />
            </div>
            <div class="col-3 txt-rgt">
                <label>
                    <?=__('Recursive')?>
                    <input type="checkbox" name="recursive" value="1">
                    <span class="icon-checkbox"></span>
                </label>
                <button type="submit" class="btn-info small"><?= __('Save')?></button>
            </div>
        </div>
    </form>
</div>
