<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_login();

$do = $_POST['do'] ?? '';
$redirect_to = $_POST['redirect_to'] ?? '/dashboard.php';

function safe_redirect(string $to): void {
    // Only allow local paths, never external URLs
    if (!str_starts_with($to, '/')) { $to = '/dashboard.php'; }
    header('Location: ' . $to);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect($redirect_to);
}

switch ($do) {

    // Update an inventory item's quantity (+/-), with mandatory typed name, and log it.
    case 'update_stock': {
        $item_id    = (int)($_POST['item_id'] ?? 0);
        $direction  = $_POST['direction'] ?? 'add';
        $amount     = (float)($_POST['amount'] ?? 0);
        $staff_name = trim($_POST['staff_name'] ?? '');

        if ($item_id <= 0 || $amount <= 0 || $staff_name === '') {
            flash_set('Please enter your name and a valid amount.', 'error');
            break;
        }

        $change = $direction === 'subtract' ? -$amount : $amount;
        $stmt = $mysqli->prepare('SELECT quantity, name FROM items WHERE id = ?');
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        if ($item) {
            $new_qty = max(0, $item['quantity'] + $change);
            $mysqli->begin_transaction();
            $s1 = $mysqli->prepare('UPDATE items SET quantity = ? WHERE id = ?');
            $s1->bind_param('di', $new_qty, $item_id);
            $s1->execute();
            $s2 = $mysqli->prepare('INSERT INTO stock_logs (item_id, staff_name, change_amount, resulting_quantity) VALUES (?,?,?,?)');
            $s2->bind_param('isdd', $item_id, $staff_name, $change, $new_qty);
            $s2->execute();
            $mysqli->commit();
            flash_set('Saved: ' . $item['name'] . ' is now ' . rtrim(rtrim(number_format($new_qty, 2), '0'), '.') . '.');
        } else {
            flash_set('Item not found.', 'error');
        }
        break;
    }

    // Record-only "Log Purchase" for an inventory item or an extra (non-inventory) buy item.
    case 'log_purchase': {
        $item_id       = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : null;
        $extra_item_id = !empty($_POST['extra_item_id']) ? (int)$_POST['extra_item_id'] : null;
        $item_name     = trim($_POST['item_name'] ?? '');
        $quantity      = $_POST['quantity_needed'] !== '' ? (float)$_POST['quantity_needed'] : null;
        $logged_by     = trim($_POST['logged_by'] ?? '');

        if ($item_name === '' || $logged_by === '') {
            flash_set('Please enter your name to log the purchase.', 'error');
            break;
        }

        $stmt = $mysqli->prepare('INSERT INTO purchase_log (item_id, extra_item_id, item_name, quantity_needed, logged_by) VALUES (?,?,?,?,?)');
        $stmt->bind_param('iisds', $item_id, $extra_item_id, $item_name, $quantity, $logged_by);
        $stmt->execute();

        // Non-inventory extra items are fully resolved once purchased — remove from active buy list.
        if ($extra_item_id) {
            $s = $mysqli->prepare('UPDATE extra_buy_items SET is_bought = 1 WHERE id = ?');
            $s->bind_param('i', $extra_item_id);
            $s->execute();
        }
        flash_set('Purchase logged for ' . $item_name . '.');
        break;
    }

    // Add a brand-new item to the buy list that is NOT tracked in inventory.
    case 'add_extra_item': {
        $name    = trim($_POST['name'] ?? '');
        $qty     = $_POST['quantity_needed'] !== '' ? (float)$_POST['quantity_needed'] : null;
        $unit    = trim($_POST['unit'] ?? 'pcs');
        if ($unit === '__other__') { $unit = trim($_POST['unit_other'] ?? 'pcs'); }
        $location = trim($_POST['buy_location'] ?? '');
        $contact  = trim($_POST['supplier_contact'] ?? '');
        $added_by = current_user_name() ?? '';

        if ($name === '') {
            flash_set('Please enter an item name.', 'error');
            break;
        }
        $stmt = $mysqli->prepare('INSERT INTO extra_buy_items (name, quantity_needed, unit, buy_location, supplier_contact, added_by) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('sdssss', $name, $qty, $unit, $location, $contact, $added_by);
        $stmt->execute();
        flash_set($name . ' added to the buy list.');
        break;
    }

    // Flag an existing inventory item for the buy list (from the "Add item" modal).
    case 'flag_item': {
        $id = (int)($_POST['item_id'] ?? 0);
        $qty = $_POST['quantity_needed'] !== '' ? (float)$_POST['quantity_needed'] : null;
        if ($id > 0) {
            $stmt = $mysqli->prepare('UPDATE items SET flagged_for_purchase = 1, quantity_needed = COALESCE(?, quantity_needed) WHERE id = ?');
            $stmt->bind_param('di', $qty, $id);
            $stmt->execute();
            flash_set('Added to the buy list.');
        }
        break;
    }

    // Remove an inventory item from the buy list (unflag). If it's still low stock it will remain listed.
    case 'unflag_item': {
        $id = (int)($_POST['item_id'] ?? 0);
        $stmt = $mysqli->prepare('UPDATE items SET flagged_for_purchase = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        flash_set('Removed from the buy list.');
        break;
    }

    // Set "quantity needed to buy" for an inventory item or an extra item.
    case 'set_quantity_needed': {
        $item_id       = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : null;
        $extra_item_id = !empty($_POST['extra_item_id']) ? (int)$_POST['extra_item_id'] : null;
        $qty = $_POST['quantity_needed'] !== '' ? (float)$_POST['quantity_needed'] : null;

        if ($item_id) {
            $stmt = $mysqli->prepare('UPDATE items SET quantity_needed = ? WHERE id = ?');
            $stmt->bind_param('di', $qty, $item_id);
            $stmt->execute();
        } elseif ($extra_item_id) {
            $stmt = $mysqli->prepare('UPDATE extra_buy_items SET quantity_needed = ? WHERE id = ?');
            $stmt->bind_param('di', $qty, $extra_item_id);
            $stmt->execute();
        }
        flash_set('Quantity needed updated.');
        break;
    }

    // Remove an extra (non-inventory) buy item entirely.
    case 'delete_extra_item': {
        $id = (int)($_POST['extra_item_id'] ?? 0);
        $stmt = $mysqli->prepare('DELETE FROM extra_buy_items WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        flash_set('Removed from buy list.');
        break;
    }
}

safe_redirect($redirect_to);
