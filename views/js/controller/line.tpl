<tr>
    <td>{{label}}</td>
    <td>
        {{type}}
        <input type="hidden" name="users[{{user}}][type]" value="{{type}}">
    </td>
    <td>
        <label>
            <input type="checkbox" name="users[{{user}}][WRITE]" value="1" checked>
            <span class="icon-checkbox"></span>
        </label>
    </td>
    <td>
        <label>
            <input type="checkbox" name="users[{{user}}][GRANT]" value="1">
            <span class="icon-checkbox"></span>
        </label>
    </td>
    <td>
        <button class="small" >{{__ "Transfert ownership"}}</button>
        &nbsp;
        <button class="small delete_permission" data-acl-user="{{user}}" data-acl-type="{{type}}" data-acl-label="{{label}}" >
            <span class="icon-bin"></span>{{__ "Delete"}}
        </button>
    </td>
</tr>
