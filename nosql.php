<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
 
class Nosql {
    
    private $db = NULL;
    private $db_checksum = NULL;
    private $name = NULL;
    private $structure = NULL;
    private $table = NULL;
    private $where_statement = NULL;
    private $or_where_statement = NULL;
    private $select_limit = NULL;
    private $default_operator = '=';
    private $super = NULL;
    
    function __construct($table = NULL) {
        if (empty($table)) return FALSE;
        $this->name = $table;
        fopen(DATABASE_PATH . $table . '.db','a+');
        $this->db = new SQLite3(DATABASE_PATH . $table . '.db');
        fopen(DATABASE_PATH . $table . '_checksum.db','a+');
        $this->db_checksum = new SQLite3(DATABASE_PATH . $table . '_checksum.db');
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
        if (!isset($fields) || !is_string($fields)) $fields = '*';
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
        if (!isset($row) || !is_array($row)) return NULL;
        // check if there is a relation to some table
        if ( !empty($this->structure['relation'])) {
            $relation_structure = $this->structure['relation'];
            foreach($relation_structure as $relation_table) {
                if (isset($row[$relation_table]) && is_array($row[$relation_table])) {
                    $row[$relation_table . '_id'] = $this->super->{$relation_table}->insert($row[$relation_table]);
                }
            }
        }
        $row = $this->check_structure($row);
        $row_state = $this->check_row_checksum($row);
        if (empty($row_state)) {
            // brand new raw data
            $keys = array_keys($row);
            //$values = array_values($row);
            $imploded_field_keys = implode(',' , $keys);
            $imploded_field_vars = implode(',:' , $keys);
            $insert = 'INSERT INTO '.$this->table.' ('.$imploded_field_keys.') VALUES (:'.$imploded_field_vars.')';
            $stmt = $this->db->prepare($insert);
            foreach($row as $field => $value) {
                if (is_string($value)) $stmt->bindValue(':'.$field, $value, SQLITE3_TEXT);   
                else $stmt->bindValue(':'.$field, $value, SQLITE3_INTEGER);
            }
            $stmt->execute();
            $row_id = $this->db->lastInsertRowID();
            $this->create_row_checksum($row,$row_id);
            return $row_id;
        } else return $row_state;
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
            // if the table structure and binary file already exists then bypass
            $cached_structure = @file_get_contents(DATABASE_PATH . $this->name . '.' . $table_name.'.struct');
            if ($cached_structure) {
                $this->{$table_name} = new Nosql($this->name);
                $this->{$table_name}->table = $table_name;
                $this->{$table_name}->structure = json_decode($cached_structure, TRUE);
                $this->{$table_name}->super = $this;
                continue;
            }
            // process goes through here if it is the first time running
            // check if its declared the columns and indexes of the table
            if (!isset($table_structure['columns'])) continue;
            if (!isset($table_structure['index'])) continue;
            $instructions = array();
            array_push($table_structure['index'], $table_name . '_id');
            $indexes = $table_structure['index'];
            // check if their here relations to other tables mentioned in the structure
            if (isset($table_structure['relation'])) {
                $relations = $table_structure['relation'];
                foreach($relations as $field_name) {
                    $table_structure['columns'][$field_name . '_id'] = array('length' => 20, 'type' => 'INTEGER');
                    // add the relation key as an index also
                    $indexes[] = $field_name;
                }
            }
            // create the main key of the table
            $table_structure['columns'][$table_name . '_id'] = array('length' => 20, 'type' => 'INTEGER PRIMARY KEY');
            $columns = $table_structure['columns'];
            // defining column data types (essentialy like as normal sql)
            foreach($columns as $field_name => $field_structure) {
                $instructions[] = $field_name . ' ' . $field_structure['type'];
            }
            // create the binary table 
            $create = 'CREATE TABLE `' . $table_name . '` ( ' . implode(',' , $instructions) .  ' )';
            $this->db->exec($create);
            $this->{$table_name} = new Nosql($this->name);
            $this->{$table_name}->table = $table_name;
            $this->{$table_name}->structure = $table_structure;
            $this->{$table_name}->super = $this;
            // create the indexes
            foreach($indexes as $field_name) {
                $this->add_index($field_name);
            }
            // create the auxiliry checksum table of fast select/update/insert operations
            $this->{$table_name}->create_checksum();
            // save the table structure to a simple format rawdata file
            file_put_contents(DATABASE_PATH . $this->name . '.' . $table_name.'.struct', json_encode($table_structure));
        }
    }
    
    
    // checksum methods
    private  function create_checksum() {
        $table_name = $this->table;
        $instructions = array(
            $table_name . '_checksum_id INTEGER PRIMARY KEY',
            'hash TEXT',
            'target INTEGER'
        );
        $create = 'CREATE TABLE `' . $table_name . '_checksum` ( ' . implode(',' , $instructions) .  ' )';
        $this->db_checksum->exec($create);
        @$this->db_checksum->exec('CREATE INDEX '.$table_name .'_checksum_id_index ON '.$table_name . '_checksum ('.$table_name . '_checksum_id)');
        @$this->db_checksum->exec('CREATE INDEX hash_index ON '.$table_name . '_checksum (hash)');
    }
    private  function check_row_checksum($row = NULL) {
        if (empty($row)) return FALSE;
        // generate hash
        $row_imploded = implode('',$row);
        $hash = md5($row_imploded);
        $table_name = $this->table;
        $sql = 'SELECT target FROM '.$this->table . "_checksum WHERE hash = '".$hash."'";
        $results = $this->db_checksum->query($sql);
        $values = array();
        while ($row = $results->fetchArray()) {$values[] = $row;}
        if (empty($values)) return NULL; else {
            $result = current($values);
            return $result['target'];
        }
    }
    private  function create_row_checksum($row = NULL,$id = NULL) {
        if (empty($row)) return FALSE;
        // generate hash
        $row_imploded = implode('',$row);
        $hash = md5($row_imploded);
        // insert the new hash
        $insert = 'INSERT INTO '.$this->table.'_checksum (hash,target) VALUES (:hash,:target)';
        $stmt = $this->db_checksum->prepare($insert);
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->bindValue(':target', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    private  function add_index($column = NULL) {
        if (empty($column)) return FALSE;
        @$this->db->exec('CREATE INDEX ' . $column . '_index ON '.$this->table.' ('.$column.')');
    }
    private  function drop_index($column = NULL) {
        if (empty($column)) return FALSE;
        @$this->db->exec('DROP INDEX ' . $column . '_index ON '.$this->table);
    }
}
?>