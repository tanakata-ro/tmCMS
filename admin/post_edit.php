<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole(['admin', 'editor']);

$pdo    = Database::getInstance();
$userId = Auth::id();
$role   = Auth::role();

$postId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$post   = null; $selectedTags = [];

$tags = $pdo->query('SELECT id, name FROM tags ORDER BY name')->fetchAll();
$sg   = $pdo->prepare('SELECT id, name FROM article_groups WHERE user_id = ? ORDER BY name');
$sg->execute([$userId]); $myGroups = $sg->fetchAll();

if ($postId) {
    $s = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $s->execute([$postId]); $post = $s->fetch();
    if (!$post) { http_response_code(404); exit('記事が見つかりません。'); }
    if ($role !== 'admin' && (int)$post['author_id'] !== $userId) { http_response_code(403); exit('権限がありません。'); }
    $pt = $pdo->prepare('SELECT tag_id FROM post_tags WHERE post_id = ?');
    $pt->execute([$postId]); $selectedTags = array_column($pt->fetchAll(), 'tag_id');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $title     = trim((string)($_POST['title']           ?? ''));
    $content   = (string)($_POST['content']              ?? '');
    $action    = (string)($_POST['action']                ?? 'draft');
    $postTags  = array_filter(array_map('intval', (array)($_POST['tags'] ?? [])));
    $groupId   = (int)($_POST['group_id'] ?? 0) ?: null;
    $updatedAt = (string)($_POST['updated_at']            ?? '');
    $publishAt = (string)($_POST['publish_at']            ?? '');
    $accessPw  = (string)($_POST['access_password']       ?? '');
    $metaDesc  = (string)($_POST['meta_description']      ?? '');
    $metaKw    = (string)($_POST['meta_keywords']         ?? '');
    $thumbPath = (string)($_POST['thumbnail_path']        ?? '');
    $showAds   = isset($_POST['show_ads']) ? 1 : 0;

    if ($title === '')   $errors[] = 'タイトルは必須です。';
    if ($content === '') $errors[] = '本文は必須です。';

    $status = 'draft'; $publishAtDb = null;
    switch ($action) {
        case 'publish':  $status = 'published'; break;
        case 'review':   $status = 'review';    break;
        case 'unlisted':
            $status = 'unlisted';
            if ($accessPw === '') $errors[] = '限定公開にはパスワードが必要です。';
            break;
        case 'schedule':
            $status = 'scheduled';
            if ($publishAt === '') { $errors[] = '予約日時を指定してください。'; break; }
            $publishAtDb = date('Y-m-d H:i:s', strtotime($publishAt));
            if (strtotime($publishAtDb) <= time()) $errors[] = '予約日時は未来に設定してください。';
            break;
    }

    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        $ts  = ($role === 'admin' && $updatedAt !== '') ? date('Y-m-d H:i:s', strtotime($updatedAt)) : $now;
        $pw  = $status === 'unlisted' ? $accessPw : null;
        $pdo->beginTransaction();
        try {
            if ($postId) {
                $canTs = ($post['status'] !== 'published') || ($role === 'admin' && $updatedAt !== '');
                $sql   = 'UPDATE posts SET title=?,content=?,status=?,access_password=?,thumbnail_path=?,
                          meta_description=?,meta_keywords=?,group_id=?,publish_at=?,show_ads=?'
                       . ($canTs ? ',updated_at=?' : '') . ' WHERE id=?';
                $p = [$title,$content,$status,$pw,$thumbPath,$metaDesc,$metaKw,$groupId,$publishAtDb,$showAds];
                if ($canTs) $p[] = $ts; $p[] = $postId;
                $pdo->prepare($sql)->execute($p);
            } else {
                $pdo->prepare('INSERT INTO posts
                    (title,content,author_id,status,access_password,thumbnail_path,
                     meta_description,meta_keywords,group_id,publish_at,show_ads,created_at,updated_at)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$title,$content,$userId,$status,$pw,$thumbPath,$metaDesc,$metaKw,$groupId,$publishAtDb,$showAds,$ts,$ts]);
                $postId = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM post_tags WHERE post_id=?')->execute([$postId]);
            if ($postTags) {
                $ins = $pdo->prepare('INSERT INTO post_tags (post_id,tag_id) VALUES(?,?)');
                foreach ($postTags as $tid) $ins->execute([$postId, $tid]);
            }
            $pdo->commit(); header('Location: dashboard.php'); exit;
        } catch (Throwable $e) { $pdo->rollBack(); $errors[] = 'DB保存エラー: ' . $e->getMessage(); }
    }
}

$fmtUpd = $post ? date('Y-m-d\TH:i', strtotime($post['updated_at'])) : date('Y-m-d\TH:i');
$fmtPub = ($post && $post['publish_at']) ? date('Y-m-d\TH:i', strtotime($post['publish_at'])) : date('Y-m-d\TH:i', time()+3600);
$tok = Csrf::token();
?>
<?php ob_start(); ?>
  <?php if ($errors): ?>
  <div class="alert alert--error"><ul style="margin:0;padding-left:1.25rem">
    <?php foreach ($errors as $e): ?><li><?= Security::e($e) ?></li><?php endforeach; ?>
  </ul></div>
  <?php endif; ?>

  <?php if ($postId && ($post['view_count'] ?? 0) > 0): ?>
  <div class="alert alert--info" style="margin-bottom:.75rem">
    この記事の閲覧数: <strong><?= (int)$post['view_count'] ?></strong>
  </div>
  <?php endif; ?>

  <form method="post" id="pf">
    <input type="hidden" name="_csrf" value="<?= Security::e($tok) ?>">
    <div class="row">
      <div class="col-main">
        <div class="card elev-1"><div class="card__body">
          <div class="field">
            <label for="title">タイトル</label>
            <input type="text" id="title" name="title" value="<?= Security::e($post['title'] ?? '') ?>" required>
          </div>
        </div></div>

        <div class="card elev-1"><div class="card__body">
          <div class="card__label">本文（Markdown + BBCode）</div>
          <div class="toolbar">
            <button type="button" class="tb" onclick="ins('**','**')"><b>B</b></button>
            <button type="button" class="tb" onclick="ins('*','*')"><i>I</i></button>
            <button type="button" class="tb" onclick="ins('\n## ','')">H2</button>
            <button type="button" class="tb" onclick="ins('\n### ','')">H3</button>
            <button type="button" class="tb" onclick="ins('- ','')">List</button>
            <button type="button" class="tb" onclick="ins('[','](URL)')">Link</button>
            <button type="button" class="tb" onclick="ins('```\n','\n```')">Code</button>
            <button type="button" class="tb" onclick="ins('> ','')">Quote</button>
            <button type="button" class="tb" onclick="ins('---\n','')">HR</button>
            <button type="button" class="tb" onclick="ins('[size=18px]','[/size]')">[size]</button>
            <button type="button" class="tb" onclick="ins('[color=red]','[/color]')">[color]</button>
            <button type="button" class="tb" onclick="ins('[img width=400px]','[/img]')">[img]</button>
            <button type="button" class="tb" id="imgbtn">画像挿入</button>
            <button type="button" class="tb" id="mediabtn">メディア</button>
            <input type="file" id="imgfile" accept="image/*" style="display:none">
            <span id="upst" class="up-st"></span>
          </div>
          <div class="editor-row">
            <div class="editor-pane">
              <textarea name="content" id="content" required><?= Security::e($post['content'] ?? '') ?></textarea>
            </div>
            <div class="prev-pane">
              <span class="prev-label">プレビュー</span>
              <div id="prev"></div>
            </div>
          </div>
        </div></div>
      </div>

      <div class="col-side">
        <div class="card elev-1"><div class="card__body">
          <button type="submit" class="btn btn--primary btn--full">保存する</button>
        </div></div>

        <div class="card elev-1"><div class="card__body">
          <div class="card__label">公開設定</div>
          <?php
          $cur = $post ? ($post['status'] === 'published' ? 'publish' : $post['status']) : 'draft';
          foreach (['draft'=>'下書き','publish'=>'公開','review'=>'評価依頼','unlisted'=>'限定公開','schedule'=>'予約公開'] as $v => $l):
          ?>
          <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;margin-bottom:.35rem;font-weight:400;cursor:pointer">
            <input type="radio" name="action" value="<?= $v ?>" <?= $cur===$v?'checked':'' ?> onchange="toggleSt()">
            <?= $l ?>
          </label>
          <?php endforeach; ?>
          <div id="pwbox" style="display:none;margin-top:.6rem" class="field">
            <label>パスワード</label>
            <input type="password" name="access_password" value="<?= Security::e($post['access_password'] ?? '') ?>">
          </div>
          <div id="scbox" style="display:none;margin-top:.6rem" class="field">
            <label>予約日時</label>
            <input type="datetime-local" name="publish_at" value="<?= Security::e($fmtPub) ?>">
          </div>
          <?php if ($role === 'admin'): ?>
          <div style="margin-top:.6rem" class="field">
            <label>投稿日時（管理者のみ）</label>
            <input type="datetime-local" name="updated_at" value="<?= Security::e($fmtUpd) ?>">
          </div>
          <?php endif; ?>
          <hr class="divider">
          <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;font-weight:400;cursor:pointer">
            <input type="checkbox" name="show_ads" value="1" <?= !empty($post['show_ads']) ? 'checked' : '' ?>>
            この記事に広告を表示する
          </label>
        </div></div>

        <div class="card elev-1"><div class="card__body">
          <div class="card__label">タグ</div>
          <div style="display:flex;flex-wrap:wrap;gap:.35rem">
            <?php foreach ($tags as $t): ?>
            <label style="display:flex;align-items:center;gap:.2rem;font-size:.82rem;font-weight:400;cursor:pointer">
              <input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>" <?= in_array($t['id'],$selectedTags,true)?'checked':'' ?>>
              <?= Security::e($t['name']) ?>
            </label>
            <?php endforeach; ?>
            <?php if (!$tags): ?><p style="font-size:.8rem;color:#9e9e9e;margin:0">タグがありません。</p><?php endif; ?>
          </div>
        </div></div>

        <div class="card elev-1"><div class="card__body">
          <div class="card__label">グループ</div>
          <div class="field" style="margin:0">
            <select name="group_id">
              <option value="">未分類</option>
              <?php foreach ($myGroups as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= ($post['group_id']??null)==$g['id']?'selected':'' ?>>
                <?= Security::e($g['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div></div>

        <div class="card elev-1"><div class="card__body">
          <div class="card__label">SEO / SNS</div>
          <div class="field">
            <label>サムネイルURL</label>
            <div style="display:flex;gap:.35rem">
              <input type="text" name="thumbnail_path" id="tpath" value="<?= Security::e($post['thumbnail_path'] ?? '') ?>">
              <button type="button" class="tb" id="thbtn">画像</button>
              <input type="file" id="thfile" accept="image/*" style="display:none">
            </div>
          </div>
          <div class="field">
            <label>メタディスクリプション</label>
            <textarea name="meta_description" style="min-height:56px;border:1px solid #bdbdbd;border-radius:4px;padding:.55rem .75rem;font-family:inherit;font-size:.875rem;width:100%"><?= Security::e($post['meta_description'] ?? '') ?></textarea>
          </div>
          <div class="field" style="margin:0">
            <label>メタキーワード</label>
            <input type="text" name="meta_keywords" value="<?= Security::e($post['meta_keywords'] ?? '') ?>">
          </div>
        </div></div>
      </div>
    </div>
  </form>
</div>

<!-- メディアモーダル -->
<div class="modal-bg" id="mm">
  <div class="modal">
    <div class="mhd">
      <h3>メディアライブラリ</h3>
      <input type="text" id="msrch" placeholder="ファイル名で検索">
      <button type="button" class="mclose" id="mc">&#10005;</button>
    </div>
    <div class="mtabs">
      <button type="button" class="mtab active" onclick="stab(0,this)">全ファイル</button>
      <?php if ($postId): ?>
      <button type="button" class="mtab" onclick="stab(<?= $postId ?>,this)">この記事</button>
      <?php endif; ?>
    </div>
    <div class="mupbar">
      <span>アップロード:</span>
      <label class="drop-lbl" id="dlbl">
        クリックまたはドロップ
        <input type="file" id="mupf" accept="image/*" multiple style="display:none">
      </label>
      <span id="must" class="up-st"></span>
    </div>
    <div class="mgrid" id="mg"><p class="mempty">読み込み中...</p></div>
    <div class="mft">
      <button id="mpv" disabled>前へ</button>
      <span id="mpi" style="font-size:.8rem;color:#757575;min-width:4em;text-align:center"></span>
      <button id="mnx" disabled>次へ</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
const PID=<?= $postId??0 ?>,CSRF=<?= json_encode($tok) ?>,ta=document.getElementById('content'),pv=document.getElementById('prev');
function bbp(t){t=t.replace(/\[size=(\d+)px\]([\s\S]*?)\[\/size\]/gi,'<span style="font-size:$1px">$2</span>');t=t.replace(/\[color=([a-zA-Z]+|#[0-9a-fA-F]{3,6})\]([\s\S]*?)\[\/color\]/gi,'<span style="color:$1">$2</span>');t=t.replace(/\[img\s+width=([0-9]+(?:px|%))\]([\s\S]*?)\[\/img\]/gi,'<img src="$2" style="width:$1;max-width:100%;height:auto">');return t;}
function render(){try{pv.innerHTML=marked.parse(bbp(ta.value));pv.querySelectorAll('pre code').forEach(b=>hljs.highlightElement(b));}catch(e){}}
ta.addEventListener('input',render);render();
const LS='tmd_'+PID;
setInterval(()=>localStorage.setItem(LS,JSON.stringify({t:document.getElementById('title').value,c:ta.value})),20000);
document.getElementById('pf').addEventListener('submit',()=>localStorage.removeItem(LS));
(()=>{const d=localStorage.getItem(LS);if(!d)return;try{const j=JSON.parse(d);if(j.c&&j.c!==ta.value&&confirm('自動保存された下書きを復元しますか？')){document.getElementById('title').value=j.t||document.getElementById('title').value;ta.value=j.c;render();}else localStorage.removeItem(LS);}catch(e){}})();
function ins(b,a){b=b.replace(/\\n/g,'\n');const s=ta.selectionStart,e=ta.selectionEnd,sel=ta.value.slice(s,e);ta.value=ta.value.slice(0,s)+b+sel+a+ta.value.slice(e);ta.selectionStart=ta.selectionEnd=s+b.length+sel.length;ta.focus();render();}
function toggleSt(){const v=document.querySelector('input[name="action"]:checked')?.value;document.getElementById('pwbox').style.display=v==='unlisted'?'block':'none';document.getElementById('scbox').style.display=v==='schedule'?'block':'none';}
toggleSt();
async function up(f,pid,onDone){const st=document.getElementById('upst');st.textContent='アップロード中...';const fd=new FormData();fd.append('image',f);fd.append('post_id',pid);fd.append('_csrf',CSRF);try{const r=await fetch('upload_handler.php',{method:'POST',body:fd});const d=await r.json();if(d.success){st.textContent='完了';if(onDone)onDone(d.url);return d.url;}else st.textContent='エラー: '+d.message;}catch(e){st.textContent='通信エラー';}return null;}
document.getElementById('imgbtn').onclick=()=>document.getElementById('imgfile').click();
document.getElementById('imgfile').onchange=async function(){if(this.files[0]){const u=await up(this.files[0],PID,null);if(u)ins(`![${this.files[0].name}](${u})`,'');}this.value='';};
document.getElementById('thbtn').onclick=()=>document.getElementById('thfile').click();
document.getElementById('thfile').onchange=async function(){if(this.files[0]){const u=await up(this.files[0],PID,null);if(u)document.getElementById('tpath').value=u;}this.value='';};
ta.addEventListener('dragover',e=>{e.preventDefault();ta.classList.add('drag-over');});
ta.addEventListener('dragleave',()=>ta.classList.remove('drag-over'));
ta.addEventListener('drop',async e=>{e.preventDefault();ta.classList.remove('drag-over');for(const f of e.dataTransfer.files)if(f.type.startsWith('image/')){const u=await up(f,PID,null);if(u)ins(`![${f.name}](${u})`,'')}});
let mPage=1,mSearch='',mPid=0;
document.getElementById('mediabtn').onclick=()=>{document.getElementById('mm').classList.add('open');loadM();};
document.getElementById('mc').onclick=()=>document.getElementById('mm').classList.remove('open');
document.getElementById('mm').onclick=function(e){if(e.target===this)this.classList.remove('open');};
document.getElementById('msrch').addEventListener('input',function(){mSearch=this.value;mPage=1;loadM();});
document.getElementById('mpv').onclick=()=>{mPage--;loadM();};document.getElementById('mnx').onclick=()=>{mPage++;loadM();};
function stab(pid,btn){mPid=pid;mPage=1;document.querySelectorAll('.mtab').forEach(t=>t.classList.remove('active'));btn.classList.add('active');loadM();}
async function loadM(){const g=document.getElementById('mg');g.innerHTML='<p class="mempty">読み込み中...</p>';const p=new URLSearchParams({action:'list',page:mPage,search:mSearch,pid:mPid});const d=await fetch('media_api.php?'+p).then(r=>r.json()).catch(()=>({success:false}));if(!d.success||!d.images?.length){g.innerHTML='<p class="mempty">画像がありません。</p>';document.getElementById('mpv').disabled=document.getElementById('mnx').disabled=true;document.getElementById('mpi').textContent='';return;}g.innerHTML='';d.images.forEach(img=>{const div=document.createElement('div');div.className='mitem';div.innerHTML=`<img src="${img.thumb_url}" alt="${img.file}" loading="lazy"><div class="mitem-ft"><span class="mitem-nm" title="${img.file}">${img.file}</span><button type="button" class="mitem-del">削除</button></div>`;div.querySelector('img').onclick=()=>{ins(`![${img.file}](${img.url})`,'');document.getElementById('mm').classList.remove('open');};div.querySelector('.mitem-del').onclick=async e=>{e.stopPropagation();if(!confirm('削除しますか？'))return;const r=await fetch('media_api.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({file:img.file,uid:img.uid,pid:img.pid,_csrf:CSRF})});const dd=await r.json();if(dd.success)loadM();else alert(dd.message);};g.appendChild(div);});document.getElementById('mpv').disabled=d.page<=1;document.getElementById('mnx').disabled=d.page>=d.total_pages;document.getElementById('mpi').textContent=d.total_pages>1?`${d.page}/${d.total_pages}`:'';};
const mup=document.getElementById('mupf'),dl=document.getElementById('dlbl'),ms=document.getElementById('must');
mup.onchange=async function(){await bulk(this.files);this.value='';};
dl.addEventListener('dragover',e=>{e.preventDefault();dl.classList.add('dragover');});dl.addEventListener('dragleave',()=>dl.classList.remove('dragover'));dl.addEventListener('drop',async e=>{e.preventDefault();dl.classList.remove('dragover');await bulk(e.dataTransfer.files);});
async function bulk(files){let n=0;for(const f of files){if(!f.type.startsWith('image/'))continue;ms.textContent=`${++n}/${files.length}処理中...`;const fd=new FormData();fd.append('image',f);fd.append('post_id',mPid>0?mPid:PID);fd.append('_csrf',CSRF);await fetch('upload_handler.php',{method:'POST',body:fd});}ms.textContent=n>0?`${n}件完了`:'';loadM();}
</script>
<?php
$__title = isset($postId) && $postId ? '記事編集' : '新規記事';
Theme::renderAdmin($__title, ob_get_clean(), [
    'head_extra' => '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">',
]);
