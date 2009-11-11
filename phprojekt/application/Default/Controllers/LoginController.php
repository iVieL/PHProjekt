<?php
/**
 * Login handling
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
 * @author     Eduardo Polidor <soria_parra@mayflower.de>
 * @package    PHProjekt
 * @subpackage Default
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 */

/**
 * Login handling
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @version    Release: @package_version@
 * @license    LGPL 2.1 (See LICENSE file)
 * @package    PHProjekt
 * @subpackage Default
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @author     Eduardo Polidor <epolidor@mayflower.de>
 */
class LoginController extends Zend_Controller_Action
{
    /**
     * Default action
     *
     * The function sets up the template index.phtml and renders it.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->getResponse()->clearHeaders();
        $this->getResponse()->clearBody();

        $this->view->webpath        = Phprojekt::getInstance()->getConfig()->webpath;
        $this->view->compressedDojo = (bool) Phprojekt::getInstance()->getConfig()->compressedDojo;

        $this->render('index');
    }

    /**
     * Executes the login using the username and password provided on login form
     *
     * If it works fine, redirect the user to homepage,
     * if not, show the error message.
     *
     * Keep the hash for redirect the user after the login.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - string <b>username</b>   Username for login.
     *  - string <b>password</b>   Password for login.
     *  - string <b>hash</b>       Hash URL for redirect after the login.
     *  - string <b>keepLogged</b> "on" if the user clicks on the checkbox.
     * </pre>
     *
     * @return void
     */
    public function loginAction()
    {
        $username   = (string) $this->getRequest()->getParam('username', null);
        $password   = (string) $this->getRequest()->getParam('password', null);
        $hash       = (string) $this->getRequest()->getParam('hash', null);
        $keepLogged = (string) $this->getRequest()->getParam('keepLogged', null);

        if ($keepLogged == "on" || $keepLogged == 1) {
            $keepLogged = true;
        } else {
            $keepLogged = false;
        }

        $this->view->webpath        = Phprojekt::getInstance()->getConfig()->webpath;
        $this->view->compressedDojo = (bool) Phprojekt::getInstance()->getConfig()->compressedDojo;

        try {
            $success = Phprojekt_Auth::login($username, $password, $keepLogged);
            if ($success === true) {
                $config = Phprojekt::getInstance()->getConfig();
                $this->_redirect($config->webpath . 'index.php' . $hash);
                die();
            }
        } catch (Phprojekt_Auth_Exception $error) {
            $this->view->message  = $error->getMessage();
            $this->view->username = $username;
            $this->view->hash     = $hash;

            $this->render('index');
        }
    }

    /**
     * Executes the login by json using the username and password.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - string <b>username</b> Username for login.
     *  - string <b>password</b> Password for login.
     * </pre>
     *
     * The return is a string in JSON format with:
     * <pre>
     *  - type    => 'success' or 'error'.
     *  - message => Success or error message.
     * </pre>
     *
     * @return void
     */
    public function jsonLoginAction()
    {
        $username = (string) $this->getRequest()->getParam('username', null);
        $password = (string) $this->getRequest()->getParam('password', null);

        try {
            $success = Phprojekt_Auth::login($username, $password);
            if ($success === true) {
                $return = array('type'    => 'success',
                                'message' => '');
            }
        } catch (Phprojekt_Auth_Exception $error) {
            $return = array('type'    => 'error',
                            'message' => $error->getMessage());
        }

        $this->_helper->viewRenderer->setNoRender();
        $this->view->clearVars();

        Phprojekt_Converter_Json::echoConvert($return);
    }

    /**
     * Logout action
     *
     * Logout the user, and redirect them to the login page.
     *
     * @return void
     */
    public function logoutAction()
    {
        Phprojekt_Auth::logout();
        $config = Phprojekt::getInstance()->getConfig();
        $this->_redirect($config->webpath . 'index.php');
        die();
    }
}
