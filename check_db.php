<?php
defined('PHPWG_ROOT_PATH') or trigger_error('Hacking attempt!', E_USER_ERROR);

load_language('plugin.lang', RVDI_PATH);

function check_table_ref($t, $f, $reft, $reff)
{
  $query = '
DESCRIBE '.$reft.' '.$reff;
  $row = pwg_db_fetch_assoc( pwg_query($query) );
  $ref_nullable = ( $row['Null'] != '');

  $query = '
SELECT ref.'.$reff.'
  FROM '.$reft.' ref LEFT JOIN '.$t.' main ON ref.'.$reff.' = main.'.$f.'
  WHERE main.'.$f.' IS NULL';
  if ($ref_nullable)
    $query.=' AND ref.'.$reff.' IS NOT NULL';
  $query.='
  GROUP BY ref.'.$reff;

	return query2array($query,null,$reff);
}

$default_checked = count($_POST)==0 ? 'checked="checked"' : '';

// Standard reference tests ---------------------------------------------------
$reference_tests = array();

$reference_tests[IMAGES_TABLE] = array(
    array(CADDIE_TABLE,           'element_id'),
    array(CATEGORIES_TABLE,       'representative_picture_id'),
    array(COMMENTS_TABLE,         'image_id'),
    array(FAVORITES_TABLE,        'image_id'),
    array(IMAGE_CATEGORY_TABLE,   'image_id'),
    array(IMAGE_TAG_TABLE,        'image_id'),
    array(RATE_TABLE,             'element_id'),
  );

$reference_tests[CATEGORIES_TABLE] = array(
    array(CATEGORIES_TABLE,       'id_uppercat'),
    array(GROUP_ACCESS_TABLE,     'cat_id'),
    array(IMAGE_CATEGORY_TABLE,   'category_id'),
    array(IMAGES_TABLE,           'storage_category_id'),
    array(OLD_PERMALINKS_TABLE,   'cat_id'),
    array(USER_ACCESS_TABLE,      'cat_id'),
    array(USER_CACHE_CATEGORIES_TABLE, 'cat_id'),
  );

$reference_tests[TAGS_TABLE] = array(
    array(IMAGE_TAG_TABLE,   'tag_id'),
  );

$reference_tests[GROUPS_TABLE] = array(
    array(GROUP_ACCESS_TABLE,     'group_id'),
    array(USER_GROUP_TABLE,       'group_id'),
  );

foreach ($reference_tests as $table=>$ref_test)
{
	$field_name = 'id';
	$tpl_var =
		array(
			'ID' => 'test-'.$table,
			'LABEL' => '#'.$table.'.'.$field_name,
			'CHECKED' => isset($_POST['test-'.$table]) ? 'checked="checked"' : $default_checked,
			'COUNT' => count($ref_test),
		);

	if ( isset($_POST['test-'.$table]) )
	{
		$failed = 0;
		foreach ($ref_test as $test)
		{
			$err = check_table_ref($table, $field_name, $test[0], $test[1] );
			if (count($err))
			{
				$failed++;
				$tpl_var['errors'][]= count($err).' error references; #'.$test[0].'.'.$test[1].' referring to #'.$table.'.'.$field_name;
				$tpl_var['errors'][]= 'Offending '.$test[1].' '.implode(',',$err);
			}
		}
		$tpl_var['result'] = $failed;
	}
	$template->append('reference_tests', $tpl_var);
}

// Permalinks test ------------------------------------------------------------
$tpl_var = array(
		'ID' => 'permalinks',
		'LABEL' => l10n('Pemalinks'),
		'CHECKED' => isset($_POST['permalinks']) ? 'checked="checked"' : $default_checked,
		'COUNT' => 1,
	);
if (isset($_POST['permalinks']))
{
	$query = '
SELECT c.permalink, c.id, op.cat_id
  FROM '.CATEGORIES_TABLE.' c INNER JOIN '.OLD_PERMALINKS_TABLE.' op ON c.permalink=op.permalink';
	$result = pwg_query($query);
	$tpl_var['result'] = pwg_db_num_rows($result);
	while ($row=pwg_db_fetch_assoc($result))
	{
		$tpl_var['errors'][] = $row['permalink'].' matches categories '.$row['id'].' and '.$row['cat_id'];
	}
}
$template->append('reference_tests', $tpl_var);

// Status/visible tests
$tpl_var = array(
		'ID' => 'status',
		'LABEL' => 'Album status/visibility',
		'CHECKED' => isset($_POST['status']) ? 'checked="checked"' : $default_checked,
		'COUNT' => 1,
	);
if (isset($_POST['status']))
{
	$failed = 0;
	$query = '
SELECT id,name,id_uppercat,status,visible
  FROM '.CATEGORIES_TABLE;
	$cats = query2array($query,'id');
	foreach($cats as $cat)
	{
		if (!isset($cat['id_uppercat'])) continue;
		if ('public'==$cat['status'] && 'public'!=$cats[$cat['id_uppercat']]['status'])
		{
			$failed++;
			$tpl_var['errors'][] = 'Public album '.$cat['id'].' '.$cat['name'].' parent '.$cat['id_uppercat'].' is '.$cats[$cat['id_uppercat']]['status'];
		}
		if ('true'==$cat['visible'] && 'true'!=$cats[$cat['id_uppercat']]['visible'])
		{
			$failed++;
			$tpl_var['errors'][] = 'Visible album '.$cat['id'].' '.$cat['name'].' parent '.$cat['id_uppercat'].' is '.$cats[$cat['id_uppercat']]['visible'];
		}
	}
        $tpl_var['result'] = $failed;
}
$template->append('reference_tests', $tpl_var);

// Permissions
$tpl_var = array(
		'ID' => 'permissions',
		'LABEL' => 'Permissions',
		'CHECKED' => isset($_POST['permissions']) ? 'checked="checked"' : $default_checked,
		'COUNT' => 1,
	);
if (isset($_POST['permissions']))
{
	$query = '
SELECT id,name,id_uppercat
	FROM '.CATEGORIES_TABLE.'
	WHERE status="private"';
	$cats = query2array($query,'id');

	$groups = array_fill_keys( array_keys($cats), array());
	$query = 'SELECT cat_id,group_id FROM '.GROUP_ACCESS_TABLE;
	$result = pwg_query($query);
	while ($row=pwg_db_fetch_assoc($result))
		$groups[$row['cat_id']][] = $row['group_id'];

	$users = array_fill_keys( array_keys($cats), array());
	$query = 'SELECT cat_id,user_id FROM '.USER_ACCESS_TABLE;
	$result = pwg_query($query);
	while ($row=pwg_db_fetch_assoc($result))
		$users[$row['cat_id']][] = $row['user_id'];

	$failed = 0;
	foreach($cats as $cat)
	{
		if (!isset($cats[$cat['id_uppercat']])) continue;
		foreach( array('users','groups') as $type)
		{
			$arr = $$type;
			$me = $arr[$cat['id']];
			$dad = $arr[$cat['id_uppercat']];
			$delta = array_diff($me, $dad);
			if (count($delta))
			{
				$tpl_var['errors'][] = 'Album '.$cat['id'].' '.$cat['name'].' too many '.$type.' permissions '.implode(',',$delta);
				$failed++;
			}
		}
	}
	$tpl_var['result'] = $failed;
}
$template->append('reference_tests', $tpl_var);


// #images(id,storage_category_id) vs. #image_category(image_id,category_id) ---
$tpl_var = array(
		'ID' => 'id_storage_category_id',
		'LABEL' => l10n('#images(id,storage_category_id) in #image_category'),
		'CHECKED' => isset($_POST['id_storage_category_id']) ? 'checked="checked"' : $default_checked,
		'COUNT' => 1,
	);
if (isset($_POST['id_storage_category_id']))
{
	$query = '
SELECT i.id, i.storage_category_id, i.path
  FROM '.IMAGES_TABLE.' i LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ic ON ic.image_id=i.id AND ic.category_id=i.storage_category_id
  WHERE (ic.category_id IS NULL OR ic.image_id IS NULL) AND i.storage_category_id IS NOT NULL';
	$result = pwg_query($query);
	$tpl_var['result'] = pwg_db_num_rows($result);
	$i=0;
	while ($row=pwg_db_fetch_assoc($result) and $i<=50 )
	{
		$tpl_var['errors'][] = $row['path'].' missing entry ('.$row['id'].','.$row['storage_category_id']. ') in #'.IMAGE_CATEGORY_TABLE;
		$i++;
	}
}
$template->append('reference_tests', $tpl_var);


$template->set_filename('check', dirname(__FILE__).'/check_db.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'check');

?>
