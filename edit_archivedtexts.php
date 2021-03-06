<?php

/**************************************************************
"Learning with Texts" (LWT) is released into the Public Domain.
This applies worldwide.
In case this is not legally possible, any entity is granted the
right to use this work for any purpose, without any conditions, 
unless such conditions are required by law.

Developed by J.P. in 2011, 2012.
***************************************************************/

/**************************************************************
Call: edit_archivedtexts.php?....
      ... markaction=[opcode] ... do actions on marked texts
      ... del=[textid] ... do delete
      ... unarch=[textid] ... do unarchive
      ... op=Change ... do update
      ... chg=[textid] ... display edit screen 
      ... filterlang=[langid] ... language filter 
      ... sort=[sortcode] ... sort 
      ... page=[pageno] ... page  
      ... query=[titlefilter] ... title filter   
Manage archived texts
***************************************************************/

include "connect.inc.php";
include "settings.inc.php";
include "utilities.inc.php";

$currentlang = validateLang(processDBParam("filterlang",'currentlanguage','',0));
$currentsort = processDBParam("sort",'currentarchivesort','1',1);

$currentpage = processSessParam("page","currentarchivepage",'1',1);
$currentquery = processSessParam("query","currentarchivequery",'',0);
$currenttag1 = validateArchTextTag(processSessParam("tag1","currentarchivetexttag1",'',0),$currentlang);
$currenttag2 = validateArchTextTag(processSessParam("tag2","currentarchivetexttag2",'',0),$currentlang);
$currenttag12 = processSessParam("tag12","currentarchivetexttag12",'',0);

$wh_lang = ($currentlang != '') ? (' and AtLgID=' . $currentlang) : '';
$wh_query = convert_string_to_sqlsyntax(str_replace("*","%",mb_strtolower($currentquery, 'UTF-8')));
$wh_query = ($currentquery != '') ? (' and AtTitle like ' . $wh_query) : '';

if ($currenttag1 == '' && $currenttag2 == '')
	$wh_tag = '';
else {
	if ($currenttag1 != '') {
		if ($currenttag1 == -1)
			$wh_tag1 = "group_concat(AgT2ID) IS NULL";
		else
			$wh_tag1 = "concat('/',group_concat(AgT2ID separator '/'),'/') like '%/" . $currenttag1 . "/%'";
	} 
	if ($currenttag2 != '') {
		if ($currenttag2 == -1)
			$wh_tag2 = "group_concat(AgT2ID) IS NULL";
		else
			$wh_tag2 = "concat('/',group_concat(AgT2ID separator '/'),'/') like '%/" . $currenttag2 . "/%'";
	} 
	if ($currenttag1 != '' && $currenttag2 == '')	
		$wh_tag = " having (" . $wh_tag1 . ') ';
	elseif ($currenttag2 != '' && $currenttag1 == '')	
		$wh_tag = " having (" . $wh_tag2 . ') ';
	else
		$wh_tag = " having ((" . $wh_tag1 . ($currenttag12 ? ') AND (' : ') OR (') . $wh_tag2 . ')) ';
}

$no_pagestart = 
	(getreq('markaction') == 'deltag');
if (! $no_pagestart) {
	pagestart('My ' . getLanguage($currentlang) . ' Text Archive',true);
}

$message = '';

// MARK ACTIONS

if (isset($_REQUEST['markaction'])) {
	$markaction = $_REQUEST['markaction'];
	$actiondata = stripTheSlashesIfNeeded(getreq('data'));
	$message = "Multiple Actions: 0";
	if (isset($_REQUEST['marked'])) {
		if (is_array($_REQUEST['marked'])) {
			$l = count($_REQUEST['marked']);
			if ($l > 0 ) {
				$list = "(" . $_REQUEST['marked'][0];
				for ($i=1; $i<$l; $i++) $list .= "," . $_REQUEST['marked'][$i];
				$list .= ")";
				
				if ($markaction == 'del') {
					$message = runsql('delete from archivedtexts where AtID in ' . $list, "Archived Texts deleted");
					adjust_autoincr('archivedtexts','AtID');
					runsql("DELETE archtexttags FROM (archtexttags LEFT JOIN archivedtexts on AgAtID = AtID) WHERE AtID IS NULL",'');
				} 

				elseif ($markaction == 'addtag' ) {
					$message = addarchtexttaglist($actiondata,$list);
				}
				
				elseif ($markaction == 'deltag' ) {
					$message = removearchtexttaglist($actiondata,$list);
					header("Location: edit_archivedtexts.php");
					exit();
				}
				
				elseif ($markaction == 'unarch') {
					$count = 0;
					$sql = "select AtID, AtLgID from archivedtexts where AtID in " . $list;
					$res = mysql_query($sql);		
					if ($res == FALSE) die("Invalid Query: $sql");
					while ($record = mysql_fetch_assoc($res)) {
						$ida = $record['AtID'];
						$mess = 0 + runsql('insert into texts (TxLgID, TxTitle, TxText, TxAudioURI) select AtLgID, AtTitle, AtText, AtAudioURI from archivedtexts where AtID = ' . $ida, "");
						$count += $mess;
						$id = get_last_key();
						runsql('insert into texttags (TtTxID, TtT2ID) select ' . $id . ', AgT2ID from archtexttags where AgAtID = ' . $ida, "");	
						splitText(
							get_first_value(
							'select TxText as value from texts where TxID = ' . $id), 
							$record['AtLgID'], 
							$id );	
						runsql('delete from archivedtexts where AtID = ' . $ida, "");
					}
					mysql_free_result($res);
					adjust_autoincr('archivedtexts','AtID');
					runsql("DELETE archtexttags FROM (archtexttags LEFT JOIN archivedtexts on AgAtID = AtID) WHERE AtID IS NULL",'');
					$message = 'Unarchived Text(s): ' . $count;
				} 
												
			}
		}
	}
}

// DEL

if (isset($_REQUEST['del'])) {
	$message = runsql('delete from archivedtexts where AtID = ' . $_REQUEST['del'], 
		"Archived Texts deleted");
	adjust_autoincr('archivedtexts','AtID');
	runsql("DELETE archtexttags FROM (archtexttags LEFT JOIN archivedtexts on AgAtID = AtID) WHERE AtID IS NULL",'');
}

// UNARCH

elseif (isset($_REQUEST['unarch'])) {
	$message2 = runsql('insert into texts (TxLgID, TxTitle, TxText, TxAudioURI) select AtLgID, AtTitle, AtText, AtAudioURI from archivedtexts where AtID = ' . $_REQUEST['unarch'], "Texts added");
	$id = get_last_key();
	runsql('insert into texttags (TtTxID, TtT2ID) select ' . $id . ', AgT2ID from archtexttags where AgAtID = ' . $_REQUEST['unarch'], "");	
	splitText(
		get_first_value(
		'select TxText as value from texts where TxID = ' . $id), 
		get_first_value(
		'select TxLgID as value from texts where TxID = ' . $id), 
		$id );	
	$message1 = runsql('delete from archivedtexts where AtID = ' . $_REQUEST['unarch'], "Archived Texts deleted");
	$message = $message1 . " / " . $message2 . " / Sentences added: " . get_first_value('select count(*) as value from sentences where SeTxID = ' . $id) . " / Text items added: " . get_first_value('select count(*) as value from textitems where TiTxID = ' . $id);
	adjust_autoincr('archivedtexts','AtID');
	runsql("DELETE archtexttags FROM (archtexttags LEFT JOIN archivedtexts on AgAtID = AtID) WHERE AtID IS NULL",'');
}

// UPD

elseif (isset($_REQUEST['op'])) {
	
	// UPDATE
	
	if ($_REQUEST['op'] == 'Change') {
		$message = runsql('update archivedtexts set ' .
		'AtLgID = ' . $_REQUEST["AtLgID"] . ', ' .
		'AtTitle = ' . convert_string_to_sqlsyntax($_REQUEST["AtTitle"]) . ', ' .
		'AtText = ' . convert_string_to_sqlsyntax($_REQUEST["AtText"]) . ', ' .
		'AtAudioURI = ' . convert_string_to_sqlsyntax($_REQUEST["AtAudioURI"]) . ' ' .
		'where AtID = ' . $_REQUEST["AtID"], "Updated");
		$id = $_REQUEST["AtID"];
	}
	saveArchivedTextTags($id);
	
}

// CHG

if (isset($_REQUEST['chg'])) {
	
	$sql = 'select AtLgID, AtTitle, AtText, AtAudioURI from archivedtexts where AtID = ' . $_REQUEST['chg'];
	$res = mysql_query($sql);		
	if ($res == FALSE) die("Invalid Query: $sql");
	if ($record = mysql_fetch_assoc($res)) {

		?>
	
		<h4>Edit Archived Text</h4>
		<form class="validate" action="<?php echo $_SERVER['PHP_SELF']; ?>#rec<?php echo $_REQUEST['chg']; ?>" method="post">
		<input type="hidden" name="AtID" value="<?php echo $_REQUEST['chg']; ?>" />
		<table class="tab3" cellspacing="0" cellpadding="5">
		<tr>
		<td class="td1 right">Language:</td>
		<td class="td1">
		<select name="AtLgID" class="notempty setfocus">
		<?php
		echo get_languages_selectoptions($record['AtLgID'],"[Choose...]");
		?>
		</select> <img src="icn/status-busy.png" title="Field must not be empty" alt="Field must not be empty" />
		</td>
		</tr>
		<tr>
		<td class="td1 right">Title:</td>
		<td class="td1"><input type="text" class="notempty" name="AtTitle" value="<?php echo tohtml($record['AtTitle']); ?>" maxlength="200" size="60" /> <img src="icn/status-busy.png" title="Field must not be empty" alt="Field must not be empty" /></td>
		</tr>
		<tr>
		<td class="td1 right">Text:</td>
		<td class="td1">
		<textarea name="AtText" class="notempty checkbytes" data_maxlength="65000" data_info="Text" cols="60" rows="20"><?php echo tohtml($record['AtText']); ?></textarea> <img src="icn/status-busy.png" title="Field must not be empty" alt="Field must not be empty" />
		</td>
		</tr>
		<tr>
		<td class="td1 right">Tags:</td>
		<td class="td1">
		<?php echo getArchivedTextTags($_REQUEST['chg']); ?>
		</td>
		</tr>
		<tr>
		<td class="td1 right">Audio-URI:</td>
		<td class="td1"><input type="text" name="AtAudioURI" value="<?php echo tohtml($record['AtAudioURI']); ?>" maxlength="200" size="60" />
		<span id="mediaselect"><?php echo selectmediapath('TxAudioURI'); ?></span>		
		</td>
		</tr>
		<tr>
		<td class="td1 right" colspan="2">
		<input type="button" value="Cancel" onclick="location.href='edit_archivedtexts.php#rec<?php echo $_REQUEST['chg']; ?>';" /> 
		<input type="submit" name="op" value="Change" /></td>
		</tr>
		</table>
		</form>
		
		<?php

	}
	mysql_free_result($res);

}

// DISPLAY

else {

	echo error_message_with_hide($message,0);

	$sql = 	'select count(*) as value from (select AtID from (archivedtexts left JOIN archtexttags ON AtID = AgAtID) where (1=1) ' . $wh_lang . $wh_query . ' group by AtID ' . $wh_tag . ') as dummy';
	$recno = get_first_value($sql);
	if ($debug) echo $sql . ' ===&gt; ' . $recno;

	$maxperpage = getSettingWithDefault('set-archivedtexts-per-page');

	$pages = $recno == 0 ? 0 : (intval(($recno-1) / $maxperpage) + 1);
	
	if ($currentpage < 1) $currentpage = 1;
	if ($currentpage > $pages) $currentpage = $pages;
	$limit = 'LIMIT ' . (($currentpage-1) * $maxperpage) . ',' . $maxperpage;

	$sorts = array('AtTitle','AtID desc');
	$lsorts = count($sorts);
	if ($currentsort < 1) $currentsort = 1;
	if ($currentsort > $lsorts) $currentsort = $lsorts;
	
?>

<form name="form1" action="#" onsubmit="document.form1.querybutton.click(); return false;">
<table class="tab1" cellspacing="0" cellpadding="5">
<tr>
<th class="th1" colspan="4">Filter <img src="icn/funnel.png" title="Filter" alt="Filter" />&nbsp;
<input type="button" value="Reset All" onclick="resetAll('edit_archivedtexts.php');" /></th>
</tr>
<tr>
<td class="td1 center" colspan="2">
Language:
<select name="filterlang" onchange="{setLang(document.form1.filterlang,'edit_archivedtexts.php');}"><?php	echo get_languages_selectoptions($currentlang,'[Filter off]'); ?></select>
</td>
<td class="td1 center" colspan="2">
Text Title (Wildc.=*):
<input type="text" name="query" value="<?php echo tohtml($currentquery); ?>" maxlength="50" size="15" />&nbsp;
<input type="button" name="querybutton" value="Filter" onclick="{val=document.form1.query.value; location.href='edit_archivedtexts.php?page=1&amp;query=' + val;}" />&nbsp;
<input type="button" value="Clear" onclick="{location.href='edit_archivedtexts.php?page=1&amp;query=';}" />
</td>
</tr>
<tr>
<td class="td1 center" colspan="2" nowrap="nowrap">
Tag #1:
<select name="tag1" onchange="{val=document.form1.tag1.options[document.form1.tag1.selectedIndex].value; location.href='edit_archivedtexts.php?page=1&amp;tag1=' + val;}"><?php echo get_archivedtexttag_selectoptions($currenttag1,$currentlang); ?></select>
</td>
<td class="td1 center" nowrap="nowrap">
Tag #1 .. <select name="tag12" onchange="{val=document.form1.tag12.options[document.form1.tag12.selectedIndex].value; location.href='edit_archivedtexts.php?page=1&amp;tag12=' + val;}"><?php echo get_andor_selectoptions($currenttag12); ?></select> .. Tag #2
</td>
<td class="td1 center" nowrap="nowrap">
Tag #2:
<select name="tag2" onchange="{val=document.form1.tag2.options[document.form1.tag2.selectedIndex].value; location.href='edit_archivedtexts.php?page=1&amp;tag2=' + val;}"><?php echo get_archivedtexttag_selectoptions($currenttag2,$currentlang); ?></select>
</td>
</tr>
<?php if($recno > 0) { ?>
<tr>
<th class="th1" nowrap="nowrap">
<?php echo $recno; ?> Text<?php echo ($recno==1?'':'s'); ?>
</th>
<th class="th1" colspan="2" nowrap="nowrap">
<?php makePager ($currentpage, $pages, 'edit_archivedtexts.php', 'form1'); ?>
</th>
<th class="th1" nowrap="nowrap">
Sort Order:
<select name="sort" onchange="{val=document.form1.sort.options[document.form1.sort.selectedIndex].value; location.href='edit_archivedtexts.php?page=1&amp;sort=' + val;}"><?php echo get_textssort_selectoptions($currentsort); ?></select>
</th></tr>
<?php } ?>
</table>
</form>

<?php
if ($recno==0) {
?>
<p>No archived texts found.</p>
<?php
} else {
?>
<form name="form2" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="data" value="" />
<table class="tab1" cellspacing="0" cellpadding="5">
<tr><th class="th1" colspan="2">Multi Actions <img src="icn/lightning.png" title="Multi Actions" alt="Multi Actions" /></th></tr>
<tr><td class="td1 center">
<input type="button" value="Mark All" onclick="selectToggle(true,'form2');" />
<input type="button" value="Mark None" onclick="selectToggle(false,'form2');" />
</td><td class="td1 center">
Marked Texts:&nbsp; 
<select name="markaction" id="markaction" disabled="disabled" onchange="multiActionGo(document.form2, document.form2.markaction);"><?php echo get_multiplearchivedtextactions_selectoptions(); ?></select>
</td></tr></table>

<table class="sortable tab1" cellspacing="0" cellpadding="5">
<tr>
<th class="th1 sorttable_nosort">Mark</th>
<th class="th1 sorttable_nosort">Actions</th>
<?php if ($currentlang == '') echo '<th class="th1 clickable">Lang.</th>'; ?>
<th class="th1 clickable">Title [Tags] / Audio?</th>
</tr>

<?php

$sql = 'select AtID, AtTitle, LgName, AtAudioURI, ifnull(concat(\'[\',group_concat(distinct T2Text order by T2Text separator \', \'),\']\'),\'\') as taglist from ((archivedtexts left JOIN archtexttags ON AtID = AgAtID) left join tags2 on T2ID = AgT2ID), languages where LgID=AtLgID ' . $wh_lang . $wh_query . ' group by AtID ' . $wh_tag . ' order by ' . $sorts[$currentsort-1] . ' ' . $limit;

if ($debug) echo $sql;

$res = mysql_query($sql);		
if ($res == FALSE) die("Invalid Query: $sql");
while ($record = mysql_fetch_assoc($res)) {
	echo '<tr>';
	echo '<td class="td1 center"><a name="rec' . $record['AtID'] . '"><input name="marked[]" class="markcheck"  type="checkbox" value="' . $record['AtID'] . '" ' . checkTest($record['AtID'], 'marked') . ' /></a></td>';
	echo '<td nowrap="nowrap" class="td1 center">&nbsp;<a href="' . $_SERVER['PHP_SELF'] . '?unarch=' . $record['AtID'] . '"><img src="icn/inbox-upload.png" title="Unarchive" alt="Unarchive" /></a>&nbsp; <a href="' . $_SERVER['PHP_SELF'] . '?chg=' . $record['AtID'] . '"><img src="icn/document--pencil.png" title="Edit" alt="Edit" /></a>&nbsp; <span class="click" onclick="if (confirm (\'Are you sure?\')) location.href=\'' . $_SERVER['PHP_SELF'] . '?del=' . $record['AtID'] . '\';"><img src="icn/minus-button.png" title="Delete" alt="Delete" /></span>&nbsp;</td>';
	if ($currentlang == '') echo '<td class="td1 center">' . tohtml($record['LgName']) . '</td>';
	echo '<td class="td1 center">' . tohtml($record['AtTitle']) . ' <span class="smallgray2">' . tohtml($record['taglist']) . '</span> &nbsp;'  . (isset($record['AtAudioURI']) ? '<img src="icn/speaker-volume.png" title="With Audio" alt="With Audio" />' : '') . '</td>';
	echo '</tr>';
}
mysql_free_result($res);

?>

</table>
</form>

<?php if( $pages > 1) { ?>
<table class="tab1" cellspacing="0" cellpadding="5">
<tr>
<th class="th1" nowrap="nowrap">
<?php echo $recno; ?> Text<?php echo ($recno==1?'':'s'); ?>
</th><th class="th1" nowrap="nowrap">
<?php makePager ($currentpage, $pages, 'edit_archivedtexts.php', 'form1'); ?>
</th></tr></table>
<?php } ?>

<?php

}

?>

<p><input type="button" value="Active Texts" onclick="location.href='edit_texts.php?query=&amp;page=1';" /></p>

<?php

}

pageend();

?>