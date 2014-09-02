<div class="main-container tao-scope">
    <?php $items = get_data('items');
    foreach ($items as $item):?>
    <h1><?= __('Access Permissions for')?> <em><?=$item['resource']['label']?></em></h1>
    <form action="#" method="POST">
        <input type="hidden" name="ressource_id" value="<?= $item['ressource']['id']?>">
        <table class="matrix">
            <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th><?= __('Type');?></th>
                    <th><?= __('Can Write')?></th>
                    <th><?= __('Can Share')?></th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item['users'] as $user):?>
                <tr>
                    <td><?= $user['name']?></td>
                    <td>
                        <?= $user['type']?>
                        <input type="hidden" name="[<?= $user['id']?>][type]" value="<?= $user['type']?>">
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" name="[<?= $user['id']?>]['write']" value="1" <?= ($user['permissions']['WRITE'] == true) ? 'checked' : '' ?>>
                            <span class="icon-checkbox"></span>
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" name="[<?= $user['id']?>]['grant']" value="1" <?= ($user['permissions']['GRANT'] == true) ? 'checked' : '' ?>>
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
            <div class="col-4">
                <select name="new_user" class="select2" data-placeholder="<?= __('Select a user to add')?>">
                    <option value="1">User 1</option>
                    <option value="2">User 2</option>
                    <option value="3">User 3</option>
                    <option value="4">User 4</option>
                    <option value="5">User 5</option>
                </select>
                <button class="btn-info small" type="button"><?= __('Add user(s)')?></button>
            </div>
            <div class="col-7">
                <select name="new_role" class="select2" data-placeholder="<?= __('Select a role to add')?>">
                    <option value="1">Role 1</option>
                    <option value="2">Role 2</option>
                    <option value="3">Role 3</option>
                </select>
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
            <button class=""><?= __('Cancel')?></button>
            <button class="btn-success"><?= __('Proceed')?></button>
        </div>
    </div>
</div>
