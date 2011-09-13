<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2008 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');

include_once("./include/global.php");
include_once("./lib/time.php");
include_once("./lib/html_tree.php");
include_once("./lib/api_graph.php");
include_once("./lib/api_tree.php");
include_once("./lib/api_data_source.php");

$graphs_array = array(
	1  => "1 Graphs",
	2  => "2 Graphs",
	4  => "4 Graphs",
	6  => "6 Graphs",
	8  => "8 Graphs",
	10 => "10 Graphs"
);

$graph_cols = array(
	1  => "1 Column",
	2  => "2 Columns",
	3  => "3 Columns",
	4  => "4 Columns",
	5  => "5 Columns"
);

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("tree_id"));
input_validate_input_number(get_request_var_request("timespan"));
input_validate_input_number(get_request_var_request("graphs"));
input_validate_input_number(get_request_var_request("graph"));
input_validate_input_number(get_request_var_request("cols"));
input_validate_input_number(get_request_var_request("id"));
/* ==================================================== */

/* clean up search string */
if (isset($_REQUEST["filter"])) {
	$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
}

/* clean up legend */
if (isset($_REQUEST["legend"])) {
	$_REQUEST["legend"] = sanitize_search_string(get_request_var_request("legend"));
}

$changed = false;
$changed += cycle_check_changed("filter", "sess_cycle_filter");
$changed += cycle_check_changed("tree_id", "sess_cycle_tree_id");
$changed += cycle_check_changed("graphs", "sess_cycle_graphpp");

if ($changed) {
	$_REQUEST["id"] = -1;
}

/* remember these search fields in session vars so we don't have to keep passing them around */
load_current_session_value("filter",   "sess_cycle_filter",   "");
load_current_session_value("tree_id",  "sess_cycle_tree_id",  read_config_option("cycle_custom_graphs_tree"));
load_current_session_value("graphs",   "sess_cycle_graphpp",  read_config_option("cycle_graphs"));
load_current_session_value("cols",     "sess_cycle_cols",     read_config_option("cycle_cols"));
load_current_session_value("legend",   "sess_cycle_legend",   read_config_option("cycle_legend"));
load_current_session_value("action",   "sess_cycle_action",   "view");

$legend  = get_request_var_request("legend");
$tree_id = get_request_var_request("tree_id");
$graphpp = get_request_var_request("graphs");
$cols    = get_request_var_request("cols");
$filter  = get_request_var_request("filter");
$id      = get_request_var_request("id");

if (empty($tree_id)) $tree_id = read_config_option("cycle_custom_graphs_tree");
if (empty($id))      $id      = -1;

/* get the start and end times for the graph */
$timespan        = array();
$first_weekdayid = read_graph_config_option("first_weekdayid");
get_timespan($timespan, time(), get_request_var_request("timespan") , $first_weekdayid);

$graph_tree = $tree_id;
$html       = "";
$out        = "";

/* detect the next graph regardless of type */
get_next_graphid($graphpp, $filter);

/* process the filter section */
switch(read_config_option("cycle_custom_graphs_type")) {
case "0":
case "1":
	/* will only use the filter for full rotation */

	break;
case "2":
	$tree_list = get_graph_tree_array();
	if (sizeof($tree_list) > 1) {
		$html ="<select id='tree' name='tree' onChange='newTree()'>\n";

		foreach ($tree_list as $tree) {
			$html .= "<option value='".$tree["id"]."'".($graph_tree == $tree["id"] ? " selected" : "").">".title_trim($tree["name"], 30)."</option>\n";
		}

		$html .= "</select>\n";
	}

	$graphs = get_tree_graphs($graph_tree, $graphpp, $filter);

	if (sizeof($graphs)) {
		$html .= "<select id='graph' name='graph' onChange='newGraph()'>";

		foreach($graphs as $data) {
			foreach($data as $subdata) {
				if (is_array($subdata)) {
					foreach($subdata as $graph) {
						$html .= "<option value='" . $graph['graph_id'] . "'" . ($graph_id == $graph['graph_id'] ? " selected" : "") . ">" . title_trim($graph['graph_title'], 70) . "</option>\n";
					}
				}
			}
		}

		$html .= "</select>\n";
	}

	$html .= "<input id='id' name='id' type='hidden' value='-1'>";

	break;
}

$html .= "<input id='filter' name='filter' type='textbox' size='40' onkeypress='processReturn(event)' value='" . $filter . "'>";
//$html .= "<input id='prev_graph_id' name='prev_graph_id' type='hidden' value='$prev_graph_id'>";
//$html .= "<input id='next_graph_id' name='next_graph_id' type='hidden' value='$next_graph_id'>";
//$html .= "<input id='cur_leaf_id'   name='cur_leaf_id'   type='hidden' value='$cur_leaf_id'>";

/* create the graph structure and output */
$out       = '<table cellpadding="5" cellspacing="5" border="0">';
$max_cols  = $cols;
$col_count = 1;

if (sizeof($graphs)) {
foreach($graphs as $graph) {
	if ($col_count == 1)
		$out .= '<tr>';

	$out .= '<td align="center" class="graphholder" style="width:' . (read_config_option('cycle_width')) . 'px;">'
		.'<a href = ../../graph.php?local_graph_id='.$graph['graph_id'].'&rra_id=all>'
		."<img border='0' src='../../graph_image.php?local_graph_id=".$graph['graph_id']."&rra_id=0&graph_start=".$timespan["begin_now"]
		.'&graph_end='.time().'&graph_width='.read_config_option('cycle_width').'&graph_height='.read_config_option('cycle_height').($legend==0 || $legend=='' ? '&graph_nolegend=true' : '')."'>"
		.'</a></td>';

	$out .= "<td valign='top' style='padding: 3px;' class='noprint' width='10px'>" . 
		"<a href='./../../graph.php?action=zoom&local_graph_id=" . $graph['graph_id'] . "&rra_id=5&view_type='><img src='./../../images/graph_zoom.gif' border='0' alt='Zoom Graph' title='Zoom Graph' style='padding: 3px;'></a><br>" . 
		"<a href='./../../graph_xport.php?local_graph_id=" . $graph['graph_id'] . "&rra_id=5&view_type='><img src='./../../images/graph_query.png' border='0' alt='CSV Export' title='CSV Export' style='padding: 3px;'></a><br>" . 
		"<a href='./../../graph.php?action=properties&local_graph_id=" . $graph['graph_id'] . "&rra_id=5&view_type='><img src='./../../images/graph_properties.gif' border='0' alt='Graph Source/Properties' title='Graph Source/Properties' style='padding: 3px;'></a><br>";

	ob_start();
	api_plugin_hook('graph_buttons', array('hook' => 'view', 'local_graph_id' =>$graph['graph_id'], 'rra' => '5', 'view_type' => ""));
	$out .= ob_get_clean();

	if ($col_count == $max_cols) {
		$out .= '</tr>';
		$col_count=1;
	} else {
		$col_count++;
	}
}
}

if ($col_count  <= $max_cols) {
	$col_count--;
	$addcols = $max_cols - $col_count;

	for($x=1; $x <= $addcols; $x++) {
		$out .= '<td class="graphholder">&nbsp;</td>';
	}

	$out .= '</tr>';
}

$out .= '</table>';

$output = array("html" => $html, "graphid" => $graph_id, "nextgraphid" => $next_graph_id, "prevgraphid" => $prev_graph_id, "cur_leaf_id" => $cur_leaf_id, "image" => base64_encode($out));
print json_encode($output);

exit;

function cycle_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function get_next_graphid($graphpp, $filter) {
	global $id, $graph_id, $graphs, $next_graph_id, $prev_graph_id, $graph_tree, $cur_leaf_id;

	/* if no default graph list has been specified, default to 0 */
	$type = read_config_option("cycle_custom_graphs_type");
	if ($type == 1 && !strlen(read_config_option("cycle_custom_graphs_list"))) {
		$type = 0;
	}

	switch($type) {
	case "0":
	case "1":
		$graph_id = $id;

		if ($graph_id <= 0) {
			$graph_id = 0;
		}

		$sql_where = "WHERE gl.id>=$graph_id";
		if (strlen($filter)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " gtg.title_cache LIKE '%$filter%'";
		if ($type == 1) {
			$cases = explode(",", read_config_option("cycle_custom_graphs_list"));
			$newcase = "";
			foreach($cases as $case) {
				$newcase .= (is_numeric($case) ? (strlen($newcase) ? ",":"") . $case:"");
			}

			if (strlen($newcase)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " gl.id IN($newcase)";
		}

		$done          = false;
		$start         = 0;
		$next_graph_id = 0;
		$prev_graph_id = 0;
		$title         = "";
		$graphs        = array();
		$i             = 0;

		/* Build a graphs array of the number of graphs requested
		 * this graph array will be used for rendering.  In addition
		 * when the user hit's next, or the graphs cycle, we need
		 * to know the next graph id to display.  Calculate that
		 * based upon the offset $graphpp.  If we overflow,  start
		 * from the beginning, which is the second section until
		 * we either run out of rows, or reach the $graphpp limit.
		 *
		 * Finally, don't try to grap all graphs at once.  It takes
		 * too much memory on big systems.
		 */

		/* first pass is moving up in ids */
		while (!$done) {
			$sql = "SELECT
				gl.id,
				gtg.title_cache
				FROM graph_local AS gl
				INNER JOIN graph_templates_graph AS gtg
				ON (gtg.local_graph_id=gl.id)
				$sql_where
				ORDER BY gl.id ASC
				LIMIT $start, $graphpp";

			$rows = db_fetch_assoc($sql);

			if ($graph_id > 0) {
				$curr_found    = true;
			}else{
				$curr_found    = false;
			}

			if (sizeof($rows)) {
			foreach ($rows as $row) {
				if (is_graph_allowed($row["id"])) {
					if (!$curr_found) {
						$graph_id   = $row['id'];
						$title      = $row['title_cache'];
						$curr_found = true;
cacti_log("Found (1) current graph id '" . $row['id'] . "'", false);
						$graphs[$i]['graph_id'] = $graph_id;
						$i++;
					}else{
						if (sizeof($graphs) < $graphpp) {
cacti_log("Found (1) graph id '" . $row['id'] . "'", false);
							$graphs[$i]['graph_id'] = $row['id'];
							$i++;
						}else{
cacti_log("Found (1) next graph id '" . $row['id'] . "'", false);
							$next_graph_id = $row['id'];
							$next_found    = true;

							break;
						}
					}
				}
			}
			}

			if ($next_graph_id > 0) {
				$done = true;
			}elseif (sizeof($rows) == 0) {
				$done = true;
			}else{
				$start += $graphpp;
			}
		}

		/* if we did not fine all the graphs requested
		 * move backwards from lowest graph id until the
		 * array fully populated, or we run out of graphs.
		 */
		if (sizeof($graphs) < $graphpp) {
			$sql_where = "";
			if (strlen($filter)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " gtg.title_cache LIKE '%$filter%'";

			$start = 0;
			$done  = false;

			while (!$done) {
				$sql = "SELECT
					gl.id,
					gtg.title_cache
					FROM graph_local AS gl
					INNER JOIN graph_templates_graph AS gtg
					ON (gtg.local_graph_id=gl.id)
					$sql_where
					ORDER BY gl.id ASC
					LIMIT $start, $graphpp";

				$rows = db_fetch_assoc($sql);

				if (sizeof($rows)) {
				foreach ($rows as $row) {
					if (is_graph_allowed($row["id"])) {
						if (!$curr_found) {
							$graph_id   = $row['id'];
							$title      = $row['title_cache'];
							$curr_found = true;
cacti_log("Found (2) current graph id '" . $row['id'] . "'", false);
							$graphs[$i]['graph_id'] = $graph_id;
							$i++;
						}else{
							if (sizeof($graphs) < $graphpp) {
cacti_log("Found (2) graph id '" . $row['id'] . "'", false);
								$graphs[$i]['graph_id'] = $row['id'];
								$i++;
							}else{
cacti_log("Found (2) next graph id '" . $row['id'] . "'", false);
								$next_graph_id = $row['id'];
								$next_found    = true;
		
								break;
							}
						}
					}
				}
				}

				if ($next_graph_id > 0) {
					$done = true;
				}elseif (sizeof($rows) == 0) {
					$done = true;
				}else{
					$start += $graphpp;
				}
			}
		}

		/* When a user hits the 'Prev' button, we have to go backward
		 * Therefore, find the graph_id that would have to be used as 
		 * the starting point if the user were to hit the 'Prev' button.
		 *
		 * Just like the 'Next' button, we need to scan the database until
		 * we reach the $graphpp variable or until we run out of rows.  We
		 * also have to ajust for underflow in this case.
		 */
		$sql_where = "WHERE gl.id<$graph_id";
		if (strlen($filter)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " gtg.title_cache LIKE '%$filter%'";

		$done    = false;
		$start   = 0;
		$pgraphs = array();

		while (!$done) {
			$sql = "SELECT gl.id,
				gtg.title_cache
				FROM graph_local AS gl
				INNER JOIN graph_templates_graph AS gtg
				ON (gtg.local_graph_id=gl.id)
				$sql_where
				ORDER BY id DESC
				LIMIT $start, $graphpp";

			$rows = db_fetch_assoc($sql);

			if (sizeof($rows)) {
			foreach ($rows as $row) {
				if (is_graph_allowed($row['id'])) {
					if (sizeof($pgraphs) < ($graphpp-1)) {
cacti_log("Found (1) prev graph id '" . $row['id'] . "'", false);
						$pgraphs[] = $row['id'];
					}else{
cacti_log("Found (1) prev prev graph id '" . $row['id'] . "'", false);
						$prev_graph_id = $row['id'];
						break;
					}
				}
			}
			}

			if ($prev_graph_id > 0) {
				$done = true;
			}elseif (sizeof($rows) == 0) {
				$done = true;
			}else{
				$start += $graphpp;
			}
		}

		/* Now handle the underflow case, when we have not
		 * completed building the $pgraphs array to the
		 * correct size.
		 */
		if ($prev_graph_id == 0) {
			$sql_where = "";
			if (strlen($filter)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " gtg.title_cache LIKE '%$filter%'";

			$start = 0;
			$done  = false;

			while (!$done) {
				$sql = "SELECT gl.id,
					gtg.title_cache
					FROM graph_local AS gl
					INNER JOIN graph_templates_graph AS gtg
					ON (gtg.local_graph_id=gl.id)
					$sql_where
					ORDER BY id DESC
					LIMIT $start, $graphpp";

				$rows = db_fetch_assoc($sql);

				if (sizeof($rows)) {
				foreach ($rows as $row) {
					if (is_graph_allowed($row['id'])) {
						if (sizeof($pgraphs) < ($graphpp-1)) {
cacti_log("Found (2) prev graph id '" . $row['id'] . "'", false);
							$pgraphs[] = $row['id'];
						}else{
cacti_log("Found (2) prev prev graph id '" . $row['id'] . "'", false);
							$prev_graph_id = $row['id'];
							break;
						}
					}
				}
				}

				if ($prev_graph_id > 0) {
					$done = true;
				}elseif (sizeof($rows) == 0) {
					$done = true;
				}else{
					$start += $graphpp;
				}
			}
		}

		break;
	case "1":
		$graphs   = explode(",", read_config_option("cycle_custom_graphs_list"));
		$graph_id = $id;
		$ngraphs  = array();

		if ($graph_id == -1) {
			if (isset($graphs[$graphpp+1])) {
				$next_graph_id = $graphs[$graphpp+1];
			}else{
				$total = sizeof($graphs);
				$next_graph_id = $graphs[$graphpp-$total];
			}

			$prev_graph_id = $graphs[count($graphs)-1];
			$graph_id      = $graphs[0];
		} else {
			$where = array_search($id, $graphs);
			if (count($graphs)-1 > $where) {
				$next_graph_id = $graphs[$where+1];
			}

			if (0<$where) {
				$prev_graph_id = $graphs[$where-1];
			}
		}

		if (empty($next_graph_id)) {
			$next_graph_id = $graphs[0];
		}

		if (empty($prev_graph_id)) {
			$prev_graph_id = $graphs[count($graphs)-1];
		}

		$sql = "SELECT
			graph_local.id,
			graph_templates_graph.title_cache
			FROM graph_local
			INNER JOIN graph_templates_graph
			ON graph_local.id=graph_templates_graph.local_graph_id 
			WHERE graph_local.id=$graph_id";

		$row      = db_fetch_row($sql);
		$graph_id = $row['id'];
		$title    = $row['title_cache'];

		break;
	case "2":
		$graph_data = get_tree_graphs($graph_tree, $graphpp, $filter);
		$graph_id   = $id;

		$graphs = array();
		$count  = 0;

		if (sizeof($graph_data)>0) {
			foreach($graph_data as $data) {
				foreach($data as $subdata) {
					if (is_array($subdata)) {
						foreach($subdata as $key=>$graph) {
								$graphs[$count] = $graph['graph_id'];
								$count = $count + 1;
						}
					}
				}
			}

			if ($graph_id == -1) {
				if (isset($graphs[1])) {
					$next_graph_id = $graphs[1];
				}else{
					$next_graph_id = $graphs[0];
				}

				$prev_graph_id = $graphs[count($graphs)-1];
				$graph_id      = $graphs[0];
			} else {
				$where = array_search($_GET['id'], $graphs);

				if (count($graphs)-1 > $where) {
					$next_graph_id = $graphs[$where+1];
				}

				if (0 < $where) {
					$prev_graph_id = $graphs[$where-1];
				}
			}

			if (empty($next_graph_id)) {
				$next_graph_id = $graphs[0];
			}

			if (empty($prev_graph_id)) {
				$prev_graph_id = $graphs[count($graphs)-1];
			}
		}

		/*
		$graphs        = get_tree_graphs($graph_tree, $graphpp, $filter);
		$cur_leaf_id   = $id;
		$prev_graph_id = null;
		$next_graph_id = null;
		$leaf_found    = false;
		$first_leaf    = null;
		$leaf_name     = "";

		if (sizeof($graphs)) {
			foreach ($graphs as $leaf_id => $leaf_data) {
				if (is_null($first_leaf)) {
					$first_leaf = $leaf_id;
				}

				if ($cur_leaf_id < 1) {
					$cur_leaf_id   = $leaf_id;
					$prev_graph_id = $leaf_id;
					$leaf_found    = true;
				} elseif ($cur_leaf_id == $leaf_id) {
					$leaf_found    = true;
				} elseif ($leaf_found == true) {
					$next_graph_id = $leaf_id;
					break;
				} else {
					$prev_graph_id = $leaf_id;
					continue;
				}

				if (isset($leaf_data['graph_data'])) {
					$graph_id = $leaf_data['graph_data'];
					$title    = "Tree View";
				}
			}
		}

		if (is_null($next_graph_id)) {
			$next_graph_id = $first_leaf;
		}
		*/

		break;
	}
}

function get_tree_graphs($tree_id, $graphpp, $filter) {
	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		/* at this point this user is good to go... so get some setting about this
		user and put them into variables to save excess SQL in the future */
		$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);
	
		/* find out if we are logged in as a 'guest user' or not */
		if (db_fetch_cell("SELECT id FROM user_auth WHERE username='" . read_config_option("guest_user") . "'") == $_SESSION["sess_user_id"]) {
			$using_guest_account = true;
		}
	
		/* find out if we should show the "console" tab or not, based on this user's permissions */
		if (sizeof(db_fetch_assoc("SELECT realm_id FROM user_auth_realm WHERE realm_id=8 AND user_id=" . $_SESSION["sess_user_id"])) == 0) {
			$show_console_tab = false;
		}
	}
	
	/* check permissions */
	if (read_config_option("auth_method") != 0) {
		/* get policy information for the sql where clause */
		$sql_where = "WHERE " . get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
			
		$sql_join = "LEFT JOIN graph_templates_graph ON (graph_templates_graph.local_graph_id=graph_tree_items.local_graph_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id
			AND user_auth_perms.type=1 AND user_auth_perms.user_id=".$_SESSION["sess_user_id"].")
			OR (graph_tree_items.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=".$_SESSION["sess_user_id"].")
			OR (graph_templates_graph.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=".$_SESSION["sess_user_id"]."))";
			
	}else{
		$sql_where = "";
		$sql_join = "LEFT JOIN graph_templates_graph ON (graph_templates_graph.local_graph_id=graph_tree_items.local_graph_id)";
	}

	if (strlen($filter)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " graph_templates_graph.title_cache LIKE '%$filter%'";
	
	$sql = "SELECT DISTINCT graph_tree_items.local_graph_id, graph_tree_items.host_id
		FROM graph_tree_items
		$sql_join
		$sql_where 
		" . (empty($sql_where) ? "WHERE" : "AND") . " graph_tree_items.graph_tree_id=$tree_id
		ORDER BY graph_tree_items.order_key";

	$rows     = db_fetch_assoc($sql);
	$outArray = array();

	if (count($rows)) {
		$title_id = null;
		foreach ($rows as $row) {
			if (((!empty($row['title'])) && ($row['host_id'] == 0)) && ($row['local_graph_id'] == 0)) {
				//$outArray[$row['id']]['title'] = $row['title'];
				//$title_id = $row['id'];
			} elseif ((empty($row['title'])) && ($row['local_graph_id'] > 0 )) {
				$outArray[$title_id]['graph_data'][] = array( 'graph_id' => $row['local_graph_id'], 'graph_title' => get_graph_title($row['local_graph_id']));
			} elseif ($row['host_id'] > 0) {
				/* Host Tree Graph Search */
				
				/* Check Permission */
				if (read_config_option("auth_method") != 0) {
					/* get policy information for the sql where clause */
					$sql_where = "WHERE " . get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
						
					$sql_join = "LEFT JOIN graph_templates_graph ON (graph_templates_graph.local_graph_id=graph_local.id)
						LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id
						AND user_auth_perms.type=1 AND user_auth_perms.user_id=".$_SESSION["sess_user_id"].")
						OR (graph_local.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=".$_SESSION["sess_user_id"].")
						OR (graph_local.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=".$_SESSION["sess_user_id"]."))";
						
				}else{
					$sql_where = "";
					$sql_join = "LEFT JOIN graph_templates_graph ON (graph_templates_graph.local_graph_id=graph_tree_items.local_graph_id)";
				}
				
				if (strlen($filter)) $sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " graph_templates_graph.title_cache LIKE '%$filter%'";

				$sql = "SELECT DISTINCT graph_local.id
					FROM graph_local
					$sql_join
					$sql_where 
					" . (empty($sql_where) ? "WHERE" : "AND") . " graph_local.host_id=".$row['host_id']."
					ORDER BY graph_templates_graph.title";
								
				$rows2     = db_fetch_assoc($sql);
				if (count($rows2)) {
					$title_id = null;
					foreach ($rows2 as $row2) {
						if ($row2['id'] > 0) {
							$outArray[$title_id]['graph_data'][] = array( 'graph_id' => $row2['id'], 'graph_title' => get_graph_title($row2['id']));
						}
					}
				}
			}
		}
	}
	
	$resultArray = super_unique($outArray);
	return($resultArray);
}

function super_unique($array) {
	$result = array_map("unserialize", array_unique(array_map("serialize", $array)));

	foreach ($result as $key => $value) {
		if (is_array($value)) {
			$result[$key] = super_unique($value);
		}
	}

	return $result;
}
