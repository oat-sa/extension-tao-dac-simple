define([
    'jquery',
    'i18n',
    'tpl!taoDacSimple/controller/line',
    'helpers',
    'select2'
    ], function($, __, lineTpl, helpers){
        'use strict';

        var userSelect,
            roleSelect;

        /**
         * Confirm and transfert ownership with XHR call
         * @param  {array} data Datas contained by the dataStore of the button clicked
         */
        var _confirmTransfertOwnership = function (data) {

            var confirmBox = $('#ownership-transfert'),
                cancel = confirmBox.find('.cancel'),
                confirm = confirmBox.find('.confirm'),
                close = confirmBox.find('.modal-close'),
                ressourceId = $('#resource_id').val();

            confirmBox.modal({ width: 500 });

            confirm.on('click', function () {
                $.ajax({
                    url: helpers._url('transferOwnership','taoDacSimple','taoDacSimple'),
                    type: 'POST',
                    //dataType: '',
                    data: {resource: ressourceId, user: data.user, user_type: data.type},
                })
                .done(function() {
                    // 1. Activate all transfert ownership buttons & all delete buttons
                    // 2. De-activate the new owner "Transfer ownershop" button
                    // 3. De-activate the new owner "Delete" button
                    // 4. Display success ??
                    helpers.createInfoMessage('YEAH');
                })
                .fail(function() {
                    helpers.createErrorMessage("NAY !");
                })
                .always(function() {
                    confirmBox.modal('close');
                });
            });

            cancel.on('click', function () {
                confirmBox.modal('close');
            });
        };

        /**
         * Delete a permission row for a user/role
         * @param  {DOM Element} element DOM element that triggered the function
         */
        var _deletePermission = function(element) {
            // 1. Get the user / role
            var $this = $(element),
                type = $this.data('acl-type'),
                user = $this.data('acl-user'),
                label = $this.data('acl-label');

            if( typeof type !== "undefined" &&
                typeof user !== "undefined" &&
                typeof label !== "undefined" &&
                type !== "" && user !== "" && label !== ""){
                // 2. Add it to the select & remove the line
                switch(type){
                    case 'user':
                        $('#add-user').append($('<option/>',{ text : label , value : user }));
                        $this.closest('tr').remove();
                        break;
                    case 'role':
                        $('#add-role').append($('<option/>',{ text : label , value : user }));
                        $this.closest('tr').remove();
                        break;
                    default:
                        break;
                }
            }
        }
        /**
         * Add a new lines into the permissions table regarding what is selected into the add-* select
         * @param {string} type role/user regarding what it will be added.
         */
        var _addPermission = function(type) {
            var $table = $('#permissions-table'),
                body = $table.find('tbody')[0],
                selection = [];
            //1. Get a list of all elements to add
            switch(type){
                case 'user':
                    $.each(userSelect.select2("data"), function(index, val) {
                        // Push each selected element into an array
                        selection.push({
                            type : 'user',
                            user : val.id,
                            label : val.text
                        });
                        // Remove them from DOM
                        userSelect.find('option[value="' + val.id + '"]').remove();
                    });
                    // Reset Select2 tag display
                    userSelect.select2("val","");
                    break;
                case 'role':
                    $.each(roleSelect.select2("data"), function(index, val) {
                        // Push each selected element into an array
                        selection.push({
                            type : 'role',
                            user : val.id,
                            label : val.text
                        });
                        // Remove them from DOM
                        roleSelect.find('option[value="' + val.id + '"]').remove();
                    });
                    // Reset Select2 tag display
                    roleSelect.select2("val","");
                    break;
                default:
                    break;
            }
            // 2. Inject them into the table
            $.each(selection, function(index,val) {
                $(body).append(lineTpl(val));
            });
        }


        var mainCtrl = {
            'start' : function(){
                console.log('started');

                userSelect = $('#add-user').select2();
                roleSelect = $('#add-role').select2();

                /**
                 * Listen all clicks on delete buttons to call the _deletePersmission function
                 */
                $('.delete_permission').on('click', function(event) {
                    event.preventDefault();
                    _deletePermission(this);
                });
                /**
                 * Listen clicks on add user button
                 */
                $('#add-user-btn').on('click', function(event) {
                    event.preventDefault();
                    _addPermission('user');
                });
                /**
                 * Listen clicks on add role button
                 */
                $('#add-role-btn').on('click', function(event) {
                    event.preventDefault();
                    _addPermission('role');
                });

                $('.transfert_ownership').on('click', function(event) {
                    event.preventDefault();
                    _confirmTransfertOwnership($(this).data());
                });
            }
        }
        return mainCtrl;
    })
