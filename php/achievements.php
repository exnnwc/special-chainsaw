<?php

require_once ("config.php");
function achievement_name_exists($name, $parent) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("select count(*) from achievements where deleted=0 and name=? and parent=? limit 1");
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $parent, PDO::PARAM_INT);
    $statement->execute();
    return boolval($statement->fetchcolumn());
}

function activate_achievement($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set active=1 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}

function are_ranks_duplicated($parent) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->query("SELECT COUNT(*) as count FROM achievements where parent=$parent and deleted=0 GROUP BY rank HAVING COUNT(*) > 1");
    if ((int) $statement->fetchColumn() > 0) {
        return true;
    }
    return false;
}
function change_achievement_to_deleted($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set deleted=1 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}
function change_achievement_to_undeleted($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set deleted=0 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}
function change_description($id, $description) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set description=? where id=?");
    $statement->bindValue(1, $description, PDO::PARAM_STR);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_documentation_status($id, $status) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set documented=? where id=?");
    $statement->bindValue(1, $status, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_name($id, $name) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set name=? where id=?");
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_power($id, $power) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set power=? where id=?");
    $statement->bindValue(1, $power, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_quality($id, $quality) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set quality=? where id=?");
    $statement->bindValue(1, $quality, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_rank($id, $new_rank) {
    $achievement = fetch_achievement($id);
    $highest_rank=fetch_highest_rank($achievement->parent);
    if (fetch_num_of_achievements($achievement) != $highest_rank){        
        error_log("Line #".__LINE__ . " " . __FUNCTION__ . "($id, $new_rank): Holes in rank");
        fix_achievement_ranks("updated", $achievement->parent);
        return;
    } 
    update_rank($id, $new_rank);
    change_achievement_to_deleted($achievement->id);
    if ($new_rank <= 0) {
        error_log("Line #".__LINE__ . " " . __FUNCTION__ . "($id, $new_rank): Shouldn't be able to change rank to 0 or negative");
        return;
    }
    if (are_ranks_duplicated($achievement->parent)) {
        error_log("Line #".__LINE__ . " " . __FUNCTION__ . "($id, $new_rank): Ranks duplicated.");
        fix_achievement_ranks("updated", $achievement->parent);
        return;        
    }
    if ($new_rank > $highest_rank) {
        activate_achievement($achievement->id);
        fix_achievement_ranks("rank", $achievement->parent);
        return;
    }
    rank_achievements($achievement, $new_rank);
    change_achievement_to_undeleted($achievement->id);
}


function complete_achievement($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set deleted=1, completed=now() where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}

function count_achievements() {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $data = [];

    $statement = $connection->query("select count(*) from achievements where deleted=0 and parent=0 and  active=1");
    $num_of_working_achievements = (int) $statement->fetchColumn();

    $statement = $connection->query("select count(*) from achievements where deleted=0 and quality=true");
    $num_of_qualities = (int) $statement->fetchColumn();

    $statement = $connection->query("select count(*) from achievements where deleted=0 and parent=0");
    $num_of_achievements = (int) $statement->fetchColumn();

    $num_of_nonworking_achievements = $num_of_achievements - $num_of_working_achievements - $num_of_qualities;
    $data = ["total" => $num_of_achievements,
        "working" => $num_of_working_achievements,
        "not_working" => $num_of_nonworking_achievements,
        "qualities" => $num_of_qualities];

    echo json_encode($data);
}

function create_achievement($name, $parent) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $achievement = fetch_achievement($parent);
    if (achievement_name_exists($name, $parent)) {        
        error_log("Line #".__LINE__ . " " . __FUNCTION__ . "($name, $parent): Achievement already exists by that name.");
        return;
    }
    if ($parent == 0) {
        $query = "insert into achievements(name, parent, rank) values (?, ?, ?)";
    } else if ($parent > 0) {
        $query = "insert into achievements(name, parent, rank, documented) values (?, ?, ?, $achievement->documented)";
    }
    $statement = $connection->prepare($query);
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $parent, PDO::PARAM_INT);
    $statement->bindValue(3, fetch_highest_rank($parent) + 1, PDO::PARAM_INT);
    $statement->execute();
}

function deactivate_achievement($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set active=0 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}

function delete_achievement($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $achievement = fetch_achievement($id);
    change_achievement_to_deleted($id);
    $connection->exec("update achievements set rank=rank-1 where deleted=0 and parent=$achievement->parent and rank>=$achievement->rank");
    //This is a quick fix. May require a deleted tag so that tags can still stay active when an achievement is deleted.
    $connection->exec("update tags set active=0 where achievement_id=$id");
}

function fetch_achievement($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("select * from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchObject();
}

function fetch_achievement_name($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("select name from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchColumn();
}

function fetch_achievement_by_rank_and_parent($rank, $parent) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("select * from achievements where deleted=0 and rank=? and parent=?");
    $statement->bindValue(1, $rank, PDO::PARAM_INT);
    $statement->bindValue(2, $parent, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchObject();
}

function fetch_highest_rank($parent) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("select rank from achievements where deleted=0 and parent=? order by rank desc limit 1");
    $statement->bindValue(1, $parent, PDO::PARAM_INT);
    $statement->execute();
    return (int)$statement->fetchColumn();
}

function fetch_num_of_achievements($achievement){

    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection -> query ("select count(*) from achievements where deleted=0 and parent=$achievement->parent");
    return (int)$statement->fetchColumn();
}

function fetch_random_achievement_id() {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->query("select id from achievements where deleted=0 and parent=0 order by rand() limit 1");
    return $statement->fetchColumn();
}

function fix_achievement_ranks($field, $parent) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $connection->exec("set @rank=0");
    $connection->exec("update achievements set rank=@rank:=@rank+1 where deleted=0 and parent=$parent order by $field ");
}

function is_it_active($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("select deleted from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    echo json_encode(!(boolean)$statement->fetchColumn());
}

function rank_achievements($achievement, $new_rank) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $connection->exec("set @rank=$new_rank");
    if ($new_rank - $achievement->rank > 0) {
        $connection->exec("update achievements set rank=@rank:=@rank-1 where deleted=0 and parent=$achievement->parent and rank<=$new_rank order by rank desc");
    } else if ($new_rank - $achievement->rank < 0) {
        
        $connection->exec("update achievements set rank=@rank:=@rank+1 where deleted=0 and parent=$achievement->parent and rank>=$new_rank order by rank");
    } else if ($new_rank - $achievement->rank == 0) {
        error_log("Line #".__LINE__ . " " . __FUNCTION__ . "($achievement->id, $new_rank): New rank should not be the same as the old.");
    }
}


function toggle_documentation_status($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $achievement = fetch_achievement($id);
    $statement = $connection->prepare("update achievements set documented=? where id=?");
    $statement->bindValue(1, !$achievement->documented, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function toggle_quality($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $achievement = fetch_achievement($id);
    $statement = $connection->prepare("update achievements set quality=? where id=?");
    $statement->bindValue(1, !$achievement->quality, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();    
}

function toggle_work_status($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $achievement=fetch_achievement($id);
    $statement = $connection->prepare("update achievements set work=? where id=?");
    $statement->bindValue(1, !$achievement->work, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}
function update_rank($id, $new_rank) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set rank=? where id=?");
    $statement->bindValue(1, $new_rank, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function uncomplete_achievement($id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $statement = $connection->prepare("update achievements set completed=0 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}

function undelete_achievement($id){
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $achievement = fetch_achievement($id);
    change_achievement_to_undeleted($id);
    update_rank($id, fetch_highest_rank($achievement->parent)+1);
}
