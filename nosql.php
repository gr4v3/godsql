<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
 
class Nosql {
    
    private $db = NULL;
    private $name = NULL;
    private $structure = NULL;
    private $table = NULL;
    private $where_statement = NULL;
    private $or_where_statement = NULL;
    private $select_limit = NULL;
    private $default_operator = '=';
    
    function __construct($table = NULL) {
        if (empty($table)) return FALSE;
        $this->name = $table;
        fopen(DATABASE_PATH . $table . '.db','a+');
        $this->db = new SQLite3(DATABASE_PATH . $table . '.db');
    }
    private function check_structure($row = array(), $return_empty = TRUE, $return_where = FALSE) {
        if (empty($this->structure)) return FALSE;
        $structure = $this->structure;
        $columns = $structure['columns'];
        $verified = array_intersect_key($row, $columns);
        //$diference = array_diff_key($this->structure, $verified);
        foreach($columns as $field => $structure) {
            if (! isset($verified[$field]) && $return_empty) continue;
            if ($return_where && isset($verified[$field])) {
                $matches = null;
                if(preg_match('/(!|<|>|)=|like|<|>/', $verified[$field], $matches)) {
                    $operator = current($matches);
                } else $operator = $this->default_operator;
                $verified[$field] = preg_replace('/(!|<|>|)=|like|<|>/', '', $verified[$field]);
                if (empty($verified[$field])) {
                    $operator = 'is';
                    $verified[$field] = 'NULL';
                }
                if ($structure['type'] == 'TEXT') $verified[$field] = "$field $operator '".$verified[$field]."'";
                else $verified[$field] = $field.' '.$operator.' '.$verified[$field];
            }
        }
        return $verified;
    }
    // begin public funtions segment
    public  function limit($safe_limit = NULL) {
        $this->select_limit = $safe_limit;
        return $this;
    }
    public  function where($params = array()) {
        if (empty($params)) return FALSE;
        $this->where_statement = $this->check_structure($params, FALSE, TRUE);
        return $this;
    }
    public  function or_where($params = array()) {
        if (empty($params)) return FALSE;
        $this->or_where_statement = $this->check_structure($params, FALSE, TRUE);
        return $this;
    }
    public  function select($fields = NULL) {
        if (!isset($fields)) $fields = '*';
        $sql = 'SELECT '.$fields.' FROM '.$this->table;
        if (isset($this->where_statement)) {
            $sql.= ' WHERE '.implode(' AND ', $this->where_statement);
        }
        if (isset($this->or_where_statement)) {
            $sql.= ' OR '.implode(' AND ', $this->or_where_statement);
        }
        if (isset($this->select_limit)) $sql.= ' LIMIT ' . $this->select_limit;
        $results = $this->db->query($sql);
        $values = array();
        while ($row = $results->fetchArray()) {$values[] = array_intersect_key($row, $this->structure['columns']);}
        $this->where_statement = NULL;
        $this->select_limit = NULL;
        return $values;
    } 
    public  function insert($row = NULL) {
        if (!isset($row)) return NULL;
        $row = $this->check_structure($row);
        $keys = array_keys($row);
        $values = array_values($row);
        $imploded_field_keys = implode(',' , $keys);
        $imploded_field_vars = implode(',:' , $keys);
        $insert = 'INSERT INTO '.$this->table.' ('.$imploded_field_keys.') VALUES (:'.$imploded_field_vars.')';
        $stmt = $this->db->prepare($insert);
        foreach($row as $field => $value) {
            if (is_string($value)) $stmt->bindValue(':'.$field, $value, SQLITE3_TEXT);   
            else $stmt->bindValue(':'.$field, $value, SQLITE3_INTEGER);
        }
        $stmt->execute();
        return $this->db->lastInsertRowID();
    }
    public  function update($row = array()) {
        if (!isset($row)) return NULL; 
        $row = $this->check_structure($row, FALSE);
        $update = array();
        foreach($row as $field => $value) $update[] = $field . ' = :'.$field;
        $sql = 'UPDATE '.$this->table.' SET '.implode(',', $update);
        if (isset($this->where_statement)) {
            $sql.= ' WHERE '.implode(' AND ', $this->where_statement);
        }
        $stmt = $this->db->prepare($sql);
        foreach($row as $field => $value) {
            if (is_string($value)) $stmt->bindValue(':'.$field, $value, SQLITE3_TEXT);   
            else $stmt->bindValue(':'.$field, $value, SQLITE3_INTEGER);
        }
        $result = $stmt->execute();
        $this->where_statement = NULL;
        return $result;
    }
    public  function delete() {
        if ( ! isset($this->where_statement)) return FALSE;
        $sql = 'DELETE FROM '.$this->table . ' WHERE '.implode(' AND ', $this->where_statement);
        $this->db->exec($sql);
        $this->where_statement = NULL;
        return $this->db->changes();
    }
    public  function structure($struct = array()) {
        if (empty($struct)) return FALSE;
        foreach($struct as $table_name => &$table_structure) {
            $cached_structure = @file_get_contents(DATABASE_PATH . $this->name . '.' . $table_name.'.struct');
            if ($cached_structure) {
                $this->{$table_name} = new Nosql($this->name);
                $this->{$table_name}->table = $table_name;
                $this->{$table_name}->structure = json_decode($cached_structure, TRUE);
                continue;
            }
            if (!isset($table_structure['columns'])) continue;
            if (!isset($table_structure['index'])) continue;
            $instructions = array();
            $indexes = $table_structure['index'];
            if (isset($table_structure['father'])) {
                $fathers = $table_structure['father'];
                foreach($fathers as $field_name) {
                    $table_structure['columns'][$field_name . '_id'] = array('length' => 20, 'type' => 'INTEGER');
                    $indexes[] = $field_name;
                }
            }
            $table_structure['columns'][$table_name . '_id'] = array('length' => 20, 'type' => 'INTEGER PRIMARY KEY');
            $columns = $table_structure['columns'];
            foreach($columns as $field_name => $field_structure) {
                $instructions[] = $field_name . ' ' . $field_structure['type'];
            }
            $create = 'CREATE TABLE `' . $table_name . '` ( ' . implode(',' , $instructions) .  ' )';
            $this->db->exec($create);
            $this->{$table_name} = new Nosql($this->name);
            $this->{$table_name}->table = $table_name;
            $this->{$table_name}->structure = $table_structure;
            foreach($indexes as $field_name) {
                $this->add_index($field_name);
            }
            file_put_contents(DATABASE_PATH . $this->name . '.' . $table_name.'.struct', json_encode($table_structure));
        }
    }
    public  function add_index($column = NULL) {
        if (empty($column)) return FALSE;
        @$this->db->exec('CREATE INDEX ' . $column . '_index ON '.$this->table.' ('.$column.')');
    }
    public  function drop_index($column = NULL) {
        if (empty($column)) return FALSE;
        @$this->db->exec('DROP INDEX ' . $column . '_index ON '.$this->table);
    }
}
?>