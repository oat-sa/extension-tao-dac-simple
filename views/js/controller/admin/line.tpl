<tr>
    <td>{{label}}</td>
    <td>
        {{type}}
        <input type="hidden" name="users[{{user}}][type]" value="{{type}}">
    </td>
    <td>
        <label>
            <input type="checkbox" class="can-share" name="users[{{user}}][GRANT]" value="1">
            <span class="icon-checkbox"></span>
        </label>
    </td>
    <td>
        <label>
            <input type="checkbox" class="can-manage" name="users[{{user}}][WRITE]" value="1" checked>
            <span class="icon-checkbox"></span>
        </label>
    </td>
    <td>
        <button type="button" class="small delete_permission tooltip btn-link" data-acl-user="{{user}}" data-acl-type="{{type}}" data-acl-label="{{label}}" >
            <span class="icon-bin"></span>{{__ "Remove"}}
        </button>
    </td>
</tr>
