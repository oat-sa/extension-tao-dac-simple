<div class="main-container tao-scope">
    <?php $items = get_data('items');
    foreach ($items as $item):?>
    <h1><?= __('Access Permissions for')?> <em><?=$item['resource']['label']?></em></h1>
    <form action="<?=_url('savePrivileges','taoDacSimple','taoDacSimple')?>" method="POST">
        <input type="hidden" name="resource_id" value="<?= $item['resource']['id']?>">
        <table class="matrix">
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
                            <input type="checkbox" name="users[<?= $user['id']?>][WRITE]" value="1" <?= ($user['permissions']['WRITE'] == true) ? 'checked' : '' ?> <?= ($user['permissions']['OWNER'] == true) ? 'disabled' : ''?>>
                            <span class="icon-checkbox"></span>
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" name="users[<?= $user['id']?>][GRANT]" value="1" <?= ($user['permissions']['GRANT'] == true) ? 'checked' : '' ?> <?= ($user['permissions']['OWNER'] == true) ? 'disabled' : ''?>>
                            <span class="icon-checkbox"></span>
                        </label>
                    </td>
                    <td>
                        <?php
                        // [TODO] : Check if the current user has GRANT permissions
                        if(true) :?>
                        <button class="small"<?= ((count($item['users']) == 1) || ($user['permissions']['OWNER'] == true)) ? 'disabled' : '' ?> data-modal="#ownership-transfert"><?= __('Transfert ownership')?></button>
                        <?php endif;?>
                        &nbsp;
                        <button class="small" <?= ($user['permissions']['OWNER'] == true) ? 'disabled' : '' ?>><span class="icon-bin"></span><?= __('Delete')?></button>
                    </td>
                </tr>
                <?php endforeach;?>
            </tbody>
        </table>
        <div class="grid-row">
            <div class="col-3">
                <select name="new_user" class="select2" multiple style="width:100%">
                    <?php foreach ($users as $userId => $username):?>
                    <option value="<?=$userId?>"><?=$username?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-2">
                <button class="btn-info small" type="button"><?= __('Add user(s)')?></button>
            </div>
            <div class="col-3">
                <select name="new_role" class="select2" multiple style="width:100%">
                    <?php foreach ($roles as $roleId => $roleLabel):?>
                    <option value="<?=$roleId?>"><?=$roleLabel?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-3">
                <button class="btn-info small" type="button"><?= __('Add role(s)')?></button>
            </div>
            <div class="col-1">
                <button type="submit" class="btn-info small" type="button"><?= __('Save')?></button>
            </div>
        </div>
    </form>
    <?php endforeach;?>


    <div id="ownership-transfert" class="modal">
        <h1><?= __('Be carefull')?></h1>
        <p><?= __('You are about to transfert the ownership of the ressource')?> <em><?=$item['resource']['label']?></em>. <?= __('Once you have transfert the ownership, you will not be able to manage the ownership anymore')?>.</p>
        <div class="rgt">
            <button class="cancel"><?= __('Cancel')?></button>
            <button class="btn-success confirm"><?= __('Proceed')?></button>
        </div>
    </div>
</div>
