<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/groups_schema.php';

$logoDir = __DIR__ . '/uploads/groups/logos/';
$bgDir   = __DIR__ . '/uploads/groups/bg/';
$imageExt = ['jpg','jpeg','png','gif','webp'];
$maxImgBytes = 5 * 1024 * 1024; // 5MB

$message = ""; $msgType = "ok";

// small helper: validate + save an uploaded image, returns relative path or null
function saveGroupImage($field, $destDir, $destRel, $prefix, $imageExt, $maxImgBytes, &$err){
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) { $err = "That image failed to upload."; return null; }
    if ($_FILES[$field]['size'] > $maxImgBytes) { $err = "Image is too big. Max size is 5MB."; return null; }
    $orig = $_FILES[$field]['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $ok   = in_array($ext, $imageExt, true) && @getimagesize($_FILES[$field]['tmp_name']);
    if (!$ok) { $err = "Please upload a valid image (jpg, png, gif or webp)."; return null; }
    if (!is_dir($destDir)) mkdir($destDir, 0775, true);
    $newName = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $destDir . $newName)) { $err = "Could not save the image."; return null; }
    return $destRel . $newName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_group') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $message = "Please give the group a name."; $msgType = "err";
        } elseif (mb_strlen($name) > 80) {
            $message = "Group name is too long."; $msgType = "err";
        } else {
            $err = null;
            $logoPath = saveGroupImage('logo', $logoDir, 'uploads/groups/logos/', 'glogo', $imageExt, $maxImgBytes, $err);
            $bgPath   = $err ? null : saveGroupImage('background', $bgDir, 'uploads/groups/bg/', 'gbg', $imageExt, $maxImgBytes, $err);

            if ($err) {
                $message = $err; $msgType = "err";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO chat_groups (name, is_private, logo_path, bg_path, created_by) VALUES (?,1,?,?,?)");
                mysqli_stmt_bind_param($stmt, "sssi", $name, $logoPath, $bgPath, $userId);
                mysqli_stmt_execute($stmt);
                $groupId = mysqli_insert_id($conn);

                $own = mysqli_prepare($conn, "INSERT INTO chat_group_members (group_id, user_id, role) VALUES (?,?, 'owner')");
                mysqli_stmt_bind_param($own, "ii", $groupId, $userId);
                mysqli_stmt_execute($own);

                $memberIds = $_POST['members'] ?? [];
                if (is_array($memberIds)) {
                    foreach ($memberIds as $mid) {
                        $mid = intval($mid);
                        if ($mid === $userId || $mid <= 0) continue;
                        $ins = mysqli_prepare($conn, "INSERT IGNORE INTO chat_group_members (group_id, user_id, role) VALUES (?,?, 'member')");
                        mysqli_stmt_bind_param($ins, "ii", $groupId, $mid);
                        mysqli_stmt_execute($ins);
                    }
                }
                header("Location: group_chat.php?id=" . $groupId);
                exit;
            }
        }
    }

    if ($action === 'add_members') {
        $groupId = intval($_POST['group_id'] ?? 0);
        if (groupRole($conn, $groupId, $userId) === 'owner') {
            $memberIds = $_POST['members'] ?? [];
            if (is_array($memberIds)) {
                foreach ($memberIds as $mid) {
                    $mid = intval($mid);
                    if ($mid <= 0) continue;
                    $ins = mysqli_prepare($conn, "INSERT IGNORE INTO chat_group_members (group_id, user_id, role) VALUES (?,?, 'member')");
                    mysqli_stmt_bind_param($ins, "ii", $groupId, $mid);
                    mysqli_stmt_execute($ins);
                }
            }
        }
        header("Location: groups.php"); exit;
    }

    if ($action === 'remove_member') {
        $groupId = intval($_POST['group_id'] ?? 0);
        $target  = intval($_POST['user_id'] ?? 0);
        if (groupRole($conn, $groupId, $userId) === 'owner' && groupRole($conn, $groupId, $target) !== 'owner') {
            $del = mysqli_prepare($conn, "DELETE FROM chat_group_members WHERE group_id=? AND user_id=?");
            mysqli_stmt_bind_param($del, "ii", $groupId, $target);
            mysqli_stmt_execute($del);
        }
        header("Location: groups.php"); exit;
    }

    if ($action === 'leave_group') {
        $groupId = intval($_POST['group_id'] ?? 0);
        $role = groupRole($conn, $groupId, $userId);
        if ($role) {
            mysqli_query($conn, "DELETE FROM chat_group_members WHERE group_id=$groupId AND user_id=$userId");
            if ($role === 'owner') {
                // hand ownership to whoever joined earliest, or delete the group if it's now empty
                $next = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM chat_group_members WHERE group_id=$groupId ORDER BY joined_at ASC LIMIT 1"));
                if ($next) {
                    mysqli_query($conn, "UPDATE chat_group_members SET role='owner' WHERE group_id=$groupId AND user_id=" . intval($next['user_id']));
                } else {
                    deleteGroupCompletely($conn, $groupId);
                }
            }
        }
        header("Location: groups.php"); exit;
    }

    if ($action === 'delete_group') {
        $groupId = intval($_POST['group_id'] ?? 0);
        if (groupRole($conn, $groupId, $userId) === 'owner') {
            deleteGroupCompletely($conn, $groupId);
        }
        header("Location: groups.php"); exit;
    }
}

function deleteGroupCompletely($conn, $groupId){
    $groupId = intval($groupId);
    $g = mysqli_fetch_assoc(mysqli_query($conn, "SELECT logo_path, bg_path FROM chat_groups WHERE id=$groupId"));
    $files = mysqli_query($conn, "SELECT file_path FROM group_messages WHERE group_id=$groupId AND file_path IS NOT NULL");
    while ($f = mysqli_fetch_assoc($files)) {
        if (file_exists(__DIR__ . '/' . $f['file_path'])) @unlink(__DIR__ . '/' . $f['file_path']);
    }
    if ($g) {
        if (!empty($g['logo_path']) && file_exists(__DIR__ . '/' . $g['logo_path'])) @unlink(__DIR__ . '/' . $g['logo_path']);
        if (!empty($g['bg_path'])   && file_exists(__DIR__ . '/' . $g['bg_path']))   @unlink(__DIR__ . '/' . $g['bg_path']);
    }
    mysqli_query($conn, "DELETE FROM chat_groups WHERE id=$groupId"); // cascades members + messages
}

// groups this user belongs to
$myGroups = [];
$res = mysqli_query($conn, "
    SELECT g.id, g.name, g.logo_path, g.bg_path, g.created_by, cgm.role,
           (SELECT COUNT(*) FROM chat_group_members m2 WHERE m2.group_id = g.id) AS member_count,
           (SELECT gm.body FROM group_messages gm WHERE gm.group_id = g.id ORDER BY gm.id DESC LIMIT 1) AS last_body,
           (SELECT gm.created_at FROM group_messages gm WHERE gm.group_id = g.id ORDER BY gm.id DESC LIMIT 1) AS last_at
    FROM chat_groups g
    JOIN chat_group_members cgm ON cgm.group_id = g.id AND cgm.user_id = $userId
    ORDER BY (last_at IS NULL), last_at DESC, g.created_at DESC
");
while ($row = mysqli_fetch_assoc($res)) { $myGroups[] = $row; }

// everyone else, to invite when creating a group / adding members
$allUsers = [];
$res2 = mysqli_query($conn, "SELECT id, name, username, role, avatar_path FROM login_accounts WHERE id != $userId AND is_approved = 1 ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($res2)) { $allUsers[] = $row; }

$pageTitle = "Groups";
$pageSub   = "Private group chats you're a part of.";
$activeNav = "groups";
include __DIR__ . '/includes/layout_head.php';
?>

<?php if ($message): ?><div class="alert alert-<?php echo $msgType==='err'?'err':'ok'; ?>"><?php echo h($message); ?></div><?php endif; ?>

<div class="section-head">
  <div>
    <div class="section-eyebrow">Private &amp; invite-only</div>
    <div class="section-title">Group Chats</div>
    <div class="section-sub">Only members you invite can see a group's messages.</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('createGroupModal')">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Group
  </button>
</div>

<?php if (empty($myGroups)): ?>
  <div class="card card-pad empty-state">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <div class="empty-title">No groups yet</div>
    <div class="empty-sub">Create a private group chat and invite classmates.</div>
  </div>
<?php else: ?>
<div class="group-grid">
  <?php foreach ($myGroups as $g): ?>
  <div class="group-card">
    <div class="group-card-top" <?php if(!empty($g['bg_path'])): ?>style="background-image:linear-gradient(180deg, rgba(6,14,12,.15), rgba(6,14,12,.85)), url('<?php echo h($g['bg_path']); ?>')"<?php endif; ?>>
      <?php if (!empty($g['logo_path'])): ?>
        <img src="<?php echo h($g['logo_path']); ?>" class="group-logo" alt="">
      <?php else: ?>
        <div class="group-logo group-logo-fallback"><?php echo h(mb_strtoupper(mb_substr($g['name'],0,1))); ?></div>
      <?php endif; ?>
    </div>
    <div class="group-card-body">
      <div class="group-name"><?php echo h($g['name']); ?> <?php if($g['role']==='owner'): ?><span class="badge role-admin">owner</span><?php endif; ?></div>
      <div class="group-meta"><?php echo (int)$g['member_count']; ?> member<?php echo $g['member_count']==1?'':'s'; ?> · private</div>
      <?php if ($g['last_body']): ?><div class="group-last"><?php echo h(mb_strimwidth($g['last_body'],0,60,'…')); ?></div><?php endif; ?>
      <div class="group-card-actions">
        <a href="group_chat.php?id=<?php echo (int)$g['id']; ?>" class="btn btn-primary btn-sm">Open Chat</a>
        <?php if ($g['role']==='owner'): ?>
          <button type="button" class="btn btn-outline btn-sm" onclick="openManage(<?php echo (int)$g['id']; ?>, '<?php echo h(addslashes($g['name'])); ?>')">Manage</button>
        <?php else: ?>
          <form method="POST" onsubmit="return confirm('Leave this group?');">
            <input type="hidden" name="action" value="leave_group">
            <input type="hidden" name="group_id" value="<?php echo (int)$g['id']; ?>">
            <button type="submit" class="btn btn-outline btn-sm">Leave</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Group Modal -->
<div class="modal-bg" id="createGroupModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">New Private Group</div><button class="modal-close" onclick="closeModal('createGroupModal')">✕</button></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create_group">
      <div class="form-row"><div><label>Group Name</label><input type="text" name="name" required maxlength="80" placeholder="e.g. IT301 Project Team"></div></div>

      <div class="form-row cols-2">
        <div>
          <label>Group Logo <span class="opt-tag">optional</span></label>
          <input type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
        </div>
        <div>
          <label>Background Image <span class="opt-tag">optional</span></label>
          <input type="file" name="background" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
        </div>
      </div>

      <div class="form-row">
        <label>Invite Members</label>
        <div class="member-picker">
          <?php if (empty($allUsers)): ?>
            <div class="empty-sub" style="padding:10px 4px">No other approved accounts yet.</div>
          <?php else: foreach ($allUsers as $u): ?>
            <label class="member-row">
              <input type="checkbox" name="members[]" value="<?php echo (int)$u['id']; ?>">
              <?php echo renderAvatar($u['name'], $u['avatar_path'] ?? null, 'member-av'); ?>
              <span class="member-name"><?php echo h($u['name']); ?> <span class="member-role"><?php echo h($u['role']); ?></span></span>
            </label>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('createGroupModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Group</button>
      </div>
    </form>
  </div>
</div>

<!-- Manage Group Modal (owner only) -->
<div class="modal-bg" id="manageGroupModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-title">Manage "<span id="mgName"></span>"</div><button class="modal-close" onclick="closeModal('manageGroupModal')">✕</button></div>

    <div id="mgMembersList" class="member-picker" style="margin-bottom:16px"></div>

    <form method="POST" id="mgAddForm">
      <input type="hidden" name="action" value="add_members">
      <input type="hidden" name="group_id" id="mgGroupId">
      <label>Add More Members</label>
      <div class="member-picker">
        <?php foreach ($allUsers as $u): ?>
          <label class="member-row" data-uid="<?php echo (int)$u['id']; ?>">
            <input type="checkbox" name="members[]" value="<?php echo (int)$u['id']; ?>">
            <?php echo renderAvatar($u['name'], $u['avatar_path'] ?? null, 'member-av'); ?>
            <span class="member-name"><?php echo h($u['name']); ?> <span class="member-role"><?php echo h($u['role']); ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="modal-actions" style="justify-content:space-between">
        <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Delete this group permanently for everyone? This cannot be undone.')){document.getElementById('mgDeleteForm').submit();}">Delete Group</button>
        <button type="submit" class="btn btn-primary">Add Selected</button>
      </div>
    </form>
    <form method="POST" id="mgDeleteForm" style="display:none">
      <input type="hidden" name="action" value="delete_group">
      <input type="hidden" name="group_id" id="mgDeleteGroupId">
    </form>
  </div>
</div>

<script>
// current members per group, injected so "Manage" can show who's already in + let the owner remove them
const GROUP_MEMBERS = <?php
  $membersByGroup = [];
  foreach ($myGroups as $g) {
      if ($g['role'] !== 'owner') continue;
      $mres = mysqli_query($conn, "SELECT u.id, u.name, u.avatar_path, cgm.role FROM chat_group_members cgm JOIN login_accounts u ON u.id=cgm.user_id WHERE cgm.group_id=" . (int)$g['id'] . " ORDER BY cgm.role DESC, u.name ASC");
      $list = [];
      while ($mm = mysqli_fetch_assoc($mres)) { $list[] = ['id'=>(int)$mm['id'],'name'=>$mm['name'],'role'=>$mm['role']]; }
      $membersByGroup[$g['id']] = $list;
  }
  echo json_encode($membersByGroup);
?>;

function openManage(groupId, name){
  document.getElementById('mgName').textContent = name;
  document.getElementById('mgGroupId').value = groupId;
  document.getElementById('mgDeleteGroupId').value = groupId;

  const list = document.getElementById('mgMembersList');
  const members = GROUP_MEMBERS[groupId] || [];
  list.innerHTML = members.map(m => `
    <div class="member-row" style="justify-content:space-between">
      <span class="member-name">${m.name} <span class="member-role">${m.role}</span></span>
      ${m.role === 'owner' ? '' : `
      <form method="POST" style="margin:0" onsubmit="return confirm('Remove this member?');">
        <input type="hidden" name="action" value="remove_member">
        <input type="hidden" name="group_id" value="${groupId}">
        <input type="hidden" name="user_id" value="${m.id}">
        <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 10px">Remove</button>
      </form>`}
    </div>`).join('') || '<div class="empty-sub" style="padding:6px 2px">No members yet.</div>';

  // hide "add" checkboxes for people already in the group
  const already = new Set(members.map(m => m.id));
  document.querySelectorAll('#mgAddForm .member-row').forEach(row => {
    row.style.display = already.has(parseInt(row.dataset.uid,10)) ? 'none' : 'flex';
  });

  openModal('manageGroupModal');
}
</script>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
