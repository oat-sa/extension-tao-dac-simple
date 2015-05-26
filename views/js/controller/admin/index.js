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
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */
define([
    'jquery',
    'lodash',
    'i18n',
    'tpl!taoDacSimple/controller/admin/line',
    'helpers',
    'ui/feedback',
    'ui/autocomplete',
    'tooltipster',
    'jqueryui'
], function ($, _, __, lineTpl, helpers, feedback, autocomplete) {
    'use strict';

    var errorMsgManagePermission = __('You must have one role or user that have the manage permission on this element.');

    var tooltipConfigManagePermission = {
        content : __(errorMsgManagePermission),
        theme : 'tao-info-tooltip',
        trigger: 'hover'
    };

    /**
     * Checks the managers, we need at least one activated manager.
     * @returns {Boolean} Returns `true` if there is at least one manager in the list
     * @private
     */
    var _checkManagers = function () {
        var $managers = $('#permissions-table').find('.privilege-GRANT:checked');
        var checkOk = true;

        if (!$managers.length) {
            checkOk = false;
        }
        return checkOk;
    };

    /**
     * Avoids to remove all managers
     */
    var _preventManagerRemoval = function(){
        var $container = $('.permission-container');
        var $form = $('form', $container);
        var $submitter = $(':submit', $form);

        $submitter.tooltipster(tooltipConfigManagePermission);
        if (!_checkManagers()) {
            $submitter.addClass('disabled').tooltipster('enable');
        } else {
            $submitter.removeClass('disabled').tooltipster('disable');
        }
    };

    /**
     * Allow to enable / disable the access checkbox based on the state of the grant privilege
     */
    var _disableAccessOnGrant = function () {
        var $managersChecked = $('#permissions-table').find('.privilege-GRANT:checked').closest('tr'),
            $cantWrite = $managersChecked.find('.privilege-WRITE'),
            $cantRead = $managersChecked.find('.privilege-READ'),

            $managers = $('#permissions-table').find('.privilege-GRANT').not(':checked').closest('tr'),
            $canWrite = $managers.find('.privilege-WRITE'),
            $canRead = $managers.find('.privilege-READ');

        $canWrite.removeClass('disabled');
        $canRead.removeClass('disabled');

        $cantWrite.addClass('disabled');
        $cantRead.addClass('disabled');

        _preventManagerRemoval();
    };

    /**
     * Delete a permission row for a user/role
     * @param  {DOM Element} element DOM element that triggered the function
     */
    var _deletePermission = function (element) {
        // 1. Get the user / role
        var $this = $(element),
            type = $this.data('acl-type'),
            user = $this.data('acl-user'),
            label = $this.data('acl-label');

        // 2. Remove it from the list
        if (!_.isEmpty(type) && !_.isEmpty(user) && !_.isEmpty(label)) {
            $this.closest('tr').remove();
        }

        _preventManagerRemoval();
    };

    /**
     * Checks if a permission has already been added to the list.
     * Highlight the list if the permission is already in the list.
     * @param {String} type role/user regarding what it will be added.
     * @param {String} id The identifier of the resource.
     * @returns {boolean} Returns true if the permission is already in the list
     * @private
     */
    var _checkPermission = function (type, id) {
        var $table = $('#permissions-table'),
            $btn = $table.find('button[data-acl-user="' + id + '"]'),
            $line = $btn.closest('tr');

        if ($line.length) {
            $line.effect('highlight', {}, 1500);
            return true;
        }

        return false;
    };

    /**
     * Add a new lines into the permissions table regarding what is selected into the add-* select
     * @param {String} type role/user regarding what it will be added.
     * @param {String} id The identifier of the resource.
     * @param {String} label The label of the resource.
     */
    var _addPermission = function (type, id, label) {
        var $table = $('#permissions-table'),
            $body = $table.find('tbody').first();

        // only add the permission if it's not already present in the list
        if (!_checkPermission(type, id)) {
            $body.append(lineTpl({
                type: type,
                user: id,
                label: label
            }));
        }
    };

    /**
     * Installs a search purpose autocompleter onto an element.
     * @param {jQuery|Element|String} element The element on which install the autocompleter
     * @param {Object} options A list of options to set
     * @returns {Autocompleter} Returns the instance of the autocompleter component
     */
    var searchFactory = function (element, options) {
        if (_.isFunction(options)) {
            options = {
                onSelectItem: options
            };
        }

        options = _.assign({
            isProvider: true,
            preventSubmit: true
        }, options || {});

        return autocomplete(element, options);
    };

    var mainCtrl = {
        'start': function () {

            var $container = $('.permission-container');
            var $form = $('form', $container);
            var $submitter = $(':submit', $form);

            _disableAccessOnGrant();

            // install autocomplete for user add
            searchFactory('#add-user', function (event, value, label) {
                _addPermission('user', value, label);
            });

            // install autocomplete for role add
            searchFactory('#add-role', function (event, value, label) {
                _addPermission('role', value, label);
            });

            /**
             * Ensure that if you give the manage (GRANT) permission, access (WRITE and READ) permissions are given too
             * &
             * Listen all clicks on delete buttons to call the _deletePersmission function
             */
            $('#permissions-table').on('click', '.privilege-GRANT:not(.disabled) ', function () {
                if ($(this).is(':checked') != []) {
                    var $tr = $(this).closest('tr');
                    var $writeCheckbox = $tr.find('.privilege-WRITE').not(':checked').first();
                    var $readCheckbox = $tr.find('.privilege-READ').not(':checked').first();
                    $writeCheckbox.click();
                    $readCheckbox.click();
                }
                _disableAccessOnGrant();
            }).on('click', '.delete_permission:not(.disabled)', function (event) {
                event.preventDefault();
                _deletePermission(this);
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
            });
            $submitter.on('click', function (e) {
                e.preventDefault();

                if ($submitter.hasClass('disabled')) {
                    return;
                }

                if (!_checkManagers()) {
                    feedback().error(errorMsgManagePermission);
                   return;
                }

                $submitter.addClass('disabled');

                $.post($form.attr('action'), $form.serialize())
                    .done(function (res) {
                        if (res && res.success) {
                            feedback().success(__("Permissions saved"));
                        } else {
                            feedback().error(__("Something went wrong..."));
                        }
                    })
                    .complete(function () {
                        $submitter.removeClass('disabled');
                    });
            });
        }
    };

    return mainCtrl;
});
