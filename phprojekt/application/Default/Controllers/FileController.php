<?php
/**
 * File Controller for PHProjekt 6
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 2.1 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    $Id$
 * @author     Gustavo Solt <solt@mayflower.de>
 * @package    PHProjekt
 * @subpackage Default
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 */

/**
 * File Controller for PHProjekt 6
 *
 * The controller will get all the actions for manage upload files
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @version    Release: @package_version@
 * @license    LGPL 2.1 (See LICENSE file)
 * @package    PHProjekt
 * @subpackage Default
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @author     Gustavo Solt <solt@mayflower.de>
 */
class FileController extends IndexController
{
    /**
     * The function sets up the template upload.phtml and renders it.
     *
     * This function draws the upload field in the form.
     *
     * <pre>
     * OPTIONAL request parameters:
     *  - string  <b>moduleName</b> Current module name.
     *  - string  <b>value</b>      Value for the upload field (All the uploaded filenames).
     *  - integer <b>id</b>         Id of the current item.
     *  - string  <b>field</b>      Name of the field in the module.
     * </pre>
     *
     * @return void
     */
    public function fileFormAction()
    {
        $module = Cleaner::sanitize('alnum', $this->getRequest()->getParam('moduleName', 'Project'));
        $value  = (string) $this->getRequest()->getParam('value', null);
        $itemId = (int) $this->getRequest()->getParam('id', null);
        $field  = Cleaner::sanitize('alnum', $this->getRequest()->getParam('field', null));

        $this->getResponse()->clearHeaders();
        $this->getResponse()->clearBody();

        $linkBegin = Phprojekt::getInstance()->getConfig()->webpath . 'index.php/Default/File/';

        $_SESSION['uploadedFiles_' . $field] = $value;

        $this->_fileRenderView($linkBegin, $module, $itemId, $field, $value, false);
    }

    /**
     * Runs the upload routine and then rendera the upload.phtml template.
     *
     * This function draws the upload field in the form.
     *
     * <pre>
     * OPTIONAL request parameters:
     *  - string  <b>moduleName</b>    Current module name.
     *  - string  <b>field</b>         Name of the field in the module.
     *  - integer <b>MAX_FILE_SIZE</b> Max size allowed for the file.
     *  - integer <b>itemId</b>        Id of the current item.
     * </pre>
     *
     * @return void
     */
    public function fileUploadAction()
    {
        $module     = Cleaner::sanitize('alnum', $this->getRequest()->getParam('moduleName', 'Project'));
        $field      = Cleaner::sanitize('alnum', $this->getRequest()->getParam('field', null));
        $value      = $_SESSION['uploadedFiles_' . $field];
        $maxSize    = (int) $this->getRequest()->getParam('MAX_FILE_SIZE', null);
        $itemId     = (int) $this->getRequest()->getParam('itemId', null);
        $addedValue = '';

        $model = Phprojekt_Loader::getModel($module, $module);
        $this->_fileCheckWritePermission($model, $itemId);

        // Fix name for save it as md5
        if (is_array($_FILES) && !empty($_FILES) && isset($_FILES['uploadedFile'])) {
            $md5name                        = md5(uniqid(rand(), 1));
            $addedValue                     = $md5name . '|' . $_FILES['uploadedFile']['name'];
            $_FILES['uploadedFile']['name'] = $md5name;
        }

        $adapter = new Zend_File_Transfer_Adapter_Http();
        $adapter->setDestination(Phprojekt::getInstance()->getConfig()->uploadpath);

        $this->getResponse()->clearHeaders();
        $this->getResponse()->clearBody();

        if (!$adapter->receive()) {
            $messages = $adapter->getMessages();
            foreach ($messages as $index => $message) {
                $messages[$index] = Phprojekt::getInstance()->translate($message);
                if ($index == 'fileUploadErrorFormSize') {
                    $maxSize           = (int) ($maxSize / 1024);
                    $messages[$index] .= ': ' . $maxSize . ' Kb.';
                }
            }
            $this->view->errorMessage = implode("\n", $messages);
        } else {
            if (!empty($value)) {
                $value .= '||';
            }
            $value .= $addedValue;
        }
        $_SESSION['uploadedFiles_' . $field] = $value;

        $linkBegin = Phprojekt::getInstance()->getConfig()->webpath . 'index.php/Default/File/';

        $this->_fileRenderView($linkBegin, $module, $itemId, $field, $value, true);
    }

    /**
     * Retrieves the file from upload folder.
     *
     * <pre>
     * OPTIONAL request parameters:
     *  - string  <b>moduleName</b>  Current module name.
     *  - integer <b>itemId</b>      Id of the current item.
     *  - string  <b>field</b>       Name of the field in the module.
     *  - integer <b>order</b>       Position of the file (Can be many uploaded files in the same field).
     * </pre>
     *
     * @return void
     */
    public function fileDownloadAction()
    {
        $module = Cleaner::sanitize('alnum', $this->getRequest()->getParam('moduleName', 'Project'));
        $itemId = (int) $this->getRequest()->getParam('itemId', null);
        $field  = Cleaner::sanitize('alnum', $this->getRequest()->getParam('field', null));
        $order  = (int) $this->getRequest()->getParam('order', null);

        $model = Phprojekt_Loader::getModel($module, $module);
        $this->_fileCheckParamField($model, $field);

        if ($itemId > 0) {
            $model->find($itemId);
            // The user has download permission?
            $rights = $model->getRights();
            if (!$rights['currentUser']['download']) {
                $error = Phprojekt::getInstance()->translate('You don\'t have permission for downloading on this '
                    + 'item.');
                die($error);
            }
            $files = $model->$field;
        } else {
            $files = $_SESSION['uploadedFiles_' . $field];
        }
        $files = explode('||', $files);

        $this->_fileCheckParamOrder($order, count($files));

        list($md5Name, $fileName) = explode("|", $files[$order - 1]);

        if (!empty($fileName)) {
            $md5Name = Phprojekt::getInstance()->getConfig()->uploadpath . $md5Name;
            if (file_exists($md5Name)) {
                header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
                header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
                header("Cache-Control: no-store, no-cache, must-revalidate");
                header("Cache-Control: post-check=0, pre-check=0", false);
                header("Pragma: no-cache");
                header('Content-Length: ' . filesize($md5Name));
                header("Content-Disposition: attachment; filename=\"" . (string) $fileName . "\"");
                header('Content-Type: download');
                $fh = fopen($md5Name, 'r');
                fpassthru($fh);
            }
        }
    }

    /**
     * Deletes a file and then renders the upload.phtml template.
     *
     * This function draws the upload field in the form.
     *
     * <pre>
     * OPTIONAL request parameters:
     *  - string  <b>moduleName</b> Current module name.
     *  - string  <b>field</b>      Name of the field in the module.
     *  - integer <b>id</b>         Id of the current item.
     *  - integer <b>order</b>      Position of the file (Can be many uploaded files in the same field).
     * </pre>
     *
     * @return void
     */
    public function fileDeleteAction()
    {
        $module = Cleaner::sanitize('alnum', $this->getRequest()->getParam('moduleName', 'Project'));
        $field  = Cleaner::sanitize('alnum', $this->getRequest()->getParam('field', null));
        $itemId = (int) $this->getRequest()->getParam('id', null);
        $order  = (int) $this->getRequest()->getParam('order', 0);

        $this->getResponse()->clearHeaders();
        $this->getResponse()->clearBody();

        $linkBegin = Phprojekt::getInstance()->getConfig()->webpath . 'index.php/Default/File/';
        $model     = Phprojekt_Loader::getModel($module, $module);

        $this->_fileCheckParamField($model, $field);
        $this->_fileCheckWritePermission($model, $itemId);

        if ($itemId > 0) {
            $model->find($itemId);
            $files = $model->$field;
        } else {
            $files = $_SESSION['uploadedFiles_' . $field];
        }

        $filesIn = explode('||', $files);

        $this->_fileCheckParamOrder($order, count($filesIn));

        // Delete the file name and md5 from the string
        $filesOut = '';
        $i        = 1;
        foreach ($filesIn as $file) {
            if ($i != $order) {
                if ($filesOut != '') {
                    $filesOut .= '||';
                }
                $filesOut .= $file;
            } else {
                // Delete the file from the server
                $md5Name          = substr($file, 0, strpos($file, '|'));
                $fileAbsolutePath = Phprojekt::getInstance()->getConfig()->uploadpath . $md5Name;
                if (file_exists($fileAbsolutePath)) {
                    unlink($fileAbsolutePath);
                }
            }
            $i++;
        }

        $_SESSION['uploadedFiles_' . $field] = $filesOut;

        $this->_fileRenderView($linkBegin, $module, $itemId, $field, $filesOut, true);
    }

    /**
     * Renders the upload.phtml template for display an upload field.
     *
     * This function draws the upload field in the form.
     * All the uploaded files are displayed with a cross for delete it and a link for download it.
     *
     * @param string  $linkBegin    URL for use in the links.
     * @param string  $module       Current module name.
     * @param integer $itemId       Current item id.
     * @param string  $field        Name of the field in the module.
     * @param string  $value        Value of the field.
     * @param boolean $filesChanged Defines if is needed to reload the field value.
     *
     * @return void
     */
    private function _fileRenderView($linkBegin, $module, $itemId, $field, $value, $filesChanged)
    {
        $this->view->webpath        = Phprojekt::getInstance()->getConfig()->webpath;
        $this->view->compressedDojo = (bool) Phprojekt::getInstance()->getConfig()->compressedDojo;
        $this->view->formPath       = $linkBegin . 'fileUpload/moduleName/' . $module;
        $this->view->downloadLink   = '';
        $this->view->fileName       = null;
        $this->view->itemId         = $itemId;
        $this->view->field          = $field;
        $this->view->value          = $value;
        $this->view->filesChanged   = $filesChanged;

        $filesForView = array();

        // Is there any file?
        if (!empty($value)) {
            $files = explode('||', $value);
            $model = Phprojekt_Loader::getModel($module, $module);
            $model->find($itemId);
            $rights = $model->getRights();
            $i      = 0;
            foreach ($files as $file) {
                $fileName = strstr($file, '|');
                $fileData = 'moduleName/' . $module . '/itemId/' . $itemId . '/field/' . $field . '/order/'
                    . (string) ($i + 1);

                $filesForView[$i] = array('fileName' => substr($fileName, 1));
                if ($rights['currentUser']['download']) {
                    $filesForView[$i]['downloadLink'] = $linkBegin . 'fileDownload/' . $fileData;
                }
                if ($rights['currentUser']['write']) {
                    $filesForView[$i]['deleteLink'] = $linkBegin . 'fileDelete/' . $fileData;
                }
                $i++;
            }
        }
        if (isset($this->view->errorMessage) && !empty($this->view->errorMessage)) {
            $filesForView[] = array();
        }

        $this->view->files = $filesForView;
        $this->render('upload');
    }

    /**
     * Checks that the 'field' parameter for download and delete file actions is valid.
     * If not, terminates script execution.
     *
     * @param Phprojekt_Model_Interface $model Current module.
     * @param string                    $field Name of the field in the module.
     *
     * @return void
     */
    private function _fileCheckParamField($model, $field)
    {
        $dbManager = $model->getInformation();
        $dbField   = $dbManager->find($field);
        $valid     = false;

        if (!empty($dbField)) {
            $fieldType = $dbManager->getType($field);
            if ($fieldType == 'upload') {
                $valid = true;
            }
        }
        if (!$valid) {
            $error  = Phprojekt::getInstance()->translate('Error in received parameter, consult the admin. Parameter:');
            $error .= ' field';

            // Log error
            Phprojekt::getInstance()->getLog()->err("Error: wrong 'field' parameter trying to Download or Delete a file"
                . ". User Id: " . Phprojekt_Auth::getUserId() . " - Module: " . $this->getRequest()->getModuleName());
            // Show error to user and stop script execution
            die($error);
        }
    }

    /**
     * Checks that the 'order' parameter for download and delete file actions is valid.
     * If not, terminates script execution printing an error.
     *
     * @param int $order       Position of the file (Can be many uploaded files in the same field).
     * @param int $filesAmount Number of uploaded files for the field.
     *
     * @return void
     */
    private function _fileCheckParamOrder($order, $filesAmount)
    {
        if ($order < 1 || $order > $filesAmount) {
            $error  = Phprojekt::getInstance()->translate('Error in received parameter, consult the admin. Parameter:');
            $error .= " order";

            // Log error
            Phprojekt::getInstance()->getLog()->err("Error: wrong 'order' parameter trying to Download or Delete a file"
                . ". User Id: " . Phprojekt_Auth::getUserId() . " - Module: " . $this->getRequest()->getModuleName());
            // Show error to user and stop script execution
            die($error);
        }
    }

    /**
     * Checks that the user has permission for modifying the item, in this case for uploading or deleting files.
     * If not, prints an error, terminating script execution.
     *
     * @param Phprojekt_Model_Interface $model  Current module.
     * @param integer                   $itemId Current item id.
     *
     * @return void
     */
    private function _fileCheckWritePermission($model, $itemId)
    {
        $model->find($itemId);
        $rights = $model->getRights();
        if (!$rights['currentUser']['write']) {
            $error = Phprojekt::getInstance()->translate('You don\'t have permission for modifying this item.');

            // Log error
            Phprojekt::getInstance()->getLog()->err("Error: trying to Delete or Upload a file without write access. "
                . "User Id: " . Phprojekt_Auth::getUserId() . " - Module: " . $this->getRequest()->getModuleName());
            // Show error to user and stop script execution
            die($error);
        }
    }
}
