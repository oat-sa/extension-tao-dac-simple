<div class="main-container tao-scope">
    <?php $items = get_data('items');
    foreach ($items as $item):?>
    <h1>Access Permissions for <em><?=$item['resource']['label']?></em></h1>
    <table class="matrix">
        <thead>
            <tr>
                <th>&nbsp;</th>
                <th>Type</th>
                <th>Can Write</th>
                <th>Can Share</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($item['users'] as $user):?>
            <tr>
                <td><?= $user['name']?></td>
                <td><?= $user['type']?></td>
                <td>
                    <label>
                        <input type="checkbox" <?= ($user['permissions']['WRITE'] == true) ? 'checked' : '' ?>>
                        <span class="icon-checkbox"></span>
                    </label>
                </td>
                <td>
                    <label>
                        <input type="checkbox" <?= ($user['permissions']['GRANT'] == true) ? 'checked' : '' ?>>
                        <span class="icon-checkbox"></span>
                    </label>
                </td>
                <td>
                    <?php if($user['permissions']['OWNER'] == true) :?>
                    <button class="small"<?= (count($item['users']) == 1) ? 'disabled' : '' ?>>Owner <span class="icon-edit r"></span></a>
                    <?php else:?>
                    <button class="small"><span class="icon-bin r"></span></button>
                    <?php endif;?>
                </td>
            </tr>
            <?php endforeach;?>
        </tbody>
    </table>
    <div class="grid-row">
        <div class="col-11">
            <button class="btn-info" type="button" data-modal="#add-user">Add user(s)</button>
            <button class="btn-info" type="button" data-modal="#add-role">Add role(s)</button>
        </div>
        <div class="col-1">
            <button class="btn-info" type="button">Close</button>
        </div>
    </div>
    <?php endforeach;?>


    <div id="add-user" class="modal">
        <h1>Select users to share with</h1>
        <p>
        scotch ale attenuation bottle conditioning gravity attenuation dextrin? <br>
        seidel cold filter becher chocolate malt aroma hops, balthazar pub bock racking.<br>
        aerobic length sparge lager brewhouse bitter hefe? trappist,<br>
        microbrewery pitching abbey berliner weisse chocolate malt, wit infusion. <br>
        seidel aerobic tulip glass wort aerobic final gravity, biere de garde.</p>
    </div>
    <div id="add-role" class="modal">
        <h1>Select roles to share with</h1>
        <p>
        scotch ale attenuation bottle conditioning gravity attenuation dextrin? <br>
        seidel cold filter becher chocolate malt aroma hops, balthazar pub bock racking.<br>
        aerobic length sparge lager brewhouse bitter hefe? trappist,<br>
        microbrewery pitching abbey berliner weisse chocolate malt, wit infusion. <br>
        seidel aerobic tulip glass wort aerobic final gravity, biere de garde.</p>
    </div>
</div>
