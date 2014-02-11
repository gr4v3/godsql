<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
include 'nosql.php';
define('DATABASE_PATH','bd/');

$nosql = new Nosql('session');
$nosql->structure(array(
        'session' => array(
            'columns' => array(
                'hash' => array('length' => 26, 'type' => 'TEXT'),
                'logged' => array('length' => 2, 'type' => 'INTEGER'),
                'modified' => array('length' => 64, 'type' => 'INTEGER'),
                'created' => array('length' => 64, 'type' => 'INTEGER'),
                'redirect' => array('length' => 250, 'type' => 'TEXT'),
                'ip' => array('length' => 20, 'type' => 'TEXT')
            ),
            'index' => array('logged','hash'),
            'father' => array('user','level')
        ),
        'user' => array(
            'columns' => array(
                'user' => array('length' => 20, 'type' => 'TEXT'),
                'name' => array('length' => 20, 'type' => 'TEXT'),
                'access' => array('length' => 32, 'type' => 'TEXT'),
            ),
            'index' => array('access'),
            'father' => array('level')
        ),
        'level' => array(
            'columns' => array(
                'name' => array('length' => 20, 'type' => 'TEXT'),
                'description' => array('length' => 250, 'type' => 'TEXT'),
                'is_admin' => array('length' => 2, 'type' => 'INTEGER'),
                'active' => array('length' => 2, 'type' => 'INTEGER')
            ),
            'index' => array('active')
        )
    )
);
