<?php
/**
 * Handler for assessmentValuesInListQuestionsRPC Plugin for LimeSurvey
 * Based on the work by Denis Chenu:
 * https://gitlab.com/SondagesPro/RemoteControl/extendRemoteControl
 * 
 * @author Riccardo Angius <riccardo.angius@pm.me>
 * @copyright 2015-2016 Denis Chenu <http://sondages.pro> and 2023 Riccardo Angius
 * @license GPL v3
 * @version 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */


class RemoteControlHandler extends remotecontrol_handle
{
    protected $storage = 'DbStorage';
    static protected $description = 'Endpoint for questions with assessment values plugin';


    public function __construct(AdminController $controller)
    {
        /* Deactivate web log */
        foreach (Yii::app()->log->routes as $route) {
            $route->enabled = $route->enabled && !($route instanceOf CWebLogRoute);
        }
        parent::__construct($controller);
    }


    /**
     * Return the ids and info of (sub-)questions of a survey/group (RPC function)
     * together with information of the assessment values of possible answers.
     * Modeled against the RemoteControl list_questions method.
     *
     * Returns array of ids and info.
     *
     * @access public
     * @param string $sSessionKey Auth credentials
     * @param int $iSurveyID ID of the Survey to list questions
     * @param int $iGroupID Optional id of the group to list questions
     * @param string $sLanguage Optional parameter language for multilingual questions
     * @return array The list of questions
     */

    public function list_questions_with_assessment_values($sSessionKey, $iSurveyID, $iGroupID = null, $sLanguage = null)
    {
        if ($this->_checkSessionKey($sSessionKey)) {
            Yii::app()->loadHelper("surveytranslator");
            $iSurveyID = (int) $iSurveyID;
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey)) {
                return array('status' => 'Error: Invalid survey ID');
            }

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'read')) {
                if (is_null($sLanguage)) {
                    $sLanguage = $oSurvey->language;
                }

                if (!array_key_exists($sLanguage, getLanguageDataRestricted())) {
                    return array('status' => 'Error: Invalid language');
                }

                if ($iGroupID != null) {
                    $iGroupID = (int) $iGroupID;
                    $oGroup = QuestionGroup::model()->findByAttributes(array('gid' => $iGroupID));
                    $sGroupSurveyID = $oGroup['sid'];

                    if ($sGroupSurveyID != $iSurveyID) {
                        return array('status' => 'Error: IMissmatch in surveyid and groupid');
                    } else {
                        $aQuestionList = Question::model()->findAllByAttributes(array("sid" => $iSurveyID, "gid" => $iGroupID, "language" => $sLanguage));
                    }
                } else {
                    $aQuestionList = Question::model()->findAllByAttributes(array("sid" => $iSurveyID, "language" => $sLanguage));
                }

                if (count($aQuestionList) == 0) {
                    return array('status' => 'No questions found');
                }

                foreach ($aQuestionList as $oQuestion) {
                    $aData[] = array('id' => $oQuestion->primaryKey) + $oQuestion->attributes;

                    $oAttributes = Answer::model()->findAllByAttributes(array('qid' => $oQuestion->primaryKey, 'language' => $sLanguage), array('order' => 'sortorder'));
    

                    if (count($oAttributes) > 0) {
                        $aAnswerData = array();
                        foreach ($oAttributes as $oAttribute) {
                            $aAnswerData[$oAttribute['code']]['assessment_value'] = $oAttribute['assessment_value'];
                        }
                        $aData['answeroptions'] = $aAnswerData;
                    }


                }
                return $aData;
            } else {
                return array('status' => 'No permission');
            }
        } else {
            return array('status' => 'Invalid session key');
        }
    }
}