<?php
/**
 * types.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2010-2012 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../globals.php");
require_once("$srcdir/acl.inc");

use OpenEMR\Core\Header;

// This script can be run either inside the OpenEMR frameset for order catalog
// maintenance, or as a popup window for selecting an item to order.  In the
// popup case the GET variables 'popup' (a boolean) and 'order' (an optional
// item ID to select) will be provided, and maintenance may also be permitted.

$popup = empty($_GET['popup']) ? 0 : 1;
$order = isset($_GET['order']) ? $_GET['order'] + 0 : 0;
$labid = isset($_GET['labid']) ? $_GET['labid'] + 0 : 0;

// If Save was clicked, set the result, close the window and exit.
//
if ($popup && $_POST['form_save']) {
    $form_order = isset($_GET['form_order']) ? $_GET['form_order'] + 0 : 0;
    $ptrow = sqlQuery("SELECT name FROM procedure_type WHERE " .
        "procedure_type_id = '$form_order'");
    $name = addslashes($ptrow['name']);
    ?>
    <script type="text/javascript" src="<?php echo $webroot ?>/interface/main/tabs/js/include_opener.js"></script>
    <script language="JavaScript">
        if (opener.closed || !opener.set_proc_type) {
            alert('<?php xl('The destination form was closed; I cannot act on your selection.', 'e'); ?>');
        }
        else {
            opener.set_proc_type(<?php echo "$form_order, '$name'" ?>);
            <?php
            // This is to generate the "Questions at Order Entry" for the Procedure Order form.
            // GET parms needed for this are: formid, formseq.
            if (isset($_GET['formid'])) {
                require_once("qoe.inc.php");
                $qoe_init_javascript = '';
                echo ' opener.set_proc_html("';
                echo generate_qoe_html($form_order, intval($_GET['formid']), 0, intval($_GET['formseq']));
                echo '", "' . $qoe_init_javascript . '");' . "\n";
            }
            ?>
        }
        window.close(); // comment out for debugging
    </script>
    <?php
    exit();
}

// end Save logic

?>
<html>

<head>
    <?php Header::setupHeader(['opener']); ?>

    <title><?php echo xlt('Order and Result Types'); ?></title>

    <style type="text/css">
        body {
            font-family: sans-serif;
            font-size: 10pt;
            font-weight: normal;
            padding: 5px 3px 5px 3px;
        }

        #con0 table {
            margin: 0;
            padding: 0;
            width: 100%;
        }

        #con0 td {
            padding: 0pt;
            font-family: sans-serif;
            font-size: 10pt;
        }

        .plusminus {
            font-family: monospace;
            font-size: 12pt;
        }

        .haskids {
            color: #0000dd;
            cursor: pointer;
            cursor: hand;
        }

        tr.head {
            font-size: 12pt;
            background-color: #cccccc;
            font-weight: bold;
        }

        tr.evenrow {
            background-color: #ddddff;
        }

        tr.oddrow {
            background-color: #ffffff;
        }

        .col1 {
            width: 35%
        }

        .col2 {
            width: 8%
        }

        .col3 {
            width: 12%
        }

        .col4 {
            width: 35%
        }

        .col5 {
            width: 10%
        }
    </style>

    <?php if ($popup) { ?>
        <script type="text/javascript" src="../../library/topdialog.js"></script>
    <?php } ?>

    <script language="JavaScript">

        <?php if ($popup) {
            require($GLOBALS['srcdir'] . "/restoreSession.php");
} ?>

        <?php
        // Create array of IDs to pre-select, leaf to top.
        echo "preopen = [";
        echo $order > 0 ? $order : 0;
        for ($parentid = $order; $parentid > 0;) {
            $row = sqlQuery("SELECT parent FROM procedure_type WHERE procedure_type_id = '$parentid'");
            $parentid = $row['parent'] + 0;
            echo ", $parentid";
        }

        echo "];\n";
        ?>

        // initiate by loading the top-level nodes
        $(document).ready(function () {
            nextOpen();
        });

        // This is called repeatedly at initialization until all desired nodes
        // have been opened.
        function nextOpen() {
            if (preopen.length) {
                var thisid = preopen.pop();
                if (thisid == 0 || preopen.length > 0) {
                    if (thisid > 0)
                        toggle(thisid);
                    else
                        $.getScript('types_ajax.php?id=' + thisid + '&order=<?php echo $order; ?>' + '&labid=<?php echo $labid; ?>');
                }
                else {
                    recolor();
                }
            }
            else {
                recolor();
            }
        }

        // toggle expansion indicator from + to - or vice versa
        function swapsign(td1, from, to) {
            var s = td1.html();
            var i = s.indexOf('>' + from + '<');
            if (i >= 0) td1.html(s.substring(0, i + 1) + to + s.substring(i + 2));
        }

        // onclick handler to expand or collapse a node
        function toggle(id) {
            var td1 = $('#td' + id);
            if (!td1.hasClass('haskids')) return;
            if (td1.hasClass('isExpanded')) {
                $('#con' + id).remove();
                td1.removeClass('isExpanded');
                swapsign(td1, '-', '+');
                recolor();
            }
            else {
                td1.parent().after('<tr class="outertr"><td colspan="5" id="con' + id + '" style="padding:0">Loading...</td></tr>');
                td1.addClass('isExpanded');
                swapsign(td1, '+', '-');
                $.getScript('types_ajax.php?id=' + id + '&order=<?php echo $order; ?>' + '&labid=<?php echo $labid; ?>');
            }
        }

        // Called by the edit window to refresh a given node's children
        function refreshFamily(id, haskids) {
            if (id) { // id == 0 means top level
                var td1 = $('#td' + id);
                if (td1.hasClass('isExpanded')) {
                    $('#con' + id).remove();
                    td1.removeClass('isExpanded');
                    swapsign(td1, '-', '+');
                }
                if (td1.hasClass('haskids') && !haskids) {
                    td1.removeClass('haskids');
                    // swapsign(td1, '+', '.');
                    swapsign(td1, '+', '|');
                    return;
                }
                if (!td1.hasClass('haskids') && haskids) {
                    td1.addClass('haskids');
                    // swapsign(td1, '.', '+');
                    swapsign(td1, '|', '+');
                }
                if (haskids) {
                    td1.parent().after('<tr class="outertr"><td colspan="5" id="con' + id + '" style="padding:0">Loading...</td></tr>');
                    td1.addClass('isExpanded');
                    swapsign(td1, '+', '-');
                }
            }
            if (haskids)
                $.getScript('types_ajax.php?id=' + id + '&order=<?php echo $order; ?>' + '&labid=<?php echo $labid; ?>');
            else
                recolor();
        }

        // edit a node
        function enode(id) {
            dlgopen('types_edit.php?parent=0&typeid=' + id, '_blank', 600, 500, false, 'Edit Procedure Type');
        }

        // add a node
        function anode(id) {
            dlgopen('types_edit.php?typeid=0&parent=' + id, '_blank', 600, 500, false, 'Add Procedure Type');
        }

        // call this to alternate row colors when anything changes the number of rows
        function recolor() {
            var i = 0;
            $('#con0 tr').each(function (index) {
                // skip any row that contains other rows
                if ($(this).hasClass('outertr')) return;
                this.className = (i++ & 1) ? "evenrow" : "oddrow";
            });
        }

    </script>

</head>

<body class="body_nav">
    <h3 style='margin-top:0'><?php echo xlt('Types of Orders and Results') ?></h3>
<div class="container-fluid">
    <form method='post' name='theform' action='types.php?popup=<?php echo $popup ?>&order=<?php
    echo $order;
    if (isset($_GET['formid'])) {
        echo '&formid=' . attr($_GET['formid']);
    }

    if (isset($_GET['formseq'])) {
        echo '&formseq=' . attr($_GET['formseq']);
    }
    ?>'>

        <table width='100%' cellspacing='0' cellpadding='0' border='0'>
            <tr class='head'>
                <th class='col1' align='left'>&nbsp;&nbsp;<?php xl('Name', 'e') ?></th>
                <th class='col2' align='left'><?php xl('Order', 'e') ?></th>
                <th class='col3' align='left'><?php xl('Code', 'e') ?></th>
                <th class='col4' align='left'><?php xl('Description', 'e') ?></th>
                <th class='col5' align='left'>&nbsp;</th>
            </tr>
        </table>

        <div id="con0"></div>

        <p>
            <?php if ($popup) { ?>
                <input type='submit' name='form_save' value='<?php xl('Save', 'e'); ?>'/>
                <input type='button' value=<?php xl('Cancel', 'e'); ?> onclick='window.close()'/>
            <?php } ?>
            <input type='button' value='<?php xl('Add Top Level', 'e'); ?>' onclick='anode(0)'/>
        </p>

    </form>
</div>
</body>
</html>

