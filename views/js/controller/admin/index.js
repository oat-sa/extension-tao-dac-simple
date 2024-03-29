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
 * Copyright (c) 2015-2020 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */
define([
    'jquery',
    'i18n',
    'tpl!taoDacSimple/controller/admin/line',
    'helpers',
    'ui/feedback',
    'ui/autocomplete',
    'ui/tooltip',
    'core/request',
    'ui/taskQueue/taskQueue',
    'ui/taskQueueButton/standardButton',
], function (
    $,
    __,
    lineTpl,
    helpers,
    feedback,
    autocomplete,
    tooltip,
    request,
    taskQueue,
    taskCreationButtonFactory
) {
    'use strict';

    /**
     * The warning message shown when all managers have been removed
     * @type {String}
     */
    const errorMsgManagePermission = __('You must have one role or user that have the manage permission on this element.');

    /**
     * tooltip instance that serves all methods with same tooltip data and its state
     * @type {Tooltip}
     */
    let errorTooltip;
    /**
     * Checks the managers, we need at least one activated manager.
     * @param {jQuery|Element|String} container
     * @returns {Boolean} Returns `true` if there is at least one manager in the list
     * @private
     */
    const _checkManagers = function (container) {
        const $managers = $(container).find('.privilege-GRANT:checked');
        let checkOk = true;

        if (!$managers.length) {
            checkOk = false;
        }
        return checkOk;
    };

    /**
     * Avoids to remove all managers
     * @param {jQuery|Element|String} container
     * @private
     */
    const _preventManagerRemoval = function (container) {
        const $form = $(container).closest('form');
        let $submitter = $(':submit', $form);
        if ($submitter.length === 0) {
            $submitter = $('.bottom-bar button', $form);
        }

        if (!_checkManagers($form)) {
            $submitter.addClass('disabled');
            $submitter.attr('disabled', 'disabled');
            errorTooltip = tooltip.warning($submitter, errorMsgManagePermission, {
                placement : 'bottom',
                trigger: "hover",
            });
            feedback().warning(errorMsgManagePermission);
        } else {
            $submitter.removeClass('disabled');
            $submitter.removeAttr('disabled');
            if(errorTooltip){
                errorTooltip.dispose();
            }
        }
    };

    /**
     * Allow to enable / disable the access checkbox based on the state of the grant privilege
     * @param {jQuery|Element|String} container
     * @private
     */
    const _disableAccessOnGrant = function (container) {
        const $container = $(container);

        const $managersChecked = $container.find('.privilege-GRANT:checked').closest('tr');
        const $cantChangeWrite = $managersChecked.find('.privilege-WRITE');
        const $cantChangeRead = $managersChecked.find('.privilege-READ');

        const $managers = $container.find('.privilege-GRANT').not(':checked').closest('tr');
        const $canChangeWrite = $managers.find('.privilege-WRITE');
        const $canChangeRead = $managers.find('.privilege-READ');

        $canChangeWrite.removeClass('disabled');
        $canChangeRead.removeClass('disabled');

        $cantChangeWrite.addClass('disabled').prop('checked', true);
        $cantChangeRead.addClass('disabled').prop('checked', true);

        _preventManagerRemoval($container);
        _disableAccessOnWrite($container);
    };

    /**
     * Allow to enable / disable the access checkbox based on the state of the write privilege
     * @param {jQuery|Element|String} container
     * @private
     */
    const _disableAccessOnWrite = function (container) {
        const $container = $(container);

        const $writersChecked = $container.find('.privilege-WRITE:checked').closest('tr');
        const $cantChangeRead = $writersChecked.find('.privilege-READ');

        const $writers = $container.find('.privilege-WRITE').not(':checked').closest('tr');
        const $canChangeRead = $writers.find('.privilege-READ');

        $canChangeRead.removeClass('disabled');

        $cantChangeRead.addClass('disabled').prop('checked', true);
    };

    /**
     * Delete a permission row for a user/role
     * @param  {DOM Element} element DOM element that triggered the function
     * @private
     */
    const _deletePermission = function (element) {
        // 1. Get the user / role
        const $this = $(element);
        const $container = $this.closest('table');
        const type = $this.data('acl-type');
        const user = $this.data('acl-user');
        const label = $this.data('acl-label');

        // 2. Remove it from the list
        if (type && user && label) {
            $this.closest('tr').remove();
        }

        _preventManagerRemoval($container);
    };

    /**
     * Checks if a permission has already been added to the list.
     * Highlight the list if the permission is already in the list.
     * @param {jQuery|Element|String} container
     * @param {String} type role/user regarding what it will be added.
     * @param {String} id The identifier of the resource.
     * @returns {boolean} Returns true if the permission is already in the list
     * @private
     */
    const _checkPermission = function (container, type, id, label) {
        const $btn = $(container).find('button[data-acl-user="' + id + '"]');
        const $line = $btn.closest('tr');
        let message;

        if (container.selector === '#permissions-table-users') {
            message = `${ label } is already in the list of users with access permissions.`;
        } else {
            message = `${ label } is already in the list of roles with access permissions.`;
        }

        if ($line.length) {
            feedback().info(message);
            return true;
        }

        return false;
    };

    /**
     * Add a new lines into the permissions table regarding what is selected into the add-* select
     * @param {jQuery|Element|String} container
     * @param {String} type role/user regarding what it will be added.
     * @param {String} id The identifier of the resource.
     * @param {String} label The label of the resource.
     * @private
     */
    const _addPermission = function (container, type, id, label) {
        const $container = $(container),
            $body = $container.find('tbody').first();

        // only add the permission if it's not already present in the list
        if (!_checkPermission($container, type, id, label)) {
            $body.append(lineTpl({
                type: type,
                user: id,
                label: label
            }));
            _disableAccessOnGrant($container);
        }
    };

    /**
     * Ensures that if you give the manage (GRANT) permission, access (WRITE and READ) permissions are given too
     * Listens all clicks on delete buttons to call the _deletePermission function
     * @param {jQuery|Element|String} container The container on which apply the listeners
     * @private
     */
    const _installListeners = function(container) {
        const $container = $(container);
        $container.on('click', '.privilege-GRANT:not(.disabled) ', function () {
            _disableAccessOnGrant($container);
        }).on('click', '.privilege-WRITE:not(.disabled) ', function () {
            _disableAccessOnWrite($container);
        }).on('click', '.delete_permission:not(.disabled)', function (event) {
            event.preventDefault();
            _deletePermission(this);
        });
    };


    /**
     * Installs a search purpose autocompleter onto an element.
     * @param {jQuery|Element|String} element The element on which install the autocompleter
     * @param {jQuery|Element|String} appendTo Container where suggestions will be appended. Default value document.body. Make sure to set position: absolute or position: relative for that element
     * @param {Function} onSelectItem - The selection callback
     * @returns {Autocompleter} Returns the instance of the autocompleter component
     */
    const _searchFactory = function (element, appendTo, onSelectItem) {
        const autocompleteOptions = {
            isProvider: true,
            preventSubmit: true,
            appendTo: appendTo,
            ontologyParam: ['rootNode', 'parentNode'],
            params: {
                'params[structure]': '',
                rows: 20,
                page: 1,
                parentNode: ''
            },
            labelField: 'label'
        };
        if (typeof onSelectItem === "function") {
            autocompleteOptions.onSelectItem = onSelectItem;
        }
        return autocomplete(element, autocompleteOptions);
    };

    const mainCtrl = {
        start: function start () {

            const $container = $('.permission-container');
            const $form = $('form', $container);
            const $oldSubmitter = $(':submit', $form);

            _disableAccessOnGrant('#permissions-table-users');
            _disableAccessOnGrant('#permissions-table-roles');

            // install autocomplete for user add
            _searchFactory('#add-user', '#add-user-wrapper', function (event, value, label) {
                $('#add-user').focus();
                _addPermission('#permissions-table-users', 'user', value, label);
            });

            // install autocomplete for role add
            _searchFactory('#add-role','#add-role-wrapper', function (event, value, label) {
                $('#add-role').focus();
                _addPermission('#permissions-table-roles', 'role', value, label);
            });

            // ensure that if you give the manage (GRANT) permission, access (WRITE and READ) permissions are given too
            _installListeners('#permissions-table-users');
            _installListeners('#permissions-table-roles');

            //find the old submitter and replace it with the new component
            const taskCreationButton = taskCreationButtonFactory({
                type : 'info',
                icon : 'save',
                title : __('Save'),
                label : __('Save'),
                taskQueue : taskQueue,
                taskCreationUrl : $form.attr('action'),
                taskCreationData : function taskCreationData() {
                    return $form.serializeArray();
                },
                taskReportContainer : $container
            }).on('finished', function(result){
                if (result.task
                    && result.task.report
                    && Array.isArray(result.task.report.children)
                    && result.task.report.children.length
                    && result.task.report.children[0]) {
                    if(result.task.report.children[0].type === 'success'){
                        feedback().success(__('Permissions saved'));
                    } else {
                        feedback().error( __('Error'));
                    }
                }
            }).on('error', function(err){
                //format and display error message to user
                feedback().error(err);
            }).render($oldSubmitter.closest('.bottom-bar'));

            //replace the old submitter with the new one and apply its style
            $oldSubmitter.replaceWith(taskCreationButton.getElement().css({float: 'right'}));

            $form.on('submit', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
            });
        }
    };

    return mainCtrl;
});
