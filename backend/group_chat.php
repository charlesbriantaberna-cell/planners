<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/groups_schema.php';

$groupId = intval($_GET['id'] ?? $_POST['group_id'] ?? 0);
$group = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM chat_groups WHERE id=$groupId"));

if (!$group || !isGroupMember($conn, $groupId, $userId)) {
    header("Location: groups.php");
    exit;
}
$myRole = groupRole($conn, $groupId, $userId);

$chatUploadDir = __DIR__ . '/uploads/chat/';
$imageExt = ['jpg','jpeg','png','gif','webp'];
$fileExt  = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar'];
$maxChatBytes = 8 * 1024 * 1024; // 8MB

$message = ""; $msgType = "ok";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $body = trim($_POST['body'] ?? '');
        $filePathDb = null; $fileNameDb = null; $fileTypeDb = null;
        $hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $message = "The attachment failed to upload."; $msgType = "err";
            } elseif ($_FILES['attachment']['size'] > $maxChatBytes) {
                $message = "Attachment is too big. Max size is 8MB."; $msgType = "err";
            } else {
                $origName = $_FILES['attachment']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $isImage = in_array($ext, $imageExt, true) && @getimagesize($_FILES['attachment']['tmp_name']);
                if (!$isImage && !in_array($ext, $fileExt, true)) {
                    $message = "That file type isn't allowed."; $msgType = "err";
                } else {
                    if (!is_dir($chatUploadDir)) mkdir($chatUploadDir, 0775, true);
                    $newName = 'gmsg_' . $userId . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $chatUploadDir . $newName)) {
                        $filePathDb = 'uploads/chat/' . $newName;
                        $fileNameDb = mb_substr(basename($origName), 0, 200);
                        $fileTypeDb = $isImage ? 'image' : 'file';
                    } else {
                        $message = "Could not save the attachment."; $msgType = "err";
                    }
                }
            }
        }

        if ($message === '') {
            if ($body === '' && !$filePathDb) {
                $message = "Message can't be empty."; $msgType = "err";
            } elseif (mb_strlen($body) > 1000) {
                $message = "Message is too long."; $msgType = "err";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO group_messages (group_id, user_id, body, file_path, file_name, file_type) VALUES (?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, "iissss", $groupId, $userId, $body, $filePathDb, $fileNameDb, $fileTypeDb);
                mysqli_stmt_execute($stmt);
            }
        }
    }

    if ($action === 'unsend_message') {
        $id = intval($_POST['message_id']);
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, file_path FROM group_messages WHERE id=$id AND group_id=$groupId"));
        if ($row && ($myRole === 'owner' || $isAdmin || (int)$row['user_id'] === $userId)) {
            if (!empty($row['file_path']) && file_exists(__DIR__ . '/' . $row['file_path'])) {
                @unlink(__DIR__ . '/' . $row['file_path']);
            }
            mysqli_query($conn, "DELETE FROM group_messages WHERE id=$id");
        }
    }

    if ($action === 'hide_message') {
        $id = intval($_POST['message_id']);
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO group_message_hides (message_id, user_id) VALUES (?,?)");
        mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
        mysqli_stmt_execute($stmt);
    }

    if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) { header("Location: group_chat.php?id=$groupId&ajax=1"); exit; }
    header("Location: group_chat.php?id=$groupId" . ($message ? ('&err=' . urlencode($message)) : ''));
    exit;
}

function renderGroupMsgRow($m, $userId, $canModerate){
    $mine = ($m['uid'] == $userId);
    $canUnsend = $canModerate || $mine;
    ob_start();
    ?>
    <div class="msg-row <?php echo $mine ? 'me' : 'them'; ?>" data-id="<?php echo (int)$m['id']; ?>">
        <?php if (!$mine): ?><?php echo renderAvatar($m['name'], $m['avatar_path'] ?? null, 'msg-avatar'); ?><?php endif; ?>
        <div class="msg-col">
            <?php if (!$mine): ?>
            <div class="msg-name"><?php echo h($m['name']); ?>
                <?php if ($m['role'] === 'admin'): ?><span class="badge role-admin msg-badge">admin</span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (trim((string)$m['body']) !== ''): ?>
            <div class="msg-bubble"><?php echo nl2br(h($m['body'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($m['file_path'])): ?>
                <?php if ($m['file_type'] === 'image'): ?>
                    <a href="<?php echo h($m['file_path']); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo h($m['file_path']); ?>" class="msg-image" alt="<?php echo h($m['file_name']); ?>">
                    </a>
                <?php else: ?>
                    <a href="<?php echo h($m['file_path']); ?>" class="msg-file" download target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="fname"><?php echo h($m['file_name']); ?></span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <div class="msg-time">
                <?php echo date('g:i A · M j', strtotime($m['created_at'])); ?>
                <?php if ($canUnsend): ?>
                    <button type="button" class="msg-del" title="Unsend for everyone" onclick="unsendMsg(<?php echo (int)$m['id']; ?>)">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        Unsend
                    </button>
                <?php endif; ?>
                <button type="button" class="msg-hide" title="Delete for you only" onclick="hideMsg(<?php echo (int)$m['id']; ?>)">
                    <svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-10-7-10-7a18.5 18.5 0 0 1 4.22-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c7 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    Delete
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$canModerate = $isAdmin || ($myRole === 'owner');

$msgSelect = "
    SELECT gm.id, gm.body, gm.created_at, gm.file_path, gm.file_name, gm.file_type,
           u.name, u.role, u.id AS uid, u.avatar_path
    FROM group_messages gm
    JOIN login_accounts u ON u.id = gm.user_id
    LEFT JOIN group_message_hides gh ON gh.message_id = gm.id AND gh.user_id = $userId
    WHERE gm.group_id = $groupId AND gh.message_id IS NULL
    ORDER BY gm.id ASC";

if (isset($_GET['ajax'])) {
    $rows = mysqli_query($conn, $msgSelect);
    while ($m = mysqli_fetch_assoc($rows)) {
        echo renderGroupMsgRow($m, $userId, $canModerate);
    }
    exit;
}

$rows = mysqli_query($conn, $msgSelect);
$allMessages = [];
while ($m = mysqli_fetch_assoc($rows)) { $allMessages[] = $m; }

$memberCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM chat_group_members WHERE group_id=$groupId"))['c'];

$pageTitle = h($group['name']);
$pageSub   = "Private group · $memberCount member" . ($memberCount == 1 ? '' : 's');
$activeNav = "groups";
include __DIR__ . '/includes/layout_head.php';
?>

<?php if (isset($_GET['err'])): ?><div class="alert alert-err"><?php echo h($_GET['err']); ?></div><?php endif; ?>

<div class="card chat-wrap">
  <div class="chat-head group-chat-head">
    <div class="flex items-center gap-10">
      <a href="groups.php" class="btn btn-ghost btn-icon" title="Back to Groups">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </a>
      <?php if (!empty($group['logo_path'])): ?>
        <img src="<?php echo h($group['logo_path']); ?>" class="group-head-logo" alt="">
      <?php else: ?>
        <div class="group-head-logo group-logo-fallback"><?php echo h(mb_strtoupper(mb_substr($group['name'],0,1))); ?></div>
      <?php endif; ?>
      <div>
        <div class="section-title" style="font-size:16px"><?php echo h($group['name']); ?></div>
        <div class="section-sub">Private group · <?php echo (int)$memberCount; ?> member<?php echo $memberCount==1?'':'s'; ?></div>
      </div>
    </div>
    <span class="badge online"><span class="badge-dot"></span> <?php echo count($allMessages); ?> messages</span>
  </div>

  <div class="chat-messages" id="chatMessages"
       <?php if (!empty($group['bg_path'])): ?>style="background-image:linear-gradient(rgba(6,14,12,.78),rgba(6,14,12,.86)), url('<?php echo h($group['bg_path']); ?>');background-size:cover;background-position:center"<?php endif; ?>>
    <?php if (empty($allMessages)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <div class="empty-title">No messages yet</div>
        <div class="empty-sub">Say hello to the group 👋</div>
      </div>
    <?php else: foreach ($allMessages as $m): echo renderGroupMsgRow($m, $userId, $canModerate); endforeach; endif; ?>
  </div>

  <div class="chat-attach-preview" id="attachPreview">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <span class="name" id="attachName"></span>
    <button type="button" onclick="clearAttachment()" title="Remove">&times;</button>
  </div>

  <div class="emoji-pop" id="emojiPop">
    <div class="emoji-tabs" id="emojiTabs"></div>
    <div class="emoji-grid" id="emojiGrid"></div>
  </div>

  <form method="POST" enctype="multipart/form-data" class="chat-input-row" id="chatForm">
    <input type="hidden" name="action" value="send_message">
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>">
    <input type="file" name="attachment" id="chatAttachment" style="display:none"
      accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,image/*">
    <div class="chat-attach-btn" title="Attach a photo or file" onclick="document.getElementById('chatAttachment').click()">
      <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
    </div>
    <div class="chat-attach-btn" id="emojiBtn" title="Emoji" onclick="toggleEmojiPop(event)">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
    </div>
    <input type="text" name="body" id="chatBody" placeholder="Message the group…" autocomplete="off" maxlength="1000">
    <button type="submit" class="btn btn-primary btn-icon" title="Send">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>

  <form method="POST" id="unsendForm" style="display:none">
    <input type="hidden" name="action" value="unsend_message">
    <input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>">
    <input type="hidden" name="message_id" id="unsendMsgId">
  </form>
  <form method="POST" id="hideForm" style="display:none">
    <input type="hidden" name="action" value="hide_message">
    <input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>">
    <input type="hidden" name="message_id" id="hideMsgId">
  </form>
</div>

<script>
const GROUP_ID = <?php echo (int)$groupId; ?>;
const chatBox = document.getElementById('chatMessages');
chatBox.scrollTop = chatBox.scrollHeight;

function unsendMsg(id){
  if(!confirm('Unsend this message? It will be removed for everyone in the group and cannot be undone.')) return;
  document.getElementById('unsendMsgId').value = id;
  const fd = new FormData(document.getElementById('unsendForm'));
  fd.set('ajax','1');
  fetch('group_chat.php?id='+GROUP_ID, { method:'POST', body: fd }).then(refresh);
}

function hideMsg(id){
  if(!confirm('Delete this message for you? It will still be visible to everyone else.')) return;
  const row = chatBox.querySelector('.msg-row[data-id="' + id + '"]');
  if (row) row.remove();
  document.getElementById('hideMsgId').value = id;
  const fd = new FormData(document.getElementById('hideForm'));
  fd.set('ajax','1');
  fetch('group_chat.php?id='+GROUP_ID, { method:'POST', body: fd });
}

const attachInput   = document.getElementById('chatAttachment');
const attachPreview = document.getElementById('attachPreview');
const attachName    = document.getElementById('attachName');
attachInput.addEventListener('change', function(){
  if (this.files && this.files.length){
    attachName.textContent = this.files[0].name;
    attachPreview.classList.add('show');
  } else {
    clearAttachment();
  }
});
function clearAttachment(){
  attachInput.value = '';
  attachPreview.classList.remove('show');
  attachName.textContent = '';
}

document.getElementById('chatForm').addEventListener('submit', function(e){
  e.preventDefault();
  const input = document.getElementById('chatBody');
  const val = input.value.trim();
  if(!val && !attachInput.files.length) return;
  const fd = new FormData(this);
  fetch('group_chat.php?id='+GROUP_ID, { method:'POST', body: fd }).then(refresh);
  input.value = '';
  clearAttachment();
});

function refresh(){
  fetch('group_chat.php?id='+GROUP_ID+'&ajax=1')
    .then(r => r.text())
    .then(html => {
      const wasAtBottom = chatBox.scrollTop + chatBox.clientHeight >= chatBox.scrollHeight - 40;
      chatBox.innerHTML = html || chatBox.innerHTML;
      if (wasAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
    })
    .catch(()=>{});
}
setInterval(refresh, 4000);

// ---------- Emoji picker (same behavior as the main chat) ----------
const EMOJI_CATEGORIES = {
  "Smileys": ["😀","😁","😂","🤣","😃","😄","😅","😆","😉","😊","😋","😎","😍","😘","🥰","😗","😙","😚","🙂","🤗","🤩","🤔","🤨","😐","😑","😶","🙄","😏","😣","😥","😮","🤐","😯","😪","😫","🥱","😴","😌","😛","😜","😝","🤤","😒","😓","😔","😕","🙃","🤑","😲","☹️","🙁","😖","😞","😟","😤","😢","😭","😦","😧","😨","😩","🤯","😬","😰","😱","🥵","🥶","😳","🤪","😵","😡","😠","🤬","😷","🤒","🤕","🤢","🤮","🥴","😇","🥳","🥺","🤠","🤡","🤥","🤫","🤭","🧐","🤓"],
  "People": ["👋","🤚","🖐️","✋","🖖","👌","🤏","✌️","🤞","🤟","🤘","🤙","👈","👉","👆","🖕","👇","☝️","👍","👎","✊","👊","🤛","🤜","👏","🙌","👐","🤲","🙏","🤝","💪","🦵","🦶","👂","👃","🧠","🦷","👀","👁️","👅","👄","💋","💯","👶","🧒","👦","👧","🧑","👨","👩","🧓","👴","👵","🙋","🙆","🙅","💁","🙇","🤦","🤷","👮","🕵️","💂","👷","🤴","👸","👳","👲","🧕","🤵","👰","🤰","🤱","👼","🎅","🤶","🦸","🦹","🧙","🧚","🧛","🧜","🧝","🧞","🧟"],
  "Animals": ["🐶","🐱","🐭","🐹","🐰","🦊","🐻","🐼","🐨","🐯","🦁","🐮","🐷","🐽","🐸","🐵","🙈","🙉","🙊","🐒","🐔","🐧","🐦","🐤","🐣","🐥","🦆","🦅","🦉","🦇","🐺","🐗","🐴","🦄","🐝","🐛","🦋","🐌","🐞","🐜","🦟","🦗","🕷️","🕸️","🦂","🐢","🐍","🦎","🦖","🦕","🐙","🦑","🦐","🦞","🦀","🐡","🐠","🐟","🐬","🐳","🐋","🦈","🐊","🐅","🐆","🦓","🦍","🦧","🐘","🦛","🦏","🐪","🐫","🦒","🦘","🐃","🐂","🐄","🐎","🐖","🐏","🐑","🦙","🐐","🦌","🐕","🐩","🦮","🐈","🐓","🦃","🦤","🦚","🦜","🦢","🦩","🕊️","🐇","🦝","🦨","🦡","🦫","🦦","🦥","🐁","🐀","🐿️","🦔"],
  "Food": ["🍏","🍎","🍐","🍊","🍋","🍌","🍉","🍇","🍓","🫐","🍈","🍒","🍑","🥭","🍍","🥥","🥝","🍅","🍆","🥑","🥦","🥬","🥒","🌶️","🫑","🌽","🥕","🫒","🧄","🧅","🥔","🍠","🥐","🥯","🍞","🥖","🥨","🧀","🥚","🍳","🧈","🥞","🧇","🥓","🥩","🍗","🍖","🌭","🍔","🍟","🍕","🫓","🥪","🥙","🧆","🌮","🌯","🫔","🥗","🥘","🫕","🥫","🍝","🍜","🍲","🍛","🍣","🍱","🥟","🦪","🍤","🍙","🍚","🍘","🍥","🥠","🥮","🍢","🍡","🍧","🍨","🍦","🥧","🧁","🍰","🎂","🍮","🍭","🍬","🍫","🍿","🧂","🍩","🍪","🌰","🥜","🍯","🥛","🍼","☕","🍵","🧃","🥤","🧋","🍶","🍺","🍻","🥂","🍷","🥃","🍸","🍹","🧉","🍾"],
  "Activities": ["⚽","🏀","🏈","⚾","🥎","🎾","🏐","🏉","🥏","🎱","🪀","🏓","🏸","🏒","🏑","🥍","🏏","🥅","⛳","🪁","🏹","🎣","🤿","🥊","🥋","🎽","🛹","🛼","🛷","⛸️","🥌","🎿","⛷️","🏂","🪂","🏋️","🤼","🤸","⛹️","🤺","🤾","🏌️","🏇","🧘","🏄","🏊","🤽","🚣","🧗","🚵","🚴","🏆","🥇","🥈","🥉","🏅","🎖️","🏵️","🎗️","🎫","🎟️","🎪","🤹","🎭","🩰","🎨","🎬","🎤","🎧","🎼","🎹","🥁","🪘","🎷","🎺","🎸","🪕","🎻","🎲","♟️","🎯","🎳","🎮","🎰","🧩"],
  "Travel": ["🚗","🚕","🚙","🚌","🚎","🏎️","🚓","🚑","🚒","🚐","🛻","🚚","🚛","🚜","🦯","🦽","🦼","🛴","🚲","🛵","🏍️","🛺","🚨","🚔","🚍","🚘","🚖","🚡","🚠","🚟","🚃","🚋","🚞","🚝","🚄","🚅","🚈","🚂","🚆","🚇","🚊","🚉","✈️","🛫","🛬","🛩️","💺","🛰️","🚀","🛸","🚁","🛶","⛵","🚤","🛥️","🛳️","⛴️","🚢","⚓","🪝","⛽","🚧","🚦","🚥","🗺️","🗿","🗽","🗼","🏰","🏯","🏟️","🎡","🎢","🎠","⛲","⛱️","🏖️","🏝️","🏜️","🌋","⛰️","🏔️","🗻","🏕️","⛺","🏠","🏡","🏘️","🏚️","🏗️","🏭","🏢","🏬","🏣","🏤","🏥","🏦","🏨","🏪","🏫","🏩","💒","🏛️","⛪","🕌","🕍","🛕","🕋"],
  "Objects": ["⌚","📱","💻","⌨️","🖥️","🖨️","🖱️","🖲️","💽","💾","💿","📀","📷","📸","📹","🎥","📽️","🎞️","📞","☎️","📟","📠","📺","📻","🎙️","🎚️","🎛️","🧭","⏱️","⏲️","⏰","🕰️","⌛","⏳","📡","🔋","🔌","💡","🔦","🕯️","🪔","🧯","🛢️","💸","💵","💴","💶","💷","🪙","💰","💳","💎","⚖️","🪜","🧰","🔧","🔨","⚒️","🛠️","⛏️","🪓","🪚","🔩","⚙️","🪤","🧱","⛓️","🔫","💣","🧨","🪃","🔪","🗡️","⚔️","🛡️","🚬","⚰️","🪦","⚱️","🏺","🔮","📿","🧿","💈","🔭","🔬","🕳️","💊","💉","🩸","🩹","🩺","🚪","🛏️","🛋️","🪑","🚽","🚿","🛁","🪒","🧴","🧷","🧹","🧺","🧻","🪣","🧼","🪥","🧽","🧯","🛒"],
  "Symbols": ["❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟","☮️","✝️","☪️","🕉️","☸️","✡️","🔯","🕎","☯️","☦️","🛐","⛎","♈","♉","♊","♋","♌","♍","♎","♏","♐","♑","♒","♓","🆔","⚛️","🉑","☢️","☣️","📴","📳","🈶","🈚","🈸","🈺","🈷️","✴️","🆚","💮","🉐","㊙️","㊗️","🈴","🈵","🈹","🈲","🅰️","🅱️","🆎","🆑","🅾️","🆘","❌","⭕","🛑","⛔","📛","🚫","💯","💢","♨️","🚷","🚯","🚳","🚱","🔞","📵","🚭","❗","❕","❓","❔","‼️","⁉️","🔅","🔆","〽️","⚠️","🚸","🔱","⚜️","🔰","♻️","✅","🈯","💹","❇️","✳️","❎","🌐","💠","Ⓜ️","🌀","💤","🏧","🚾","♿","🅿️","🈳","🈂️","🛂","🛃","🛄","🛅"],
  "Flags": ["🏁","🚩","🎌","🏴","🏳️","🏳️‍🌈","🏴‍☠️","🇵🇭","🇺🇸","🇯🇵","🇰🇷","🇨🇳","🇬🇧","🇨🇦","🇦🇺","🇩🇪","🇫🇷","🇮🇹","🇪🇸","🇧🇷","🇮🇳","🇷🇺","🇸🇬","🇹🇭","🇻🇳","🇲🇾","🇮🇩","🇸🇦","🇦🇪","🇲🇽"]
};

let emojiOpen = false;

function buildEmojiPicker(){
  const tabsEl = document.getElementById('emojiTabs');
  const gridEl = document.getElementById('emojiGrid');
  const cats = Object.keys(EMOJI_CATEGORIES);
  tabsEl.innerHTML = cats.map((c,i) => `<button type="button" class="emoji-tab${i===0?' active':''}" data-cat="${c}">${EMOJI_CATEGORIES[c][0]}</button>`).join('');
  function showCat(cat){
    gridEl.innerHTML = EMOJI_CATEGORIES[cat].map(e => `<button type="button" class="emoji-item">${e}</button>`).join('');
  }
  tabsEl.querySelectorAll('.emoji-tab').forEach(btn => {
    btn.addEventListener('click', function(){
      tabsEl.querySelectorAll('.emoji-tab').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      showCat(this.dataset.cat);
    });
  });
  gridEl.addEventListener('click', function(e){
    e.stopPropagation();
    const btn = e.target.closest('.emoji-item');
    if (!btn) return;
    insertEmoji(btn.textContent);
  });
  tabsEl.addEventListener('click', function(e){ e.stopPropagation(); });
  showCat(cats[0]);
}

function insertEmoji(emoji){
  const input = document.getElementById('chatBody');
  const start = input.selectionStart ?? input.value.length;
  const end   = input.selectionEnd ?? input.value.length;
  input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
  const pos = start + emoji.length;
  input.focus();
  input.setSelectionRange(pos, pos);
}

function toggleEmojiPop(e){
  e.stopPropagation();
  const pop = document.getElementById('emojiPop');
  const btn = document.getElementById('emojiBtn');
  emojiOpen = !emojiOpen;
  if (emojiOpen) {
    const r = btn.getBoundingClientRect();
    const popWidth = Math.min(300, window.innerWidth - 20);
    let left = r.left - popWidth + r.width;
    left = Math.max(10, Math.min(left, window.innerWidth - popWidth - 10));
    pop.style.left = left + 'px';
    pop.style.bottom = (window.innerHeight - r.top + 10) + 'px';
    pop.style.top = 'auto';
  }
  pop.classList.toggle('show', emojiOpen);
}

document.addEventListener('click', function(e){
  const pop = document.getElementById('emojiPop');
  const btn = document.getElementById('emojiBtn');
  if (emojiOpen && !pop.contains(e.target) && !btn.contains(e.target)) {
    pop.classList.remove('show');
    emojiOpen = false;
  }
});

buildEmojiPicker();
</script>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
