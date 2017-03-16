<?php

# Copyright (c) 2011 John Reese
# Licensed under the MIT license
#
# 20131119 - fmancardi - TICKET 3 - REQ - Display due date on board
# 20120705 - fmancardi - TICKET 1 - REQ - Custom fields used to track work - add configuration option
#

require_once("icon_api.php");

$current_project = helper_get_current_project();
$project_ids = current_user_get_all_accessible_subprojects($current_project);
$project_ids[] = $current_project;

$resolved_threshold = config_get("bug_resolved_status_threshold");

$bug_table = db_get_table("mantis_bug_table");
$version_table = db_get_table("mantis_project_version_table");

# Collect user input
$task_owner = gpc_get_int("user",0);
if($task_owner < 0)
{
        $task_owner = auth_get_current_user_id();
}




# Fetch list of target versions in use for the given projects
$query = "SELECT DISTINCT v.version,v.id,v.date_order FROM {$version_table} v " .
		 "JOIN {$bug_table} b ON b.target_version= v.version " .
		 "WHERE v.project_id IN (".join(", ", $project_ids). ") " .
		 "ORDER BY v.date_order DESC";

$result = db_query_bound($query);

$versions = array();
while ($row = db_fetch_array($result))
{
	if ($row["version"])
	{
	     $versions[$row['id']] = $row["version"];
	     $versionsGui[$row['id']] = array('name' => $row["version"], 'verbose' => $row['version'] . ' (' . date('d/m/Y',$row['date_order']) . ')');
	}
}

# Get the selected target version, and use 'Sprint Backlog' as default (Uninitialized)
$target_version = gpc_get_string("version", "Sprint Backlog");
if (!in_array($target_version, $versions))
{
	$target_version = "";
}

# Fetch list of categories in use for the given projects
$params = array();
$query = "SELECT DISTINCT category_id FROM {$bug_table} WHERE project_id IN (" . join(", ", $project_ids) . ") ";

if ($target_version)
{
	$query .= "AND target_version=" . db_param();
	$params[] = $target_version;
}

$result = db_query_bound($query, $params);
$categories = array();
$category_ids = array();
while ($row = db_fetch_array($result))
{
	if ($row["category_id"])
	{
		$category_id = $row["category_id"];
		$category_ids[] = $category_id;
		$category_name = category_full_name($category_id, false);

		if (isset($categories[$category_name]))
		{
			$categories[$category_name][] = $category_id;
		}
		else
		{
			$categories[$category_name] = array($category_id);
		}
	}
}

# Get the selected category
$category = gpc_get_string("category", "");
if (isset($categories[$category]))
{
	$category_ids = $categories[$category];
}

# Retrieve all bugs with the matching target version
$params = array();
$query = "SELECT id FROM {$bug_table} WHERE project_id IN (" . join(", ", $project_ids) . ")";

if ($target_version)
{
	$query .= " AND target_version=" . db_param();
	$params[] = $target_version;
}
if ($category_name)
{
	$query .= " AND category_id IN (" . join(", ", $category_ids) . ")";
}

# Users
if ($task_owner > 0)
{
	$dummy = array(0,$task_owner);
    $query .= " AND handler_id IN (" . join(", ", $dummy) . ")";
}






$query .= " ORDER BY status ASC, priority DESC, id DESC";
$result = db_query_bound($query, $params);

$bug_ids = array();
while ($row = db_fetch_array($result))
{
	$bug_ids[] = $row["id"];
}

bug_cache_array_rows($bug_ids);
$bugs = array();
$status = array();
$columns = plugin_config_get("board_columns");
$sevcolors = plugin_config_get("board_severity_colors");
$rescolors = plugin_config_get("board_resolution_colors");
$sprint_length = plugin_config_get("sprint_length");
$custom_fields = plugin_config_get("custom_fields");

// var_dump($columns);

//
$t_work_fields_set = array();
//$key = $custom_fields["work"];
foreach($custom_fields["work"] as $key => $cfname )
{
	$t_work_fields_set[$key] = custom_field_get_id_from_name($cfname);
}

$use_source = plugin_is_loaded("Source");
$resolved_count = 0;

$rem_work = array();
$est_work = array();
$act_work = array();
$est_work_total = 0;
$act_work_total = 0;
$rem_work_total = 0;

$lbl = array();
$lbl['due_date']=lang_get('due_date');
$due_date_format = config_get('calendar_date_format');

foreach ($bug_ids as $bug_id)
{
	$bug = bug_get($bug_id);

	$bugs[$bug->status][] = $bug;

	$source_count[$bug_id] = $use_source ? count(SourceChangeset::load_by_bug($bug_id)) : "";
	if ($bug->status >= $resolved_threshold)
	{
		$resolved_count++;
	}
	
	$est_work[$bug_id] = custom_field_get_value($t_work_fields_set['scrum_effort'], $bug_id);
	$act_work[$bug_id] = custom_field_get_value($t_work_fields_set['scrum_work_done'], $bug_id);
	$rem_work[$bug_id] = custom_field_get_value($t_work_fields_set['scrum_tobe_done'], $bug_id);

	$rem_work_total += $rem_work[$bug_id] ;
	$est_work_total += $est_work[$bug_id];
	$act_work_total += $act_work[$bug_id];
}


// Check for divide by zero
$workleft_percent = ($est_work_total > 0) ? floor(100 - 100 * $rem_work_total / $est_work_total) : 100;
 
$bug_count = count($bug_ids);
if ($bug_count > 0)
{
  $resolved_percent = floor(100 * $resolved_count / $bug_count);
}
else
{
	$resolved_percent = 0;
}

if ($target_version)
{
	foreach($project_ids as $project_id)
	{
		$version_id = version_get_id($target_version, $project_id, true);
		if ($version_id !== false)
		{
			break;
		}
	}

	$version = version_get($version_id);
	$version_date = $version->date_order;
        $version_ddmmyyyy =  date("d/m/Y",$version_date);

	$now = time();

	$time_diff = $version_date - $now;
	$time_hours = floor($time_diff / 3600);
	$time_days = floor($time_diff / 86400);
	$time_weeks = floor($time_diff / 604800);

	$timeleft_percent = min(100, 100 - floor(100 * $time_diff / $sprint_length));
        $timeleft_string = '';
	if ($time_diff <= 0)
	{
		$timeleft_string = plugin_lang_get("time_up");
	}
	else if ($time_weeks > 1)
	{
		$timeleft_string = $time_weeks . plugin_lang_get("time_weeks");
	}
	else if ($time_days > 1)
	{
		$timeleft_string = $time_days . plugin_lang_get("time_days");
	}
	else if ($time_hours > 1)
	{
		$timeleft_string = $time_hours . plugin_lang_get("time_hours");
	}

        if($timeleft_string != '')
        {
           $timeleft_string .= ' (' . $version_ddmmyyyy . ')';
        }
}

html_page_top(plugin_lang_get("board"));

?>

<link rel="stylesheet" type="text/css" href="<?php echo plugin_file("scrumboard.css") ?>"/>

<br/>
<table class="width100 scrumboard" align="center" cellspacing="1">

<tr>
<td class="form-title" colspan="<?php echo count($columns) ?>">
<?php echo plugin_lang_get("board") ?>
<form action="<?php echo plugin_page("board") ?>" method="get">
<input type="hidden" name="page" value="Scrum/board"/>
<select name="version">
<option value=""><?php echo plugin_lang_get("all") ?></option>
<?php foreach ($versionsGui as $version): ?>
<option value="<?php echo string_attribute($version['name']) ?>" <?php if ($version['name'] == $target_version) echo 'selected="selected"' ?>>
<?php echo string_display_line($version['verbose']) ?></option>
<?php endforeach ?>
</select>
<select name="category">
<option value=""><?php echo plugin_lang_get("all") ?></option>
<?php foreach (array_keys($categories) as $category_name): ?>
<option value="<?php echo $category_name ?>" <?php if ($category == $category_name) echo 'selected="selected"' ?>><?php echo $category_name ?></option>
<?php endforeach ?>
</select>


<!-- USER -->
<?php echo '&nbsp;&nbsp;' . lang_get("assigned_to") . '&nbsp;' ?>
<select name="user">
<option value="0"><?php echo '[' . lang_get( 'any' ) .']'; ?></option>
<option value="-1"><?php echo '[' . lang_get( 'myself' ) . ']'; ?></option>
<?php print_user_option_list($task_owner); ?>
</select>


<input type="submit" value="Go"/>
</form>
</td>
</tr>

<tr>
<td colspan="<?php echo count($columns) ?>">
<div class="scrumbar">
<?php if ($resolved_percent > 50): ?>
<span class="bar" style="width: <?php echo $resolved_percent ?>%"><?php echo "{$resolved_count}/{$bug_count} ({$resolved_percent}%)" ?></span>
<?php else: ?>
<span class="bar" style="width: <?php echo $resolved_percent ?>%">&nbsp;</span><span><?php echo "{$resolved_count}/{$bug_count} ({$resolved_percent}%)" ?></span>
<?php endif ?>
</div>

<?php if ($target_version): ?>
<div class="scrumbar">
<?php if ($timeleft_percent > 50): ?>
<span class="bar" style="width: <?php echo $timeleft_percent ?>%"><?php echo $timeleft_string ?></span>
<?php else: ?>
<span class="bar" style="width: <?php echo $timeleft_percent ?>%">&nbsp;</span><span><?php echo $timeleft_string ?></span>
<?php endif ?>
</div>
<?php endif ?>

</td>
</tr>

<tr>
<td colspan="<?php echo count($columns) ?>">
<div class="scrumbar">
<?php if ($workleft_percent > 50): ?>
<span class="bar" title="Est/Act/Rem" style="width: <?php echo $workleft_percent ?>%"><?php echo "{$est_work_total}/{$act_work_total}/{$rem_work_total} ({$workleft_percent}%)" ?></span>
<?php else: ?>
<span class="bar" title="Est/Act/Rem" style="width: <?php echo $workleft_percent ?>%">&nbsp;</span><span><?php echo "{$est_work_total}/{$act_work_total}/{$rem_work_total} ({$workleft_percent}%)" ?></span>
<?php endif ?>
</div>

</td>
</tr>

<tr class="row-category">

<?php foreach ($columns as $column => $statuses): ?>
<td><?php echo $column ?></td>
<?php endforeach ?>
</tr>

<tr class="row-1">

<?php foreach ($columns as $column => $statuses): ?>
<td class="scrumcolumn">
<?php $first = true; foreach ($statuses as $status): ?>
<?php if (isset($bugs[$status]) || plugin_config_get("show_empty_status")): ?>
<?php if ($first): $first = false; else: ?>
<hr/>
<?php endif ?>
<?php $status_name = get_enum_element("status", $status); if ($status_name != $column): ?>
<p class="scrumstatus"><?php echo get_enum_element("status", $status) ?></p>
<?php endif ?>
<?php if (isset($bugs[$status])) foreach ($bugs[$status] as $bug):
$sevcolor = $sevcolors[$bug->severity];
$rescolor = $rescolors[$bug->resolution];
?>

<div class="scrumblock">
<p class="priority"><?php print_status_icon($bug->priority) ?></p>
<p class="bugid"></p>
<p class="commits" title="Est/Act/Rem"><?php echo $est_work[$bug->id] ?>/<?php echo $act_work[$bug->id] ?>/<?php echo $rem_work[$bug->id] ?></p>
<?php
$displayAlertLevel = true;
$redAlert = false;
$zum = null;
$total = $act_work[$bug->id] + $rem_work[$bug->id];
$est = $est_work[$bug->id] > 0 ? $est_work[$bug->id] : null; 
if( !is_null($est) )
{
  $zum = ($est_work[$bug->id] / $total) * 100;
  $redAlert = (( ($total / $est) * 100 ) > 100);
}

// we need to check status, for new is nonsense
// if()
// var_dump($columns['New']);


$alertLevelString = $redAlert ? 'RED ALERT' : 'Good! -Under Control';
if( in_array($bug->status,$columns['New']))
{
	$redAlert = false;
	$displayAlertLevel = false;
	$alertLevelString = '';
}
// Check if people is Lazy => remember it
if($est_work[$bug->id] == 0)
{
	$alertLevelString = 'Please Assign Est. Work (hours)';
}

?>


<p class="bugid"> <?php echo $alertLevelString; ?> </p>

<p class="category">

<?php 
$due_date_class = bug_is_overdue($bug->id) ? "overdue" : '';
$due_date_string = '<span class="' . $due_date_class . '">' . date($due_date_format, $bug->due_date) . '</span>';

if ($bug->project_id != $current_project) {
	$project_name = project_get_name($bug->project_id);
	echo "<span class=\"project\">{$project_name}</span> - ";
}
echo category_full_name($bug->category_id, false) ?>


</p>
<p class="summary"><?php echo print_bug_link($bug->id) ?>: <?php echo $bug->summary ?></p>
<p class="handler" title="<?php echo($lbl['due_date']);?>"> <?php echo $due_date_string; ?></p>

<p class="severity" style="background: <?php echo $sevcolor ?>" title="Severity: <?php echo get_enum_element("severity", $bug->severity) ?>"></p>
<p class="resolution" style="background: <?php echo $rescolor ?>" title="Resolution: <?php echo get_enum_element("resolution", $bug->resolution) ?>"></p>
<p class="handler"><?php echo $bug->handler_id > 0 ? user_get_name($bug->handler_id) : "" ?></p>
</div>

<?php endforeach ?>
<?php endif ?>
<?php endforeach ?>
</td>
<?php endforeach ?>

</tr>
</table>

<?php
html_page_bottom();

