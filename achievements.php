<?php

$connection = new PDO("mysql:host=localhost;dbname=rla", "root", "");
//rank_table_in_order(0, 0);

switch ($_POST['function_to_be_called']) {
    case "change_documentation_status":
        change_documentation_status($_POST['id'], $_POST['status']);
        break;
    case "change_description":
        change_description($_POST['id'], $_POST['description']);
        break;
    case "change_name":
        change_name($_POST['id'], $_POST['new_name']);
        break;
    case "change_power":
        change_power($_POST['id'], $_POST['new_power']);
        break;
    case "change_rank":
        change_rank($_POST['id'], $_POST['new_rank']);
        break;
    case "create_quick":
        create_quick($_POST['name'], $_POST['parent']);
        break;
    case "delete":
        delete($_POST['id']);
        break;
    case "is_it_active":
        is_it_active($_POST['id']);
        break;
    case "list":
        list_all($_POST['sort_by']);
        break;
    case "list_children":
        list_children($_POST['parent']);
        break;
}

function change_description($id, $description) {
    //In the future, create a new one instead of just changing it.
    global $connection;
    $statement = $connection->prepare("update achievements set description=? where id=?");
    $statement->bindValue(1, $description, PDO::PARAM_STR);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_documentation_status($id, $status) {
    global $connection;
    $statement = $connection->prepare("update achievements set documented=? where id=?");
    $statement->bindValue(1, $status, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_name($id, $name){
    global $connection;
    //echo "$id - $name";
    $statement=$connection->prepare("select count(*) from achievements where id=? and name=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->bindValue(2, $name, PDO::PARAM_STR);
    $statement->execute();
    if ($statement->fetchColumn()==0){
        $statement=$connection->prepare("update achievements set name=? where id=?");
        $statement->bindValue(1, $name, PDO::PARAM_STR);
        $statement->bindValue(2, $id, PDO::PARAM_INT);
        $statement->execute();
    }
}

function change_power($id, $power) {
//    echo "$id $power";
    global $connection;
    $statement = $connection->prepare("update achievements set power=? where id=?");
    $statement->bindValue(1, $power, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
    //Need to reorder the other ones.
}

function change_rank($id, $new_rank) {
    global $connection;
    $achievement = fetch_achievement($id);
    //Add owner reference
    $statement = $connection->prepare("select count(*) from achievements where active=1 and parent=? and rank=?");
    $statement->bindValue(1, $achievement->parent, PDO::PARAM_INT);
    $statement->bindValue(2, $new_rank, PDO::PARAM_INT);
    $statement->execute();
    $is_there_another_achievement = $statement->fetchColumn();

    //Need to also make it to where if the user sets a rank that's above the rang, it puts it at the end and reorders everything else.
    if ($new_rank > fetch_rank($achievement->parent)) {
        $new_rank = fetch_rank($achievement->parent) + 1;
    }
    update_rank($id, $new_rank);
     
    if ($is_there_another_achievement) {
        $statement = $connection->prepare("select * from achievements where active=1 and parent=? and rank=?");
        $statement->bindValue(1, $achievement->parent, PDO::PARAM_INT);
        $statement->bindValue(2, $new_rank, PDO::PARAM_INT);
        $statement->execute();
        $other_achievement = $statement->fetchObject();

        if (abs($achievement->rank - $other_achievement->rank) == 1) {
            update_rank($other_achievement->id, $achievement->rank);
        } else if (abs($achievement->rank - $other_achievement->rank) > 1) {
            if ($achievement->rank - $other_achievement->rank > 1) {
                $end = $achievement->rank;
                $begin = $other_achievement->rank;
                $query = "update achievements set rank=rank+1 where rank>=$begin and rank<=$end and id != $id";
            } else if ($achievement->rank - $other_achievement->rank < -1) {
                $begin = $achievement->rank;
                $end = $other_achievement->rank;
                $query = "update achievements set rank=rank-1 where rank>=$begin and rank<=$end and id != $id";
            } else {
                
            }
            $connection->exec($query);
        } else {
            error_log(date("m/d/y H:i", time()) . __FUNCTION__ . " " . __FILE__ . " @ line " . __LINE__ . " "
                    . var_dump($achievement) . "<BR>\n " . var_dump($other_achievement) . "<BR>\n");
        }
    } else {
        rank_table_in_order(0, $achievement->parent);
    }
}

function create_quick($name, $parent) {
    global $connection;

    $statement = $connection->prepare("insert into achievements(name, parent, rank) values (?, ?, ?)");
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $parent, PDO::PARAM_INT);
    $statement->bindValue(3, fetch_rank($parent) + 1, PDO::PARAM_INT);
    $statement->execute();
}

function delete($id) {
    global $connection;
    $achievement = fetch_achievement($id);
    $statement = $connection->prepare("update achievements set active=0 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    //Add user ownership.
    $connection->exec("update achievements set rank=rank-1 where active=1 and parent=$achievement->parent and rank>=$achievement->rank");
}

function fetch_achievement($id) {
    global $connection;
    $statement = $connection->prepare("select * from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchObject();
}

function fetch_rank($parent) {
    global $connection;
    $statement = $connection->prepare("select rank from achievements where active=1 and parent=? order by rank desc limit 1");
    $statement->bindValue(1, $parent, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchColumn();
}

function fetch_order_query($sort_by) {
    switch ($sort_by) {
        case "default":
            //set this according to user prefernces
            $order_by = " order by rank asc";
            break;
        case "power":
            $order_by = " order by power asc";
            break;
        case "power_rev":
            $order_by = " order by power desc, rank asc";
            break;
        case "rank":
            $order_by = " order by rank asc";
            break;
        case "rank_rev":
            $order_by = " order by rank desc";
            break;
        case "created":
            $order_by = " order by created asc";
            break;
        case "created_rev":
            $order_by = " order by created desc";
            break;
        case "name":
            $order_by = " order by name asc";
            break;
        case "name_rev":
            $order_by = " order by name desc";
            break;
    }
    return $order_by;
}

function is_it_active($id) {
    global $connection;
    $statement = $connection->prepare("select active from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    echo $statement->fetchColumn();
}

function list_all($sort_by) {
    //echo $sort_by;
    echo "<table style='text-align:center;'>"
    . "<tr><td>X</td><td>Rank</td><td>Power</td><td>Achievement Name</td></tr>";
    global $connection;
    $statement = $connection->query("select * from achievements where active=1 and parent=0" . fetch_order_query($sort_by));
    while ($achievement = $statement->fetchObject()) {
        echo "<tr><td>
              <input type='button' value='X' onclick=\"DeleteAchievement($achievement->id, $achievement->parent, false);\" />
                  </td><td>              <input type='button' value='-' 
                onclick=\"ChangeRank($achievement->id, " . ($achievement->rank + 1) . ", false);\"/>                    
              <input type='text' style='width:32px;text-align:center;' value='$achievement->rank' 
                  onkeypress=\"if (event.keyCode==13){ChangeRank($achievement->id, this.value, false); }\"/>
              <input type='button' value='+' 
                onclick=\"ChangeRank($achievement->id, " . ($achievement->rank - 1) . ", false);\"/>
                    </td><td>
              <input type='button' value='-' 
                onclick=\"ChangePower($achievement->id, " . ($achievement->power - 1) . ", false);\"/>                    
              <input type='text' style='width:32px;text-align:center;' value='$achievement->power' 
                  onkeypress=\"if (event.keyCode==13){ChangePower($achievement->id, this.value, false); }\"/>
              <input type='button' value='+' 
                onclick=\"ChangePower($achievement->id, " . ($achievement->power + 1) . ", false);\"/>
                    </td><td style='text-align:left'>
              <a href='http://" . $_SERVER['SERVER_NAME'] . "/rla/?rla=$achievement->id'> $achievement->name </a>
                  </td></td></tr>
              ";
    }
    echo "</table>";
}

function list_children($id) {
    global $connection;
    $statement = $connection->prepare("select * from achievements where active=1 and parent=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    while ($achievement = $statement->fetchObject()) {
        echo "<div>
              <input type='button' value='X' onclick=\"DeleteAchievement($achievement->id, $achievement->parent, true);\" />
              <a href='http://" . $_SERVER['SERVER_NAME'] . "/rla/?rla=$achievement->id'> $achievement->name </a>
              </div>";
    }
}

function rank_table_in_order($query, $parent) {
//Erase if not used by 01/15/15
    global $connection;
    $rank = 1;
    if ($query == 0) {
        $query = "select * from achievements where active=1 and parent=$parent order by rank";
    }
    //var_dump($connection);
    $statement = $connection->query($query);
    while ($achievement = $statement->fetchObject()) {
        //echo "$rank <BR>";
        $connection->exec("update achievements set rank=$rank where id=$achievement->id");
        $rank++;
    }
}

function update_rank($id, $new_rank) {
    global $connection;
    $statement = $connection->prepare("update achievements set rank=? where id=?");
    $statement->bindValue(1, $new_rank, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}
