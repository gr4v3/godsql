<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
class godmethods extends godchecksum {
    public function select() {
        
    }
    public function update() {
        
    }
    public function delete() {
        
    }
    public function limit() {
        
    }
    public function where() {
        
    }        
}

class godchecksum {
    function create() {
        
    }
    function check() {
        
    }
}


class godtable extends godmethods {
    public $name = NULL;
    public $columns = NULL;
    public $key = NULL;
    public $indexes = NULL;
    public $relations = NULL;
    public $structure = NULL;
    public $root = NULL;
    function __construct($table = NULL) {
        if (empty($table)) return FALSE;
        $this->structure = $table;
        foreach($table as $index => $value) {$this->{$index} = NULL;}
        $this->key = $table['name'] . '_id';
        
        /*
        'name' => 'session',
        'columns' => array(
            'hash' => array('length' => 26, 'type' => 'TEXT'),
            'logged' => array('length' => 2, 'type' => 'INTEGER'),
            'modified' => array('length' => 64, 'type' => 'INTEGER'),
            'created' => array('length' => 64, 'type' => 'INTEGER'),
            'redirect' => array('length' => 250, 'type' => 'TEXT'),
            'ip' => array('length' => 20, 'type' => 'TEXT')
        ),
        'index' => array('logged','hash'),
        'relation' => array('user','level')
        */
        
        
        return $this;
    }
}




define('DATABASE_PATH','bd/');
class godsql {
    private $db = null;
    private $db_checksum = NULL;
    function __construct($structure = NULL) {
        if (empty($structure)) return NULL;
        $database = key($structure);
        $this->structure($structure);
        //$tables = reset($structure);
        fopen(DATABASE_PATH . $database . '.db','a+');
        $this->db = new SQLite3(DATABASE_PATH . $database . '.db');
        fopen(DATABASE_PATH . $database . '_checksum.db','a+');
        $this->db_checksum = new SQLite3(DATABASE_PATH . $database . '_checksum.db');
        return $this;
     }
     function structure($structure = NULL) {
         if (empty($structure)) return FALSE;
         $tables = array_pop($structure);
         foreach($tables as $item) {
             $name = $item['name'];
             $table = new godtable($item);
             $table->root = $this;
             $this->{$name} = $table;
         }
     }
}

$bd = new godsql(array(
    'god' => array(
        array(
            'name' => 'session',
            'columns' => array(
                'hash' => array('length' => 26, 'type' => 'TEXT'),
                'logged' => array('length' => 2, 'type' => 'INTEGER'),
                'modified' => array('length' => 64, 'type' => 'INTEGER'),
                'created' => array('length' => 64, 'type' => 'INTEGER'),
                'redirect' => array('length' => 250, 'type' => 'TEXT'),
                'ip' => array('length' => 20, 'type' => 'TEXT')
            ),
            'index' => array('logged','hash'),
            'relation' => array('user','level')
        ),
        array(
            'name' => 'user',
            'columns' => array(
                'user' => array('length' => 20, 'type' => 'TEXT'),
                'name' => array('length' => 20, 'type' => 'TEXT'),
                'access' => array('length' => 32, 'type' => 'TEXT'),
            ),
            'index' => array('access'),
            'relation' => array('level,region')
        ),
        array(
            'name' => 'level',
            'columns' => array(
                'name' => array('length' => 20, 'type' => 'TEXT'),
                'description' => array('length' => 250, 'type' => 'TEXT'),
                'is_admin' => array('length' => 2, 'type' => 'INTEGER'),
                'active' => array('length' => 2, 'type' => 'INTEGER')
            ),
            'index' => array('active')
        )
    )
));





$fabio = $bd->user->select(array('name' => 'fabio'));




var_dump($bd);