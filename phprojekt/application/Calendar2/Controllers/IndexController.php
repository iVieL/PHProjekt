<?php
/**
 * Calendar2 Module Controller.
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @category   PHProjekt
 * @package    Application
 * @subpackage Calendar2
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Simon Kohlmeyer <simon.kohlmeyer@mayflower.de>
 */

/**
 * Calendar2 Module Controller.
 *
 * @category   PHProjekt
 * @package    Application
 * @subpackage Calendar2
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Simon Kohlmeyer <simon.kohlmeyer@mayflower.de>
 */
class Calendar2_IndexController extends IndexController
{
    /**
     * Returns all events in the given period of time that the user is
     * involved in. Only days are recognized.
     *
     * Request parameters:
     * <pre>
     *  - datetime Start
     *  - datetime End
     * </pre>
     */
    public function jsonPeriodListAction()
    {
        $dateStart = $this->getRequest()->getParam('dateStart');
        $dateEnd   = $this->getRequest()->getParam('dateEnd');

        if (!Cleaner::validate('isoDate', $dateStart)) {
            throw new Phprojekt_PublishedException(
                "Invalid dateStart '$dateStart'"
            );
        }
        if (!Cleaner::validate('isoDate', $dateEnd)) {
            throw new Phprojekt_PublishedException("Invalid dateEnd $dateEnd");
        }

        $timezone = $this->_getUserTimezone();
        $start = new Datetime($dateStart, $timezone);
        $start->setTime(0, 0, 0);
        $end = new Datetime($dateEnd, $timezone);
        $end->setTime(23, 59, 59);

        $model  = new Calendar2_Models_Calendar2();
        $events = $model->fetchAllForPeriod($start, $end);

        Phprojekt_Converter_Json::echoConvert(
            $events,
            Phprojekt_ModelInformation_Default::ORDERING_FORM
        );
    }

    /**
     * Returns all events on the given day that the user is involved in.
     *
     * Request parameters:
     * <pre>
     *  - datetime date
     * </pre>
     */
    public function jsonDayListSelfAction()
    {
        $date = $this->getRequest()->getParam('date');

        if (!Cleaner::validate('isoDate', $date)) {
            throw new Phprojekt_PublishedException("Invalid date '$date'");
        }

        $start = new Datetime($date, $this->_getUserTimezone());
        $start->setTime(0, 0, 0);

        $end = clone $start;
        $end->setTime(23, 59, 59);

        $model  = new Calendar2_Models_Calendar2();
        $events = $model->fetchAllForPeriod($start, $end);

        Phprojekt_Converter_Json::echoConvert(
            $events,
            Phprojekt_ModelInformation_Default::ORDERING_FORM
        );
    }

    /**
     * Saves the current item.
     *
     * If the request parameter "id" is null or 0, the function will add a new item,
     * if the "id" is an existing item, the function will update it.
     *
     * Request parameters:
     * <pre>
     *  - integer <b>id</b>                      id of the item to save.
     *  - string  <b>start</b>                   Start datetime of the item or recurring.
     *  - string  <b>end</b>                     End datetime of the item or recurring.
     *  - string  <b>rrule</b>                   Recurrence rule.
     *  - array   <b>dataParticipant</b>         Array with users id involved in the event.
     *  - boolean <b>multipleEvents</b>          Aply the save for one item or multiple events.
     *  - mixed   <b>all other module fields</b> All the fields values to save.
     * </pre>
     *
     * If there is an error, the save will return a Phprojekt_PublishedException,
     * if not, it returns a string in JSON format with:
     * <pre>
     *  - type    => 'success'.
     *  - message => Success message.
     *  - code    => 0.
     *  - id      => Id of the item.
     * </pre>
     *
     * @throws Phprojekt_PublishedException On error in the action save or wrong id.
     *
     * @return void
     */
    public function jsonSaveAction()
    {
        $id = $this->getRequest()->getParam('id');
        $recurrenceId = $this->getRequest()->getParam('recurrenceId');

        if (!Cleaner::validate('int', $id, true)
                && 'null'      !== $id
                && 'undefined' !== $id) {
            throw new Phprojekt_PublishedException("Invalid id '$id'");
        }
        if (!preg_match('/\d{8}T\d{6}/', $recurrenceId)) {
            throw new Phprojekt_PublishedException(
                "Invalid reucrrenceId '$recurrenceId'"
            );
        }

        $id = (int) $id;

        // Note that all function this gets passed to must validate the
        // parameters they use.
        $params = $this->getRequest()->getParams();

        $model   = new Calendar2_Models_Calendar2();
        $message = Phprojekt::getInstance()->translate(self::ADD_TRUE_TEXT);

        if (!empty($id)) {
            $start = new Datetime(
                $this->getRequest()->getParam('recurrenceId'),
                $this->_getUserTimezone()
            );
            $model->findOccurrence($id, $start);
            $message = Phprojekt::getInstance()->translate(self::EDIT_TRUE_TEXT);
        }

        if (!empty($id) && $model->ownerId != Phprojekt_Auth::getUserId()) {
            $newId = $this->_updateConfirmationStatusAction($model, $params);
        } else {
            $newId = $this->_saveAction($model, $params);
        }

        Phprojekt_Converter_Json::echoConvert(array(
            'type'    => 'success',
            'message' => $message,
            'code'    => 0,
            'id'      => $newId
        ));
    }

    public function jsonDetailAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $start = $this->getRequest()->getParam('start');

        if (!Cleaner::validate('int', $id) && 'null' !== $id) {
            throw new Phprojekt_PublishedException("Invalid id '$id'");
        }
        if (!self::_validateTimestamp($start) && 'undefined' !== $start) {
            throw new Phprojekt_PublishedException(
                "Invalid start timestamp '$start'"
            );
        }

        $id = (int) $id;

        if ('undefined' === $start) {
            $start = null;
        } else {
            $start = new Datetime($start, $this->_getUserTimezone());
        }
        $this->setCurrentProjectId();

        $record = new Calendar2_Models_Calendar2;

        if (!empty($id)) {
            if (empty($start)) {
                $record = $record->find($id);
            } else {
                $record = $record->findOccurrence($id, $start);
            }
        }

        Phprojekt_Converter_Json::echoConvert(
            $record,
            Phprojekt_ModelInformation_Default::ORDERING_FORM
        );
    }

    /**
     * Deletes a certain item.
     *
     * REQUIRED request parameters:
     * <pre>
     *  - integer <b>id</b> id of the item to delete.
     * </pre>
     *
     * Optional request parameters:
     * <pre>
     *  - timestamp <b>start</b>          The start date of the occurrence
     *                                    to delete.
     *  - boolean   <b>multipleEvents</b> Whether all events in this series
     *                                    beginning with this one should be
     *                                    deleted or just this
     *                                    single occurrence.
     * </pre>
     *
     * The return is a string in JSON format with:
     * <pre>
     *  - type    => 'success' or 'error'.
     *  - message => Success or error message.
     *  - code    => 0.
     *  - id      => id of the deleted item.
     * </pre>
     *
     * @throws Phprojekt_PublishedException On wrong parameters.
     *
     * @return void
     */
    public function jsonDeleteAction()
    {
        $id       = $this->getRequest()->getParam('id');
        $start    = $this->getRequest()->getParam('start');
        $multiple = $this->getRequest()->getParam('multipleEvents', 'true');

        if (!Cleaner::validate('int', $id)) {
            throw new Phprojekt_PublishedException("Invalid id '$id'");
        }
        if (!self::_validateTimestamp($start)) {
            throw new Phprojekt_PublishedException("Invalid start timestamp '$start'");
        }
        if (!Cleaner::validate('boolean', $multiple)) {
            throw new Phprojekt_PublishedException("Invalid multiple '$multiple'");
        }

        $id = (int) $id;
        $multiple = ('true' == strtolower($multiple));

        $model = new Calendar2_Models_Calendar2;

        if (empty($start)) {
            $model = $model->find($id);
        } else {
            $start = new Datetime($start, $this->_getUserTimezone());
            $model = $model->findOccurrence($id, $start);
        }

        if ($multiple) {
            $model->delete();
        } else {
            $model->deleteSingleEvent();
        }

        Phprojekt_Converter_Json::echoConvert(
            array(
                'type'    => 'success',
                'message' => Phprojekt::getInstance()->translate(
                    self::DELETE_TRUE_TEXT
                ),
                'code'    => 0,
                'id'      => $id
            )
        );
    }

    /**
     * Updates the current user's confirmation status on the given event.
     *
     * @param Calendar2_Models_Calendar2 $model  The model to update.
     * @param Array                      $params The Request's parameters. All
     *                                           values taken from this array
     *                                           will be validated.
     *
     * @return int The id of the (new) model object.
     *
     * @throws Phprojekt_PublishedException On malformed $params content.
     */
    private function _updateConfirmationStatusAction($model, $params)
    {
        $status   = $params['confirmationStatus'];
        $multiple = array_key_exists('multipleEvents', $params)
            ? $params['multipleEvents']
            : 'true';

        if (!Cleaner::validate('int', $status)
                || !Calendar2_Models_Calendar2::isValidStatus((int) $status)) {
            throw new Phprojekt_PublishedException(
                "Invalid confirmationStatus '$status'"
            );
        }
        if (!Cleaner::validate('boolean', $multiple)) {
            throw new Phprojekt_PublishedException(
                "Invalid multiple '$multiple'"
            );
        }

        $status   = (int) $status;
        $multiple = ('true' == strtolower($multiple));

        $model->setConfirmationStatus(Phprojekt_Auth::getUserId(), $status);

        if ($multiple) {
            $model->save();
        } else {
            $model->saveSingleEvent();
        }

        return $model->id;
    }

    /**
     * Saves the model.
     *
     * @param Calendar2_Models_Calendar2 $model  The model object
     * @param Array                      $params The request's parameters. All
     *                                           values taken from this array
     *                                           will be validated.
     *
     * @return int The id of the (new) model object.
     */
    private function _saveAction($model, $params)
    {
        $participants = array_key_exists('newParticipants', $params)
            ? $params['newParticipants']
            : array();
        $location    = trim($params['location']);
        $start       = $params['start'];
        $end         = $params['end'];
        $summary     = trim($params['summary']);
        $description = trim($params['description']);
        $comments    = trim($params['comments']);
        $visibility  = $params['visibility'];
        $rrule       = array_key_exists('rrule', $params)
            ? trim($params['rrule'])
            : null;
        $multiple = array_key_exists('multipleEvents', $params)
            ? $params['multipleEvents']
            : 'true';

        if (!is_array($participants)) {
            throw new Phprojekt_PublishedException(
                "Invalid newParticipants '$participants'"
            );
        }
        foreach ($participants as $p) {
            if (!Cleaner::validate('int', $p)) {
                //TODO: Check if the participant exists?
                throw new Phprojekt_PublishedException(
                    "Invalid participant $p"
                );
            }
        }
        if (!self::_validateTimestamp($start)) {
            throw new Phprojekt_PublishedException("Invalid start '$start'");
        }
        if (!self::_validateTimestamp($end)) {
            throw new Phprojekt_PublishedException("Invalid end '$end'");
        }
        if (!Cleaner::validate('int', $visibility)
                || !Calendar2_Models_Calendar2::isValidVisibility(
                        (int) $visibility
                   )) {
           throw new Phprojekt_PublishedException(
               "Invalid visibility '$visibility'"
            );
        }
        $visibility = (int) $visibility;
        if (!Cleaner::validate('boolean', $multiple)) {
            throw new Phprojekt_PublishedException("Invalid multiple '$multiple'");
        }
        $multiple = ('true' == strtolower($multiple));

        $model->ownerId = Phprojekt_Auth::getUserId();
        $model->setParticipants($participants);

        if ($model->id
                && ($model->location !== $location
                    || $model->start !== $start
                    || $model->end   !== $end)) {
            $model->setParticipantsConfirmationStatuses(
                Calendar2_Models_Calendar2::STATUS_PENDING
            );
        }

        $model->summary     = $summary;
        $model->description = $description;
        $model->location    = $location;
        $model->comments    = $comments;
        $model->visibility  = $visibility;

        // Using Datetime would be much nicer here.
        // But Phprojekt doesn't really support Datetime yet.
        // (Dates will automatically be converted from Usertime to UTC)
        $model->start = $start;
        $model->end   = $end;
        $model->rrule = $rrule;

        if ($multiple) {
            $model->save();
        } else {
            $model->saveSingleEvent();
        }

        return $model->id;
    }

    /**
     * This function wraps around the phprojekt setting for the user timezone
     * to return a DateTimeZone object.
     *
     * @return DateTimeZone The timezone of the user.
     */
    private function _getUserTimezone()
    {
        $tz = Phprojekt_User_User::getSetting('timezone', '0');
        $tz = explode('_', $tz);
        $hours = (int) $tz[0];
        if ($hours >= 0) {
            $hours = '+' . $hours;
        }
        $minutes = '00';
        if (array_key_exists(1, $tz)) {
            // We don't need the minus sign
            $minutes = abs($tz[1]);
        }
        $datetime = new Datetime($hours . ':' . $minutes);
        return $datetime->getTimezone();
    }

    /**
     * Wrapper around Cleaner::validate('timestamp', $value) because the client
     * sends timestamps without seconds. Set emptyOk if null values are
     * permitted.
     */
    private static function _validateTimestamp($value, $emptyOk = false)
    {
        if (preg_match('/\d{4}-\d\d-\d\d \d\d:\d\d/', $value)) {
            return true;
        } else {
            return Cleaner::validate('timestamp', $value, $emptyOk);
        }
    }
}
