define([
    'jquery',
    'i18n',
    'select2'
    ], function($, __){
        'use strict';

        var userSelect,
            roleSelect;

        /**
         * Confirm to save the item
         */
        var _confirmTransfertOwnership = function () {

            var confirmBox = $('#ownership-transfert'),
                cancel = confirmBox.find('.cancel'),
                confirm = confirmBox.find('.confirm'),
                close = confirmBox.find('.modal-close');

            confirmBox.modal({ width: 500 });

            confirm.on('click', function () {
                $.ajax({
                    url: '/path/to/file',
                    type: 'POST',
                    dataType: 'default: Intelligent Guess (Other values: xml, json, script, or html)',
                    data: {ressourceID: 'value1', userID: '', userType: ''}, // How to get that ?
                })
                .done(function() {
                    // 1. Activate all transfert ownership buttons & all delete buttons
                    // 2. De-activate the new owner "Transfer ownershop" button
                    // 3. De-activate the new owner "Delete" button
                    // 4. Display success ??
                })
                .fail(function() {
                    // Display Error Message Alert
                })
                .always(function() {
                    confirmBox.modal('close');
                });
            });

            cancel.on('click', function () {
                confirmBox.modal('close');
            });
        };


        var _deletePermission = function(element) {
            // 1. Get the user / role
            var $this = $(element),
                type = $this.data('acl-type'),
                user = $this.data('acl-user'),
                label = $this.data('acl-label');
            // 2. Add it to the select
            switch(type){
                case 'user':
                    $('#add-user').append($('<option/>',{ text : label , value : user }));
                    break;
                case 'role':
                    $('#add-role').append($('<option/>',{ text : label , value : user }));
                    break;
                default:
                    break;
            }
            // 3. Remove the line
            $this.closest('tr').remove();
        }


        var mainCtrl = {
            'start' : function(){
                console.log('started');

                userSelect = $('#add-user').select2();
                roleSelect = $('#add-role').select2();
            }
        }
        return mainCtrl;
    })
