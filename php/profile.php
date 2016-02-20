<?php
require_once ("achievements.php");
require_once ("config.php");
require_once ("work.php");
$pref_date_format = "F j, Y g:i:s";

//There could be an issue where users spoof this to see other people's achievements.
//Be sure to check user's session data and page reference before commencing.


$connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);

$achievement = fetch_achievement(filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT));
?>

<div id="navbar" style='text-align:center'>
    <div style="margin:5px;">
        <a href="<?= SITE_ROOT ?>">List</a>
    </div><div style="margin-bottom:10px;">

        <a href="<?= SITE_ROOT ?>/?rla=<?php echo fetch_random_achievement_id() ?>">Random</a>
    </div>
    <div>
        <?php echo fetch_nav_menu($achievement->id, $achievement->rank, $achievement->parent); ?>
    </div>    
</div>
<h1 id="achievement_name" style='text-align:center;'> 
    <?= $achievement->name ?> 
</h1>
<div>
    <?php if ($achievement->completed == 0): ?>
        <input id='complete<?php echo $achievement->id;?>' value="Complete" class='complete_button' type='button' />
    <?php endif; ?>

        <!--EVENT-->
    <div id="new_achievement_name_div" style="display:none;">
        <input maxlength="255" id="new_achievement_name" type="text" value="<?= $achievement->name ?>"/>
        <input id='edit_achievement_name_button' type="button" value="Change name"/>
    </div>
    <input id="show_new_achievement_name" type="button" value="Change Name"/>
    <input id="hide_new_achievement_name" type="button" value="Cancel" style="display:none"/>
    <input id='delete<?php echo $achievement->id; ?>' class='delete_achievement_button' type='button' value='Delete' />
</div>
<div>
    <?php echo $achievement->quality ? "Quality" : "Achievement";?>
</div>
<div>
    Parent: 
    <?php
    echo ($achievement->parent == 0) ? "Top level" : "<a href='" . SITE_ROOT . "/?rla=$achievement->parent'>" . fetch_achievement_name($achievement->parent) . "</a>";
    ?>
</div>
<div>
    Created: <?php echo date($pref_date_format, strtotime($achievement->created)); ?>
</div>
<div>
<?php
($achievement->completed != 0)
        and print ("Completed:<span style='margin-left:8px;'>" . date($pref_date_format, strtotime($achievement->completed))
                . "</span><input style='margin-left:8px;' id='cancel$achievement->id' class='cancel_completion_button' type='button' value='Cancel' />")
?>
</div>
<div> 
    Rank:<?php echo $achievement->rank; ?>
    <div>
        Power:<?php echo $achievement->power; ?>
    </div>
    <div>
        Work: <?php echo convert_work_num_to_caption($achievement->work) . " ($achievement->work)"; ?>
        <input id='work<?php echo $achievement->id; ?>' type='button' 
               class='change_work_button' value='Toggle Work Status' />
        <input id='work_status<?php echo $achievement->id; ?>' type='hidden' value='<?php echo json_encode ((int)$achievement->work); ?>' />
        
    </div>
    <div>
<?php
echo $achievement->documented ? "Documented (Requires proof of completion)" . display_documentation_menu($achievement->id, 0) : "Undocumented (No proof of completion required)" . display_documentation_menu($achievement->id, 1);
?>
    </div>
    <h3>
        Actions
        <input id="hide_new_actions" type="button" value="-" style="display:none"/>
        <input id="show_new_actions" type="button" value="+" style=""/>        
    </h3>
    <div id="new_actions" style="display:none;">
        <div>
            <select id="list_of_current_actions<?php echo $achievement->id; ?>"> </select>
        </div>
        <input id="new_action_input" type="text"/> 
        <input id='create_action_button' type="button" value="Create Action" />
    </div>
</div>
<div id="actions<?php echo $achievement->id; ?>"> </div>
<h3>
    Description
    <input id="show_new_description" type='button' value='Edit' />
</h3>
<span id="current_description">
           <?php
           echo $achievement->description ? $achievement->description : "<div style=' font-style:italic;'>There is no description.</div>";
           ?>
</span>
<span id="new_description_input" style="display:none">
    <textarea maxlength="20000" id="new_description" style="width:600px;height:150px;">
    <?php echo $achievement->description ? $achievement->description : ""; ?>
    </textarea>
    <div>
        <input id="hide_new_description" type="button" value="Cancel" />
        <input id='change_description' type='button' value='Submit' />
    </div>
</span>
</div>
<div>
    <h3>
        Children
        <input id="hide_new_children" type="button" value="-" style="display:none"/>
        <input id="show_new_children" type="button" value="+" style=""/>

    </h3>
    <div id="new_children" style="display:none">
        <input id="new_child_name" type='text' maxlength="255"/>
        <input id="create_child" type="button" value="Quick Create"/>
    </div>
    <div id='child_achievements_of_<?php echo $achievement->id; ?>'>
    </div>
</div>


<h2 style='text-align:center;'>
    Other Achievements
    <input id="hide_other_achievements" type="button" value="-" style="float:left;" />
    <input id="show_other_achievements" type="button" value="+" style="float:left;display:none;" />
</h2>
<div id="other_achievements<?php echo $achievement->id ?>" style="">
    <h3>
        Required For Completion
        <input id="show_new_required_for" type="button" value="+" style="margin-left:5px;" 
               onclick="listNewRequirements(<?php echo $achievement->id; ?>);
                       $('#new_required_for').show();
                       $('#hide_new_required_for').show();
                       $('#show_new_required_for').hide();"/>
        <input id="hide_new_required_for" type="button" value="-" style="margin-left:5px;display:none;" 
               onclick="$('#new_required_for').hide();
                       $('#hide_new_required_for').hide();
                       $('#show_new_required_for').show();"/>
    </h3>
    <div id="new_required_for" style="display:none;">
        <div id="requirements_error<?php echo $achievement->id; ?>" style="color:red;"></div>
        <select id="list_of_new_required_for<?php echo $achievement->id; ?>"></select><br>
        <input type="button" value="Required for completion" 
               onclick="createRequirement(<?php echo $achievement->id; ?>, $('#list_of_new_required_for<?php echo $achievement->id; ?>').val(), 'for');"/>
    </div>
    <div id="required_for_<?php echo $achievement->id ?>"></div>

    <h3>
        Required By Others
        <input id="show_new_required_by" type="button" value="+" style="margin-left:5px;" 
               onclick="listNewRequirements(<?php echo $achievement->id; ?>);
                       $('#new_required_by').show();
                       $('#hide_new_required_by').show();
                       $('#show_new_required_by').hide();"/>
        <input id="hide_new_required_by" type="button" value="-" style="margin-left:5px;display:none;" 
               onclick="$('#new_required_by').hide();
                       $('#hide_new_required_by').hide();
                       $('#show_new_required_by').show();"/>
    </h3>
    <div id="new_required_by" style="display:none;">
        <div id="requirements_error<?php echo $achievement->id; ?>" style="color:red;"></div>
        <select id="list_of_new_required_by<?php echo $achievement->id; ?>"></select><br>       
        <input type="button" value="Required by others" 
               onclick="createRequirement($('#list_of_new_required_by<?php echo $achievement->id; ?>').val(), <?php echo $achievement->id; ?>, 'by');"/>
    </div>
    <div id="required_by_<?php echo $achievement->id ?>"></div>


    <div>
        <h3>
            Related
            <input id="show_new_relation" type="button" value="+" style="" 
                   onclick="$('#new_relation').show();
                           $('#hide_new_relation').show();
                           $('#show_new_relation').hide();" />
            <input id="hide_new_relation" type="button" value="-"   style="display:none;"
                   onclick="$('#new_relation').hide();
                           $('#hide_new_relation').hide();
                           $('#show_new_relation').show();" />
        </h3>
        <div id="new_relation" style="display:none;">
            <select id="list_of_new_relations<?php echo $achievement->id ?>" style="text-align:center;">

            </select>

            <input type="button" value="Create Relation" 
                   onclick="createRelation(<?php echo $achievement->id ?>, $('#list_of_new_relations<?php echo $achievement->id ?>').val());" />
        </div>
        <div id="relation_error" style="color:red;"></div>
        <div id="list_of_relations<?php echo $achievement->id ?>"></div>

    </div>
</div>

<div>
    <h2 style='text-align:center;'>
        Notes    
        <input id="show_notes" type="button" value="+" style="float:left;display:none;"
               onclick="$('#all_notes').show();
                       $('#hide_notes').show();
                       $('#show_notes').hide();" />
        <input id="hide_notes" type="button" value="-" style="float:left;" 
               onclick="$('#all_notes').hide();
                       $('#hide_notes').hide();
                       $('#show_notes').show();" />
    </h2>
    <div id="all_notes">
        <input id="show_new_notes" type="button" value="Create Note" 
               onclick="$('#show_new_notes').hide();
                        $('#new_notes').show();" />
        <div id="new_notes" style="display:none;">
            <textarea id="new_note_inputted" style='width:400px;height:100px;'></textarea>
            <div>
                <input type="button" value="Cancel"
                       onclick="$('#new_notes').hide();
                                $('#show_new_notes').show();" />
                <input type="button" value="Create Note"
                       onclick="    createNote($('#new_note_inputted').val(), <?php echo $achievement->id; ?>, 0);
                               $('#new_notes').hide();
                               $('#hide_new_notes').hide();
                               $('#show_new_notes').show();
                               $('#new_note_inputted').val('');" />
            </div>
        </div>
        <div id="list_of_notes<?php echo $achievement->id; ?>"></div>
    </div>
</div>

<?php

function display_documentation_menu($id, $status) {
    $menu = "<input style='margin-left:8px;' type='button' value='Change to ";
    $menu = $status ? $menu . "documented" : $menu . "undocumented";
    $menu = $menu . "' onclick=\"changeDocumentationStatus($id, $status)\" />";
    return $menu;
}

function fetch_nav_menu($id, $rank, $parent) {
    $prev_achievement = fetch_achievement_by_rank_and_parent($rank - 1, $parent);
    $next_achievement = fetch_achievement_by_rank_and_parent($rank + 1, $parent);
    $string = ($rank > 1) ? " <div title='$prev_achievement->name' style='float:left'>
                <a href='" . SITE_ROOT . "/?rla=$prev_achievement->id'>Previous</a>
            </div>" : " <div style='float:left;'>Previous</div>";
    $string = $string . generate_select_achievement_menu($parent, $id);
    $string = ($rank < fetch_highest_rank($parent)) ? $string . "   <div title='$next_achievement->name' style='float:right'>
                            <a href='" . SITE_ROOT . "/?rla=$next_achievement->id'>Next</a>
                        </div>" : $string . "   <div class='right'>Next</div>";
    return $string;
}

function generate_select_achievement_menu($parent, $id) {
    $connection = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PWD);
    $string = " <select id='achievement_id' style='text-align:center;'
                  onchange=\"window.location.assign('". SITE_ROOT. "?rla='+$('#achievement_id').val())\">
                    <option>Go to another achievement here</option>";

    $statement = $connection->prepare("select * from achievements where active=1 and parent=? and id!=? order by name asc");
    $statement->bindValue(1, $parent, PDO::PARAM_INT);
    $statement->bindValue(2, $id, PDO::PARAM_INT);
    $statement->execute();
    while ($achievement = $statement->fetchObject()) {
        $string = $string . "<option value='$achievement->id' > $achievement->name</option>";
    }
    $string = $string . "  </select>";
    return $string;
}

