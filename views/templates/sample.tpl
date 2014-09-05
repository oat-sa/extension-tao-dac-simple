<div class="main-container tao-scope">
    <?php
        $items = get_data('items');
        $item = current($items);
    ?>
    <h1><?= __('Access Permissions for')?> <em><?=$item['resource']['label']?></em></h1>
    <form action="<?=_url('savePrivileges','TaoDacSimple','taoDacSimple')?>" method="POST" class="grid-container">
        <input type="hidden" name="resource_id" id="resource_id" value="<?= $item['resource']['id']?>">
        <table class="matrix" id="permissions-table">
            <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th><?= __('Type');?></th>
                    <th><?= __('Can Write')?></th>
                    <th><?= __('Can Share')?></th>
                    <th><?= __('Actions')?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item['users'] as $user):?>
                <tr>
                    <td><?= $user['name']?></td>
                    <td>
                        <?= $user['type']?>
                        <input type="hidden" name="users[<?= $user['id']?>][type]" value="<?= $user['type']?>">
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" class="can-access" name="users[<?= $user['id']?>][WRITE]" value="1" <?= ($user['permissions']['WRITE'] == true) ? 'checked' : '' ?>>
                            <span class="icon-checkbox"></span>
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" class="can-manage" name="users[<?= $user['id']?>][GRANT]" value="1" <?= ($user['permissions']['GRANT'] == true) ? 'checked' : '' ?>>
                            <span class="icon-checkbox"></span>
                        </label>
                    </td>
                    <td>
                        <button class="small delete_permission" data-acl-user="<?= $user['id']?>" data-acl-type="<?= $user['type']?>" data-acl-label="<?= $user['name']?>" >
                            <span class="icon-bin"></span><?= __('Delete')?>
                        </button>
                    </td>
                </tr>
                <?php endforeach;?>
            </tbody>
        </table>
        <div class="grid-row">
            <div class="col-3">
                <select id="add-user" multiple style="width:100%">
                    <?php foreach ($users as $userId => $username):?>
                    <option value="<?=$userId?>"><?=$username?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-2">
                <button class="btn-info small" id="add-user-btn" type="button"><?= __('Add user(s)')?></button>
            </div>
            <div class="col-3">
                <select id="add-role" multiple style="width:100%">
                    <?php foreach ($roles as $roleId => $roleLabel):?>
                    <option value="<?=$roleId?>"><?=$roleLabel?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-3">
                <button class="btn-info small" id="add-role-btn" type="button"><?= __('Add role(s)')?></button>
            </div>
            <div class="col-1">
                <button type="submit" class="btn-info small" type="button"><?= __('Save')?></button>
            </div>
        </div>
    </form>
</div>
