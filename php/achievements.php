<?php

include_once ("config.php");
//TODO: Keep track of all changes. 
$connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);

function activate_achievement($id) {
    global $connection;
    $statement = $connection->prepare("update achievements set active=1 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}

function are_ranks_duplicated($parent) {
    global $connection;
    $statement = $connection->query("SELECT COUNT(*) as count FROM achievements where parent=$parent and active=1 GROUP BY rank HAVING COUNT(*) > 1");
    if ((int) $statement->fetchColumn() > 0) {
        return true;
    }
    return false;
}

function change_description($id, $description) {
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

function change_name($id, $name) {
    global $connection;
    $statement = $connection->prepare("update achievements set name=? where id=?");
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_power($id, $power) {
    global $connection;
    $statement = $connection->prepare("update achievements set power=? where id=?");
    $statement->bindValue(1, $power, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function change_rank($id, $new_rank) {
    $achievement = fetch_achievement($id);
    update_rank($id, $new_rank);
    deactivate_achievement($achievement->id);
    if ($new_rank <= 0) {
        //BAD - shouldn't be able to change rank to 0 or a negative
    }
    if (are_ranks_duplicated($achievement->parent)) {
        fix_achievement_ranks("updated", $achievement->parent);
        exit;
        //BAD - ranks shouldn't be duplicated 
    }
    //if user picks a new rank too big
    if ($new_rank > (fetch_highest_rank($achievement->parent))) {
        activate_achievement($achievement->id);
        fix_achievement_ranks("rank", $achievement->parent);
        exit;
    }
    rank_achievements($achievement, $new_rank);
    activate_achievement($achievement->id);
}

function change_work_status_of_achievement($id, $status) {
    global $connection;
    $statement = $connection->prepare("update achievements set work=? where id=?");
    $statement->bindValue(1, $status, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
    update_work_status_for_related_actions($achievement_id);
}

function achievement_name_exists($name, $parent) {    
    global $connection;
    $statement = $connection->prepare("select count(*) from achievements where active=1 and name=? and parent=? limit 1");
    $statement->bindValue(1, $name, PDO::PARAM_STR);
    $statement->bindValue(2, $parent, PDO::PARAM_INT);
    $statement->execute();    
    return boolval($statement->fetchcolumn());
}



function count_achievements() {
    global $connection;
    $data=[];
    
    $statement = $connection->query("select count(*) from achievements where quality=false and active=1 and parent=0 and  work>0");
    $num_of_working_achievements = (int) $statement->fetchColumn();
    
    $statement = $connection->query("select count(*) from achievements where active=1 and quality=true");
    $num_of_qualities = (int) $statement->fetchColumn();
    
    $statement = $connection->query("select count(*) from achievements where active=1 and parent=0");
    $num_of_achievements = (int) $statement->fetchColumn();
    
    $num_of_nonworking_achievements = $num_of_achievements - $num_of_working_achievements - $num_of_qualities;
    $data=["total"=>$num_of_achievements, 
           "working"=>$num_of_working_achievements, 
           "not_working"=>$num_of_nonworking_achievements, 
           "qualities"=>$num_of_qualities];
    
    echo json_encode($data);
}


function create_achievement($name, $parent) {
    global $connection;
    $achievement = fetch_achievement($parent);
    if (achievement_name_exists($name, $parent)) {
        //ERROR $name already exists.
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
    global $connection;
    $statement = $connection->prepare("update achievements set active=0 where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
}

function delete_achievement($id) {
    global $connection;
    deactivate_achievement($id);
    $achievement = fetch_achievement($id);
    $connection->exec("update achievements set rank=rank-1 where active=1 and parent=$achievement->parent and rank>=$achievement->rank");
}

function display_achievement_listing_menu($achievement, $child) {
//revisit
    if ($child) {
        $string = "<input class='delete_button' type='button' value='X' />
        
            <input type='button' value='-' 
                onclick=\"changeRank($achievement->id, " . ($achievement->rank + 1) . ", true, $achievement->parent);\"/>                    
              <input type='text' style='width:32px;text-align:center;' value='$achievement->rank' 
                  onkeypress=\"if (event.keyCode==13){changeRank($achievement->id";
        $string = $string . ", this.value, true, $achievement->parent); }\"/>
              <input type='button' value='+' 
                onclick=\"changeRank($achievement->id, " . ($achievement->rank - 1) . ", true, $achievement->parent);\"/>";
    } else {
        $string = "<td>
                        <input class='new_shit' type='button' value='X'  
                            onclick=\"deleteAchievement($achievement->id, $achievement->parent, 0);\" />
                  </td><td>
                        <input id='down_rank_$achievement->id' type='button' class='down_rank_button' value='-' />
                        <input id='change_rank_$achievement->id' type='text' 
                            class='change_rank' value='$achievement->rank' style='width:32px;text-align:center;' 
                                onkeypress=\"if (event.keyCode===13){changeRank($achievement->id, this.value, false, $achievement->parent);}\" />
                        <input id='up_rank_$achievement->id' type='button' class='up_rank_button' value='+' />";
        $string = $string . "</td><td>
                    $achievement->power
                    </td><td>
                    $achievement->power_adj
                    </td><td>";
        $string = $string .
                "<input id='turn_work_on_$achievement->id' type='button' class='change_work_button' value='" . display_current_work_status($achievement->id) . "' 
                    onclick=\"toggleWorkStatus($achievement->id, $achievement->work, $achievement->parent);\"/></td><td>";
        $achievement->quality ? $string = $string . "<input type='button' value='On' 
							onclick=\"changeQuality($achievement->id, false);\"/>" : $string = $string . "<input type='button' value='Off' 
					   onclick=\"changeQuality($achievement->id, true);\"/>";
        $string = $string . "</td>";
    }
    return $string;
}

function display_current_work_status($id) {
    global $connection;
    $statement = $connection->prepare("select work from achievements where active=1 and id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();

    return convert_work_num_to_caption($statement->fetchColumn());
}

function fetch_achievement($id) {
    global $connection;
    $statement = $connection->prepare("select * from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchObject();
}

function fetch_highest_rank($parent) {
    global $connection;
    $statement = $connection->prepare("select rank from achievements where active=1 and parent=? order by rank desc limit 1");
    $statement->bindValue(1, $parent, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchColumn();
}

function fetch_order_query($sort_by) {
    //I understand why this was flagged.  I could just reference an array.
    switch ($sort_by) {
        case "default":
            $order_by = " order by quality asc, rank asc";
            break;
        case "power":
            $order_by = " order by power asc";
            break;
        case "powerrev":
            $order_by = " order by power desc, rank asc";
            break;
        case "power_adj":
            $order_by = " order by power_adj asc";
            break;
        case "power_adjrev":
            $order_by = " order by power_adj desc, rank asc";
            break;
        case "rank":
            $order_by = " order by rank asc";
            break;
        case "rankrev":
            $order_by = " order by rank desc";
            break;
        case "created":
            $order_by = " order by created asc";
            break;
        case "createdrev":
            $order_by = " order by created desc";
            break;
        case "name":
            $order_by = " order by name asc";
            break;
        case "namerev":
            $order_by = " order by name desc";
            break;
        case "work":
            $order_by = " order by work";
            break;
        case "workrev":
            $order_by = " order by work desc";
            break;
    }
    return $order_by;
}

function fix_achievement_ranks($field, $parent) {
    global $connection;
    $connection->exec("set @rank=0");
    $connection->exec("update achievements set rank=@rank:=@rank+1 where active=1 and parent=$parent order by $field");
}

function is_it_active($id) {
    global $connection;
    $statement = $connection->prepare("select active from achievements where id=?");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    echo $statement->fetchColumn();
}

function list_achievements($sort_by) {

    echo "<table style='text-align:center;'>"
    . "<tr><td>X</td><td>Rank</td><td>Power</td><td>Power (Adj)</td><td>
            <a href='" . SITE_ROOT . "/work/' style='color:black;'>Work</a>
                </td><td>
			Quality
		</td><td>Achievement Name</td></tr>";
    global $connection;
    $query = "select * from achievements where active=1 and parent=0" . fetch_order_query($sort_by);
    $statement = $connection->query($query);
    while ($achievement = $statement->fetchObject()) {
        echo "<tr>"
        . display_achievement_listing_menu($achievement, false)
        . "<td style='text-align:left'>
              <a href='" . SITE_ROOT . "/?rla=$achievement->id' style='text-decoration:none;";
        if ($achievement->quality) {
            echo "color:gray;";
        } else if ($achievement->work) {
            echo "color:green;";
        } else {
            echo "color:red;";
        }
        echo "'> 
                $achievement->name 
                    </a>
                  </td></tr>
              ";
    }
    echo "</table>";
}

function list_children($id) {
    global $connection;
    $statement = $connection->prepare("select count(*) from achievements where active=1 and parent=? limit 1");
    $statement->bindValue(1, $id, PDO::PARAM_INT);
    $statement->execute();
    if ($statement->fetchColumn() == 0) {
        echo "<div style=' font-style:italic;'>This achievement has no children.</div>";
    } else {
        $statement = $connection->prepare("select * from achievements where active=1 and parent=? order by rank");
        $statement->bindValue(1, $id, PDO::PARAM_INT);
        $statement->execute();
        while ($achievement = $statement->fetchObject()) {
            echo "<div>"
            . display_achievement_listing_menu($achievement, true)
            . " <a href='" . SITE_ROOT . "/?rla=$achievement->id'>$achievement->name </a>
              </div>";
        }
    }
}

function rank_achievements($achievement, $new_rank) {
    global $connection;
    $connection->exec("set @rank=$new_rank");
    if ($new_rank - $achievement->rank > 0) {
        $connection->exec("update achievements set rank=@rank:=@rank-1 where active=1 and parent=$achievement->parent and rank<=$new_rank order by rank");
    } else if ($new_rank - $achievement->rank < 0) {
        $connection->exec("update achievements set rank=@rank:=@rank+1 where active=1 and parent=$achievement->parent and rank>=$new_rank order by rank");
    } else if ($new_rank - $achievement->rank == 0) {
        //BAD - new rank should not be the same as the old
    }
}

function change_quality($id, $quality) {
    global $connection;
    $statement = $connection->prepare("update achievements set quality=? where id=?");
    $statement->bindValue(1, $quality, PDO::PARAM_BOOL);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}

function update_rank($id, $new_rank) {
    global $connection;
    $statement = $connection->prepare("update achievements set rank=? where id=?");
    $statement->bindValue(1, $new_rank, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
}
