<?php
require_once ("config.php");

function create_tag($achievement_id, $name){
    $name=trim($name);
    $is_a_new_tag=!is_it_already_tagged(0, $name);
    if ($is_a_new_tag){
        insert_tag_into_db(0,$name);  
    }
    
    if (!is_it_already_tagged($achievement_id, $name)){
        insert_tag_into_db($achievement_id,$name);
            $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
            $statement = $connection ->prepare("update tags set tally=tally+1 where achievement_id=0 and name=?");
            $statement->bindValue(1, $name, PDO::PARAM_INT);
            $statement->execute();            
    }
}

function delete_tag ($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $tag=fetch_tag($id);
    $statement = $connection ->prepare("update tags set active=0 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    $statement->$connection->prepare("update tags set tally=tally-1 where active=1 and achievement_id=0 and name=?");
    $statement->bindValue(1, $tag->name, PDO::PARAM_STR);
    $statement->execute();
         
}

function fetch_tag($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement=$connection -> prepare("selecT * from tags where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchObject();
}
function is_it_already_tagged($achievement_id, $tag){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection -> prepare ("select count(*) from tags where active=1 and achievement_id=? and name=? limit 1");
    $statement->bindValue(1, $achievement_id, PDO::PARAM_INT);
    $statement->bindValue(2, $tag, PDO::PARAM_STR);
    $statement->execute();
    return (boolean)$statement->fetchColumn();
}
function insert_tag_into_db($achievement_id,$name){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection -> prepare("insert into tags (name, achievement_id) values (?, ?)");
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $achievement_id, PDO::PARAM_INT); 
    $statement->execute();
}